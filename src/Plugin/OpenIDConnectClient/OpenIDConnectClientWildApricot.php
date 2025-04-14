<?php
/**
 * @file
 * OpenID Connect client for Wild Apricot.
 */
namespace Drupal\openid_connect_wild_apricot\Plugin\OpenIDConnectClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientBase;

/**
 * Wild Apricot OpenID Connect client.
 *
 * Implements OpenID Connect Client plugin for Wild Apricot.
 *
 * @OpenIDConnectClient(
 *   id = "wild_apricot",
 *   label = @Translation("Wild Apricot")
 * )
 */

class OpenIDConnectClientWildApricot extends OpenIDConnectClientBase {

  /**
   * {@inheritdoc}
   */
  public function authorize($scope = '') {
    // scopes documented at https://support.wildapricot.com/hc/en-us/articles/360008088794-API-access-options
    return parent::authorize('contacts_me');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['api_key'] = array(
      '#title' => t('Wild Apricot Integration API Key'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['api_key']
    );

    $form['site_url'] = array(
      '#title' => t('Site URL'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['site_url']
    );

    $form['account_id'] = array(
      '#title' => t('Wild Apricot Account ID #'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['account_id']
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoints() {
    return array(
      'authorization' =>  $this->configuration['site_url'] . '/sys/login/OAuthLogin',
      'token' => 'https://oauth.wildapricot.org/auth/token',
      'userinfo' => sprintf('https://api.wildapricot.org/v2.2/accounts/%d/contacts/me',
          $this->configuration['account_id']),
      'membershiplevels' => sprintf('https://api.wildapricot.org/v2.2/accounts/%d/membershiplevels',
          $this->configuration['account_id'])
    );
  }

  /**
   * Retrieve an array of all defined Wild Apricot membership levels (ID => Name)
   */
  public function retrieveMembershipLevels($access_token) {
    $request_options = array(
      'headers' => array(
        'Authorization' => 'Bearer ' . $access_token
      ),
    );

    $endpoints = $this->getEndpoints();
    
    $client = $this->httpClient;
    
    try {
      $response = $client->get($endpoints['membershiplevels'], $request_options);
      $response_data = json_decode((string) $response->getBody(), TRUE);
      $membership_levels = array();
      foreach($response_data as $membership_level) {
        $membership_levels[$membership_level['Id']] = $membership_level['Name'];
      }
      return $membership_levels;
    }
    catch (\Exception $e) {
      $variables = [
        '@message' => 'Could not retrieve membership levels',
        '@error_message' => $e->getMessage(),
      ];

      if ($e instanceof RequestException && $e->hasResponse()) {
        $response_body = $e->getResponse()->getBody()->getContents();
        $variables['@error_message'] .= ' Response: ' . $response_body;
      }

      $this->loggerFactory->get('openid_connect_' . $this->pluginId)
        ->error('@message. Details: @error_message', $variables);
      
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveTokens($authorization_code) {
    // Exchange `code` for access token and ID token.
    $redirect_uri = $this->getRedirectUrl()->toString();
    $endpoints = $this->getEndpoints();
    // $redirect_uri = OPENID_CONNECT_REDIRECT_PATH_BASE . '/' . $this->name;

    if ($authorization_code == NULL) {
      $form_params = array(
        'grant_type' => 'client_credentials',
        'scope' => 'auto'
      );
      $authorization = 'Basic ' . base64_encode('APIKEY:' . $this->configuration['api_key']);
    } else {
      $form_params = [
        'code' => $authorization_code,
        'client_id' => $this->configuration['client_id'],
        'redirect_uri' => $redirect_uri, //url($redirect_uri, array('absolute' => TRUE)),
        'grant_type' => 'authorization_code',
      ];
      $authorization = 'Basic ' . base64_encode($this->configuration['client_id'] . ':' . $this->configuration['client_secret']);
    }

    $request_options = [
      // 'method' => 'POST',
      // 'data' => drupal_http_build_query($post_data),
      'form_params' => $form_params,
      'timeout' => 15,
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Authorization' => $authorization
      ],
    ];
    
    $client = $this->httpClient;
    
    // $endpoints = $this->getEndpoints();
    // $response = drupal_http_request($endpoints['token'], $request_options);
    try {
      $response = $client->post($endpoints['token'], $request_options);

    // if (!isset($response->error) && $response->code == 200) {
      $response_data = json_decode((string) $response->getBody(), TRUE);
      $tokens = array(
        'id_token' => '',
        'access_token' => $response_data['access_token'],
      );
      if (array_key_exists('expires_in', $response_data)) {
        $tokens['expire'] = REQUEST_TIME + $response_data['expires_in'];
      }
      if (array_key_exists('refresh_token', $response_data)) {
        $tokens['refresh_token'] = $response_data['refresh_token'];
      }
      return $tokens;
    // }
    }
    catch (\Exception $e) {
      $variables = [
        '@message' => 'Could not retrieve tokens',
        '@error_message' => $e->getMessage(),
      ];

      if ($e instanceof RequestException && $e->hasResponse()) {
        $response_body = $e->getResponse()->getBody()->getContents();
        $variables['@error_message'] .= ' Response: ' . $response_body;
      }

      $this->loggerFactory->get('openid_connect_' . $this->pluginId)
        ->error('@message. Details: @error_message', $variables);
      
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveUserInfo($access_token) {
    $userinfo = parent::retrieveUserInfo($access_token);

    return openid_connect_wild_apricot_map_claim($userinfo);
  }

  /**
   * {@inheritdoc}
   */
  public function decodeIdToken($id_token) {
    return array();
  }
}
