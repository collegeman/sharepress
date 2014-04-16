<?php
add_action('init', 'sp_profile_init');

function sp_profile_init() {
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
 * @param mixed $profile Either an integer or a SharePressProfile instance.
 * @return SharePressProfile or false if none exists
 */
function sp_get_profile($profile) {
  if ($profile instanceof SharePressProfile) {
    return $profile;
  }
  if (!is_wp_error($profile = SharePressProfile::forPost($profile_ref = $profile))) {
    return apply_filters('sp_get_profile', $profile);
  } else {
    return $profile;
  }
}

/**
 * Try to find a Profile for the given service tag (service name and service unique ID).
 * This is less efficient then calling sp_get_profile() directly, so use it only
 * when a Profile cannot be found for a known ID.
 * @param String Profile service tag of format {$service}:{$service_id}
 * @return SharePressProfile or false if none exists
 * @see sp_get_profile
 */
function sp_get_profile_for_service_tag($service_tag) {
  global $wpdb;

  if (!$service_tag) {
    return false;
  }
  
  $profile_id = $wpdb->get_var( $wpdb->prepare("
    SELECT post_id 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = 'service_tag' 
      AND meta_value = %s
    LIMIT 1
  ", $service_tag) );

  if ($profile_id) {
    return sp_get_profile($profile_id);
  } else {
    return false;
  }
}

add_filter('sp_get_profile', 'sp_profile_filters', 10);

function sp_profile_filters($profile) {
  if ( has_filter('sp_extended_profile_filter') ) {
    return apply_filters('sp_extended_profile_filter', $profile);
  }
  return ($profile->user_id == get_current_user_id()) ? $profile : null;
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
    foreach(get_post_meta($data['id']) as $meta_key => $values) {
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

  function __get($name) {
    if ($name === 'service_tag') {
      return "{$this->service}:{$this->service_id}";
    }
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
    $data['id'] = (int) $data['id'];
    return $data;
  }

}


/**
 * Query the profiles available in this site.
 * @param mixed Filtering args:
 * @return array(stdClass), ready for sending down the wire
 */
function sp_get_profiles($args = '') {
  global $wpdb;

  $args = wp_parse_args($args);

  $args['post_type'] = 'sp_profile';
  $args['numberposts'] = !empty($args['limit']) ? (int) $args['limit'] : 0;

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
    $profile = sp_get_profile($post);
    if (empty($args['service']) || $profile->service === $args['service']) {
      $profiles[] = $profile;
    }
  }
  return $profiles;
}

/**
 * Delete a profile record
 * @param mixed Either an integer or a SharePressProfile object
 */
function sp_delete_profile($profile) {
  if (!is_object($profile)) {
    $profile = sp_get_profile($profile_id = $profile);
    if (!$profile) {
      return false;
    }
  }
  return false !== wp_delete_post($profile->id, true);
}


/** 
 * Create or update a profile record
 * @param mixed Either an array or a stdClass of data to store
 */
function sp_update_profile($profile) {
  global $wpdb;

  $profile = (array) $profile;

  $service_tag = false;
  if (!empty($profile['parent'])) {
    $parent = sp_get_profile($profile['parent']);
    $client = sp_get_client($parent);
    foreach($client->profiles() as $child) {
      if ($child->service_id === $profile['service_id']) {
        $profile = (array) $child;
        break;
      }
    }
    $service_tag = trim("{$profile['service']}:{$profile['service_id']}");
  } else if (!empty($profile['service']) && !empty($profile['service_id'])) {
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

  if (!empty($profile['link'])) {
    $meta['link'] = $profile['link'];
  }

  if (!empty($profile['avatar'])) {
    $meta['avatar'] = $profile['avatar'];
  }

  if (!empty($profile['timezone'])) {
    $meta['timezone'] = $profile['timezone'];
  }

  if (!empty($profile['limit'])) {
    $meta['limit'] = $profile['limit'];
  }

  if (array_key_exists('default_text', $profile)) {
    $meta['default_text'] = $profile['default_text'];
  }

  $meta['service'] = $profile['service'];

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

  $profile = sp_get_profile($post_id);

  sp_update_buffer($profile);

  return $profile;
}

/**
 * Allow for the given WordPress user account to post updates via
 * the given SharePressProfile.
 * @param SharePressProfile $profile
 * @param int $user_id
 */
function sp_add_team_member($profile, $user_id) {
  if (!$profile = sp_get_profile($profile_ref = $profile)) {
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
function sp_remove_team_member($profile, $user_id) {
  if (!$profile = sp_get_profile($profile_ref = $profile)) {
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

