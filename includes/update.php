<?php
add_action('init', 'sp_update_init');
add_filter('sp_update_text_format', 'sp_default_update_text_format', 10, 2);
add_filter('sp_permalink', 'sp_default_permalink', 10, 3);
add_action('wp_insert_post', 'sp_create_default_updates', 10, 3);
add_action('admin_menu', 'sp_updates_menu');
add_action('publish_post', 'sp_post_pending');

function sp_updates_menu() {
  // TODO: manage_options is the wrong perm--all authors should be able to see this screen
  add_submenu_page('sp-settings', 'SharePress Update History', 'Update History', 'manage_options', 'sp-updates', 'sp_updates_page');
}

function sp_updates_page() {
  wp_enqueue_script('sp_metabox_script', SP_URL.'/js/updates.js', array('sp_sharepress_script'));
  wp_enqueue_style('sp-updates', SP_URL.'/css/updates.css');
  sp_require_view('updates');
}

/**
 * Add our custom post type for SharePress updates
 */
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
      'text' => $post->post_content
    );

    return new SharePressUpdate($data);
  }

  function __toString() {
    return "sp_update:{$this->id}";
  }

  function __construct($data) {
    foreach(get_post_meta($data['id']) as $meta_key => $values) {
      if ($meta_key !== 'error') {
        $value = array_pop($values);
        $data[$meta_key] = maybe_unserialize($value);
      }
    }
   
    if ($data['status'] !== 'sent') {
      $data['sent_at'] = false;
      $data['service_update_id'] = false;
    }

    foreach((array) $data as $key => $value) {
      $this->{$key} = $value;
    }

    if (!$profile = sp_get_profile($this->profile_id)) {
      // try service tag
      $profile = sp_get_profile_for_service_tag($this->profile_service_tag);
    }
    $this->profile_service = $profile !== false ? $profile->service : false;
  }

  function __get($name) {
    if ($name === 'text_formatted') {
      return apply_filters('sp_update_text_format', $this->text, $this);
    } else {
      return null;
    }
  }

  function toJSON() {
    $data = get_object_vars($this);
    unset($data['shorten']);
    unset($data['sent_data']);
    $data['text_formatted'] = $this->text_formatted;
    $data['id'] = (int) $data['id'];
    $data['profile_id'] = (int) $data['profile_id'];
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
 * @param int The ID of an update
 * @return mixed If the update exists and has an error, return WP_Error;
 * otherwise returns false.
 */
function get_last_error_for_update($id) {
  if ($errors = get_post_meta($id, 'error')) {
    return array_pop($errors);
  } else {
    return false;
  }
}

/**
 * @return Count of Updates, indexed by status. 
 */
function sp_count_updates() {
  global $wpdb;

  $data = $wpdb->get_results("
    SELECT COUNT(*) AS cnt, post_status 
    FROM {$wpdb->posts} 
    WHERE post_type = 'sp_update' 
    GROUP BY post_status
  ");

  $counts = array(
    'all' => 0,
    'trash' => 0,
    'buffer' => 0,
    'sent' => 0,
    'error' => 0
  );
  foreach($data as $r) {
    $counts[$r->post_status] = $r->cnt;
  }
  @$counts['all'] = array_sum(array_values($counts)) - $counts['trash'];

  return $counts;
}

/**
 * Put the given update in the trash
 * @return WP_Error on failure
 */
function sp_trash_update($update) {
  if (!$update = sp_get_update($update_ref = $update)) {
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }

  if ($update->status !== 'trash') {
    update_post_meta($update->id, 'old_post_status', $update->status);
  }

  return sp_set_update_status($update, 'trash');
}

/**
 * Restore the previous status of the given update
 * @return WP_Error on failure
 */
function sp_restore_update($update) {
  if (!$update = sp_get_update($update_ref = $update)) {
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }

  if (!$old_post_status = get_post_meta($update->id, 'old_post_status', true)) {
    return new WP_Error('update-missing-old-post-status', "Update cannot be restored: missing previous status");
  }

  delete_post_meta($update->id, 'old_post_status');
  return sp_set_update_status($update, $old_post_status);
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
  global $wpdb;

  // TODO: put this cron lock check in a function and reuse it
  // here as well as in includes/cron.php
  $local_time = microtime( true );
  $lock = get_transient('sp_doing_cron');
  if ( $lock > $local_time + 10*60 ) {
    $lock = 0;
  }
  if ( $lock + WP_CRON_LOCK_TIMEOUT > $local_time ) {
    sp_log('sp_post_pending: locked out by cron');
    return;
  }

  if (is_null($until)) {
    // default cron job is on a 2-min interval, so we
    // look one minute into the future
    $until = time() + 60;
  }

  // a little bit of var filtering
  $until = (int) $until;

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

  $results = array();

  foreach($posts as $post) {
    if ($update = sp_get_update($post->ID)) {
      $results[] = sp_post_update($post->ID);  
    }
  }

  do_action('post_sp_post_pending');

  return $results;
}

/**
 * Publishes the given update.
 * @param mixed $update SharePressUpdate instance or an int
 * @return On error, a WP_Error object, otherwise a stdClass of data
 * representing the update and details of the transmission.
 */
function sp_post_update($update) {
  if (!$update = sp_get_update($update_ref = $update)) {
    sp_log("sp_post_update #{$update_ref} does not exist", 'ERROR');
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }

  sp_log("sp_post_update #{$update->id}");
  
  // if this update is bound to a Post ID
  $post = false;
  if ($update->post_id) {
    $post = get_post($update->post_id);
    if ($post->post_status === 'trash') {
      // return an error, but only for information purposes--no need to log this
      $error = new WP_Error('post-trashed', "Associated Post has been trashed [{$update->post_id}]");
      $error->add_data(array(
        'update' => $update->toJSON()
      ));
      return $error;
    } else if ($post->post_status !== 'publish') {
      // return an error, but only for information purposes--no need to log this
      $error = new WP_Error('post-not-published', "Associated Post is not published [{$update->post_id}]");
      $error->add_data(array(
        'update' => $update->toJSON()
      ));
      return $error;
    }
  }

  if (!$profile = sp_get_profile($update->profile_id)) {
    $error = new WP_Error('profile-does-not-exist', "Profile does not exist [{$update->profile_id}]"); 
    sp_set_update_error_status($update, $error);
    $error->add_data(array(
      'update' => $update->toJSON()
    ));
    return $error;
  }

  if (is_wp_error($client = sp_get_client($profile))) {
    $error = $client;
    $error->add_data(array(
      'update' => $update->toJSON()
    ));
    sp_set_update_error_status($update, $error);
    return $error;
  }
  
  $message = apply_filters('sp_update_text_format', $client->filter_update_text($update->text), $update);

  if (is_wp_error($message)) {
    $error = $message;
    $error->add_data(array(
      'profile' => $profile->toJSON(),
      'update' => $update->toJSON()
    ));
    sp_set_update_error_status($update, $error);
    return $error;
  }

  // prepare update configuration
  $config = array();
  // if there is a related wordpress post, attach a URL
  if ($post) {
    $config['url'] = apply_filters('sp_permalink', get_permalink($post), $post, $update);
  }

  if (is_wp_error($result = $client->post($message, $config))) {
    $error = $result;
    $error->add_data(array(
      'profile' => $profile->toJSON(),
      'update' => $update->toJSON()
    ));
    sp_set_update_error_status($update, $error);
    return $error;
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
  }

  $args['numberposts'] = ($limit = (int) $args['limit']) ? $limit : 100;
  $args['offset'] = ($offset = (int) $args['offset']) ? $offset : 0;

  $args['orderby'] = 'post_date_gmt';
  $args['order'] = !empty($args['order']) && strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

  $params = array(
    $args['post_type']
  );

  $sql = '';

  if (!empty($args['post_id']) || !empty($profile)) {
    $sql .= " JOIN {$wpdb->postmeta} ON (post_ID = ID) ";
  }

  $sql .= "
    WHERE 
      post_type = %s
  ";

  if (!empty($args['post_status'])) {
    $params[] = $args['post_status'];
    $sql .= ' AND post_status = %s ';
  } else if (!empty($args['post_id'])) {
    $sql .= " AND post_status = 'buffer' ";
  } else {
    $sql .= " AND post_status <> 'trash' ";
  }

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

  $countSql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array("SELECT COUNT({$wpdb->posts}.ID) FROM {$wpdb->posts}" . $sql), $params));
  $postsSql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array("SELECT * FROM {$wpdb->posts}" . $sql . $orderAndLimit), $params));

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

  if (!empty($update['id']) && !empty($update['restore'])) {
    return sp_restore_update($update['id']);
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

    $update['text'] = apply_filters('sp_update_text', !empty($update['text']) ? $update['text'] : "", $update, $post, $profiles);
    if (!$text = trim($update['text'])) {
      return new WP_Error("Cannot create empty Update");
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
    if ($post['post_status'] === 'sent') {
      if ($update['text'] != $post['post_content']) {
        return new WP_Error("Cannot modify this Update: it has already been sent.");
      }
    }

    $post['post_content'] = trim($update['text']);
  }

  if (array_key_exists('post_id', $update)) {
    $meta['post_id'] = $update['post_id'];
  }

  if (array_key_exists('schedule', $update)) {
    $meta['schedule'] = $update['schedule'];

    // configure due_at based on schedule
    $schedule = (array) $update['schedule'];
    // easy case: on publish, set due_at to 0
    if ($schedule['when'] === 'publish' || $schedule['when'] === 'immediately') {
      $meta['due_at'] = 0;
    // otherwise, it's schedule for future publication
    } else {
      $meta['due_at'] = $schedule['time'];
    }
  }
  
  $post_ids = array();

  foreach($profiles as $profile) {
    $meta['profile_id'] = $profile->id;
    $meta['profile_service_tag'] = $profile->service_tag;

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
function sp_set_update_error_status($update, $error = null) {
  sp_log($error, 'ERROR');
  if (!$update = sp_get_update($update_ref = $update)) {
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }
  $post = get_post($update->id);
  $post->post_status = 'error';
  wp_insert_post($post);
  add_post_meta($update->id, 'error', $error);
  return true;
}

/**
 * Change the post status of the given Update
 * @param mixed $update Either a SharePressUpdate instance or an integer
 * @param String $status Defaults to "buffer" (resetting the post status)
 * @return If the SharePressUpdate does not exist, returns WP_Error object,
 * otherwise true.
 */
function sp_set_update_status($update, $status = 'buffer') {
  if (!$update = sp_get_update($update_ref = $update)) {
    return new WP_Error('update', "Update does not exist [{$update_ref}]");
  }
  $post = get_post($update->id);
  $post->post_status = $status;
  wp_insert_post($post);
  return true;
}

/**
 * Default filter for update text: replaces [title] and [link] placeholders
 * Will return WP_Error if sp_shorten returns one
 * @see sp_shorten
 */
function sp_default_update_text_format($text, $update) {
  if ($update->post_id) {
    $orig = $text;
    $post = get_post($update->post_id);

    // [title]
    if (stripos($text, '[title]') !== false) {
      $text = preg_replace('/\[title\]/', apply_filters('sp_the_title', $post->post_title), $text);
    }

    // [link]
    if (stripos($text, '[link]') !== false) {
      $filtered_permalink = apply_filters('sp_permalink', get_permalink($post), $post, $update);
      if (is_wp_error($shortened = sp_shorten($filtered_permalink))) {
        return $shortened;
      }
      $text = preg_replace('/\[link\]/', $shortened, $text);
    }
  }

  return $text;
}

/**
 * Default filter for post permalinks
 */
function sp_default_permalink($permalink, $post, $update) {
  return $permalink;
}

/**
 * Create any default updates configured for newly created Posts
 * @param int The Post ID of the post created
 */
function sp_create_default_updates($post_ID, $post, $updated) {

}