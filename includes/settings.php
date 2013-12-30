<?php
add_action('admin_init', 'sp_admin_init');
add_action('admin_menu', 'sp_settings_menu');

function sp_admin_init() {
  $option_group = 'sp-settings';

  // service specific settings initialization:
  if (isset($_REQUEST['sp_service'])) {
    wp_enqueue_style('sp-service-settings', SP_URL.'/css/service-settings.css');
    $service = strtolower($_REQUEST['sp_service']);
    $client = sp_get_client_for_settings_page($service);
    $client->settings('sp-settings', "sp-settings-{$service}", $service);

  // global settings initialization:
  } else {
    // allow old settings to be deleted:
    register_setting('sp-global-settings', 'sharepress_settings');
    register_setting('sp-global-settings', 'sharepress_fb_a_state');
    register_setting('sp-global-settings', 'sharepress_publishing_targets');
    register_setting('sp-global-settings', 'sharepress_notifications');
    register_setting('sp-global-settings', 'sharepress_api_key');
    register_setting('sp-global-settings', 'sharepress_api_secret');

    // allow new settings to be updated:
    register_setting('sp-global-settings', sp_get_opt_name('og_tag'));
    register_setting('sp-global-settings', sp_get_opt_name('og_site_type'));
    register_setting('sp-global-settings', sp_get_opt_name('og_locale'));
    register_setting('sp-global-settings', sp_get_opt_name('og_article_publisher'));
  }
}

function sp_settings_menu() {
  if (apply_filters('sp_show_settings_screens', true)) {
    add_menu_page('SharePress', 'SharePress', 'manage_options', 'sp-settings', 'sp_settings_page', '', 81);
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
      sp_require_view('profiles', array(
        'service' => $service,
        'client' => $client,
        'option_group' => $option_group,
        'profiles' => $profiles,
        'subprofiles' => $subprofiles
      ));
    // service settings:
    } else {
      sp_require_view('service-settings', array(
        'service' => $service,
        'client' => $client,
        'option_group' => $option_group,
        'profiles' => $profiles,
        'subprofiles' => $subprofiles
      ));
    }

  // global configuration page:
  } else {
    sp_require_view('global-settings');
  }
}