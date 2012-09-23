<?php
# Emulate the awesome Buffer, bufferapp.com
add_action('init', 'buf_init');
add_action('admin_bar_menu', 'buf_admin_bar_menu', 1000);

function buf_init() {
  /*
  $labels = array(
    'name' => _x('Scheduled Posts', 'post type general name'),
    'singular_name' => _x('Post', 'post type singular name'),
    'add_new' => _x('Add New', 'buffer'),
    'add_new_item' => __('Add New Scheduled Post'),
    'edit_item' => __('Edit Scheduled Post'),
    'new_item' => __('New Scheduled Post'),
    'all_items' => __('All Scheduled Posts'),
    'view_item' => __('View Scheduled Post'),
    'search_items' => __('Search Schedule'),
    'not_found' =>  __('No scheduled posts found'),
    'not_found_in_trash' => __('No scheduled posts found in Trash'), 
    'parent_item_colon' => '',
    'menu_name' => __('Schedule')
  );
  */

  $args = array(
    // 'labels' => $labels,
    'public' => false,
    'publicly_queryable' => false,
    'show_ui' => false, 
    'show_in_menu' => false, 
    'query_var' => false,
    'rewrite' => false,
    'capability_type' => 'post',
    'has_archive' => false, 
    'hierarchical' => false,
    'menu_position' => null,
    'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
  ); 

  register_post_type('buffer', $args);

  $args = array(
    'public' => false,
    'publicly_queryable' => false,
    'show_ui' => false, 
    'show_in_menu' => false, 
    'query_var' => false,
    'rewrite' => false,
    'capability_type' => 'post',
    'has_archive' => false, 
    'hierarchical' => false,
    'menu_position' => null,
    'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
  ); 

  register_post_type('profile', $args);

  if (is_user_logged_in()) {
    wp_enqueue_script('buffer-embed', plugins_url('js/embed.js', SHAREPRESS), array('jquery'));
    wp_localize_script('buffer-embed', '_sp', array(
      // the root URL of the API
      'api' => site_url('/sp/1/'),
      // the URL of the current request, for cross-domain communication
      'host' => is_admin() ? admin_url($_SERVER['REQUEST_URI']) : site_url($_SERVER['REQUEST_URI'])
    ));
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

function buf_has_installed($service) {
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
    if ($keys = buf_has_installed($service)) {
      $class = sprintf('%sSharePressClient', ucwords($service));
      if (!class_exists($class)) {
        return false;
      }

      @session_start();  
      
      $buf_clients[$service] = new $class(array(
        'appId' => $keys->id,
        'secret' => $keys->secret
      ));
    }
  }

  return $buf_clients[$service];
}

function buf_get_profiles($args = '') {

}

function buf_update_schedule($profile_id, $schedule) {

}

function buf_update_profile($settings, $user = false) {
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

function buf_remove_profile($settings, $user = false) {

}

