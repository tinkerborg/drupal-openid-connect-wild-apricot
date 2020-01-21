<?php
/**
 * @file
 * OpenID Connect client for Wild Apricot.
 */

/**
 * Implements OpenID Connect Client plugin for Wild Apricot.
 */
class OpenIDConnectClientWildApricot extends OpenIDConnectClientBase {

  /**
   * {@inheritdoc}
   */
  public function authorize($scope = '') {
    // scopes documented at https://support.wildapricot.com/hc/en-us/articles/360008088794-API-access-options
    parent::authorize('contacts_me');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $client = openid_connect_get_client('wildapricot');
    $tokens = $client->retrieveTokens(NULL);

    $form = parent::settingsForm();

    $form['api_key'] = array(
      '#title' => t('Wild Apricot Integration API Key'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('api_key')
    );
    $form['site_url'] = array(
      '#title' => t('Site URL'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('site_url')
    );
    $form['account_id'] = array(
      '#title' => t('Wild Apricot Account ID #'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('account_id')
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoints() {
    return array(
      'authorization' =>  $this->getSetting('site_url') . '/sys/login/OAuthLogin',
      'token' => 'https://oauth.wildapricot.org/auth/token',
      'userinfo' => sprintf('https://api.wildapricot.org/v2.2/accounts/%d/contacts/me',
          $this->getSetting('account_id')),
      'membershiplevels' => sprintf('https://api.wildapricot.org/v2.2/accounts/%d/membershiplevels',
          $this->getSetting('account_id'))
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
    $response = drupal_http_request($endpoints['membershiplevels'], $request_options);
    
    if (!isset($response->error) && $response->code == 200) {
      $response_data = drupal_json_decode($response->data);
      $membership_levels = array();
      foreach($response_data as $membership_level) {
        $membership_levels[$membership_level['Id']] = $membership_level['Name'];
      }
      return $membership_levels;
    } else {
      openid_connect_log_request_error(__FUNCTION__, $this->name, $response);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveTokens($authorization_code) {
    // Exchange `code` for access token and ID token.
    $redirect_uri = OPENID_CONNECT_REDIRECT_PATH_BASE . '/' . $this->name;

    if ($authorization_code == NULL) {
      $post_data = array(
        'grant_type' => 'client_credentials',
        'scope' => 'auto'
      );
      $authorization = 'Basic ' . base64_encode('APIKEY:' . $this->getSetting('api_key'));
    } else {
      $post_data = array(
        'code' => $authorization_code,
        'client_id' => $this->getSetting('client_id'),
        'redirect_uri' => url($redirect_uri, array('absolute' => TRUE)),
        'grant_type' => 'authorization_code',
      );
      $authorization = 'Basic ' . base64_encode($this->getSetting('client_id') . ':' . $this->getSetting('client_secret'));
    }

    $request_options = array(
      'method' => 'POST',
      'data' => drupal_http_build_query($post_data),
      'timeout' => 15,
      'headers' => array(
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Authorization' => $authorization
      ),
    );
    
    $endpoints = $this->getEndpoints();
    $response = drupal_http_request($endpoints['token'], $request_options);

    if (!isset($response->error) && $response->code == 200) {
      $response_data = drupal_json_decode($response->data);
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
    }
    else {
      openid_connect_log_request_error(__FUNCTION__, $this->name, $response);
      return FALSE;
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function retrieveUserInfo($access_token) {
    $userinfo = parent::retrieveUserInfo($access_token);

    $userinfo['email'] = $userinfo['Email'];
    unset($userinfo['Email']);

    $userinfo['sub'] = $userinfo['Id'];
    unset($userinfo['Id']);
    
    $userinfo['preferred_username'] = $userinfo['email'];

    return $userinfo;
  }
  
  /**
   * {@inheritdoc}
   */
  public function decodeIdToken($id_token) {
    return array();
  }

}
