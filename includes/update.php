<?php
add_action('init', 'sp_update_init');

function sp_update_init() {
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
      'schedule' => get_post_meta($update->ID, 'schedule', true),
      'due_at' => get_post_meta($update->ID, 'due_at', true),
      'due_time' => get_post_meta($update->ID, 'due_time', true),
      'post_id' => get_post_meta($update->ID, 'post_id', true)
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

    $profile = sp_get_profile($this->profile_id);
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
 * @action pre_sp_post_pending
 * @action post_sp_post_pending
 */
function sp_post_pending($until = null) {
  error_log('sp_post_pending');

  global $wpdb;

  if (is_null($until)) {
    // default cron job is on a 2-min interval, so we
    // look one minute into the future
    $until = time() + 60;
  }

  do_action('pre_sp_post_pending');

  $posts = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts}
    JOIN {$wpdb->postmeta} ON (post_ID = ID)
    WHERE
      post_type = 'sp_update'
      AND post_status = 'buffer'
      AND meta_key = 'due_at' 
      AND meta_value IS NOT NULL
      AND meta_value <= {$until}
  ");

  foreach($posts as $post) {
    if ($update = sp_get_update($post->ID)) {
      sp_post_update($post->ID);  
    }
  }

  do_action('post_sp_post_pending');
}



/**
 * Publishes the given update.
 * @param mixed $update SharePressUpdate instance of an integer
 * @return On error, a WP_Error object, otherwise a stdClass of data
 * representing the update and details of the transmission.
 */
function sp_post_update($update) {
  if (!$update = sp_get_update($update_ref = $update)) {
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }
  if (!$profile = sp_get_profile($update->profile_id)) {
    $error = WP_Error('profile', "Profile does not exist [{$update->profile_id}]"); 
    sp_set_error_status($update, $error);
    $error->add_data(array(
      'update' => $update->toJSON()
    ));
    return $error;
  }
  if (is_wp_error($client = sp_get_client($profile))) {
    $client->add_data(array(
      'update' => $update->toJSON()
    ));
    sp_set_error_status($update, $client);
    return $client;
  }
  if (is_wp_error($result = $client->post($update->text_formatted))) {
    $result->add_data(array(
      'profile' => $profile->toJSON(),
      'update' => $update->toJSON()
    ));
    sp_set_error_status($update, $result);
    return $result;
  }
  $post = get_post($update->id);
  $post->post_status = 'sent';
  wp_insert_post($post);
  update_post_meta($update->id, 'sent_at', time());
  update_post_meta($update->id, 'service_update_id', $result->service_update_id);
  update_post_meta($update->id, 'sent_data', $result->data);
  return (object) sp_get_update($update->id)->toJSON();
}


/**
 * @param mixed $update Either an integer or a SharePressUpdate instance.
 * @return SharePressUpdate or false if none exists
 */
function sp_get_update($update) {
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
function sp_get_updates($args = '') {
  global $wpdb;

  $args = wp_parse_args($args);

  if (!empty($args['profile_id'])) {
    if (!$profile = sp_get_profile($args['profile_id'])) {
      return new WP_Error("Profile does not exist [{$args['profile_id']}]");
    }
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

  $params = array(
    $args['post_type'],
    $args['post_status']
  );

  $sql = "
    JOIN {$wpdb->postmeta} ON (post_ID = ID)
    WHERE 
      post_type = %s
      AND post_status = %s
  ";

  if (!empty($args['post_id'])) {
    $sql .= "
      AND meta_key = 'post_id'
      AND meta_value = %s
    ";
    $params[] = $args['post_id'];
  } else if (!empty($profile)) {
    $sql .= "
      AND meta_key = 'profile_id'
      AND meta_value = %s
    ";
    $params[] = $profile->id;
  }
  
  $orderAndLimit = "
    ORDER BY
      {$args['orderby']} {$args['order']}
    LIMIT %d OFFSET %d
  ";

  $params[] = $args['numberposts'];
  $params[] = $args['offset'];

  $countSql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array("SELECT COUNT(post_ID) FROM {$wpdb->posts}" . $sql . $orderAndLimit), $params));
  $postsSql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array("SELECT * FROM {$wpdb->posts}" . $sql), $params));

  $count = $wpdb->get_var($countSql);
  $posts = $wpdb->get_results($postsSql);

  $updates = array();
  foreach($posts as $post) {
    $updates[] = (object) sp_get_update($post)->toJSON();
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
function sp_delete_update($update) {
  $profile = false;
  if (!is_object($update)) {
    $update = sp_get_update($update_id = $update);
    if (!$update) {
      return false;
    }
    $profile = sp_get_profile($update->profile_id);
  }
  $result = false !== wp_delete_post($update->id, true);
  sp_update_buffer($profile);
  return $result;
}

/**
 * Create or update the record of content to be published (a.k.a., an "update")
 * @param mixed An array or a stdClass of data to store
 */
function sp_update_update($update) {
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

    if (!empty($update['profile_id'])) {
      if (isset($update['profile_ids'])) {
        $update['profile_ids'][] = $update['profile_id'];
      } else {
        $update['profile_ids'] = array($update['profile_id']);
      }
      unset($update['profile_id']);  
    }

    if (empty($update['profile_ids'])) {
      return new WP_Error('profile', 'Missing profile_ids arg');
    }

    foreach($update['profile_ids'] as $profile_id) {
      if (!$profile = sp_get_profile($profile_id)) {
        return new WP_Error('profile', "Profile does not exist [{$profile_id}]");
      }
      if ($profile->user_id !== get_current_user_id()) {
        if (!sp_current_user_is_admin() && !in_array(get_current_user_id(), $profile->team_members)) {
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

    if (!$profile = sp_get_profile($profile_id = get_post_meta($post_id, 'profile_id', true))) {
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

  if (array_key_exists('schedule', $update)) {
    $meta['schedule'] = $update['schedule'];
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
      if (is_wp_error($update = sp_post_update($post_id))) {
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
      sp_update_buffer($profile);
    }

    foreach($post_ids as $post_id) {
      $updates[] = (object) sp_get_update($post_id)->toJSON();
    }
    
    $result = array(
      'success' => true,
      'buffer_count' => null,
      'buffer_percentage' => null
    );

    $result['updates'] = $updates;
  
    return (object) $result;
  }  
}

/**
 * Update the given SharePressUpdate record's status to "error," and
 * record the given error data for future reference.
 * @param mixed $update Either a SharePressUpdate instance or an integer
 * @param mixed $error The error data to be recorded
 * @return If the SharePressUpdate does not exist, returns WP_Error object,
 * otherwise true.
 */
function sp_set_error_status($update, $error = null) {
  if (!$update = sp_get_update($update_ref = $update)) {
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }
  $post = get_post($update->id);
  $post->post_status = 'error';
  wp_insert_post($post);
  update_post_meta($update->id, 'error', $error);
  return true;
}