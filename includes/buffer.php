<?php
# Emulate the awesome Buffer, bufferapp.com
add_action('init', 'buf_init');
add_action('admin_bar_menu', 'buf_admin_bar_menu', 1000);

function buf_init() {
  register_post_type('buffer', array(
    'public' => false,
    'publicly_queryable' => false,
    'show_ui' => false, 
    'show_in_menu' => false, 
    'query_var' => false,
    'rewrite' => false,
    'capability_type' => 'post',
    'has_archive' => false, 
    'hierarchical' => false,
    'menu_position' => null
  ));

  register_post_type('profile', array(
    'public' => false,
    'publicly_queryable' => false,
    'show_ui' => false, 
    'show_in_menu' => false, 
    'query_var' => false,
    'rewrite' => false,
    'capability_type' => 'post',
    'has_archive' => false, 
    'hierarchical' => false,
    'menu_position' => null
  ));

  if (is_user_logged_in()) {

    wp_enqueue_script(
      'buffer-embed', 
      plugins_url(
        'js/embed.js', 
        SHAREPRESS
      ), 
      array('jquery')
    );
    
    wp_localize_script(
      'buffer-embed', 
      '_sp', 
      array(
        // the root URL of the API
        'api' => site_url('/sp/1/'),
        // the URL of the current request, for cross-domain communication
        'host' => is_admin() ? admin_url($_SERVER['REQUEST_URI']) : site_url($_SERVER['REQUEST_URI'])
      )
    );

  }
}

function buf_admin_bar_menu() {
  global $wp_admin_bar;
  if (!is_admin_bar_showing()) {
    return;
  }

  // $wp_admin_bar->add_menu(array(
  //   'id' => 'sp-buf-schedule',
  //   'title' => sprintf('<img src="%s">', plugins_url('img/admin-bar-wait.gif', SHAREPRESS)),
  //   'href' => '#',
  // ));
}

function buf_has_keys($service) {
  $lower = strtolower($service);
  $upper = strtoupper($service);
  
  $id = sp_get_opt("{$lower}_app_id", constant("SP_{$upper}_APP_ID"));
  $secret = sp_get_opt("{$lower}_secret", constant("SP_{$upper}_SECRET"));

  $keys = false;

  if ($id && $secret) {
    $keys = (object) array(
      'id' => $id,
      'secret' => $secret
    );  
  }

  if (apply_filters('buf_has_installed', $service, $keys)) {
    return $keys;
  }  

  return $keys;
}

function &buf_client($service) {
  global $buf_clients;

  if (!isset($buf_clients[$service])) {
    if ($keys = buf_has_keys($service)) {
      $class = sprintf('%sSharePressClient', ucwords($service));
      if (!class_exists($class)) {
        return false;
      }

      @session_start();  
      
      $buf_clients[$service] = new $class($keys->id, $keys->secret);
    }
  }

  return $buf_clients[$service];
}

function buf_get_profiles($args = '') {

}

function buf_update_schedule($profile_id, $schedule) {

}

function buf_update_profile($profile) {
  global $wpdb;

  $profile = (object) $profile;

  $post_id = $wpdb->get_var("
    SELECT id FROM {$wpdb->posts}
    JOIN {$wpdb->postmeta} post_ID = ID
    WHERE meta_key = 'sp_profile_tag'
    AND meta_value = '{$profile->service}:{$profile->service_id}'
  ");

  
  

  /*
           "avatar" : "http://a3.twimg.com/profile_images/1405180232.png",
    "created_at" :  1320703028,
    "default" : true,
    "formatted_username" : "@skinnyoteam",
    "id" : "4eb854340acb04e870000010",
    "schedules" : [{ 
        "days" : [ 
            "mon",
            "tue",
            "wed",
            "thu",
            "fri"
        ],
        "times" : [ 
            "12:00",
            "17:00",
            "18:00"
        ]
    }],
    "service" : "twitter",
    "service_id" : "164724445",
    "service_username" : "skinnyoteam",
    "statistics" : { 
        "followers" : 246 
    },
    "team_members" : [
        "4eb867340acb04e670000001"
    ],
    "timezone" : "Europe/London",
    "user_id" : "4eb854340acb04e870000010"
    */
}

function buf_remove_profile($profile) {

}

