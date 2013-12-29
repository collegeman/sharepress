<?php
add_filter('cron_schedules', 'sp_cron_schedules');
add_action('sp_twominute_cron', 'sp_twominute_cron');

// XXX: this should be on activation, not on init:
add_action('sp_init', 'sp_init_cron');

function sp_cron_schedules($schedules) {
  $schedules['twominute'] = array(
    'interval' => 60 * 2,
    'display' => __('Every 2 Minutes')
  );
  return $schedules;
}

function sp_init_cron() {
  if (!wp_next_scheduled('sp_twominute_cron')) {
    wp_schedule_event(time(), 'twominute', 'sp_twominute_cron');
  }
}

function sp_twominute_cron() {
  // fork cron, so that if SharePress takes a long time
  // other scheduled tasks don't die
  $local_time = microtime( true );

  /*
  * multiple processes on multiple web servers can run this code concurrently
  * try to make this as atomic as possible by setting doing_cron switch
  */
  $lock = get_transient('sp_doing_cron');

  if ( $lock > $local_time + 10*60 )
    $lock = 0;

  // don't run if another process is currently running it or more than once every 60 sec.
  if ( $lock + WP_CRON_LOCK_TIMEOUT > $local_time )
    return;

  $doing_wp_cron = sprintf( '%.22F', $local_time );
  set_transient( 'sp_doing_cron', $doing_wp_cron );

  $cron_url = site_url( '/sp/1/cron?sp_doing_cron=' . $doing_wp_cron );
  wp_remote_post( $cron_url, array( 'timeout' => 0.01, 'blocking' => false, 'sslverify' => false ) );
}