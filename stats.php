<?php
/*
Copyright (C)2011 Fat Panda, LLC

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class SharePressStats {

  const VERSION = '1.0';

  const MUTEX = 'sharepress_stats_mutex-fx:%s';
  
  private static $instance;
  static function load() {
    $class = __CLASS__;
    return ( self::$instance ? self::$instance : ( self::$instance = new $class() ));
  }

  private function __construct() {
    add_action('init', array($this, 'init'));
    add_action('wp_ajax_sharepress_facebook_stats', array($this, 'ajax_facebook_stats'));
  }

  function ajax_facebook_stats() {
    if (!current_user_can('level_1')) {
      return;
    }

    sharepress_cron_backfill_facebook_comment_stats();
  }

  function init() {
    add_action('wp_dashboard_setup', array($this, 'wp_dashboard_setup'));

    /*
    if (!wp_next_scheduled('sharepress_cron_backfill_facebook_comment_stats')) {
      wp_schedule_event(time(), 'hourly', 'sharepress_cron_backfill_facebook_comment_stats');
    }

    if (!wp_next_scheduled('sharepress_cron_update_facebook_comment_stats')) {
      wp_schedule_event(time(), 'oneminute', 'sharepress_cron_update_facebook_comment_stats');
    }
    */
  }

  function wp_dashboard_setup() {
    wp_add_dashboard_widget('sharepress_facebook_stats', 'SharePress Facebook Stats', array($this, 'facebook_stats_widget'));
  }

  function facebook_stats_widget() {
    ?>
      <script>
        jQuery.post(ajaxurl, { action: 'sharepress_facebook_stats' }, function(response) {
          console.log(response);
        })
      </script>
    <?php
  }

  /**
   * Beginning with $start and working backwards, download
   * $days worth of comment statistical data.
   * @param mixed $page_id The unique ID of a Facebook object (page or user)
   * @param int $oldest Unix timestamp
   * @param int $period number of seconds in each period, defaults to 86400 (24 hours)
   * @param int $intervals defaults to 31
   * @return null
   * @throws SpMutexException When the function is already running 
   * @throws spFacebookApiException When the FacebooK SDK throws an Exception
   * @throws Exception If an error is returned by the Graph API, as in the case of exceeding API limits
   */
  function download_raw_comment_stats($page_id, $oldest, $period = 86400, $intervals = 31) {
    if (is_null($oldest)) {
      $oldest = time();
    }

    global $wpdb;
    $mutex_key = sprintf(self::MUTEX, __FUNCTION__);

    if (get_transient($mutex_key)) {
      throw new SpMutexException($mutex_key);
    } 

    set_transient($mutex_key, true, 300);
    
    $tbl_raw = $wpdb->prefix . 'sharepress_raw';
      
    // does our table exist?
    if (get_option(__CLASS__.'_version') != self::VERSION) {
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta("
        CREATE TABLE {$tbl_raw} (
          `id` VARCHAR(64) NOT NULL,
          `page_id` VARCHAR(64) NOT NULL,
          `object_id` VARCHAR(64) NOT NULL,
          `post_id` VARCHAR(64) NOT NULL,
          `time` BIGINT NOT NULL,
          `fromid` VARCHAR(64) NOT NULL,
          `wp_post_id` BIGINT NULL,
          PRIMARY KEY (`id`),
          KEY `time` (`time`,`fromid`),
          KEY `wp_post_id` (`wp_post_id`)
        ) ENGINE=InnoDB;
      ");
    }  

    $dates = array();
    $batch = array();

    mysql_query('BEGIN', $wpdb->dbh);

    for($i = 0; $i < $intervals; $i++) {
      $end = $oldest - ( $period * $i );

      $start = $oldest - ( $period * ( $i + 1 ));

      $dates[] = date('Y-m-d', $start);

      $batch[] = array(
        'method' => 'POST',
        'relative_url' => "method/fql.query?query=" .
          urlencode("
            select id, post_id, object_id, fromid, time 
            from comment 
            where post_id in ( 
              select post_id 
              from stream 
              where source_id = {$page_id} 
              and created_time > {$start} and created_time < {$end} 
            )
          ")
      );

      $wpdb->query( $wpdb->prepare("DELETE FROM {$tbl_raw} WHERE `page_id` = %s AND `time` BETWEEN %d AND %d", $page_id, $start, $end) );
    }

    try {
      $responses = Sharepress::api('/', 'POST', array('batch' => json_encode($batch)));
    } catch (Exception $e) {
      mysql_query('ROLLBACK', $wpdb->dbh);
      delete_transient($mutex_key);
      throw $e;
    }

    $wpdb->show_errors();

    foreach($responses as $i => $response) {
      $result = json_decode($response['body']);
      if (is_array($result)) {
        foreach($result as $R) {
          $R->page_id = $page_id;
          $wpdb->insert($tbl_raw, (array) $R);
        }
      } else if ($result->error_msg) {
        mysql_query('ROLLBACK', $wpdb->dbh);
        delete_transient($mutex_key);
        throw new Exception($result->error_msg);
      }
    }

    mysql_query('COMMIT', $wpdb->dbh);

    delete_transient($mutex_key);
  }

  function error($error) {
    if (is_object($error)) {
      $error = $error->getMessage();
    }
    
    wp_mail(
      SharePress::load()->get_error_email(),
      "SharePress Stats Error",
      "While backfilling Facebook comments stats data, {$error}"
    );
    
    error_log("SharePress Stats Error: $error");
  }

}

function sharepress_cron_backfill_facebook_comment_stats() {
  global $wpdb;
  $tbl_raw = $wpdb->prefix . 'sharepress_raw';
  
  $page_id = 73674099237;

  // what is the oldest date we have comment data for?
  $oldest = $wpdb->get_var( $sql = $wpdb->prepare("SELECT min(`time`) FROM {$tbl_raw} WHERE page_id = %s", $page_id) );

  // is the oldest less than a year old?
  if (!$oldest || time() - 31536000 < $oldest) {
    try {
      SharePressStats::load()->download_raw_comment_stats($page_id, $oldest, 86400, 31);
    } catch (Exception $e) {
      self::error(sprintf("While backfilling Facebook comments stats data, %s", $e->getMessage()));
    }
  }
}

function sharepress_cron_forwardfill_facebook_comment_stats() {
  global $wpdb;
  $tbl_raw = $wpdb->prefix . 'sharepress_raw';
  
  $page_id = 73674099237;

  // what is the newest date we have comment data for?
  $newest = $wpdb->get_var( $sql = $wpdb->prepare("SELECT max(`time`) FROM {$tbl_raw} WHERE page_id = %s", $page_id) );

  // take the oldest of $newest and ten minutes ago
  $oldest = min( $newest, time() - 600 );

  // less than an hour? only download what is necessary
  $diff = (time() - $oldest) / 3600;

  // less than or equal to a day? just download it all
  if ($diff <= 24) {
    $period = $diff;
    $interval = 1;

  // more than a day? download up to 31 days worth 
  } else {
    $days = ceil( (time() - $oldest) / 86400 );
    $interval = max( $days, 31 );
    $period = 86400;

  }
  
  // backfill comment data  
  try {
    SharePressStats::load()->download_raw_comment_stats($page_id, time(), $period, $interval);
  } catch (Exception $e) {
    self::error(sprintf("While updating Facebook comments stats data, %s", $e->getMessage()));
  }
}

class SpMutexException extends Exception {}

SharePressStats::load();