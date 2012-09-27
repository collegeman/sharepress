<?php
add_filter('cron_schedules', 'sp_cron_schedules');
add_action('sp_activated', 'sp_init_cron');

function sp_init_cron() {
  if (!wp_next_scheduled('sp_twominute_cron')) {
    wp_schedule_event(time(), 'twominute', 'sp_twominute_cron');
  }
}

function sp_twominute_cron() {
  error_log('Two minutes!');
}

function sp_cron_schedules($schedules) {
  $schedules['twominute'] = array(
    'interval' => 60 * 2,
    'display' => __('Every 2 Minutes')
  );
  return $schedules;
}


