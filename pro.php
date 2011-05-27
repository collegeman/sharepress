<?php
/*
Plugin Name: Sharepress Pro
Plugin URI: http://getwpapps.com/plugins/sharepress
Description: Sharepress publishes your content to your personal Facebook Wall and the Walls of Pages you choose.
Author: Aaron Collegeman
Author URI: http://aaroncollegeman.com
Version: 1.0.20110514090320
License: GPL2
*/

/*
Copyright (C)2011

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

// load the core dependency
require_once('core.php');
// load the plugin updater client
require_once('update-client.php');

// support for setting individual images in options
require('postimage.php');

if (!defined('ABSPATH')) exit;

/**
 * This PHP class is a namespace for the pro version of your plugin. 
 */
class /*@PLUGIN_PRO_CLASS@*/ SharepressPro {
  
  // holds the singleton instance of your plugin's core
  static $instance;
  
  /**
   * Get the singleton instance of this plugin's core, creating it if it does
   * not already exist.
   */
  static function load() {
    if (!self::$instance) {
      self::$instance = new /*@PLUGIN_PRO_CLASS@*/ SharepressPro();
    }
    return self::$instance;
  }
  
  private function __construct() {
    
    add_action('init', array($this, 'init'), 10, 1);
    
    #
    # Discover this file's path
    #
    $parts = explode(DIRECTORY_SEPARATOR, __FILE__);
    $fn = array_pop($parts);
    $fd = (($fd = array_pop($parts)) != 'plugins' ? $fd : '');
    $file = $fd ? "{$fd}/{$fn}" : $fn;
    
    #
    # Setup the update client to be able to receive updates from getwpapps.com
    #
    PluginUpdateClient::init(array(
      'path' => __FILE__,
      'plugin' => /*@PLUGIN_PRO_SLUG@*/ 'sharepress', 
      'file' => $file
    ));
  }
  
  function init() {
    // attach a reference to the pro version onto the lite version
    /*@PLUGIN_LITE_CLASS@*/ Sharepress::$pro = $this;
    
    // enhancement #1: post thumbnails are used in messages posted to facebook
    add_theme_support('post-thumbnails');
    // enhancement #2: ability to publish to pages
    add_filter('sharepress_pages', array($this, 'pages'));
    add_action('sharepress_post', array($this, 'post'), 10, 2);
    // enhancement #3: configure the content of each post individually
    add_filter('sharepress_meta_box', array($this, 'meta_box'), 10, 7);
    add_action('wp_ajax_sharepress_get_excerpt', array($this, 'ajax_get_excerpt'));
    // enhancement #4: enhancements to the posts browser
    add_action('restrict_manage_posts', array($this, 'restrict_manage_posts'));
    add_action('manage_posts_columns', array($this, 'manage_posts_columns'));
    add_action('manage_posts_custom_column', array($this, 'manage_posts_custom_column'), 10, 2);
    if (is_admin()) {
      add_filter('posts_where', array($this, 'posts_where'));
      add_filter('posts_orderby', array($this, 'posts_orderby'));
    }
    // enhancement #5: scheduling 
    add_action("activate_{$fd}/pro.php", array($this, 'activate'));
    add_action("deactivate_{$fd}/pro.php", array($this, 'deactivate'));
    add_filter('cron_schedules', array($this, 'cron_schedules'));
    add_action('sharepress_oneminute_cron', array($this, 'oneminute_cron'));
    
    // add_filter('plugin_action_links_sharepress/pro.php', array(Sharepress::load(), 'plugin_action_links'), 10, 4);
  }
  
  
  function activate() {
    wp_schedule_event(time(), 'oneminute', 'sharepress_oneminute_cron');
  }
  
  function deactivate() {
    wp_clear_scheduled_hook('sharepress_oneminute_cron');
  }
  
  function manage_posts_columns($cols) {
    $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $current_url = remove_query_arg( 'sharepress_sort', $current_url );
    
    if ( isset( $_GET['sharepress_sort'] ) && 'desc' == $_GET['sharepress_sort'] )
      $current_order = 'desc';
    else
      $current_order = 'asc';
      
    $url = $current_url . '&sharepress_sort='. ( $current_order == 'asc' ? 'desc' : 'asc' );
    
    
    $cols['sharepress'] = '<a href="'.$url.'">'.__('Sharepress').'</a>';
    
    return $cols;
  }
  
  function manage_posts_custom_column($column_name, $post_id) {
    if ($column_name == 'sharepress') {
      $post = get_post($post_id);
      $posted = get_post_meta($post_id, Sharepress::META_POSTED, true);
      $last_posted = Sharepress::get_last_posted($post);
      $scheduled = get_post_meta($post_id, Sharepress::META_SCHEDULED, true);
      $edit = get_admin_url()."post.php?post={$post->ID}&action=edit&sharepress=schedule";
      
      if ($posted) {
        echo __('Posted').': '.date('Y/m/d g:ia', strtotime($posted) + ( get_option( 'gmt_offset' ) * 3600 )).'<br /><a href="'.$edit.'">Schedule Future Repost</a>';
      } else if ($scheduled) {
        echo __('Scheduled').': '.date('Y/m/d g:ia', $scheduled).'<br /><a href="'.$edit.'">Edit Schedule</a>';
      } else if ($last_posted) {
        echo __('Posted').': '.date('Y/m/d g:ia', $last_posted + ( get_option( 'gmt_offset' ) * 3600 )).'<br /><a href="'.$edit.'">Schedule Future Repost</a>';
      } else if ($post->post_status != 'publish') {
        echo 'Post in draft';
      } else {
        echo 'Not yet posted<br /><a href="'.$edit.'">Schedule Now</a>';
      }
    }
  }
  
  function restrict_manage_posts() {
    $current = @$_GET['sharepress'];
    ?>
      <select name="sharepress">
        <option value="">Sharepress Filter (Off)</option>
        <option value="all" <?php if ($current == 'all') echo 'selected="selected"' ?>>Show only Sharepressed</option>
        <option value="posted" <?php if ($current == 'posted') echo 'selected="selected"' ?>>&mdash; Already posted</option>
        <option value="scheduled" <?php if ($current == 'scheduled') echo 'selected="selected"' ?>>&mdash; Scheduled to be posted</option>
        <option value="error" <?php if ($current == 'error') echo 'selected="selected"' ?>>&mdash; Errors</option>
        <option value="not" <?php if ($current == 'not') echo 'selected="selected"' ?>>Show never Sharepressed</option>
      </select>
    <?php
  }
  
  function posts_where($where) {
    global $wpdb;

    if (@$_GET['sharepress'] == 'all') {
      $where .= sprintf(" 
        AND EXISTS ( 
          SELECT * FROM {$wpdb->postmeta} 
          WHERE 
            post_id = {$wpdb->posts}.ID 
            AND meta_key IN ('%s', '%s') 
            AND meta_value IS NOT NULL
        )
      ",
        Sharepress::META_RESULT, Sharepress::META_SCHEDULED
     );
    } else if (@$_GET['sharepress'] == 'posted') {
      $where .= sprintf(" 
        AND EXISTS ( 
          SELECT * FROM {$wpdb->postmeta} 
          WHERE 
            post_id = {$wpdb->posts}.ID 
            AND meta_key = '%s' 
            AND meta_value IS NOT NULL
        )
      ",
        Sharepress::META_RESULT
     );
     
    } else if (@$_GET['sharepress'] == 'scheduled') {
      $where .= sprintf(" 
        AND EXISTS ( 
          SELECT * FROM {$wpdb->postmeta} 
          WHERE 
            post_id = {$wpdb->posts}.ID 
            AND meta_key IN ('%s') 
            AND meta_value IS NOT NULL
        )
      ",
        Sharepress::META_SCHEDULED
     );
     
    } else if (@$_GET['sharepress'] == 'not') {
      $where .= sprintf(" 
        AND NOT EXISTS ( 
          SELECT * FROM {$wpdb->postmeta} 
          WHERE 
            post_id = {$wpdb->posts}.ID 
            AND meta_key IN ('%s', '%s') 
            AND meta_value IS NOT NULL
        )
      ",
        Sharepress::META_RESULT, Sharepress::META_SCHEDULED
     );
    
    } else if (@$_GET['sharepress'] == 'error') {
      $where .= sprintf(" 
        AND EXISTS ( 
          SELECT * FROM {$wpdb->postmeta} 
          WHERE 
            post_id = {$wpdb->posts}.ID 
            AND meta_key IN ('%s') 
            AND meta_value IS NOT NULL
        )
      ",
        Sharepress::META_ERROR
     );
      
    }  
    
    return $where;
  }
  
  function posts_orderby($orderby) {
    global $wpdb;
    
    if (@$_GET['sharepress_sort']) {
      $dir = $_GET['sharepress_sort'] == 'asc' ? 'asc' : 'desc';
      
      $cols = array();
      
      // these first two are arranged in ascending order -- posted stuff is older than scheduled stuff
      
      $cols[] = sprintf("
        (
          EXISTS (
            SELECT * 
            FROM {$wpdb->postmeta}
            WHERE 
              post_id = {$wpdb->posts}.ID
              AND meta_key = '%s'
              AND meta_value IS NOT NULL
          )
        )
      ", 
        Sharepress::META_POSTED
      );
      
      $cols[] = sprintf("
        (
          EXISTS (
            SELECT * 
            FROM {$wpdb->postmeta}
            WHERE 
              post_id = {$wpdb->posts}.ID
              AND meta_key = '%s'
              AND meta_value IS NOT NULL
          )
        )
      ", 
        Sharepress::META_SCHEDULED
      );
      
      // so if descending order is requested, we flip those two
      if ($dir == 'desc') {
        rsort($cols);
      }
      
      $cols[] = sprintf("
        (
          SELECT CONVERT(meta_value, signed) 
          FROM {$wpdb->postmeta}
          WHERE 
            post_id = {$wpdb->posts}.ID
            AND meta_key = '%s'
        ) {$dir}
      ",
        Sharepress::META_SCHEDULED
      );
      
      $cols[] = sprintf("
        (
          SELECT STR_TO_DATE(meta_value, '%%Y/%%m/%%d %%H:%%i:%%s')
          FROM {$wpdb->postmeta}
          WHERE 
            post_id = {$wpdb->posts}.ID
            AND meta_key = '%s'
        ) {$dir}
      ",
        Sharepress::META_POSTED
      );
        
      $orderby = implode(', ', $cols);
    }
    
    return $orderby;
  }
  
  function cron_schedules($schedules) {
    $schedules['oneminute'] = array(
      'interval' => 60,
      'display' => __('Every Minute')
    );
    
    return $schedules;
  }
  
  function oneminute_cron() {
    // load list of posts that are scheduled and ready to post
    global $wpdb;
    $posts = $wpdb->get_results(sprintf("
      SELECT P.ID
      FROM $wpdb->posts P 
      INNER JOIN $wpdb->postmeta M ON (M.post_id = P.ID)
      WHERE 
        P.post_status = 'publish'
        AND M.meta_key = '%s' 
        AND M.meta_value <= %s
        AND NOT EXISTS (
          SELECT * FROM $wpdb->postmeta E
          WHERE 
            E.post_id = P.ID
            AND E.meta_key = '%s'
            AND E.meta_value IS NOT NULL
        )
    ",
      Sharepress::META_SCHEDULED,
      current_time('timestamp'),
      Sharepress::META_POSTED
    ));
    
    foreach($posts as $post) {
      Sharepress::load()->post_on_facebook($post->ID);
    }
  }
  
  function ajax_get_excerpt() {
    global $wpdb;
    $post_id = @$_POST['post_id'];
    $content = @$_POST['content'];

    if (!current_user_can('edit_post', $post_id)) {
      exit;
    }
    
    echo str_replace( array('&nbsp;'), array(' '), Sharepress::load()->get_excerpt( null, stripslashes($content) ) );

    exit;
  }
  
  function post($meta, $post) {
    if (SHAREPRESS_DEBUG) {
      Sharepress::log(sprintf('SharepressPro::post(%s, %s)', $meta['message'], is_object($post) ? $post->post_title : $post));
      Sharepress::log(sprintf('SharepressPro::post => count(SharepressPro::pages()) = %s', count(self::pages())));
      Sharepress::log(sprintf('SharperessPro::post => $meta["targets"] = %s', serialize($meta['targets'])));
    }
    
    // loop over authorized pages
    foreach(self::pages() as $page) {
      if (in_array($page['id'], $meta['targets'])) {        
        $result = Sharepress::api($page['id'].'/feed', 'POST', array(
          'access_token' => $page['access_token'],
          'name' => $meta['name'],
          'message' => $meta['message'],
          'description' => $meta['description'],
          'picture' => $meta['picture'],
          'link' => $meta['link']
        ));
        
        Sharepress::log(sprintf("posted to the page(%s): %s", $page['name'], serialize($result)));
        
        // store the ID for queuing 
        $result['posted'] = time();
        add_post_meta($post->ID, Sharepress::META_RESULT, $result);
      }
    }
  }
  
  function meta_box($meta_box, $post, $meta, $posted, $scheduled, $last_posted, $last_result) {
    ob_start();
    require('pro-meta-box.php');
    return ob_get_clean();
  }
  
  static function sort_by_name($a, $b) {
    return strcasecmp($a['name'], $b['name']);
  }
  
  function pages($default = array()) {
    $result = Sharepress::api(Sharepress::me('id').'/accounts', 'GET', array(), '1 hour');
    if ($result) {
      $data = $result['data'];
      
      // we only care about pages...
      $pages = array();
      foreach($data as $d) {
        if (isset($d['name'])) {
          $pages[] = $d;
        }
      }
      
      // sort by page name, for sanity's sake
      usort($pages, array('SharepressPro', 'sort_by_name'));
      
      return $default + $pages;
    } else {
      throw new Exception("Failed to load pages from Facebook.");
    }
  }
  
  function get_publish_time() {
    $meta = @$_POST[Sharepress::META];
    if (!$meta) {
      return false;
    }
    
    if ($mm = @$meta['mm']) {
      $date = sprintf('%s/%s/%s %s:%s', (int) $meta['aa'], (int) $meta['mm'], (int) $meta['jj'], (int) $meta['hh'], (int) $meta['mn']);
      return strtotime($date);
    } else {
      return false;
    }
  }
  
  function touch_time($scheduled = null) {
    global $wp_locale, $post, $comment;

    $tab_index_attribute = '';
    if ( (int) $tab_index > 0 )
      $tab_index_attribute = " tabindex=\"$tab_index\"";

    // echo '<label for="timestamp" style="display: block;"><input type="checkbox" class="checkbox" name="edit_date" value="1" id="timestamp"'.$tab_index_attribute.' /> '.__( 'Edit timestamp' ).'</label><br />';

    $time_adj = current_time('timestamp');
    
    $jj = ($scheduled) ? date( 'd', $scheduled ) : gmdate( 'd', $time_adj );
    $mm = ($scheduled) ? date( 'm', $scheduled ) : gmdate( 'm', $time_adj );
    $aa = ($scheduled) ? date( 'Y', $scheduled ) : gmdate( 'Y', $time_adj );
    $hh = ($scheduled) ? date( 'H', $scheduled ) : gmdate( 'H', $time_adj );
    $mn = ($scheduled) ? date( 'i', $scheduled ) : gmdate( 'i', $time_adj );
    $ss = ($scheduled) ? date( 's', $scheduled ) : gmdate( 's', $time_adj );

    $cur_jj = gmdate( 'd', $time_adj );
    $cur_mm = gmdate( 'm', $time_adj );
    $cur_aa = gmdate( 'Y', $time_adj );
    $cur_hh = gmdate( 'H', $time_adj );
    $cur_mn = gmdate( 'i', $time_adj );

    $field = Sharepress::META;

    $month = "<select " . ( $multi ? '' : 'id="mm" ' ) . "name=\"{$field}[mm]\"$tab_index_attribute>\n";
    for ( $i = 1; $i < 13; $i = $i +1 ) {
      $month .= "\t\t\t" . '<option value="' . zeroise($i, 2) . '"';
      if ( $i == $mm )
        $month .= ' selected="selected"';
      $month .= '>' . $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) . "</option>\n";
    }
    $month .= '</select>';

    $day = '<input type="text" name="'.$field.'[jj]" onblur="if(!jQuery.trim(jQuery(this).val())) jQuery(this).val(\''.$jj.'\');" value="' . $jj . '" style="width:30px;" maxlength="2" autocomplete="off" />';
    $year = '<input type="text" name="'.$field.'[aa]" onblur="if(!jQuery.trim(jQuery(this).val())) jQuery(this).val(\''.$aa.'\');" value="' . $aa . '" style="width:50px;" maxlength="4" autocomplete="off" />';
    $hour = '<input type="text" name="'.$field.'[hh]" onblur="if(!jQuery.trim(jQuery(this).val())) jQuery(this).val(\''.$hh.'\');" value="' . $hh . '" style="width:30px;" maxlength="2" autocomplete="off" />';
    $minute = '<input type="text" name="'.$field.'[mn]" onblur="if(!jQuery.trim(jQuery(this).val())) jQuery(this).val(\''.$mn.'\');" value="' . $mn . '" style="width:30px;" maxlength="2" autocomplete="off" />';

    echo '<div class="timestamp-wrap">';
    /* translators: 1: month input, 2: day input, 3: year input, 4: hour input, 5: minute input */
    printf(__('%1$s%2$s, %3$s @ %4$s : %5$s'), $month, $day, $year, $hour, $minute);

    echo '</div>';

  }
  
}

/*@PLUGIN_PRO_CLASS@*/ SharepressPro::load();