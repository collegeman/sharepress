<?php
add_action('admin_init', 'sp_admin_init');
add_action('admin_menu', 'sp_admin_menu');


function sp_admin_init() {
  $option_group = 'sp-settings';

  if (isset($_REQUEST['sp_service'])) {
    wp_enqueue_style('sp-service-settings', SP_URL.'/css/service-settings.css');
    $service = strtolower($_REQUEST['sp_service']);
    $client = sp_get_client_for_settings_page($service);
    $client->settings('sp-settings', "sp-settings-{$service}", $service);
  }
}

function sp_admin_menu() {
  if (apply_filters('sp_show_settings_screens', true)) {
    add_options_page('SharePress', 'SharePress', 'manage_options', 'sp-settings', 'sp_settings_page');
  }
}

function sp_settings_field_text($args) {
  extract($args);
  if (!array_key_exists('name', $args)) {
    $name = $args['label_for'];
  }
  if (!array_key_exists('value', $args)) {
    $value = get_option($name);
  }
  if (!array_key_exists('id', $args)) {
    $id = $args['label_for'];
  }
  ?>
    <input id="<?php echo esc_attr($id) ?>" type="text" class="regular-text" name="<?php echo esc_attr($name) ?>" value="<?php echo esc_attr($value) ?>">
  <?php
}

function sp_get_client_for_settings_page($service) {
  $client = sp_get_client($service);
  if (is_wp_error($client)) {
    if ($client->get_error_code() === 'keys') {
      $error_data = $client->get_error_data('keys');
      $client = $error_data['client'];
      $client->has_errors = true;
    } else {
      wp_die($client->get_error_message());
    }
  }
  return $client;
}

function sp_settings_page() {
  // service configuration pages:
  if (isset($_REQUEST['sp_service'])) {
    
    $service = strtolower($_REQUEST['sp_service']);
    $client = sp_get_client_for_settings_page($service);

    $option_group = "sp-settings-{$service}";
    $profiles = sp_get_profiles(array('service' => $service));
    $subprofiles = array();
    
    // profiles picker:
    if (!empty($profiles) && !$client->has_error && isset($_REQUEST['sp_profiles'])) {
      require(SP_DIR.'/views/profiles.php');
    // service settings:
    } else {
      require(SP_DIR.'/views/service-settings.php');
    }

  // global configuration page:
  } else {
    require(SP_DIR.'/views/global-settings.php');
  }
}