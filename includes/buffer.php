<?php
add_action('admin_bar_menu', 'sp_buf_admin_bar_menu', 1000);
add_action('wp_enqueue_scripts', 'sp_buf_wp_enqueue_scripts');
add_action('admin_enqueue_scripts', 'sp_buf_wp_enqueue_scripts');

/**
 * Enqueues the scripts that are used to render the
 * Bufferapp-like UI for queueing updates.
 */
function sp_buf_wp_enqueue_scripts() {
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
function sp_buf_admin_bar_menu() {
  global $wp_admin_bar;
  if (!is_admin_bar_showing()) {
    return;
  }

  /*
  $wp_admin_bar->add_menu(array(
    'id' => 'sp-buf-schedule',
    'title' => sprintf('<img src="%s">', plugins_url('img/admin-bar-wait.gif', SHAREPRESS)),
    'href' => '#',
  ));
  */
}

/** 
 * Given a SharePressProfile, identify up to $limit of the updates scheduled to be posted
 * to that profile and reschedule them such that they are aligned to the profile's
 * underlying schedule. This should be used to refresh this "buffer" when
 * updates are created/removed and/or when the profile's schedule is modified.
 * @param mixed $profile a SharePressProfile or an integer
 * @param int $order
 */
function sp_update_buffer($profile, $order = null, $offset = null, $limit = 100) {
  global $wpdb;

  if (!$profile = sp_get_profile($profile_ref = $profile)) {
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
    if (get_post_meta($update->ID, 'post_id', true) || get_post_meta($update->ID, 'schedule', true)) {
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
