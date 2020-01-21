<?php

/**
 * Form builder: Role Mappings form.
 */
function openid_connect_wild_apricot_role_mappings_form($form, &$form_state, $rid = NULL) {
  $membership_level_bindings = variable_get('openid_connect_wild_apricot_membership_level_bindings');
	// Retrieve role names for columns.
  $role_names = user_roles(TRUE);
  if (is_numeric($rid)) {
    $role_names = array($rid => $role_names[$rid]);
  }

  // Store $role_names for use when saving the data.
  $form['role_names'] = array(
    '#type' => 'value',
    '#value' => $role_names,
  );
	
  $membership_levels = openid_connect_wild_apricot_membership_levels();
	$options = array();
	  
  $form['membership_level'] = array(
    '#type' => 'item'
  );

	foreach ($membership_levels as $id => $name) {
    $form['membership_level'][$id] = array(
      '#markup' => $name,
      '#id' => $id, 
    );
		$options[$id] = '';
	}
  $form['membership_level']['__ignore__'] = array(
    '#markup' => '<b>Ignore Role - <i>Wild Apricot will not manage this role.</i></b>',
    '#id' => '__ignore__',
  );
  $options['__ignore__'] = '';

	foreach ($role_names as $rid => $name) {
    $form['checkboxes'][$rid] = array(
      '#type' => 'checkboxes',
      '#options' => $options,

      '#default_value' => isset($membership_level_bindings[$rid]) ? $membership_level_bindings[$rid] : array(),
      '#attributes' => array('class' => array('rid-' . $rid)),
    );
    $form['role_names'][$rid] = array('#markup' => check_plain($name), '#tree' => TRUE);
  }

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save Mappings'));

  return $form;
}

function theme_openid_connect_wild_apricot_role_mappings_form($variables) {
  $form = $variables['form'];
	$rows = array();
  
  foreach (element_children($form['membership_level']) as $key) {
    $id = $form['membership_level'][$key]['#id'];
    $row = array();
	  $row[] = array(
      'data' => drupal_render($form['membership_level'][$key]),
      'class' => array('module'),
    );
    foreach (element_children($form['checkboxes']) as $rid) {
      $form['checkboxes'][$rid][$id]['#title'] = drupal_render($form['membership_level'][$key]);
      $form['checkboxes'][$rid][$id]['#title_display'] = 'invisible';
      $row[] = array('data' => drupal_render($form['checkboxes'][$rid][$id]), 'class' => array('checkbox'));
    }	
		$rows[] = $row;		
	}

  $header[] = (t('Membership Level'));
  foreach (element_children($form['role_names']) as $rid) {
    $header[] = array('data' => drupal_render($form['role_names'][$rid]), 'class' => array('checkbox'));
  }

  $output = theme('system_compact_link');
  $output .= theme('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('id' => 'membership_levels')));
  $output .= drupal_render_children($form);
  
  return $output;
}

/**
 * Save permissions selected on the administer permissions page.
 *
 * @see user_admin_permissions()
 */
function openid_connect_wild_apricot_role_mappings_form_submit($form, &$form_state) {
  $membership_level_bindings = array();
  
  foreach ($form_state['values']['role_names'] as $rid => $name) {
    $membership_level_bindings[$rid] = array_keys(array_filter($form_state['values'][$rid], function($val) {
      return $val;
    }));
  }

  variable_set('openid_connect_wild_apricot_membership_level_bindings', $membership_level_bindings);

  drupal_set_message(t('The changes have been saved.'));

  // Clear the cached pages and blocks.
  cache_clear_all();
}
