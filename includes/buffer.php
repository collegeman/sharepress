<?php
# Emulate the awesome Buffer, bufferapp.com
add_action('init', 'buf_init');
add_action('admin_bar_menu', 'buf_admin_bar_menu', 1000);

function buf_init() {
  register_post_type('sp_buffer', array(
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

  register_post_type('sp_profile', array(
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

class SharePressProfile {

  static function forPost($post) {
    if (is_numeric($post)) {
      if (!$post = get_post($post_id = $post)) {
        return false;
      }
    }

    $post = (object) $post;

    if ($post->service && $post->service_id) {
      global $wpdb;
      $post = $wpdb->get_row($sql = "
        SELECT * FROM {$wpdb->posts}
        JOIN {$wpdb->postmeta} ON (post_id = ID)
        WHERE 
          post_type = 'sp_profile'
          AND meta_key = 'service_tag'
          AND meta_value = '{$post->service}:{$post->service_id}'
      ");
      if (!$post) {
        return false;
      }
    }

    if ($post->post_type !== 'sp_profile') {
      return false;
    }

    $data = array(
      'id' => $post->ID,
      'formatted_username' => $post->post_title,
      'user_id' => $post->post_author
    );

    return new SharePressProfile($data);
  }

  private function __construct($data) {
    foreach(get_post_custom($data['id']) as $meta_key => $values) {
      $value = array_pop($values);
      if ($meta_key == 'service_tag') {
        list($service, $service_id) = explode(':', $value);
        $data['service'] = $service;
        $data['service_id'] = $service_id;
      } else {
        $data[$meta_key] = maybe_unserialize($value);
      }
    }
   
    foreach((array) $data as $key => $value) {
      $this->{$key} = $value;
    }

    if (!isset($this->user_token)) {
      $service_tag = "{$this->service}:{$this->service_id}";
      $this->user_token = sp_get_opt("user_token_{$service_tag}");
      $this->user_secret = sp_get_opt("user_secret_{$service_tag}");
    }
  }

  function toJSON() {
    $data = get_object_vars($this);
    if (!current_user_can('list_users')) {
      unset($data['user_token']);
      unset($data['user_secret']);
    }
    return $data;
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
  
  $key = sp_get_opt("{$lower}_key", constant("SP_{$upper}_KEY"));
  $secret = sp_get_opt("{$lower}_secret", constant("SP_{$upper}_SECRET"));

  $keys = false;

  if ($key && $secret) {
    $keys = (object) array(
      'key' => $key,
      'secret' => $secret
    );  
  }

  if (apply_filters('buf_has_installed', $service, $keys)) {
    return $keys;
  }  

  return $keys;
}

function &buf_get_client($service, $profile = false) {
  global $buf_clients;

  if ($service instanceof SharePressProfile) {
    $profile = $service;
    $service = $profile->service;
  }

  $class = sprintf('%sSharePressClient', ucwords($service));
  if (!class_exists($class)) {
    error_log("SharePress Error: No client exists for service [$service]");
    return false;
  }

  if (!$keys = buf_has_keys($service)) {
    error_log("SharePress Error: No keys configured for service [$service]");
    return false;
  }

  @session_start();

  if (!$profile) {
    if (!isset($buf_clients[$service])) {
      $buf_clients[$service] = new $class($keys->key, $keys->secret);
    }
    return $buf_clients[$service];
  } else {
    $client = new $class($keys->key, $keys->secret, $profile);
  }

  return $client;
}

function buf_get_profile($post = false) {
  return SharePressProfile::forPost($post);
}

function buf_get_profiles($args = '') {
  $args = wp_parse_args($args);

  $args['post_type'] = 'sp_profile';

  if (!empty($args['user_id'])) {
    $args['author'] = $args['user_id'];
    unset($args['user_id']);
  }

  $profiles = array();
  foreach(get_posts($args) as $post) {
    $profiles[] = (object) buf_get_profile($post)->toJSON();
  }
  return $profiles;
}

function buf_update_schedule($profile_id, $schedule) {

}

function buf_update_profile($profile) {
  global $wpdb;

  $profile = (array) $profile;

  $service_tag = false;
  if (!empty($profile['service']) && !empty($profile['service_id'])) {
    $service_tag = trim("{$profile['service']}:{$profile['service_id']}");
  }

  if (!$service_tag) {
    return false;
  }

  $post_id = $wpdb->get_var($sql = "
    SELECT id FROM {$wpdb->posts}
    JOIN {$wpdb->postmeta} ON (post_id = ID)
    WHERE 
      post_type = 'sp_profile'
      AND meta_key = 'service_tag'
      AND meta_value = '{$service_tag}'
  ");

  $post = array();
  $meta = array();

  $post['post_type'] = 'sp_profile';

  if (!empty($profile['formatted_username'])) {
    $post['post_title'] = $profile['formatted_username'];
  }

  if (array_key_exists('default', $profile)) {
    $meta['default'] = (bool) $profile['default'];
  }

  if (array_key_exists('config', $profile)) {
    $meta['config'] = (array) $profile['config'];
  }

  if (!empty($profile['service_username'])) {
    $meta['service_username'] = $profile['service_username'];
  }

  if (!empty($profile['avatar'])) {
    $meta['avatar'] = $profile['avatar'];
  }

  $meta['service_tag'] = $service_tag;


  $post['post_name'] = "{$profile['service']}-{$profile['service_id']}";
  $post['comment_status'] = $post['ping_status'] = 'closed';
  
  $post['post_status'] = 'publish';

  if (!$post_id) {
    if (is_user_logged_in()) {
      $user = get_currentuserinfo();
      $post['post_author'] = $user->ID;
    }

  } else {
    $post['ID'] = $post_id;
  }

  if (is_wp_error($post_id = wp_insert_post($post))) {
    return $post_id;
  }

  foreach($meta as $key => $value) {
    update_post_meta($post_id, $key, $value);
  }

  if (array_key_exists('user_token', $profile)) {
    sp_set_opt("user_token_{$service_tag}", $profile['user_token']);
    sp_set_opt("user_secret_{$service_tag}", $profile['user_secret']);
  }

  return buf_get_profile($post_id);



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

