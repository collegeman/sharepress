<?php
# Emulate the awesome Buffer, bufferapp.com
add_action('init', 'buf_init');
add_action('admin_bar_menu', 'buf_admin_bar_menu', 1000);

function buf_init() {
  register_post_type('sp_update', array(
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

  function __toString() {
    return "sp_profile:{$this->id}";
  }

  private function __construct($data) {
    foreach(get_post_custom($data['id']) as $meta_key => $values) {
      $value = array_pop($values);
      if ($meta_key == 'service_tag') {
        list($service, $service_id) = explode(':', $value);
        $data['service'] = $service;
        $data['service_id'] = $service_id;
      } else if ($meta_key == 'schedules') {
        $values[] = $value;
        $data['schedules'] = array_map('maybe_unserialize', $values);
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

    if (!is_array($this->schedules)) {
      $this->schedules = $this->schedules ? array($this->schedules) : array();
    }

    if (empty($this->team_members)) {
      $this->team_members = array();
    }
  }

  private $_time = 0;
  private $_days = array();
  private $_times = array();
  private $_idx = 0;

  function __wakeup() {
    $this->_time = 0;
  }

  /**
   * This function transforms this Profile object into a clock. Every
   * time this function is called, the next scheduled slot in this Profile's
   * buffer will be returned as a UTC timestamp. This function can be
   * called as many times as needed - for as many slots as need a timestamp
   * assigned to them.
   */
  function next() {
    if ($this->_time === 0) {
      $this->reset();
    }

    if (!isset($this->_days[$this->_idx])) {
      $this->_idx = 0;
    }

    $day = strtolower(gmdate('D', $this->_time));
    $adjust = $day === $this->_days[$this->_idx] ? $this->_times[$this->_idx] : "{$this->_days[$this->_idx]} {$this->_times[$this->_idx]}";

    $date = @gmdate('U', $time = strtotime($adjust, $this->_time));    
    $this->_time = $time;

    $this->_idx++;
    
    if ($time < time()) {
      return $this->next();
    }

    return $date;
  }

  function reset($start = null) {
    $days = array();

    $dayv = array('sun','mon','tue','wed','thu','fri','sat');

    foreach($this->schedules as $schedule) {
      foreach($schedule['days'] as $day) {
        $d = array_search($day, $dayv);
        if (!isset($days[$d])) {
          $days[$d] = array();
        }
        foreach($schedule['times'] as $time) {
          $days[$d][] = strlen($time) < 5 ? '0'.$time : $time;
        }
      }
    }

    ksort($days);
    foreach($days as $d => &$times) {
      sort($times);
      foreach($times as $time) {
        $this->_days[] = $dayv[$d];
        $this->_times[] = $time;
      }
    }

    if (is_null($start)) {
      $start = time();
    }

    // find the next calendar day
    $next = $start-86400;
    do {
      $next += 86400;
      $day = strtolower(gmdate('D', $next));
      $idx = array_search($day, $this->_days);
    } while ($idx === false);

    $this->_idx = $idx;

    $this->_time = $start;
  }

  function toJSON() {
    $data = get_object_vars($this);
    foreach($data as $key => $value) {
      if (strpos($key, '_') === 0) {
        unset($data[$key]);
      }
    }
    // if (!current_user_can('list_users')) {
      unset($data['user_token']);
      unset($data['user_secret']);
    // }
    return $data;
  }

}

class SharePressUpdate {

  static function forPost($post) {
    if (is_numeric($post)) {
      if (!$post = get_post($post_id = $post)) {
        return false;
      }
    }

    $post = (object) $post;

    if ($post->post_type !== 'sp_update') {
      return false;
    }

    $data = array(
      'id' => $post->ID,
      'user_id' => $post->post_author,
      'status' => $post->post_status,
      'text' => $post->post_content
    );

    return new SharePressUpdate($data);
  }

  function __toString() {
    return "sp_update:{$this->id}";
  }

  function __construct($data) {
    foreach(get_post_custom($data['id']) as $meta_key => $values) {
      $value = array_pop($values);
      $data[$meta_key] = maybe_unserialize($value);
    }
   
    foreach((array) $data as $key => $value) {
      $this->{$key} = $value;
    }

    if (!$this->status !== 'sent') {
      $this->sent_at = false;
      $this->service_update_id = false;
    }

    $profile = buf_get_profile($this->profile_id);
    $this->profile_service = $profile !== false ? $profile->service : false;
  }

  function __get($name) {
    if ($name === 'text_formatted') {
      $this->text_formatted = $this->text();
      return $this->text_formatted;
    } else {
      return null;
    }
  }

  function text() {
    return $this->text;
  }

  function toJSON() {
    $data = get_object_vars($this);
    unset($data['shorten']);
    unset($data['sent_data']);
    $data['text_formatted'] = $this->text_formatted;
    return $data;
  }

}

class SharePressUpdateSorter {

  function __construct($orderv) {
    $this->orderv = $orderv;
  }

  function sort($a, $b) {
    return @$this->orderv[$a->ID] > @$this->orderv[$b->ID];
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

function buf_get_client($service, $profile = false) {
  global $buf_clients;

  if ($service instanceof SharePressProfile) {
    $profile = $service;
    $service = $profile->service;
  }

  $class = sprintf('%sSharePressClient', ucwords($service));
  if (!class_exists($class)) {
    return new WP_Error('client', "SharePress Error: No client exists for service [$service]");
  }

  if (!$keys = buf_has_keys($service)) {
    return new WP_Error('keys', "SharePress Error: No keys configured for service [$service]");
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

function buf_add_team_member($profile, $user_id) {
  if (!$profile = buf_get_profile($profile_ref = $profile)) {
    return new WP_Error('profile', "Profile does not exist [{$profile_ref}]");
  }
  $team_members = get_post_meta($profile->id, 'team_members', true);
  if (!is_array($team_members)) {
    $team_members = array($user_id);
  } else if (!in_array($user_id, $team_members)) {
    $team_members[] = $user_id;
  }
  return update_post_meta($profile->id, 'team_members', $team_members);
}

function buf_remove_team_member($profile, $user_id) {
  if (!$profile = buf_get_profile($profile_ref = $profile)) {
    return new WP_Error('profile', "Profile does not exist [{$profile_ref}]");
  }
  $team_members = get_post_meta($profile->id, 'team_members', true);
  if (!is_array($team_members)) {
    return true;
  } else if (($idx = array_search($user_id, $team_members)) !== false) {
    unset($team_members[$idx]);
  }
  return update_post_meta($profile->id, 'team_members', $team_members);
}

function buf_get_profile($profile) {
  if ($profile instanceof SharePressProfile) {
    return $profile;
  }
  return SharePressProfile::forPost($profile);
}

function buf_get_profiles($args = '') {
  $args = wp_parse_args($args);

  $args['post_type'] = 'sp_profile';
  $args['numberposts'] = !empty($args['limit']) ? $args['limit'] : -1;

  if (!empty($args['user_id'])) {
    $args['author'] = $args['user_id'];
    unset($args['user_id']);
  }

  $args['post_status'] = 'any';

  $profiles = array();
  foreach(get_posts($args) as $post) {
    $profiles[] = (object) buf_get_profile($post)->toJSON();
  }
  return $profiles;
}

function buf_update_profile($profile) {
  global $wpdb;

  $profile = (array) $profile;

  $service_tag = false;
  if (!empty($profile['service']) && !empty($profile['service_id'])) {
    $service_tag = trim("{$profile['service']}:{$profile['service_id']}");
  }

  if (!$service_tag) {
    return new WP_Error('invalid-service', 'Missing service and service_id args');
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

  if ($post_id) {
    $post = (array) get_post($post_id);
  }

  $post['post_type'] = 'sp_profile';

  if (!empty($profile['formatted_username'])) {
    $post['post_title'] = $profile['formatted_username'];
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

  if (!empty($profile['timezone'])) {
    $meta['timezone'] = $profile['timezone'];
  }

  $meta['service_tag'] = $service_tag;

  $post['post_name'] = "{$profile['service']}-{$profile['service_id']}";

  $post['comment_status'] = $post['ping_status'] = 'closed';
  
  $post['post_status'] = empty($profile['status']) || $profile['status'] !== 'disabled' ? 'enabled' : 'disabled';

  if (!$post_id) {
    if (is_user_logged_in()) {
      $user = get_currentuserinfo();
      $post['post_author'] = $user->ID;
    }

  } else {
    $post['ID'] = $post_id;
  }

  if (!$post_id && !array_key_exists('schedules', $profile)) {
    $profile['schedules'] = array(
      array(
        'days' => explode(',','mon,tue,wed,thu,fri'),
        'times' => explode(',','12:00,17:00')
      )
    );
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

  if (array_key_exists('schedules', $profile)) {
    $schedules = get_post_meta($post_id, 'schedules');
    foreach($profile['schedules'] as $idx => $schedule) {
      if ($schedule == 0) {
        unset($schedules[(int) $idx]);
      } else {
        $schedules[(int) $idx] = $schedule;
      }
    }
    delete_post_meta($post_id, 'schedules');
    foreach($schedules as $schedule) {
      add_post_meta($post_id, 'schedules', $schedule);
    }
  }

  $profile = buf_get_profile($post_id);

  buf_update_buffer($profile);

  return $profile;
}

function buf_delete_profile($profile) {
  if (!is_object($profile)) {
    $profile = buf_get_profile($profile_id = $profile);
    if (!$profile) {
      return false;
    }
  }
  return false !== wp_delete_post($profile->id, true);
}

function buf_update_update($update) {
  global $wpdb;

  $update = (array) $update;

  $post_id = !empty($update['id']) ? $update['id'] : false;

  $post = array();
  $meta = array();
  $profiles = array();
  $create = false;

  if (!is_user_logged_in()) {
    return new WP_Error('auth', 'You must be logged in');
  }

  if ($post_id === false) {
    $create = true;

    $meta['created_at'] = time();

    if (empty($update['profile_ids'])) {
      return new WP_Error('profile', 'Missing profile_ids arg');
    }

    foreach($update['profile_ids'] as $profile_id) {
      if (!$profile = buf_get_profile($profile_id)) {
        return new WP_Error('profile', "Profile does not exist [{$profile_id}]");
      }
      if ($profile->user_id !== get_current_user_id()) {
        if (!buf_current_user_is_admin() && !in_array(get_current_user_id(), $profile->team_members)) {
          return new WP_Error('access-denied', "You are not allowed to post to this Profile [{$profile_id}]");
        }
      }
      $profiles[$profile->id] = $profile;
    }

    $post['post_status'] = 'buffer';

    $post['post_title'] = "{$profile->id}-{$meta['created_at']}";

    $post['post_author'] = get_current_user_id();

    $post['post_type'] = 'sp_update';

    $post['comment_status'] = $post['ping_status'] = 'closed';

    if (!empty($update['shorten'])) {
      $meta['shorten'] = true;
    }

    if (!$text = trim($update['text'])) {
      return new WP_Error('text', 'Cannot create empty Update');
    }
  
  } else {
    if (!$existing = get_post($post_id)) {
      return new WP_Error("Update does not exist [{$post_id}]");
    }

    if ($existing->post_type !== 'sp_update') {
      return new WP_Error("Not an update [{$post_id}]");
    }

    $post = (array) $existing;

    if (!$profile = buf_get_profile($profile_id = get_post_meta($post_id, 'profile_id', true))) {
      return new WP_Error("Profile no longer exists [{$profile_id}]");
    }

    $profiles = array($profile);
  }

  if (array_key_exists('text', $update)) {
    $post['post_content'] = trim($update['text']);
  }

  $post_ids = array();

  foreach($profiles as $profile) {
    $meta['profile_id'] = $profile->id;

    if (is_wp_error($post_id = wp_insert_post($post))) {
      return $post_id;
    }

    foreach($meta as $key => $value) {
      update_post_meta($post_id, $key, $value);
    }

    $post_ids[] = $post_id;    
  }

  $errors = array();

  if (!empty($update['now'])) {
    foreach($post_ids as $post_id) {
      if (is_wp_error($update = buf_post_update($post_id))) {
        $errors[] = $update;
      } else {
        $updates[] = $update;
      }
    }

    $result = array(
      'success' => !$errors,
      'updates' => $updates
    );

    if ($errors) {
      $result['errors'] = $errors;
    }

    return (object) $result;
  } else {
    foreach($profiles as $profile) {
      buf_update_buffer($profile);
    }

    foreach($post_ids as $post_id) {
      $updates[] = (object) buf_get_update($post_id)->toJSON();
    }
    
    $result = array(
      'success' => true,
      'buffer_count' => null,
      'buffer_percentage' => null
    );

    if ($create) {
      $result['updates'] = $updates;
    } else {
      $result['update'] = array_shift($updates);
    }

    return (object) $result;
  }  
}

function buf_current_user_is_admin() {
  return current_user_can('list_users');
}

function buf_set_error_status($update, $error) {
  if (!$update = buf_get_update($update_ref = $update)) {
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }
  $post = get_post($update->id);
  $post->post_status = 'error';
  wp_insert_post($post);
  update_post_meta($update->id, 'error', $error);
}

function buf_post_update($update) {
  if (!$update = buf_get_update($update_ref = $update)) {
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }
  if (!$profile = buf_get_profile($update->profile_id)) {
    $error = WP_Error('profile', "Profile does not exist [{$update->profile_id}]"); 
    buf_set_error_status($update, $error);
    $error->add_data(array(
      'update' => $update->toJSON()
    ));
    return $error;
  }
  if (is_wp_error($client = buf_get_client($profile))) {
    $client->add_data(array(
      'update' => $update->toJSON()
    ));
    buf_set_error_status($update, $client);
    return $client;
  }
  if (is_wp_error($result = $client->post($update->text_formatted))) {
    $result->add_data(array(
      'profile' => $profile->toJSON(),
      'update' => $update->toJSON()
    ));
    buf_set_error_status($update, $result);
    return $result;
  }
  $post = get_post($update->id);
  $post->post_status = 'sent';
  wp_insert_post($post);
  update_post_meta($update->id, 'sent_at', time());
  update_post_meta($update->id, 'service_update_id', $result->service_update_id);
  update_post_meta($update->id, 'sent_data', $result->service_update_id);
  return (object) buf_get_update($update->id)->toJSON();
}

function buf_update_buffer($profile, $order = null, $offset = null) {
  if (!$profile = buf_get_profile($profile_ref = $profile)) {
    return false;
  }

  if ($order) {
    if (!is_null($offset)) {
      if ($offset < 1 || $offset > 100) {
        return new WP_Error("When updating order of buffer, offset must be between 1 and 100");
      } else {
        // zero index
        $offset--;
      }
    } else {
      $offset = 0;
    }
  }

  $updates = get_posts(array(
    'post_type' => 'sp_update',
    'post_status' => 'buffer',
    'orderby' => 'menu_order',
    'order' => 'DESC',
    'numberposts' => 100
  ));

  if ($order) {
    $orderv = array();
    foreach($order as $i => $o) {
      $orderv[$o] = $i;
    }
    $sorter = array(new SharePressUpdateSorter($orderv), 'sort');
    if ($offset) {
      $before = array_slice($updates, 0, $offset);
      $to_order = array_slice($updates, $offset, count($order));
      $after = array_slice($updates, $offset + count($order));
      usort($to_order, $sorter);
      $updates = array_merge($before, $to_order, $after);
    } else {
      usort($updates, $sorter);
    }
  }

  $menu_order = count($updates);

  while($updates) {
    $update = array_shift($updates);
    $next = $profile->next();
    $update->menu_order = $menu_order;
    $menu_order--;
    $update->post_date_gmt = gmdate('Y-m-d H:i:s', $next);
    $update->post_date = gmdate('Y-m-d H:i:s', $next + ( $offset = get_option('gmt_offset') ? $offset : 0 ));
    wp_insert_post($update); 
    update_post_meta($update->ID, 'due_at', $next);
    update_post_meta($update->ID, 'due_time', gmdate('H:i a', $next));
  }
}

function buf_get_update($update) {
  if ($update instanceof SharePressUpdate) {
    return $update;
  }
  return SharePressUpdate::forPost($update);
}

function buf_get_updates($args = '') {
  global $wpdb;

  $args = wp_parse_args($args);

  if (!$profile = buf_get_profile($args['profile_id'])) {
    return new WP_Error("Profile does not exist [{$args['profile_id']}]");
  }

  $args['post_type'] = 'sp_update';

  if (!empty($args['user_id'])) {
    $args['author'] = $args['user_id'];
    unset($args['user_id']);
  }

  if (!empty($args['status'])) {
    $args['post_status'] = $args['status'];
    unset($args['status']);
  } else {
    $args['post_status'] = 'buffer';
  }

  $args['numberposts'] = ($limit = (int) $args['count']) ? $limit : 100;
  $args['offset'] = ((($offset = (int) $args['page']) ? $offset : 1) - 1) * $args['numberposts'];
  
  if ($args['offset'] + $args['numberposts'] > 100) {
    $args['numberposts'] = 100 - $args['offset'];
  }

  $args['orderby'] = 'post_date_gmt';
  $args['order'] = 'ASC';

  $results = $wpdb->prepare("
    SELECT * FROM {$wpdb->posts}
    JOIN {$wpdb->postmeta} ON (post_ID = ID)
    WHERE 
      post_type = %s
      AND post_status = %s
      AND meta_key = 'profile_id'
      AND meta_value = %s
    ORDER BY
      {$args['orderby']} {$args['order']}
    LIMIT %d OFFSET %d
  ",
    $args['post_type'],
    $args['post_status'],
    $profile->id,
    $args['numberposts'],
    $args['offset']
  );

  $count = $wpdb->prepare("
    SELECT COUNT(*) FROM {$wpdb->posts}
    JOIN {$wpdb->postmeta} ON (post_ID = ID)
    WHERE 
      post_type = %s
      AND post_status = %s
      AND meta_key = 'profile_id'
      AND meta_value = %s
  ",
    $args['post_type'],
    $args['post_status'],
    $profile->id,
    $args['numberposts'],
    $args['offset']
  );

  $count = $wpdb->get_var($count);
  $posts = $wpdb->get_results($results);

  $updates = array();
  foreach($posts as $post) {
    $updates[] = (object) buf_get_update($post)->toJSON();
  }
  
  return (object) array(
    'count' => $count,
    'updates' => $updates
  );
}

function buf_delete_update($update) {
  $profile = false;
  if (!is_object($update)) {
    $update = buf_get_update($update_id = $update);
    if (!$update) {
      return false;
    }
    $profile = buf_get_profile($update->profile_id);
  }
  $result = false !== wp_delete_post($update->id, true);
  buf_update_buffer($profile);
  return $result;
}
