<?php
/*
Copyright (C)2011-2012 Fat Panda, LLC

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
  }

  function init() {
    add_action('wp_dashboard_setup', array($this, 'wp_dashboard_setup'));
    add_filter('cron_schedules', array($this, 'cron_schedules'));
    add_action('template_redirect', array($this, 'template_redirect'));
    /*
    if (!wp_next_scheduled('sharepress_cron_backfill_facebook_comment_stats')) {
      wp_schedule_event(time(), 'tenminute', 'sharepress_cron_backfill_facebook_comment_stats');
    }
    add_action('sharepress_cron_backfill_facebook_comment_stats', array($this, 'backfill_facebook_comment_stats'));
    
    if (!wp_next_scheduled('sharepress_cron_forwardfill_facebook_comment_stats')) {
      wp_schedule_event(time(), 'fifteenminute', 'sharepress_cron_forwardfill_facebook_comment_stats');
    }
    add_action('sharepress_cron_backfill_facebook_comment_stats', array($this, 'forwardfill_facebook_comment_stats'));
    
    if (!wp_next_scheduled('sharepress_cron_process_posts')) {
      wp_schedule_event(time(), 'tenminute', 'sharepress_cron_process_posts');
    }
    add_action('sharepress_cron_backfill_facebook_comment_stats', array($this, 'process_posts'));
    */
  }

  function template_redirect() {
    if (!current_user_can('administrator')) {
      return false;
    }

    if (preg_match('#sharepress(/.*)/?#', $_SERVER['REQUEST_URI'], $matches)) {
      $slugs = array_filter(explode('/', $matches[1]));
      $callback = array($this, array_shift($slugs));
      ob_start();
      try {
        call_user_func_array($callback, $slugs);
      } catch (Exception $e) {
        status_header(500);
        echo $e->getMessage();
      }
      status_header(200);
      $content = ob_get_clean();
      echo '<pre>';
      echo $content;
      exit;
    }
  }

  function cron_schedules($schedules) {
    $schedules['tenminute'] = array(
      'interval' => 600,
      'display' => __('Every Ten Minutes')
    );

    $schedules['fifteenminute'] = array(
      'interval' => 900,
      'display' => __('Every Fifteen Minutes')
    );
    
    return $schedules;
  }

  function wp_dashboard_setup() {
    wp_add_dashboard_widget('sharepress_facebook_stats', 'Facebook Stats', array($this, 'facebook_stats_widget'));
  }

  function facebook_stats_widget() {
    
  }

  function download_posts($page_id, $oldest = null, $period = 86400, $intervals = 31) {
    global $wpdb;
    $table = self::tables();

    if (is_null($oldest)) {
      $oldest = time();
    }

    $mutex_key = sprintf(self::MUTEX, __FUNCTION__);

    if (get_transient($mutex_key)) {
      throw new SpMutexException($mutex_key);
    } 

    set_transient($mutex_key, true, 300);
    
    $dates = array();
    $batch = array();

    for($i = 0; $i < $intervals; $i++) {
      $end = $oldest - ( $period * $i );

      $start = $oldest - ( $period * ( $i + 1 ));

      $dates[] = date('Y-m-d', $start);

      $batch[] = array(
        'method' => 'POST',
        'relative_url' => "method/fql.query?query=" .
          urlencode(trim("
            select post_id, app_id, created_time, updated_time, actor_id, message, impressions
            from stream 
            where source_id = '{$page_id}'
            and created_time > {$start} and created_time < {$end} 
          "))
      );
    }
    
    try {
      $responses = Sharepress::api('/', 'POST', array('batch' => json_encode($batch)));
    } catch (Exception $e) {
      delete_transient($mutex_key);
      throw $e;
    }

    $posts = array();

    foreach($responses as $i => $response) {
      $result = json_decode($response['body']);
      if (is_array($result)) {
        foreach($result as $R) {
          $post = array(
            'post_id' => $R->post_id,
            'created_time' => $R->created_time,
            'page_id' => $page_id,
            'actor_id' => $R->actor_id,
            'message' => $R->message,
            'impressions' => $R->impressions,
            'app_id' => $R->app_id
          );

          $posts[$R->post_id] = $post;
        }
      } else if ($result->error_msg) {
        delete_transient($mutex_key);
        throw new Exception($result->error_msg);
      }
    }

    $stats = array();
    $chunks = array_chunk($posts, 50, true);

    foreach($chunks as $posts) {
      $batch = array();

      foreach($posts as $post_id => $P) {
        $batch[] = array(
          'method' => 'GET',
          'relative_url' => $post_id
        );
      }

      try {
        $responses = Sharepress::api('/', 'POST', array('batch' => json_encode($batch)));
      } catch (Exception $e) {
        delete_transient($mutex_key);
        throw $e;
      }

      foreach($responses as $i => $response) {
        $result = json_decode($response['body']);
        if ($result->error_msg) {
          delete_transient($mutex_key);
          throw new Exception($result->error_msg);
        } else {
          $post_id = $result->id;
          $posts[$post_id]['type'] = $result->type;
          $posts[$post_id]['link'] = $result->type == 'link' ? $result->link : null;
          
          $stats[$post_id]['impressions'] = $posts[$post_id]['impressions'];
          $stats[$post_id]['likes'] = $result->likes ? $result->likes->count : 0;
          $stats[$post_id]['comments'] = $result->comments ? $result->comments->count : 0;
          $stats[$post_id]['shares'] = $result->shares ? $result->shares->count : 0;
        }
      }

      foreach($posts as $P) {
        unset($P['impressions']);
        $wpdb->insert($table['posts'], $P, array('%s', '%d', '%s', '%s', '%s', '%s'));
      }
    }

    foreach($stats as $post_id => $stat) {
      $stat['post_id'] = $post_id;
      $stat['data_updated'] = time();

      if ($stat['impressions'] === '' || is_null($stat['impressions'])) {
        unset($stat['impressions']);
        $wpdb->insert($table['posts_stats'], $stat, array('%d', '%d', '%s', '%d', '%s'));  
      } else {
        $wpdb->insert($table['posts_stats'], $stat, array('%d', '%d', '%d', '%s', '%d', '%s'));  
      }  
    }

    delete_transient($mutex_key);
  }

  function download_metric($page_id, $metric, $oldest = null, $period = 86400, $intervals = 31) {
    if (strpos($metric, ',')) {
      foreach(explode(',', $metric) as $metric) {
        $this->download_metric($page_id, $metric, $oldest, $period, $intervals);
      }
      return;
    }

    global $wpdb;
    $table = self::tables();

    if (is_null($oldest)) {
      $oldest = time();
    }

    $mutex_key = sprintf(self::MUTEX, __FUNCTION__);

    // if (get_transient($mutex_key)) {
    //   throw new SpMutexException($mutex_key);
    // } 

    set_transient($mutex_key, true, 300);
    
    $batch = array();
    $dates = array();

    for($i = 0; $i < $intervals; $i++) {
      $end = $oldest - ( $period * $i );
      $end_date = date('Y-m-d', $end);
      $dates[] = strtotime($end_date);

      $batch[] = array(
        'method' => 'POST',
        'relative_url' => "method/fql.query?query=" .
          urlencode(trim("
            select value, end_time
            from insights 
            where 
              object_id = '{$page_id}'
              and metric = '{$metric}'
              and end_time = end_time_date('{$end_date}')
              and period = {$period}
          "))
      );
    }

    try {
      $responses = Sharepress::api('/', 'POST', array('batch' => json_encode($batch)));
    } catch (Exception $e) {
      delete_transient($mutex_key);
      throw $e;
    }

    foreach($responses as $i => $response) {
      $result = json_decode($response['body']);
      if (is_array($result)) {
        foreach($result as $R) {
          $R->page_id = $page_id;
          $wpdb->query($sql = "
            REPLACE INTO `{$table['metrics']}` (
              `page_id`, `end_time`, `metric`, `value`
            ) VALUES (
              '{$R->page_id}',
              '{$R->end_time}',
              '{$metric}',
              '{$R->value}'
            )
          ");
        }
      } else if ($result->error_msg) {
        delete_transient($mutex_key);
        throw new Exception($result->error_msg);
      }
    }

    delete_transient($mutex_key);
  }

  /**
   * Beginning with $oldest and working backwards, download
   * $days worth of comment statistical data.
   * @param mixed $page_id The unique ID of a Facebook object (page or user)
   * @param int $oldest Unix timestamp; defaults to now
   * @param int $period number of seconds in each period, defaults to 86400 (24 hours)
   * @param int $intervals defaults to 31
   * @return null
   * @throws SpMutexException When the function is already running 
   * @throws spFacebookApiException When the FacebooK SDK throws an Exception
   * @throws Exception If an error is returned by the Graph API, as in the case of exceeding API limits
   */
  function download_comments($page_id, $oldest = null, $period = 86400, $intervals = 31) {
    global $wpdb;
    $table = self::tables();

    if (is_null($oldest)) {
      $oldest = time();
    }

    $mutex_key = sprintf(self::MUTEX, __FUNCTION__);

    if (get_transient($mutex_key)) {
      throw new SpMutexException($mutex_key);
    } 

    set_transient($mutex_key, true, 300);
    
    $dates = array();
    $batch = array();

    for($i = 0; $i < $intervals; $i++) {
      $end = $oldest - ( $period * $i );

      $start = $oldest - ( $period * ( $i + 1 ));

      $dates[] = date('Y-m-d', $start);

      $batch[] = array(
        'method' => 'POST',
        'relative_url' => "method/fql.query?query=" .
          urlencode(trim("
            select id, post_id, object_id, fromid, time 
            from comment 
            where post_id in ( 
              select post_id 
              from stream 
              where source_id = '{$page_id}'
              and created_time > {$start} and created_time < {$end} 
            )
          "))
      );
    }

    try {
      $responses = Sharepress::api('/', 'POST', array('batch' => json_encode($batch)));
    } catch (Exception $e) {
      delete_transient($mutex_key);
      throw $e;
    }

    foreach($responses as $i => $response) {
      $result = json_decode($response['body']);
      if (is_array($result)) {
        foreach($result as $R) {
          $R->page_id = $page_id;
          $wpdb->insert($table['comments'], array(
            'id' => $R->id,
            'object_id' => $R->object_id,
            'post_id' => $R->post_id,
            'time' => $R->time,
            'fromid' => $R->fromid,
            'page_id' => $R->page_id
          ), array(
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s'
          ));
        }
      } else if ($result->error_msg) {
        delete_transient($mutex_key);
        throw new Exception($result->error_msg);
      }
    }

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

  static function tables($force_create = true) {
    global $wpdb;

    $table = array(
      'comments' => $wpdb->prefix . 'sharepress_comments',
      'posts' => $wpdb->prefix . 'sharepress_posts',
      'posts_stats' => $wpdb->prefix . 'sharepress_posts_stats',
      'metrics' => $wpdb->prefix . 'sharepress_metrics'
    );
      
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    if ($force_create || get_option(__CLASS__.'_version') != self::VERSION) {
      dbDelta("
        CREATE TABLE {$table['comments']} (
          `id` VARCHAR(64) NOT NULL,
          `page_id` VARCHAR(64) NOT NULL,
          `object_id` VARCHAR(64) NOT NULL,
          `post_id` VARCHAR(64) NOT NULL,
          `time` BIGINT NOT NULL,
          `fromid` VARCHAR(64) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `time` (`time`,`fromid`),
          KEY `post_id` (`post_id`)
        ) ENGINE=InnoDB;
      ");

      dbDelta("
        CREATE TABLE {$table['posts']} (
          `post_id` VARCHAR(64) NOT NULL,
          `app_id` VARCHAR(64) NOT NULL,
          `page_id` VARCHAR(64) NOT NULL,
          `actor_id` VARCHAR(64) NOT NULL,
          `wp_post_id` BIGINT NULL,
          `type` VARCHAR(16) NULL,
          `link` VARCHAR(255) NULL,
          `created_time` BIGINT NOT NULL,
          `message` TEXT NULL,
          PRIMARY KEY (`post_id`),
          KEY `wp_post_id` (`wp_post_id`)
        ) ENGINE=InnoDB;
      ");

      dbDelta("
        CREATE TABLE {$table['posts_stats']} (
          `post_id` VARCHAR(64) NOT NULL,
          `data_updated` BIGINT NOT NULL,
          `shares` INT,
          `likes` INT,
          `impressions` INT,
          `comments` INT,
          KEY `post_id` (`post_id`)
        ) ENGINE=InnoDB;
      ");

      dbDelta("
        CREATE TABLE {$table['metrics']} (
          `page_id` VARCHAR(64) NOT NULL,
          `metric` VARCHAR(64) NOT NULL,
          `end_time` BIGINT NOT NULL,
          `value` INT,
          PRIMARY KEY (`page_id`, `metric`, `end_time`)
        ) ENGINE=InnoDB;
      ");
    }

    return $table;
  }

  /**
   * Each time this cron job is run, it attempts to download additional
   * data for up to 20 of the posts referenced in the comments data.
   * If the post is a link, this job will attempt to determine if the link
   * is associated with one of the WordPress posts in this blog.
   */
  function process_posts() {
    global $wpdb;
    $table = SharePressStats::tables();

    $mutex_key = sprintf(SharePressStats::MUTEX, __FUNCTION__);

    if (get_transient($mutex_key)) {
      throw new SpMutexException($mutex_key);
    } 

    set_transient($mutex_key, true, 300);

    $page_id = 73674099237;

    $queue = $wpdb->get_results($sql = $wpdb->prepare("
      SELECT `post_id` 
      FROM {$table['comments']} 
      LEFT OUTER JOIN {$table['posts']} USING (`post_id`) 
      WHERE 
        {$table['posts']}.`post_id` IS NULL 
        AND {$table['comments']}.`page_id` = %s
      GROUP BY {$table['comments']}.`post_id`
      LIMIT 20
    ", $page_id ));

    if (!$queue) {
      return;
    }

    $batch = array();

    foreach($queue as $P) {
      $batch[] = array(
        'method' => 'POST',
        'relative_url' => "method/fql.query?query=" .
          urlencode(trim("
            select post_id, impressions from stream where post_id = '{$P->post_id}'
          "))
      );

      $batch[] = array(
        'method' => 'GET',
        'relative_url' => $P->post_id
      );
    }

    try {
      $responses = Sharepress::api('/', 'POST', array('batch' => json_encode($batch)));
    } catch (Exception $e) {
      delete_transient($mutex_key);
      throw $e;
    }

    $data = array();

    foreach($responses as $i => $response) {
      $result = json_decode($response['body']);
      if (is_array($result)) {
        foreach($result as $P) {
          $data[$P->post_id]['impressions'] = $P->impressions;
        }
      } else if ($result->error_msg) {
        delete_transient($mutex_key);
        throw new Exception($result->error_msg);
      } else {
        $post_id = $result->id;
        $data[$post_id]['likes'] = $result->likes ? $result->likes->count : 0;
        $data[$post_id]['comments'] = $result->comments ? $result->comments->count : 0;
        $data[$post_id]['type'] = $result->type;
        $data[$post_id]['link'] = $result->type == 'link' ? $result->link : null;
        $data[$post_id]['shares'] = $result->shares ? $result->shares->count : 0;
        $data[$post_id]['post_created'] = strtotime($result->created_time);
      }
    }

    foreach($data as $post_id => $D) {
      $D['post_id'] = $post_id;
      $D['data_updated'] = time();

      $wpdb->insert($table['posts'], $D, array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
    }
  }

  /**
   * Each time this cron job is run, it attempts to download up
   * to 31 days worth of comment data for a single Facebook page.
   * The maximum amount of data this will backfill is 2 years.
   */
  function backfill_facebook_comments() {
    global $wpdb;
    $table = SharePressStats::tables();
    
    $page_id = 73674099237;

    // what is the oldest date we have comment data for?
    $oldest = $wpdb->get_var( $sql = $wpdb->prepare("SELECT min(`time`) FROM {$table['comments']} WHERE page_id = %s", $page_id) );

    // is the oldest less than two years old?
    if (!$oldest || time() - 63072000 < $oldest) {
      try {
        SharePressStats::load()->download_raw_comment_stats($page_id, $oldest, 86400, 31);
      } catch (Exception $e) {
        SharePressStats::error(sprintf("While backfilling Facebook comments stats data, %s", $e->getMessage()));
      }
    }
  }

  /**
   * Each time this cron job is run, it attempts to download 
   * all comments that are new since the last comment was downloaded. 
   * If no data has been downloaded yet, this job triggers the backfill
   * job instead. 
   */
  function forwardfill_facebook_comments() {
    global $wpdb;
    $table = SharePressStats::tables();
    
    $page_id = 73674099237;

    // what is the newest date we have comment data for?
    $newest = $wpdb->get_var( $sql = $wpdb->prepare("SELECT max(`time`) FROM {$table['comments']} WHERE page_id = %s", $page_id) );

    if (!$newest) {
      sharepress_cron_backfill_facebook_comment_stats();
      return;
    }

    // take the oldest of $newest and ten minutes ago
    $oldest = min( $newest, time() - 600 );

    $diff = ceil( (time() - $oldest) / 3600 );

    // less than or equal to a day? download two days' worth
    if ($diff <= 24) {
      $period = 86400;
      $interval = 7;

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
      SharePressStats::error(sprintf("While updating Facebook comments stats data, %s", $e->getMessage()));
    }
  }

}



class SpMutexException extends Exception {}

SharePressStats::load();

// https://graph.facebook.com/73674099237/insights/page_active_users?access_token=AAADaj1lBuCUBAHT65FmFmJrZBX5IFBbVebLcsN5lQXc76V5ZBSiKkfmM39tZCyjllVtgY6ifJCQQEa7K8QOUlhe5oRqo6MZD