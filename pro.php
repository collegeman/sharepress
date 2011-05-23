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
    
    add_action("activate_{$fd}/pro.php", array($this, 'activate'));
    add_action("deactivate_{$fd}/pro.php", array($this, 'deactivate'));
    add_action('sharepress_oneminute_cron', array($this, 'oneminute_cron'));
    add_filter('cron_schedules', array($this, 'cron_schedules'));
        
    #
    # Setup the update client to be able to receive updates from getwpapps.com
    #
    PluginUpdateClient::init(array(
      'path' => __FILE__,
      'plugin' => /*@PLUGIN_PRO_SLUG@*/ 'sharepress', 
      'file' => $file
    ));
  }
  
  function activate() {
    wp_schedule_event(time(), 'oneminute', 'sharepress_oneminute_cron');
  }
  
  function deactivate() {
    wp_clear_scheduled_hook('sharepress_oneminute_cron');
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
  
  function init() {
    // attach a reference to the pro version onto the lite version
    /*@PLUGIN_LITE_CLASS@*/ Sharepress::$pro = $this;
    
    // enhancement #1: post thumbnails are used in messages posted to facebook
    add_theme_support('post-thumbnails');
    // enhancement #2: ability to publish to pages
    add_filter('sharepress_pages', array($this, 'pages'));
    add_action('sharepress_post', array($this, 'post'), 10, 2);
    // enhancement #3: configure the content of each post individually
    add_filter('sharepress_meta_box', array($this, 'meta_box'), 10, 5);
    add_action('wp_ajax_sharepress_get_excerpt', array($this, 'ajax_get_excerpt'));
    // enhancement #4: scheduling posts from the posts browser
    add_filter('post_row_actions', array($this, 'post_row_actions'), 10, 2);
    // add_filter('plugin_action_links_sharepress/pro.php', array(Sharepress::load(), 'plugin_action_links'), 10, 4);
  }
  
  function post_row_actions($actions, $post) {
    $posted = get_post_meta($post->ID, Sharepress::META_POSTED, true);
    if ($post->post_status == 'publish') {
      $label = $posted ? 'Publish on Facebook Again' : 'Publish on Facebook';
      $actions['sharepress'] = '<a href="post.php?post='.$post->ID.'&action=edit&sharepress=schedule">'.$label.'</a>';
    }
    
    return $actions;
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
        add_post_meta($post->ID, Sharepress::META_RESULT, $result);
      }
    }
  }
  
  function meta_box($meta_box, $post, $meta, $posted, $scheduled) {
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