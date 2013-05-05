<?php
add_action('init', 'buf_init');
add_action('admin_bar_menu', 'buf_admin_bar_menu', 1000);
add_action('wp_enqueue_scripts', 'buf_wp_enqueue_scripts');
add_action('admin_enqueue_scripts', 'buf_wp_enqueue_scripts');

/**
 * Initialize custom post types that are used to keep
 * track of updates (things to be posted via clients),
 * and profiles (abstract representations of users'
 * accounts on third-party networks, bridged by clients).
 */
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
}

/**
 * Enqueues the scripts that are used to render the
 * Bufferapp-like UI for queueing updates.
 */
function buf_wp_enqueue_scripts() {
  if (is_user_logged_in()) {
    global $post;

    wp_enqueue_script(
      'buffer-embed', 
      plugins_url(
        'js/embed.js', 
        SHAREPRESS
      ), 
      array('jquery')
    );

    if (empty($post)) {
      if (!empty($_GET['post'])) {
        $post = get_post($_GET['post']);
      }
    }
    
    wp_localize_script(
      'buffer-embed', 
      '_sp', 
      array(
        // the root URL of the API
        'api' => site_url('/sp/1/'),
        // the URL of the current request, for cross-domain communication
        'host' => is_admin() ? admin_url($_SERVER['REQUEST_URI']) : site_url($_SERVER['REQUEST_URI']),
        'post_id' => ($post ? $post->ID : false)
      )
    );

  }
}

/**
 * Add a button to the admin bar for opening the buffer modal.
 */
function buf_admin_bar_menu() {
  global $wp_admin_bar;
  if (!is_admin_bar_showing()) {
    return;
  }

  $wp_admin_bar->add_menu(array(
    'id' => 'sp-buf-schedule',
    'title' => sprintf('<img src="%s">', plugins_url('img/admin-bar-wait.gif', SHAREPRESS)),
    'href' => '#',
  ));
}

/**
 * This class models a user's account on a third-party
 * social network. It also models the automatic posting
 * schedule that this profile has, if any.
 */
class SharePressProfile {

  /**
   * Given WordPress post data, create and initialize an
   * instance of SharePressProfile
   * @param mixed $post a Post object or an ID
   * @return SharePressProfile, or if the post is invalid
   * or does not exist, false.
   */
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
      $value = $values[count($values)-1];
      if ($meta_key == 'service_tag') {
        list($service, $service_id) = explode(':', $value);
        $data['service'] = $service;
        $data['service_id'] = $service_id;
      } else if ($meta_key == 'schedules') {
        $data['schedules'] = array_map('maybe_unserialize', $values);
      } else if ($meta_key == 'team_members') {
        $data['team_members'] = array_map('maybe_unserialize', $values);
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
   * time this function is called, the next future slot in this Profile's
   * buffer will be returned as a UTC timestamp. This function can be
   * called as many times as needed - for as many slots as are needed
   * to reschedule a buffer's flexibly scheduled updates.
   * @return timestamp
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
    
    if ($time < time() + ( $this->offset() * 3600 )) {
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

    // identify the next start time that coincides
    // with the next weekday specified by this profile's
    // schedules; we use this profile's offset here
    // and then use it again in next() to ensure that
    // the clock is returning times for the user's 
    // timezone, not the system's timezone.

    $next = $start - 86400 + ( $this->offset() * 3600 );
    do {
      $next += 86400;
      $day = strtolower(gmdate('D', $next));
      $idx = array_search($day, $this->_days);
    } while ($idx === false);

    $this->_idx = $idx;

    $this->_time = $next;
  }

  function offset() {
    $timezone_object = timezone_open( $this->timezone );
    $datetime_object = date_create();
    if ( false === $timezone_object || false === $datetime_object ) {
      return false;
    }
    return round( timezone_offset_get( $timezone_object, $datetime_object ) / 3600, 2 );
  }

  function toJSON() {
    $data = get_object_vars($this);
    foreach($data as $key => $value) {
      if (strpos($key, '_') === 0) {
        unset($data[$key]);
      }
    }
    unset($data['user_token']);
    unset($data['user_secret']);
    return $data;
  }

}

/**
 * This class models content that is to be shared through
 * an active profile.
 */
class SharePressUpdate {

  /**
   * Given WordPress post data, create and initialize an
   * instance of SharePressUpdate
   * @param mixed $post a Post object or an ID
   * @return SharePressUpdate, or if the post is invalid
   * or does not exist, false.
   */
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
      'text' => $post->post_content,
      'due_at' => get_post_meta($update->ID, 'due_at', true),
      'due_time' => get_post_meta($update->ID, 'due_time', true),
      'bound_to' => get_post_meta($update->ID, 'bound_to')
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
      $this->text_formatted = self::format($this->text);
      return $this->text_formatted;
    } else {
      return null;
    }
  }

  static function format($text) {
    return $text;
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

/**
 * Trigger the publishing of all scheduled updates up until
 * some given timestamp. 
 * @param int $until All updates whose publishing due date is
 * older than this time will be published. If null, a timestamp
 * 60 seconds in the future is used.
 * @action pre_buf_post_pending
 * @action post_buf_post_pending
 */
function buf_post_pending($until = null) {
  error_log('buf_post_pending');

  global $wpdb;

  if (is_null($until)) {
    // default cron job is on a 2-min interval, so we always
    // look one minute into the future
    $until = time() + 60;
  }

  do_action('pre_buf_post_pending');

  $posts = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts}
    JOIN {$wpdb->postmeta} ON (post_ID = ID)
    WHERE
      post_type = 'sp_update'
      AND post_status = 'buffer'
      AND meta_key = 'due_at' 
      AND meta_value <= {$until}
  ");

  foreach($posts as $post) {
    if ($update = buf_get_update($post->ID)) {
      buf_post_update($post->ID);  
    }
  }

  do_action('post_buf_post_pending');
}

/**
 * Seek out the public and private keys for the given service. Default sources
 * of keys are WP options (via sp_get_opt) and constants, with WP optiosn taking
 * precedence.
 * @param String $service The unique name for the service, e.g., 'facebook'
 * @return mixed If available, an array of the keys with entries "key" and "secret",
 * otherwise false.
 * @see sp_get_opt($name, $default)
 * @filter buf_has_installed($service, $keys) Allows for other plugins to override
 * the keys used for a given service.
 */
function buf_has_keys($service) {
  $lower = strtolower($service);
  $upper = strtoupper($service);
  
  $key = @sp_get_opt("{$lower}_key", constant("SP_{$upper}_KEY"));
  $secret = @sp_get_opt("{$lower}_secret", constant("SP_{$upper}_SECRET"));

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

/**
 * Get a SharePressClient instance, optionally configured for accessing
 * the underlying network on behalf of the given SharePressProfile.
 * @param mixed $service Either a string uniquely naming a service, e.g., 'facebook',
 * or a SharePressProfile object from which the service name will be derived
 * (SharePressProfile::$service). 
 * @param SharePressProfile $profile Optionally, configure the client
 * for posting to this profile
 * @return SharePressClient or, in the case of misconfiguration, a WP_Error object.
 */
function buf_get_client($service, $profile = false) {
  global $buf_clients;

  if ($service instanceof SharePressProfile) {
    $profile = $service;
    $service = $profile->service;
  }

  $service = strtolower($service);

  do_action('pre_buf_get_client', $service);

  if (!isset($buf_clients[$service])) {
    if (file_exists($core = SP_DIR.'/includes/clients/'.$service.'.php')) {
      require_once($core);
    }
    do_action("init_buf_get_client_{$service}", $service);
  }

  $class = sprintf('%sSharePressClientPro', ucwords($service));
  if (!class_exists($class)) {
    $class = sprintf('%sSharePressClient', ucwords($service));
    if (!class_exists($class)) {
      return new WP_Error('client', "SharePress Error: No client exists for service [$service]");
    }
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

/**
 * Allow for the given WordPress user account to post updates via
 * the given SharePressProfile.
 * @param SharePressProfile $profile
 * @param int $user_id
 */
function buf_add_team_member($profile, $user_id) {
  if (!$profile = buf_get_profile($profile_ref = $profile)) {
    return new WP_Error('profile', "Profile does not exist [{$profile_ref}]");
  }
  $team_members = get_post_meta($profile->id, 'team_members');
  if (!is_array($team_members)) {
    $team_members = array($user_id);
  } else if (!in_array($user_id, $team_members)) {
    $team_members[] = $user_id;
  }
  delete_post_meta($profile->id, 'team_members');
  foreach(array_filter($team_members) as $team_member) {
    add_post_meta($profile->id, 'team_members', $team_member);
  }
  return true;
}

/**
 * Remove the given user from the list of WordPress users allowed
 * to post to the given SharePressProfile.
 * @param SharePressProfile $profile
 * @param int $user_id
 */
function buf_remove_team_member($profile, $user_id) {
  if (!$profile = buf_get_profile($profile_ref = $profile)) {
    return new WP_Error('profile', "Profile does not exist [{$profile_ref}]");
  }
  $team_members = get_post_meta($profile->id, 'team_members');
  if (!is_array($team_members)) {
    return true;
  } else if (($idx = array_search($user_id, $team_members)) !== false) {
    unset($team_members[$idx]);
  }
  delete_post_meta($profile->id, 'team_members');
  foreach(array_filter($team_members) as $team_member) {
    add_post_meta($profile->id, 'team_members', $team_member);
  }
  return true;
}

/**
 * @param mixed $profile Either an integer or a SharePressProfile instance.
 * @return SharePressProfile or false if none exists
 */
function buf_get_profile($profile) {
  if ($profile instanceof SharePressProfile) {
    return $profile;
  }
  return SharePressProfile::forPost($profile);
}

/**
 * Query the profiles available in this site.
 * @param mixed Filtering args:
 * @return array(stdClass), ready for sending down the wire
 */
function buf_get_profiles($args = '') {
  global $wpdb;

  $args = wp_parse_args($args);

  $args['post_type'] = 'sp_profile';
  $args['numberposts'] = !empty($args['limit']) ? $args['limit'] : 0;

  if (!empty($args['user_id'])) {
    $args['author'] = $args['user_id'];
    unset($args['user_id']);
  }

  $params = array();

  $sql = "SELECT * FROM {$wpdb->posts}";
  if (!empty($args['author'])) {
    $sql .= "
      LEFT OUTER JOIN {$wpdb->postmeta} ON (
        post_ID = ID 
        AND meta_key = 'team_members' 
        AND meta_value = %d
      )
    ";
    $params[] = $args['author'];
  }
  $sql .= "
    WHERE
      post_type = 'sp_profile'
      AND post_status = 'enabled'
  ";
  if (!empty($args['author'])) {
    $sql .= "
      AND (
        post_author = %d
        OR meta_value = %d
      )
    ";
    $params[] = $args['author'];
    $params[] = $args['author'];
  }

  if (!empty($args['numberposts'])) {
    $sql .= "LIMIT %d OFFSET 0";
    $params[] = $args['numberposts'];
  }

  $posts = $wpdb->get_results($sql = $wpdb->prepare($sql, $params));

  $profiles = array();
  foreach($posts as $post) {
    $profiles[] = (object) buf_get_profile($post)->toJSON();
  }
  return $profiles;
}

/** 
 * Create or update a profile record
 * @param mixed Either an array or a stdClass of data to store
 */
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

    if (empty($meta['timezone'])) {
      $current_offset = get_option('gmt_offset');
      $meta['timezone'] = get_option('timezone_string');
      if ( empty($meta['timezone']) ) { // Create a UTC+- zone if no timezone string exists
        if ( 0 == $current_offset ) {
          $meta['timezone'] = 'UTC+0';
        } elseif ($current_offset < 0) {
          $meta['timezone'] = 'UTC' . $current_offset;
        } else {
          $meta['timezone'] = 'UTC+' . $current_offset;
        }
      }
    }

  } else {
    $post['ID'] = $post_id;
  }

  // $offset = get_option('gmt_offset');
  
  if (!$post_id && !array_key_exists('schedules', $profile)) {
    $profile['schedules'] = array(
      array(
        'days' => explode(',', 'mon,tue,wed,thu,fri'),
        'times' => explode(',', '12:00,17:00')
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
        if (empty($schedule['times'])) {
          $schedule['times'] = array('12:00', '17:00');
        }
        if (empty($schedule['days'])) {
          $schedule['days'] = array('mon', 'tue', 'wed', 'thu', 'fri');
        }
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

/**
 * Delete a profile record
 * @param mixed Either an integer or a SharePressProfile object
 */
function buf_delete_profile($profile) {
  if (!is_object($profile)) {
    $profile = buf_get_profile($profile_id = $profile);
    if (!$profile) {
      return false;
    }
  }
  return false !== wp_delete_post($profile->id, true);
}

/**
 * Create or update the record of content to be published (a.k.a., an "update")
 * @param mixed An array or a stdClass of data to store
 */
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

    $post = (array) $emptyxisting;

    if (!$profile = buf_get_profile($profile_id = get_post_meta($post_id, 'profile_id', true))) {
      return new WP_Error("Profile no longer exists [{$profile_id}]");
    }

    $profiles = array($profile);
  }

  if (array_key_exists('text', $update)) {
    $post['post_content'] = trim($update['text']);
  }

  if (array_key_exists('post_id', $update)) {
    $meta['post_id'] = $update['post_id'];
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

/**
 * An alias for identifying whether or not the current user is considered to
 * have administrative privileges over the buffering features of this application.
 * Currently this implies that the current user must have the "list_users" privilege,
 * which is currently given to WordPress Super Admins and Administrators.
 * @return bool
 */
function buf_current_user_is_admin() {
  return current_user_can('list_users');
}

/**
 * Update the given SharePressUpdate record's status to "error," and
 * record the given error data for future reference.
 * @param mixed $update Either a SharePressUpdate instance or an integer
 * @param mixed $error The error data to be recorded
 * @return If the SharePressUpdate does not exist, returns WP_Error object,
 * otherwise true.
 */
function buf_set_error_status($update, $error) {
  if (!$update = buf_get_update($update_ref = $update)) {
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }
  $post = get_post($update->id);
  $post->post_status = 'error';
  wp_insert_post($post);
  update_post_meta($update->id, 'error', $error);
  return true;
}

/**
 * Publishes the given update.
 * @param mixed $update SharePressUpdate instance of an integer
 * @return On error, a WP_Error object, otherwise a stdClass of data
 * representing the update and details of the transmission.
 */
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
  update_post_meta($update->id, 'sent_data', $result->data);
  return (object) buf_get_update($update->id)->toJSON();
}

/** 
 * Given a SharePressProfile, identify up to $limit of the updates scheduled to be posted
 * to that profile and reschedule them such that they are aligned to the profile's
 * underlying schedule. This should be used to refresh this "buffer" when
 * updates are created/removed and/or when the profile's schedule is modified.
 * @param mixed $profile a SharePressProfile or an integer
 * @param int $order
 */
function buf_update_buffer($profile, $order = null, $offset = null, $limit = 100) {
  global $wpdb;

  if (!$profile = buf_get_profile($profile_ref = $profile)) {
    return false;
  }

  if ($order) {
    if (!is_null($offset)) {
      if ($offset < 1 || $offset > $limit) {
        return new WP_Error("When updating order of buffer, offset must be between 1 and {$limit}");
      } else {
        // zero index
        $offset--;
      }
    } else {
      $offset = 0;
    }
  }

  $updates = $wpdb->get_results("
    SELECT * FROM {$wpdb->posts}
    WHERE
      post_type = 'sp_update'
      AND post_status = 'buffer'
    ORDER BY menu_order DESC
    LIMIT {$limit}
  ");

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
    
    // allow for updates whose publishing date/time is fixed
    if (get_post_meta($update->ID, 'bound_to') || get_post_meta($update->ID, 'fixed_delivery')) {
      $update->menu_order = -1;
      wp_insert_post($update); 
      continue;
    }

    // cycle the clock
    $next = $profile->next();

    $update->menu_order = $menu_order;
    $menu_order--;
    
    $update->post_date_gmt = gmdate('Y-m-d H:i:s', $next);
    $update->post_date = gmdate('Y-m-d H:i:s', $next + ( get_option('gmt_offset') * 3600 ) );
    wp_insert_post($update); 
    update_post_meta($update->ID, 'due_at', $next);
    update_post_meta($update->ID, 'due_time', gmdate('H:i a', $next));
  }
}

/**
 * @param mixed $update Either an integer or a SharePressUpdate instance.
 * @return SharePressUpdate or false if none exists
 */
function buf_get_update($update) {
  if ($update instanceof SharePressUpdate) {
    return $update;
  }
  return SharePressUpdate::forPost($update);
}

/**
 * Query the updates available in this site.
 * @param mixed Filtering args:
 * @return array(stdClass), ready for sending down the wire
 */
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

/**
 * Delete an update record, and update the buffer for the attached
 * SharePressProfile.
 * @param mixed Either an integer or a SharePressUpdate object
 */
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
