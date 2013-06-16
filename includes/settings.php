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
  $client = buf_get_client($service);
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
    $profiles = buf_get_profiles(array('service' => $service));
    $subprofiles = array();
    
    // profiles picker:
    if (!empty($profiles) && !$client->has_error && isset($_REQUEST['sp_profiles'])) {
      ?>  
        <div class="wrap">
          <?php screen_icon(); ?>
          <h2><?php echo $client->getName() ?> Profiles</h2>     
          
          <h3>Connected Profiles</h3>

          <ul class="sp_profiles">
            <?php 
              foreach($profiles as $profile) {
                $c = buf_get_client($service, $profile);
                if ($children = $c->profiles()) {
                  $subprofiles[$profile->id] = $children;
                }
                ?>
                  <li class="profile media">
                    <div class="img">
                      <img src="<?php echo $profile->avatar ?>">
                    </div>
                    <div class="bd">
                      <h4 class="formatted_username"><a href="#" target="_blank"><?php echo $profile->formatted_username ?></a></h4>
                      <button class="button" data-remove="<?php echo $profile->id ?>">Remove</button>
                      <?php /*
                      <select>
                        <option>Only I may post to this profile</option>
                        <option>All editors can post to this profile</option>
                        <option>All users can post to this profile</option>
                      </select> */ ?>
                    </div>
                    <?php // print_r($profile) ?>
                  </li>
                <?php 
              } 
            ?>
          </ul>

          <?php if ($subprofiles) { ?>

            <h3>Available Profiles</h3>

            <ul class="sp_profiles">
              <?php foreach($subprofiles as $parent => $children) { if (!$children) continue; ?>
                <?php foreach($children as $profile) { if (buf_get_profile($profile)) continue; ?>
                  <li class="profile media">
                    <div class="img">
                      <img src="<?php echo $profile->avatar ?>">
                    </div>
                    <div class="bd">
                      <h4 class="formatted_username"><a href="#" target="_blank"><?php echo $profile->formatted_username ?></a></h4>
                      <button class="button button-primary" data-parent="<?php echo $parent ?>" data-service_id="<?php echo $profile->service_id ?>">Connect</button>
                    </div>
                    <?php // print_r($profile) ?>
                  </li>
                <?php } ?>
              <?php } ?>
            </ul>

          <?php } ?>

          <p class="submit">
            <?php if (apply_filters('sp_show_settings_screens', true, $service)) { ?>
              <a class="button" href="<?php echo site_url("/sp/1/auth/{$service}/config") ?>">&larr; Settings</a>
            <?php } ?>
            <a class="button button-primary" href="javascript:window.close();">Done</a>
          </p>
        </div>

        <script>
          !function($) {
            $('[data-remove]').click(function() {
              var $this = $(this);
              $.post('<?php echo site_url('/sp/1/profiles') ?>/' + $this.data('remove'), { _method: 'delete' });
              $this.attr('disabled', true).text('Removed');
              return false;
            });

            $('[data-parent]').click(function() {
              var $this = $(this), data = $this.data();
              $.post('<?php echo site_url('/sp/1/profiles') ?>', data);
              $this.removeClass('button-primary').attr('disabled', true).text('Connected');
              return false;
            });
          }(jQuery);
        </script>
      <?php

    // service settings:
    } else {
      ?>  
        <div class="wrap">
          <?php screen_icon(); ?>
          <h2><?php echo $client->getName() ?> Settings</h2>     
          <form method="post" action="<?php echo admin_url('options.php') ?>">
            <input type="hidden" name="sp_service" value="<?php echo $service ?>">
            <?php 
              settings_fields($option_group);
              do_settings_sections('sp-settings');
            ?>
            <p class="submit">
              <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
              <?php if (!$client->has_errors) { ?>
                <a class="button" href="<?php echo site_url("/sp/1/auth/{$service}/profiles") ?>">Add Profile &rarr;</a>
              <?php } ?>
            </p>
          </form>
        </div>
      <?php
    }

  // global configuration page:
  } else {

  }
}