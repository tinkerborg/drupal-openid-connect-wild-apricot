<?php

namespace Drupal\openid_connect_wild_apricot\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MembershipLevelBindingsForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The OpenID Connect client plugin manager.
   *
   * @var \Drupal\openid_connect\Plugin\OpenIDConnectClientManager
   */
  protected $pluginManager;


  public function __construct(
    OpenIDConnectClientManager $plugin_manager,
  ) {
    $this->pluginManager = $plugin_manager;
  }

    /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.openid_connect_client'),
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openid_connect_wild_apricot_membership_level_bindings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::service('config.factory')->get('openid_connect.settings.wild_apricot');
    
    $membership_level_bindings = $config->get('membership_level_bindings');

    $role_names = user_roles(TRUE);
	 
    $header = ["level" => "Membership Level"];
    
    foreach ($role_names as $rid => $role) {
      if ($rid == 'authenticated') continue;
      $header[$rid] = $role->get('label');
    }
   
    $membership_levels = $this->get_membership_levels();
    
    $membership_levels['__ignore__'] = "<b>Ignore Role - <i>Wild Apricot will not manage this role.</i></b>";

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => t('No users found'),
    ];

    foreach ($membership_levels as $level_id => $level) {
      $form['table'][$level_id] = ['level' => ['#markup' => $level]];

      foreach ($role_names as $rid => $role) {
        if ($rid == 'authenticated') continue;
        $form['table'][$level_id][$rid] = [
          '#type' => "checkbox",
          '#default_value' => (array_key_exists($level_id, $membership_level_bindings) && in_array($rid, $membership_level_bindings[$level_id])) ? 1 : 0];
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $membership_level_bindings = [];
    
    foreach ($form_state->getValue('table') as $level => $bindings) {
      $membership_level_bindings[$level] = array_keys(array_filter($bindings, function($val) {
        return $val;
      }));
    }

    $config = \Drupal::service('config.factory')->getEditable('openid_connect.settings.wild_apricot');
    $config->set('membership_level_bindings', $membership_level_bindings);
    $config->save();
    
    $this->messenger()->addStatus($this->t('Updated bindings.'));
  }
 
  private function get_client() {
    $configuration = $this->config('openid_connect.settings.wild_apricot')->get('settings');
    return $this->pluginManager->createInstance('wild_apricot', $configuration);
  }

  private function get_membership_levels() {
    $client = $this->get_client();
    $tokens = $client->retrieveTokens(NULL);
    return $client->retrieveMembershipLevels($tokens['access_token']);
  }
}
