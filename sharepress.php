<?php 
/*
Plugin Name: SharePress
Plugin URI: http://aaroncollegeman.com/sharepress
Description: SharePress publishes your content to your personal Facebook Wall and the Walls of Pages you choose.
Author: Fat Panda, LLC
Author URI: http://fatpandadev.com
Version: 2.1.16
License: GPL2
*/

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

if (!defined('ABSPATH')) exit;

// we depend on this...
require('lib/facebook.php');
// we don't care about certificate verification
SpBaseFacebook::$CURL_OPTS = SpBaseFacebook::$CURL_OPTS + array(
  CURLOPT_SSL_VERIFYPEER => false
);
  
// override this in functions.php
@define('SHAREPRESS_DEBUG', true);

class Sharepress {
  
  const OPTION_API_KEY = 'sharepress_api_key';
  const OPTION_APP_SECRET = 'sharepress_app_secret';
  const OPTION_PUBLISHING_TARGETS = 'sharepress_publishing_targets';
  const OPTION_NOTIFICATIONS = 'sharepress_notifications';
  const OPTION_DEFAULT_PICTURE = 'sharepress_default_picture';
  const OPTION_SETTINGS = 'sharepress_settings';
  const OPTION_SESSION_ARG = 'sharepress_%s';

  //const META_MESSAGE_ID = 'sharepress_message_id';
  const META_RESULT = 'sharepress_result';
  const META_TWITTER_RESULT = 'sharepress_twitter_result';
  const META_ERROR = 'sharepress_error';
  const META_POSTED = 'sharepress_posted';
  const META = 'sharepress_meta';
  const META_TWITTER = 'sharepress_twitter_meta';
  const META_SCHEDULED = 'sharepress_scheduled';
  const META_BITLY = 'sharepress_bitly_url';

  const TRANSIENT_IS_BUSINESS = 'sharepress_is_business';
  
  // holds the singleton instance of your plugin's core
  static $instance;
  // holds a reference to the pro version of the plugin
  static $pro;
  
  static function load() {
    if (!self::$instance) {
      self::$instance = new Sharepress();
    }
    return self::$instance;
  }
  
  private function __construct() {
    add_action('init', array($this, 'init'), 11, 1);
  }

  function get_permalink($ref = null) {
    $permalink = get_permalink($ref);
    return apply_filters('sharepress_get_permalink', $permalink, $ref);
  }

  static function supported_post_types() {
    $supported = apply_filters('sharepress_supported_post_types', array('post'));
    if (!$supported) { // make sure to return an array
      $supported = array();
    }
    return $supported;
  }
  
  function init() {
    if (!apply_filters('sharepress_enabled', true)) {
      return false;
    }

    /* For testing custom post type support:
    $labels = array(
      'name' => _x('Books', 'post type general name'),
      'singular_name' => _x('Book', 'post type singular name'),
      'add_new' => _x('Add New', 'book'),
      'add_new_item' => __('Add New Book'),
      'edit_item' => __('Edit Book'),
      'new_item' => __('New Book'),
      'all_items' => __('All Books'),
      'view_item' => __('View Book'),
      'search_items' => __('Search Books'),
      'not_found' =>  __('No books found'),
      'not_found_in_trash' => __('No books found in Trash'), 
      'parent_item_colon' => '',
      'menu_name' => 'Books'

    );
    $args = array(
      'labels' => $labels,
      'public' => true,
      'publicly_queryable' => true,
      'show_ui' => true, 
      'show_in_menu' => true, 
      'query_var' => true,
      'rewrite' => true,
      'capability_type' => 'post',
      'has_archive' => true, 
      'hierarchical' => false,
      'menu_position' => null,
      'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
    ); 
    register_post_type('book', $args); */

    if (is_admin()) {
      add_action('admin_notices', array($this, 'admin_notices'));
      add_action('admin_menu', array($this, 'admin_menu'));
      add_action('admin_init', array($this, 'admin_init'));
      add_action('wp_ajax_fb_save_keys', array($this, 'ajax_fb_save_keys'));
      add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
      add_filter('plugin_action_links_sharepress/sharepress.php', array($this, 'plugin_action_links'), 10, 4);

      if ($_REQUEST['page'] == 'sharepress' && isset($_REQUEST['log'])) {
        wp_enqueue_style('theme-editor');
      }
    }
    
    add_action('save_post', array($this, 'save_post'));
    add_action('transition_post_status', array($this, 'transition_post_status'), 10, 3);
    add_action('future_to_publish', array($this, 'future_to_publish'));
    add_action('publish_post', array($this, 'publish_post'));
    add_filter('filter_'.self::META, array($this, 'filter_'.self::META), 10, 2);
    add_action('wp_head', array($this, 'wp_head'));
    add_filter('cron_schedules', array($this, 'cron_schedules'));

    if (!wp_next_scheduled('sharepress_oneminute_cron')) {
      wp_schedule_event(time(), 'oneminute', 'sharepress_oneminute_cron');
    }

    if (!wp_next_scheduled('sharepress_hourly_cron')) {
      wp_schedule_event(time(), 'hourly', 'sharepress_hourly_cron');
    }

    add_action('sharepress_hourly_cron', array($this, 'hourly_cron'));

    // triggers sharepress-mu loading, if present:
    do_action('sharepress_init');
  } 

  function hourly_cron() {
    // do something to keep access token current
    self::me(null, false, true);

    self::log('Hourly cron: trying to keep access token current.');
  }

  function cron_schedules($schedules) {
    $schedules['oneminute'] = array(
      'interval' => 60,
      'display' => __('Every Minute')
    );
    
    return $schedules;
  }
  
  function wp_head() {
    global $wpdb, $post;
    
    if (self::setting('page_og_tags', 'on') == 'on' || self::setting('page_og_tags', 'on') == 'imageonly') {
      // get any values stored in meta data
      $defaults = array();
      $overrides = array();
      
      $query = $wpdb->get_results( $wpdb->prepare("
        SELECT M.meta_key AS K, M.meta_value AS V
        FROM $wpdb->postmeta M INNER JOIN $wpdb->posts P ON (M.post_ID = P.ID)
        WHERE P.ID = %d
      ", $post->ID) );
      
      foreach($query as $M) {
        if (strpos($M->K, 'og:') === 0 || strpos($M->K, 'fb:') === 0) {
          $overrides[trim($M->K)] = trim($M->V);
        }
      }

      if (is_single() || ( is_page() && !is_front_page() )) {
        $meta = get_post_meta($post->ID, self::META, true);
        
        $picture = $this->get_og_image_url($post, $meta);

        global $post;
        if (!($excerpt = $post->post_excerpt)) {
          $excerpt = preg_match('/^.{1,256}\b/s', preg_replace("/\s+/", ' ', strip_tags($post->post_content)), $matches) ? trim($matches[0]).'...' : get_bloginfo('descrption');
        }

        $defaults = array(
          'og:type' => 'article',
          'og:url' => $this->get_permalink(),
          'og:title' => get_the_title(),
          'og:image' => $picture,
          'og:site_name' => get_bloginfo('name'),
          'fb:app_id' => get_option(self::OPTION_API_KEY),
          'og:description' => $this->strip_shortcodes($excerpt),
          'og:locale' => $this->setting('og_locale', 'en_US')
        );

      } else {
        $defaults = array(
          'og:type' => self::setting('page_og_type', 'blog'),
          'og:url' => is_front_page() ? get_bloginfo('siteurl') : $this->get_permalink(),
          'og:title' => get_the_title(),
          'og:site_name' => get_bloginfo('name'),
          'og:image' => $this->get_default_picture(),
          'fb:app_id' => get_option(self::OPTION_API_KEY),
          'og:description' => $this->strip_shortcodes(get_bloginfo('description')),
          'og:locale' => $this->setting('og_locale', 'en_US')
        );
        
      }
      
      $og = array_merge($defaults, $overrides);
      
      #
      # poke out the ones that aren't allowed
      #

      // old way:
      if ($page_og_tags = $this->setting('page_og_tags')) {
        if ($page_og_tags == 'imageonly') {
          $og = array('og:image' => $og['og:image']);
        } else if ($page_og_tags == 'off') {
          $og = array();
        }

      // new way:
      } else {
        $allowable = array_merge(array(
          'og:title' => false,
          'og:type' => false,
          'og:image' => false,
          'og:url' => false,
          'fb:app_id' => false,
          'og:site_name' => false,
          'og:description' => false,
          'og:locale' => false
        ), $this->setting('page_og_tag', array()));

        foreach($allowable as $tag => $allowed) {
          if (!$allowed) {
            unset($og[$tag]);
          }
        }
      }
      

      $og = apply_filters('sharepress_og_tags', $og, $post, $meta);
      
      if ($og) {
        foreach($og as $property => $content) {
          list($prefix, $tagName) = explode(':', $property);
          $og[$property] = apply_filters("sharepress_og_tag_{$tagName}", $content);
        }

        foreach($og as $property => $content) {
          echo sprintf("<meta property=\"{$property}\" content=\"%s\" />\n", str_replace(
            array('"', '<', '>'), 
            array('&quot;', '&lt;', '&gt;'), 
            $this->strip_shortcodes($content)
          ));
        }
      }
    } 

  }

  static function twitter_ready() {
    return self::unlocked() 
      && self::setting('twitter_is_ready', 1)
      && self::setting('twitter_consumer_key') 
      && self::setting('twitter_consumer_secret') 
      && self::setting('twitter_access_token') 
      && self::setting('twitter_access_token_secret');
  }
  
  static function err($message) {
    self::log($message, 'ERROR');
  }
  
  static function log($message, $level = 'INFO') {
    if (SHAREPRESS_DEBUG) {
      global $thread_id;
      if (is_null($thread_id)) {
        $thread_id = substr(md5(uniqid()), 0, 6);
      }
      $dir = dirname(__FILE__);
      $filename = $dir.'/sharepress-'.gmdate('Ymd').'.log';
      $message = sprintf("%s %s %-5s %s\n", $thread_id, get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'H:i:s'), $level, $message);
      if (!@file_put_contents($filename, $message, FILE_APPEND)) {
        error_log("Failed to access SharePress log file [$filename] for writing: add write permissions to directory [$dir]?");
      }
    }
  }
  
  static function api_key() {
    return get_option(self::OPTION_API_KEY, '');
  }
  
  static function app_secret() {
    return get_option(self::OPTION_APP_SECRET, '');
  }

  static function session() {
    if (!self::api_key() || !self::app_secret()) {
      return false;
    }

    try {
      return self::me(null, true);
    } catch (Exception $e) {
      // log this...?
      return false;
    }
  }
  
  static function setting($name = null, $default = null) {
    $settings = get_option(self::OPTION_SETTINGS, array(
      'default_behavior' => 'on',
      'excerpt_length' => 20,
      'excerpt_more' => '...',
      'og_tags' => 'on',
      'og_type' => 'blog',
      'license_key' => null,
      'append_link' => 1
    ));
    
    return (!is_null($name)) ? ( !is_null(@$settings[$name]) ? $settings[$name] : $default ) : $settings;
  }
  
  static function targets($id = null) {
    $targets = get_option(self::OPTION_PUBLISHING_TARGETS, false);
    if ($targets === false) {
      $targets = array('wall' => 1);
    }
    
    return ($id) ? isset($targets[$id]) : $targets;
  }
  
  static $facebook;
  static function facebook() {
    if (!self::$facebook) {
      if (($api_key = self::api_key()) && ($app_secret = self::app_secret())) {
        self::$facebook = new SharePressFacebook(array(
          'appId' => $api_key,
          'secret' => $app_secret
        ), false);
      } else {
        return null;
      }
    }
    
    return self::$facebook;
  }
  
  /**
   * Make a call to the Facebook API, optionally caching in (or retrieving a cached result from) the database.
   * Only GET requests are cacheable.
   * @param string $path The Graph API endpoint
   * @param string $method (optional) The HTTP method = GET or POST
   * @param array $params (optional) Parameters to pass to the API
   * @param mixed $cache_for (optional) An expression of time (in seconds or in a manner supported by strtotime) - how long to cache the result; default is no caching
   * @return The result of the query
   */
  static function api($path, $method = 'GET', $params = array(), $cache_for = false) {
    self::log(sprintf("api(%s, %s, %s, %s)", $path, $method, serialize($params), $cache_for));
    
    if ($facebook = self::facebook()) {
      $cache = null;
      
      // we're allowed to cache when $method == 'GET' and $cache_for !== false
      if ($method == 'GET' && $cache_for !== false) {
        // build the cache key
        $args = func_get_args();
        // don't include the $cache_for value in the key
        array_pop($args);
        // hashsum it!
        $cache_key = 'sharepress_cache-'.md5(serialize($args));
        
        if ($cache = get_option($cache_key)) {    
          // when does the cache expire?
          if ($cache_for === true) {
            // forever
            $cache_until = 0;
          } else if (is_numeric($cache_for)) {
            // a numeric spec in seconds, e.g., 3600 = 1 hour
            $cache_until = time()-$cache_for;
          } else if (($cache_until = strtotime('-'.$cache_for)) === false) {
            // $cache_for was not a valid expression of time:
            throw new Exception("Invalid value for 'cache_for' parameter.");
          }
    
          if ($cache['cache_until'] !== 0 && $cache['cache_until'] < $cache_until) {
            // clear from the cache
            delete_option($cache_key);
            // nulify
            $cache = null;
          } 
        } 
      }
      
      if ($cache) {
        return $cache['packet'];
      } else {
        // this may throw an exception, but we don't care:
        $result = call_user_func_array(array($facebook, 'api'), array($path, $method, $params));
        // if we're allowed to cache, do it!
        if ($cache_for !== false && $method == 'GET') {
          // if $cache_for is literally true, then cache never expires
          if ($cache_for === true) {
            update_option($cache_key, array('cache_until' => 0, 'packet' => $result));
          } else if (is_numeric($cache_for)) {
            update_option($cache_key, array('cache_until' => time()+$cache_for, 'packet' => $result));
          } else if (($cache_until = strtotime($cache_for)) !== false) {
            update_option($cache_key, array('cache_until' => $cache_until, 'packet' => $result));
          } else {
            // $cache_for was not a valid expression of time:
            throw new Exception("Invalid value for 'cache_for' parameter.");
          }
        }
        // finally, send the response back to the caller
        return $result;
      }
    } else {
      throw new SharepressFacebookSessionException();
    }
  }
  
  /** 
   * Unify user data: standard and business accounts.
   * @param string $param (optional) Return only the value at $param
   * @param boolean $rethrow If set to true, rethrow an exception triggered by the API call
   * @return mixed If $param is defined, only a single value is returned; otherwise, an array - the whole packet
   */
  static function me($param = null, $rethrow = false, $flush = false) {
    try {
      if (self::is_business()) {
        $accounts = self::api('/me/accounts', 'GET', array(), $flush ? false : '5 minutes');
        $me = $accounts['data'][0];
        return ($param) ? $me[$param] : $me;
      } else {
        $me = self::api('/me', 'GET', array(), $flush ? false : '5 minutes');
        return ($param) ? $me[$param] : $me;
      } 
    } catch (Exception $e) {
      if ($rethrow) {
        throw $e;
      } else {
        return self::handleFacebookException($e);
      }
    }
  }

  static function is_business() {
    if (is_string($is_business = get_transient(self::TRANSIENT_IS_BUSINESS))) {
      return $is_business == '1';
    } else {
      $me = self::api('/me');
      $is_business = !$me;
      set_transient(self::TRANSIENT_IS_BUSINESS, $is_business ? '1' : '0', 3600); 
      return $is_business;
    }
  }
  
  static function handleFacebookException($e) {
    if (is_a($e, 'SharepressFacebookSessionException') || $e->getMessage() == 'Invalid OAuth access token signature.') {
      if ($client = self::facebook()) {
        $client->clearAllPersistentData();
      }
      delete_transient(self::TRANSIENT_IS_BUSINESS);
      wp_die('Your Facebook session is no longer valid. <a href="options-general.php?page=sharepress&step=1">Setup sharepress again</a>, or go to your <a href="index.php">Dashboard</a>.');
    } else if (is_admin()) {
      wp_die(sprintf('There was a problem with SharePress: %s; This is probably an issue with the Facebook API. Check <a href="http://developers.facebook.com/live_status/" target="_blank">Facebook Live Status</a> for more information. You can also <a href="%s">try resetting SharePress</a>. If the problem persists, please <a href="http://aaroncollegeman.com/sharepress/help/#support_form" target="_blank">report it to Fat Panda</a>.', $e->getMessage(), admin_url('options-general.php?page=sharepress&amp;action=clear_session')));
    } else {
      self::err(sprintf("Exception thrown by Facebook API: %s; This is definitely an issue with the Facebook API. Check http://developers.facebook.com/live_status/ for more information. If the problem persists, please report it at http://aaroncollegeman.com/sharepress/help/.", $e->getMessage()));
      throw new Exception("There is a problem with SharePress. Check the log.");
    }
  }
  
  static function pages() {
    $pages = array();
    $pages = apply_filters('sharepress_pages', $pages);
    return $pages;
  }
  
  static function clear_cache() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sharepress_cache-%'");
  }
  
  function add_meta_boxes() {
    if (self::installed()) {
      foreach(self::supported_post_types() as $type) {
        add_meta_box(self::META, 'SharePress', array($this, 'meta_box'), $type, 'side', 'high');
      }
    }
  }
  
  function get_default_picture() {
    if ($set = get_option(self::OPTION_DEFAULT_PICTURE)) {
      return $set['url'];
    } else {
      return plugins_url('img/wordpress.png', __FILE__);
    }
  }

  function filter_sharepress_meta($meta, $post) {
    if (!@$meta['message'] && ( @$meta['title_is_message'] || !self::$pro )) {
      $meta['message'] = apply_filters('post_title', $post->post_title);
    }
    
    if (!@$meta['description'] || @$meta['excerpt_is_description']) {
      $meta['description'] = $this->get_excerpt($post);
    }
    
    if (!@$meta['link'] || @$meta['link_is_permalink']) {
      $meta['link'] = $this->get_permalink($post->ID);
    }
    
    if (!@$meta['name']) {
      $meta['name'] = apply_filters('post_title', $post->post_title);
    }
    
    if (!@$meta['targets'] && !self::$pro) {
      $meta['targets'] = array('wall');
    }
    
    $meta['picture'] = $this->get_og_image_url($post, $meta);
    
    return $meta;
  }

  function get_og_image_url($post, $meta) {
    if (!$meta || !isset($meta['let_facebook_pick_pic'])) {
      $meta['let_facebook_pick_pic'] = self::setting('let_facebook_pick_pic_default', 0);
    }

    if (!$meta['let_facebook_pick_pic']) { // use featured image, fallback on first image in post, come to rest on global default
      
      if ($src = wp_get_attachment_image_src( get_post_meta( $post->ID, '_thumbnail_id', true ), array(150, 150) )) {
        $picture = $src[0];
      }

      if (!$picture) {
        $picture = $this->get_first_image_for($post->ID);
      }

      if (!$picture) {
        $picture = $this->get_default_picture();
      }

    } else if ($meta['let_facebook_pick_pic'] == 2) { // explicitly set to use the default

      $picture = $this->get_default_picture();
    
    } else { // try to use the first image in the post, fail to global default

      $picture = $this->get_first_image_for($post->ID);

      if (!$picture) {
        $picture = $this->get_default_picture();
      }

    }

    return $picture;
  }

  function get_first_image_for($post_id) {
    #
    # try the DB first...
    #
    $images = array_values( get_children(array( 
      'post_type' => 'attachment',
      'post_mime_type' => 'image',
      'post_parent' => $post_id,
      'orderby' => 'menu_order',
      'order'  => 'ASC',
      'numberposts' => 1,
    )) );

    if ($images && ( $src = wp_get_attachment_image_src($images[0]->ID, array(150, 150)) )) {
      return $src[0];
    
    #
    # fall back on sniffing out <img /> tags from post content
    #
    } else {
      $post = get_post($post_id);
      if ($content = do_shortcode($post->post_content)) {
        preg_match_all('/<img[^>]+>/i', $post->post_content, $matches);
        foreach($matches[0] as $img) {
          if (preg_match('#src="([^"]+)"#i', $img, $src)) {
            return $src[1];
          } else if (preg_match("#src='([^']+)'#i", $img, $src)) {
            return $src[1];
          }
        }
      }
    }
  }
  
  static $meta;
  
  static function sort_by_selected($p1, $p2) {
    $s1 = @in_array($p1['id'], self::$meta['targets']);
    $s2 = @in_array($p2['id'], self::$meta['targets']);
    return $s1 === $s2 ? 
      ( self::$pro ? self::$pro->sort_by_name($p1, $p2) : 0 ) 
      : 
      ( $s1 && !$s2 ? -1 : 1 );
  }
  
  function meta_box($post) {
    // standard meta box
    ob_start();
    require('meta-box.php');
    $meta_box = ob_get_clean();
    
    // nonce
    ob_start();
    wp_nonce_field(plugin_basename(__FILE__), 'sharepress-nonce');
    $nonce = ob_get_clean();
    
    $posted = get_post_meta($post->ID, self::META_POSTED, true);
    $scheduled = get_post_meta($post->ID, self::META_SCHEDULED, true);
    $last_posted = $last_posted_on_facebook = self::get_last_posted($post);
    $last_result = self::get_last_result($post);

    // load the meta data
    $meta = get_post_meta($post->ID, self::META, true);
    if (!$meta) {
      // defaults:
      $meta = array(
        'message' => $post->post_title,
        'title_is_message' => true,
        'picture' => $this->get_default_picture(),
        'let_facebook_pick_pic' => self::setting('let_facebook_pick_pic_default', 0),
        'description' => $this->get_excerpt($post),
        'excerpt_is_description' => true,
        'targets' => self::targets() ? array_keys(self::targets()) : array(),
        'enabled' => self::setting('default_behavior'),
        'append_link' => self::setting('append_link', 'on') == 'on',
        'delay_length' => self::setting('delay_length', 0),
        'delay_unit' => self::setting('delay_unit', 'minutes')
      );
    } else {
      // overrides:
      if ($meta['title_is_message']) {
        $meta['message'] = $post->post_title;
      }
      
      if ($meta['excerpt_is_description']) {
        $meta['description'] = $this->get_excerpt($post);
      }
    }
    
    // targets must have at least one... try here, and enforce with javascript
    if (!$meta['targets']) {
      $meta['targets'] = self::targets() ? array_keys(self::targets()) : array();
    }
    
    // load the meta data
    $twitter_meta = get_post_meta($post->ID, Sharepress::META_TWITTER, true);
    
    if (!$twitter_meta) {
      // defaults:
      $twitter_meta = array(
        'enabled' => Sharepress::setting('twitter_behavior', 'on'),
        'hash_tag' => self::setting('twitter_default_hashtag')
      );
    }
    $twitter_enabled = $twitter_meta['enabled'] == 'on';

    // stash $meta globally for access from Sharepress::sort_by_selected
    self::$meta = $meta;
    // allow for pro override
    $meta_box = apply_filters('sharepress_meta_box', $meta_box, array(
      'post' => $post, 
      'meta' => $meta, 
      'posted' => $posted, 
      'scheduled' => $scheduled, 
      'last_posted' => $last_posted, 
      'last_result' => $last_result,
      'twitter_meta' => $twitter_meta,
      'twitter_enabled' => $twitter_enabled
    ));
    // unstash $meta
    self::$meta = null;
    
    // nonce, followed by the form
    echo $nonce;
    
    // style
    ?>
      <style>
        #sharepress_meta label { display: block; }
        #sharepress_meta fieldset { border: 1px solid #eee; padding: 8px; }
        #sharepress_meta legend { padding: 0px 4px; }
      </style>
    <?php

    if ($posted || $scheduled || $last_posted) {
      require('published-msg.php');
      echo $meta_box;
    } else {
      $enabled = @$_GET['sharepress'] == 'schedule' || ( @$meta['enabled'] == 'on' && $post->post_status != 'publish' );
      require('behavior-picker.php');
    }
  }
  
  function save_post($post_id) {
    self::log("save_post($post_id)");
    
    // don't do anything on autosave events
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      self::log("DOING_AUTOSAVE is true; ignoring save_post($post_id)");
      return false;
    }
    
    $post = get_post($post_id);
    
    // make sure we're not working with a revision
    if ($post->post_status == 'auto-draft' || ( $parent_post_id = wp_is_post_revision($post) )) {
      self::log("Post is a revision; ignoring save_post($post_id)");
      return false;
    }

    $is_xmlrpc = defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
    if ($is_xmlrpc) {
      self::log('In XML-RPC request');
    } else {
      self::log('Not in XML-RPC request');
    }

    $is_cron = defined('DOING_CRON') && DOING_CRON;
    if ($is_cron) {
      self::log('In CRON job');
    } else {
      self::log('Not in CRON job');
    }
    
    // verify permissions
    if (!$is_cron && !current_user_can('edit_post', $post->ID)) {
      self::log("Current user is not allowed to edit posts; ignoring save_post($post_id)");
      return false;
    }
    
    $already_posted = get_post_meta($post->ID, self::META_POSTED, true);
    $is_scheduled = get_post_meta($post->ID, self::META_SCHEDULED, true);

    // if the nonce is present, update meta settings for this post from $_POST
    if (wp_verify_nonce($_POST['sharepress-nonce'], plugin_basename(__FILE__))) {

      // remove any past failures
      delete_post_meta($post->ID, self::META_ERROR);

      // update facebook meta?
      if (@$_POST[self::META]['publish_again'] || ( $_POST[self::META]['enabled'] == 'on' && !$already_posted && !$is_scheduled )) {
                
        // if publish_action was set, make sure enabled = 'on'
        if ($_POST[self::META]['publish_again']) {
          $_POST[self::META]['enabled'] = 'on';
        }
        
        // remove the publish_again flag
        unset($_POST[self::META]['publish_again']);
        // clear the published date in meta
        delete_post_meta($post->ID, self::META_POSTED);
        
        // filter the meta
        if (!$_POST[self::META]) {
          $meta = get_post_meta($post->ID, self::META, true);
        } else {
          $meta = apply_filters('filter_'.self::META, $_POST[self::META], $post);  
        }
        
        // save the meta data
        update_post_meta($post->ID, self::META, $meta);

        // filter the twitter meta
        if (!$_POST[self::META_TWITTER]) {
          $twitter_meta = get_post_meta($post->ID, self::META_TWITTER, true);
        } else {
          $twitter_meta = apply_filters('filter_'.self::META_TWITTER, $_POST[self::META_TWITTER], $post);  
        }

        if (empty($twitter_meta['enabled'])) {
          $twitter_meta['enabled'] = 'off';
        }

        // save the twitter meta data
        update_post_meta($post->ID, self::META_TWITTER, $twitter_meta);
        
        // if the post is published, then consider posting to facebook immediately
        if ($post->post_status == 'publish') {
          // if lite version or if publish time has already past
          if (!self::$pro || ( ($time = self::$pro->get_publish_time()) < current_time('timestamp') )) {
            self::log("Posting to Facebook now; save_post($post_id)");
            $this->share($post);
            
          // otherwise, if $time specified, schedule future publish
          } else if ($time) {
            self::log("Scheduling future repost at {$time}; save_post($post_id)");
            update_post_meta($post->ID, self::META_SCHEDULED, $time);
          
          // otherwise...?
          } else {
            self::log("Not time to post or no post schedule time given, so not posting to Facebook; save_post($post_id)");
          }
        
        } else {
          self::log("Post status is not 'publish'; not posting to Facebook on save_post($post_id)");

        }
        
      } else if (get_post_meta($post->ID, self::META_SCHEDULED, true) && @$_POST[self::META]['cancelled']) {
        self::log("Scheduled repost canceled by save_post($post_id)");
        delete_post_meta($post->ID, self::META_SCHEDULED);

      } else if (isset($_POST[self::META]['enabled']) && $_POST[self::META]['enabled'] == 'off') {
        self::log("User has indicated they do not wish to Post to Facebook; save_post($post_id)");
        update_post_meta($post->ID, self::META, array('enabled' => 'off'));
        update_post_meta($post->ID, self::META_TWITTER, array('enabled' => 'off'));

      } else {
        self::log("Post is already posted or is not allowed to be posted to facebook; save_post($post_id)");
      }

      

    #
    # When save_post is invoked by XML-RPC the SharePress nonce won't be 
    # available to test. So, we evaluate whether or not to post based on several
    # criteria:
    # 1. SharePress must be configured to post to Facebook by default
    # 2. The Post must not already have been posted by SharePress
    # 3. The Post must not be scheduled for future posting
    #
    } else if (($is_xmlrpc || $is_cron) && $this->setting('default_behavior') == 'on' && !$already_posted && !$is_scheduled) {
      // is there already meta data stored?
      $meta = get_post_meta($post->ID, self::META, true);
      if ($meta && $meta['enabled'] && $meta['enabled'] != 'on') {
        self::log("In XML-RPC or CRON job, but post is set not to share on Facebook; ignoring save_post($post_id)");
        return;
      }

      // remove any past failures
      delete_post_meta($post->ID, self::META_ERROR);

      // setup meta with defaults
      $meta = array(
        'message' => $post->post_title,
        'title_is_message' => true,
        'picture' => null,
        'let_facebook_pick_pic' => self::setting('let_facebook_pick_pic_default', 0),
        'link' => $this->get_permalink($post),
        'description' => $this->get_excerpt($post),
        'excerpt_is_description' => true,
        'targets' => array_keys(self::targets()),
        'enabled' => Sharepress::setting('default_behavior')
      );

      $meta = apply_filters('filter_'.self::META, $meta, $post);

      if (self::setting('append_link', 'on') == 'on') {
        if ($meta['message']) {
          $meta['message'] .= ' - ';
        }
        $meta['message'] .= $this->get_permalink($post->ID);
      }

      update_post_meta($post->ID, self::META, $meta);

      $meta = apply_filters('filter_'.self::META_TWITTER, array(
        'enabled' => Sharepress::setting('twitter_behavior')
      ));

      update_post_meta($post->ID, self::META_TWITTER, $meta);

      if ($post->post_status == 'publish') {
        self::log("Sharing with SharePress now; save_post($post_id)");
        $this->share($post);
      }

    } else {
      self::log("SharePress nonce was invalid; ignoring save_post($post_id)");
      
    }
  }
  
  function future_to_publish($post) {
    if (SHAREPRESS_DEBUG) {
      self::log(sprintf("future_to_publish(%s)", is_object($post) ? $post->post_title : $post));
    }
    
    $this->transition_post_status('publish', 'future', $post);
  }
  
  function transition_post_status($new_status, $old_status, $post) {
    if (SHAREPRESS_DEBUG) {
      self::log(sprintf("transition_post_status(%s, %s, %s)", $new_status, $old_status, is_object($post) ? $post->post_title : $post));
    }

    if (@$_POST[self::META] || $new_status != 'publish') {
      if (SHAREPRESS_DEBUG) {
        self::log(sprintf("Saving operation in progress; ignoring transition_post_status(%s, %s, %s)", $new_status, $old_status, is_object($post) ? $post->post_title : $post));
      }
      return;
    } 
    
    // value of $post here is inconsistent
    if (!is_object($post)) {
      $post = get_post($post);
    }
    
    if ($post) {
      $this->share($post);
    } 
  }
  
  function publish_post($post_id) {
    self::log("publish_post($post_id)");
    
    if (!empty($_POST[self::META])) {
      self::log("Saving operation in progress; ignoring publish_post($post_id)");
      // saving operation... don't execute this
      return;
    }
    
    $post = get_post($post_id);
    if ($post && ($post->post_status == 'publish')) {
      $this->share($post);
    }
  }

  function strip_shortcodes($text) {
    // the WordPress way:
    $text = strip_shortcodes($text);
    // the manual way:
    return preg_replace('#\[/[^\]]+\]#', '', $text);

  }
  
  function get_excerpt($post = null, $text = null) {
    if (!is_null($post)) {
      $text = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
    } 
    $text = $this->strip_shortcodes( $text );
    $text = str_replace(']]>', ']]&gt;', $text);
    $text = strip_tags($text);
    
    $excerpt_length = apply_filters('sharepress_excerpt_length', self::setting('excerpt_length'));
    $excerpt_more = apply_filters('sharepress_excerpt_more', self::setting('excerpt_more'));
    $words = preg_split("/[\n\r\t ]+/", $text, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY);
    
    if ( count($words) > $excerpt_length ) {
      array_pop($words);
      $text = implode(' ', $words);
      $text = $text . $excerpt_more;
    } else {
      $text = implode(' ', $words);
    }
    
    return $text;
  }
  
  private function can_post_on_facebook($post) {
    $can_post_on_facebook = (
      // post only if defined
      $post 
      
      // post only if sharepress meta data is available
      && ($meta = get_post_meta($post->ID, self::META, true)) 
      
      // post only if enabled
      && ($meta['enabled'] == 'on') 
      
      // post only if never posted before
      && !get_post_meta($post->ID, self::META_POSTED, true)
      
      // on schedule
      && (!($scheduled = get_post_meta($post->ID, self::META_SCHEDULED, true)) ||  $scheduled <= current_time('timestamp')) 
      
      // post only if no errors precede this posting
      && !get_post_meta($post->ID, self::META_ERROR)
    );

    self::log("Loaded for {$post->ID}: ".print_r($meta, true));
    
    return ($can_post_on_facebook ? $meta : false);
  }

  private function can_post_on_twitter($post) {
    $can_post_on_twitter = (
      // has twitter been configured?
      self::twitter_ready()

      // post only if defined
      && $post 
      
      // post only if sharepress meta data is available
      && ($meta = get_post_meta($post->ID, self::META_TWITTER, true)) 
      
      // post only if enabled
      && ($meta['enabled'] == 'on') 
      
      // post only if never posted before
      && !get_post_meta($post->ID, self::META_POSTED, true)
      
      // on schedule
      && (!($scheduled = get_post_meta($post->ID, self::META_SCHEDULED, true)) ||  $scheduled <= current_time('timestamp')) 
      
      // post only if no errors precede this posting
      && !get_post_meta($post->ID, self::META_ERROR)
    );

    return ($can_post_on_twitter ? $meta : false);
  }
  
  /**
   * @return timestamp -- the last time the post was posted to facebook, or false if never
   */
  static function get_last_posted($post) {
    if (!is_object($post)) {
      $post = get_post($post);
    }
    
    if ($result = self::get_last_result($post)) {
      return $result['posted'];
    } else if ($posted = get_post_meta($post->ID, self::META_POSTED)) {
      return strtotime($posted);
    } else {
      return false;
    }
  }
  
  static function get_last_result($post) {
    if (!is_object($post)) {
      $post = get_post($post);
    }
    
    if ($result = get_post_meta($post->ID, self::META_RESULT)) {
      usort($result, array('Sharepress', 'sort_by_posted_date'));
      return $result[0];
    } else {
      return null;
    }
  }

  function get_bitly_link($post_id) {
    $post = get_page($post_id);
    if (!$post->ID) {
      return false;
    }
    
    $permalink = $this->get_permalink($post);

    if (!$login = $this->setting('bitly_login')) {
      return $permalink;
    }

    if (!$apikey = $this->setting('bitly_apikey')) {
      return $permalink;
    }

    if ($post->post_status == 'publish') {
      
      $response = _wp_http_get_object()->request('https://api-ssl.bitly.com/v3/shorten?' . http_build_query(array(
        'login' => $login,
        'apikey' => $apikey,
        'longUrl' => $this->get_permalink($post->ID),
        'format' => 'json',
        'sslverify' => false
      )), array('method' => 'GET'));

      // SharePress::log('Bit.ly result: '.print_r($response, true));

      if (is_wp_error($response)) {
        SharePress::log('Bit.ly issue: '.print_r($response, true), 'ERROR');
        return $permalink;

      } else {
        $result = json_decode($response['body']);
        if ($result->status_code == 200) {
          return $result->data->url;
        } else {
          SharePress::log('Bit.ly issue: '.print_r($response, true), 'ERROR');
          return $permalink;
        }
        
      }   

    } else {
      return $permalink;
    }

  }
  
  function sort_by_posted_date($result1, $result2) {
    $date1 = $result1['posted'];
    $date2 = $result2['posted'];
    return ($date1 == $date2) ? 0 : ( $date1 < $date2 ? 1 : -1);
  }
  
  function share($post) {
    if (SHAREPRESS_DEBUG) {
      self::log(sprintf("share(%s)", is_object($post) ? $post->post_title : $post));
    }
    
    if (!is_object($post)) {
      $post = get_post($post);
    }

    if ($meta = $this->can_post_on_facebook($post)) {

      // determine if this should be delayed
      if ($meta['delay_length']) {
        self::log("Sharing of this post has been delayed {$meta['delay_length']} {$meta['delay_unit']}({$post->ID})");
        $time = strtotime("+{$meta['delay_length']} {$meta['delay_unit']}", current_time('timestamp'));
        update_post_meta($post->ID, self::META_SCHEDULED, $time);
        $meta['delay_length'] = 0;
        update_post_meta($post->ID, self::META, $meta);
        return false;
      }
      
      if (!empty($meta['append_link'])) {
        if ($meta['message']) {
          $meta['message'] .= ' - ';  
        }
        $meta['message'] .= $this->get_permalink($post->ID);
      }
    
      try {
        // poke the linter
        _wp_http_get_object()->request(sprintf('http://developers.facebook.com/tools/debug/og/object?q=%s', urlencode($meta['link'])));
        
        // no targets? error.
        if (!$meta['targets'] && !self::is_business()) {
          throw new Exception("No publishing Targets selected.");
        }

        // first, should we post to the wall?
        if (self::is_business() || in_array('wall', $meta['targets'])) {
          $result = self::api(self::me('id').'/links', 'POST', array(
            'name' => $meta['name'],
            'message' => $meta['message'],
            'description' => $meta['description'],
            'picture' => $meta['picture'],
            'link' => $meta['link']
          ));

          self::log(sprintf("posted to the wall: %s", serialize($result)));
          
          // store the ID and published date for queuing 
          $result['published'] = time();
          $result['message'] = $meta['message'];
          add_post_meta($post->ID, Sharepress::META_RESULT, $result);
        }
        
        // next, fire the sharepress_post action
        // the pro version picks this up
        do_action('sharepress_post', $meta, $post);
      
        if ($twitter_meta = $this->can_post_on_twitter($post)) {
       
          $client = new SharePress_TwitterClient(get_option(self::OPTION_SETTINGS));
          $tweet = sprintf('%s %s', $post->post_title, $this->get_bitly_link($post));
          if ($hash_tag = trim($twitter_meta['hash_tag'])) {
            $tweet .= ' '.$hash_tag;
          }

          $result = $client->post($tweet);
          SharePress::log(sprintf("Tweet Result for Post #{$post->ID}: %s", print_r($result, true)));
          add_post_meta($post->ID, Sharepress::META_TWITTER_RESULT, $result);

        }

        // success:
        update_post_meta($post->ID, self::META_POSTED, gmdate('Y-m-d H:i:s'));
        delete_post_meta($post->ID, self::META_SCHEDULED);
        
        $this->success($post, $meta);

            
        
    
      } catch (Exception $e) {
        self::err(sprintf("Exception thrown while in share: %s", $e->getMessage()));
        
        $this->error($post, $meta, $e);
      }
 
    }
    
   
  }

  /**
   * Add "Settings" link to the Plugins screen.
   */
  function plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
    $actions['settings'] = '<a href="options-general.php?page=sharepress">Settings</a>';
    if (!self::$pro && self::session()) {
      $actions['go-pro'] = '<a href="http://aaroncollegeman.com/sharepress?utm_source=sharepress&utm_medium=in-app-promo&utm_campaign=unlock-pro-version">Unlock Pro Version</a>';
    }
    return $actions;
  }
  
  /**
   * As part of setup, save the client-side session data - we don't trust cookies
   */
  function ajax_fb_save_keys() {
    if (current_user_can('activate_plugins')) {
      if (!self::is_mu()) {
        update_option(self::OPTION_API_KEY, $_REQUEST['api_key']);
        update_option(self::OPTION_APP_SECRET, $_REQUEST['app_secret']);
      }
      echo self::facebook()->getLoginUrl(array(
        'redirect_uri' => $_REQUEST['current_url'],
        'scope' => 'read_stream,publish_stream,manage_pages,share_item'
      ));
        
    } else {
      header('Status: 403 Not Allowed');
    }
    exit;
  }
  
  
  function admin_init() {
    if ($action = @$_REQUEST['action']) {
      
      // when the user clicks "Setup" tab on the settings screen:
      if ($action == 'clear_session') {      
        if (current_user_can('administrator')) {
          self::facebook()->clearAllPersistentData();
          delete_transient(self::TRANSIENT_IS_BUSINESS);
          self::clear_cache();
          wp_redirect('options-general.php?page=sharepress&step=1');
          exit;
        } else {
          wp_die("You're not allowed to do that.");
        }
      }

      if ($action == 'reset_twitter_settings') {
        if (current_user_can('administrator')) {
          $settings = get_option(self::OPTION_SETTINGS);
          $settings['twitter_is_ready'] = 0;
          update_option(self::OPTION_SETTINGS, $settings);
          wp_redirect('options-general.php?page=sharepress');
          exit;
        } else {
          wp_die("You're not allowed to do that.");
        }
      }
      
      // clear the cache
      if ($action == 'clear_cache') {
        if (current_user_can('administrator')) {
          self::clear_cache();
          wp_redirect('options-general.php?page=sharepress');
        } else {
          wp_die("You're not allowed to do that.");
        }
      }
      
    }

    register_setting('fb-step1', self::OPTION_API_KEY);
    register_setting('fb-step1', self::OPTION_APP_SECRET);
    register_setting('fb-settings', self::OPTION_PUBLISHING_TARGETS);
    register_setting('fb-settings', self::OPTION_NOTIFICATIONS);
    register_setting('fb-settings', self::OPTION_SETTINGS, array($this, 'sanitize_settings'));
  }

  function sanitize_settings($settings) {
    
    if (!empty($settings['license_key'])) {
      $settings['license_key'] = trim($settings['license_key']);
    }

    return $settings;
  }

  static function has_keys() {
    return ( self::api_key() && self::app_secret() );
  }
  
  static function installed() {
    return ( self::has_keys() && self::session() );
  }
  
  static function is_mu() {
    return defined('MULTISITE') && MULTISITE && defined('SHAREPRESS_MU') && SHAREPRESS_MU;
  }

  static function unlocked() {
    $license_key = self::load()->setting('license_key');
    if (self::is_mu()) {
      $license_key = defined('SHAREPRESS_MU_LICENSE_KEY') ? SHAREPRESS_MU_LICENSE_KEY : null;
    }
    return apply_filters('sharepress_enabled', true) && strlen($license_key) == 32;
  }

  function admin_notices() {
    if (current_user_can('administrator')) {
      $ok_to_show_error = preg_match('#/wp-admin/(post-new\.php|index\.php|plugins\.php)$#i', $_SERVER['SCRIPT_NAME']) && empty($_REQUEST['page']);
      if ( !self::installed() && $ok_to_show_error ) {
        ?>
          <div class="error">
            <p>You haven't finished setting up <a href="<?php echo get_admin_url() ?>options-general.php?page=sharepress">SharePress</a>.</p>
          </div>
        <?php
      } else if (@$_REQUEST['page'] == 'sharepress' && self::session() && !self::$pro) {
        if ($this->setting('license_key') && strlen($this->setting('license_key')) != 32) {
          ?>
            <div class="error">
              <p>Hmm... looks like there's something wrong with your <a href="<?php echo get_admin_url() ?>options-general.php?page=sharepress">SharePress</a> license key.</p>
            </div>
          <?php
        } else {
          ?>
            <div class="updated">
              <p><b>Go pro!</b> This plugin can do more: a lot more. <a href="http://aaroncollegeman.com/sharepress?utm_source=sharepress&utm_medium=in-app-promo&utm_campaign=learn-more">Learn more</a>.</p>
            </div>
          <?php
        }
      }      
    }
  }
  
  function admin_menu() {
    add_submenu_page('options-general.php', 'SharePress', 'SharePress', 'administrator', 'sharepress', array($this, 'settings'));
  }
  
  function settings() {
    if (isset($_REQUEST['log'])) {
      require('console.php');
      return;
    }

    if (empty($_REQUEST['step']) || isset($_REQUEST['updated'])) {
      if (!self::api_key() || !self::app_secret()) {
        $_REQUEST['step'] = '1';
      } else {
        $_REQUEST['step'] = 'config';
      }
    }

    if (self::api_key() && self::app_secret() && self::session()) {
      $_REQUEST['step'] = 'config';
    }
    
    require('settings.php');
  }

  static function getCurrentUrl() {
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
      || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
    ) {
      $protocol = 'https://';
    }
    else {
      $protocol = 'http://';
    }
    $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $parts = parse_url($currentUrl);

    $query = '';
    if (!empty($parts['query'])) {
      // drop known fb params
      $params = explode('&', $parts['query']);
      $retained_params = array();
      foreach ($params as $param) {
        if (self::shouldRetainParam($param)) {
          $retained_params[] = $param;
        }
      }

      if (!empty($retained_params)) {
        $query = '?'.implode($retained_params, '&');
      }
    }

    // use port if non default
    $port =
      isset($parts['port']) &&
      (($protocol === 'http://' && $parts['port'] !== 80) ||
       ($protocol === 'https://' && $parts['port'] !== 443))
      ? ':' . $parts['port'] : '';

    // rebuild
    return $protocol . $parts['host'] . $port . $parts['path'] . $query; 
  }

  protected static $DROP_QUERY_PARAMS = array(
    'code',
    'state',
    'signed_request',
  );

  static function shouldRetainParam($param) {
    foreach (self::$DROP_QUERY_PARAMS as $drop_query_param) {
      if (strpos($param, $drop_query_param.'=') === 0) {
        return false;
      }
    }
    return true;
  }
  
  function success($post, $meta) {
    if ($this->notify_on_success()) {
      $link = get_option('siteurl').'/wp-admin/post.php?action=edit&post='.$post->ID;
      wp_mail(
        $this->get_success_email(),
        "SharePress Success",
        "Sent message \"{$meta['message']}\" to Facebook for post {$post->ID}\n\nNeed to edit your post? Click here:\n{$link}"
      );
    }
  }
  
  function error($post, $meta, $error) {
    if (is_object($error)) {
      $error = $error->getMessage();
    }
    
    update_post_meta($post->ID, self::META_ERROR, $error);
    
    if ($this->notify_on_error()) {
      $link = get_option('siteurl').'/wp-admin/post.php?action=edit&post='.$post->ID;
      wp_mail(
        $this->get_error_email(),
        "SharePress Error",
        "SharePress Error: $error; while sending \"{$meta['message']}\" to Facebook for post {$post->ID}\n\nTo retry, simply edit your post and save it again:\n{$link}"
      );
    }
    
    error_log("SharePress Error: $error; while sending {$meta['message']} for post {$post->ID}");
  }
  
  static function get_error_email() {
    $options = get_option(self::OPTION_NOTIFICATIONS);
    return (@$options['on_error_email']) ? $options['on_error_email'] : get_option('admin_email');
  }
  
  static function notify_on_error() {
    $options = get_option(self::OPTION_NOTIFICATIONS);
    return $options ? $options['on_error'] == '1' : true;
  }
  
  static function get_success_email() {
    $options = get_option(self::OPTION_NOTIFICATIONS);
    return (@$options['on_success_email']) ? $options['on_success_email'] : get_option('admin_email');
  }
  
  static function notify_on_success() {
    $options = get_option(self::OPTION_NOTIFICATIONS);
    return $options ? $options['on_success'] == '1' : true;
  }
}

class SharepressFacebookSessionException extends Exception {}

class SharePress_TwitterClient {

  private $consumer_key;
  private $consumer_secret;
  private $access_token;
  private $access_token_secret;
  private $host = 'https://api.twitter.com/1';
  
  function __construct($settings) {
    $this->consumer_key = $settings['twitter_consumer_key'];
    $this->consumer_secret = $settings['twitter_consumer_secret'];
    $this->access_token = $settings['twitter_access_token'];
    $this->access_token_secret = $settings['twitter_access_token_secret'];
  }

  /**
   * @return String a Text message indicating success or failure and reason
   */
  function test() {
    $result = SharePress_WordPressOAuth::get($this->host.'/help/test.json', self::build_params());
    if (!is_wp_error($result)) {
      if ($result['body'] == '"ok"') {
        $tweet = "Hey, hey! Just testing SharePress: an awesome plugin for posting to Twitter and Facebook from WordPress http://bit.ly/pqo6KO";
        if (false === ($response = $this->post($tweet))) {
          return "Connection error. Please try again."; 
        } else if ($response->error) {
          return "Twitter says there's a problem: {$response->error} Make sure all of your keys are correct, and double-check your Twitter app's settings.";
        } else {
          return "Success! Remember to save your settings.";
        }
      } else {
        $response = json_decode($result['body']);
        return "Twitter says there's a problem: {$response->error} Make sure all of your keys are correct, and double-check your Twitter app's settings.";
      }
    } else {
      return "Connection error. Please try again.";
    }
  }

  /**
   * @return mixed false on connection failure; otherwise, an object representing the success or failure state as reported by the Twitter API
   */
  function post($status = '') {
    $result = Sharepress_WordPressOAuth::post($this->host.'/statuses/update.json', self::build_params(array(
      'status' => $status,
      'wrap_links' => true
    )));

    if (is_wp_error($result)) {
      return false;
    } else {
      $response = json_decode($result['body']);
      return $response;
    }
  }

  function build_params($params = array()) {
    return array_merge(
      array(
        'oauth_version' => '1.0',
        'oauth_nonce' => self::generate_nonce(),
        'oauth_timestamp' => self::generate_timestamp(),
        'oauth_consumer_key' => $this->consumer_key,
        'oauth_token' => $this->access_token
      ),
      $params, 
      array(
        'consumer_secret' => $this->consumer_secret,
        'access_token_secret' => $this->access_token_secret
      )
    );
  }

  private static function generate_timestamp() {
    return time();
  }

  /**
   * util function: current nonce
   */
  private static function generate_nonce() {
    $mt = microtime();
    $rand = mt_rand();
    return md5($mt . $rand); // md5s look nicer than numbers
  }


}

/*
 * This is based by-and-large upon Abraham's fantastic Twitter OAuth library:
 * Abraham Williams (abraham@abrah.am) http://abrah.am
 *
 * The first PHP Library to support OAuth for Twitter's REST API.
 * https://github.com/abraham/twitteroauth
 */

class SharePress_WordPressOAuth {

  public static function get($url, $params = array()) {
    extract($params);
    unset($params['consumer_secret']);
    unset($params['access_token_secret']);

    $params = array_merge( self::parse_parameters(parse_url($url, PHP_URL_QUERY)), $params );
    $params['oauth_signature_method'] = 'HMAC-SHA1';
    $params['oauth_signature'] = self::build_signature('GET', $url, $params, $params['oauth_consumer_key'], $consumer_secret, $params['oauth_token'], $access_token_secret);

    return wp_remote_get(self::get_normalized_http_url($url).'?'.self::build_http_query($params), array(
      'sslverify' => false,
      'headers' => array(
        'Expect:'
      )
    ));
  }

  public static function post($url, $params = array()) {
    extract($params);
    unset($params['consumer_secret']);
    unset($params['access_token_secret']);

    $params = array_merge( self::parse_parameters(parse_url($url, PHP_URL_QUERY)), $params );
    $params['oauth_signature_method'] = 'HMAC-SHA1';
    $params['oauth_signature'] = self::build_signature('POST', $url, $params, $params['oauth_consumer_key'], $consumer_secret, $params['oauth_token'], $access_token_secret);

    return wp_remote_post(self::get_normalized_http_url($url), array(
      'body' => self::build_http_query($params),
      'sslverify' => false,
      'headers' => array(
        'Expect:'
      )
    ));
  }

  public static function build_signature($method, $url, $params = array(), $consumer_key, $consumer_secret, $access_token, $access_token_secret) {
    $normalized_http_method = strtoupper($method);
    $normalized_http_url = self::get_normalized_http_url($url);
    $signable_params = self::get_signable_params($params);

    $parts = self::urlencode_rfc3986(array(
      $normalized_http_method,
      $normalized_http_url,
      $signable_params
    ));

    $base_string = implode('&', $parts);    
    $key_parts = self::urlencode_rfc3986(array( $consumer_secret, $access_token_secret ));
    $key = implode('&', $key_parts);

    return base64_encode(hash_hmac('sha1', $base_string, $key, true));
  }

  public static function get_signable_params($params = array()) {
    if (isset($params['oauth_signature'])) {
      unset($params['oauth_signature']);
    }

    return self::build_http_query($params);
  }

  public static function get_normalized_http_url($url) {
    $parts = parse_url($url);
    $port = @$parts['port'];
    $scheme = $parts['scheme'];
    $host = $parts['host'];
    $path = @$parts['path'];
    $port or $port = ($scheme == 'https') ? '443' : '80';
    if (($scheme == 'https' && $port != '443')
        || ($scheme == 'http' && $port != '80')) {
      $host = "$host:$port";
    }
    return "$scheme://$host$path";
  }

  public static function urlencode_rfc3986($input) {
    if (is_array($input)) {
      return array_map(array('self', 'urlencode_rfc3986'), $input);
    } else if (is_scalar($input)) {
      return str_replace(
        '+',
        ' ',
        str_replace('%7E', '~', rawurlencode($input))
      );
    } else {
      return '';
    }
  }


  // This decode function isn't taking into consideration the above
  // modifications to the encoding process. However, this method doesn't
  // seem to be used anywhere so leaving it as is.
  public static function urldecode_rfc3986($string) {
    return urldecode($string);
  }

  // Utility function for turning the Authorization: header into
  // parameters, has to do some unescaping
  // Can filter out any non-oauth parameters if needed (default behaviour)
  public static function split_header($header, $only_allow_oauth_parameters = true) {
    $pattern = '/(([-_a-z]*)=("([^"]*)"|([^,]*)),?)/';
    $offset = 0;
    $params = array();
    while (preg_match($pattern, $header, $matches, PREG_OFFSET_CAPTURE, $offset) > 0) {
      $match = $matches[0];
      $header_name = $matches[2][0];
      $header_content = (isset($matches[5])) ? $matches[5][0] : $matches[4][0];
      if (preg_match('/^oauth_/', $header_name) || !$only_allow_oauth_parameters) {
        $params[$header_name] = self::urldecode_rfc3986($header_content);
      }
      $offset = $match[1] + strlen($match[0]);
    }

    if (isset($params['realm'])) {
      unset($params['realm']);
    }

    return $params;
  }

  // helper to try to sort out headers for people who aren't running apache
  public static function get_headers() {
    if (function_exists('apache_request_headers')) {
      // we need this to get the actual Authorization: header
      // because apache tends to tell us it doesn't exist
      $headers = apache_request_headers();

      // sanitize the output of apache_request_headers because
      // we always want the keys to be Cased-Like-This and arh()
      // returns the headers in the same case as they are in the
      // request
      $out = array();
      foreach( $headers AS $key => $value ) {
        $key = str_replace(
            " ",
            "-",
            ucwords(strtolower(str_replace("-", " ", $key)))
          );
        $out[$key] = $value;
      }
    } else {
      // otherwise we don't have apache and are just going to have to hope
      // that $_SERVER actually contains what we need
      $out = array();
      if( isset($_SERVER['CONTENT_TYPE']) )
        $out['Content-Type'] = $_SERVER['CONTENT_TYPE'];
      if( isset($_ENV['CONTENT_TYPE']) )
        $out['Content-Type'] = $_ENV['CONTENT_TYPE'];

      foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) == "HTTP_") {
          // this is chaos, basically it is just there to capitalize the first
          // letter of every word that is not an initial HTTP and strip HTTP
          // code from przemek
          $key = str_replace(
            " ",
            "-",
            ucwords(strtolower(str_replace("_", " ", substr($key, 5))))
          );
          $out[$key] = $value;
        }
      }
    }
    return $out;
  }

  // This function takes a input like a=b&a=c&d=e and returns the parsed
  // parameters like this
  // array('a' => array('b','c'), 'd' => 'e')
  public static function parse_parameters( $input ) {
    if (!isset($input) || !$input) return array();

    $pairs = explode('&', $input);

    $parsed_parameters = array();
    foreach ($pairs as $pair) {
      $split = explode('=', $pair, 2);
      $parameter = self::urldecode_rfc3986($split[0]);
      $value = isset($split[1]) ? self::urldecode_rfc3986($split[1]) : '';

      if (isset($parsed_parameters[$parameter])) {
        // We have already recieved parameter(s) with this name, so add to the list
        // of parameters with this name

        if (is_scalar($parsed_parameters[$parameter])) {
          // This is the first duplicate, so transform scalar (string) into an array
          // so we can add the duplicates
          $parsed_parameters[$parameter] = array($parsed_parameters[$parameter]);
        }

        $parsed_parameters[$parameter][] = $value;
      } else {
        $parsed_parameters[$parameter] = $value;
      }
    }
    return $parsed_parameters;
  }

  public static function build_http_query($params) {
    if (!$params) return '';

    // Urlencode both keys and values
    $keys = self::urlencode_rfc3986(array_keys($params));
    $values = self::urlencode_rfc3986(array_values($params));
    $params = array_combine($keys, $values);

    // Parameters are sorted by name, using lexicographical byte value ordering.
    // Ref: Spec: 9.1.1 (1)
    uksort($params, 'strcmp');

    $pairs = array();
    foreach ($params as $parameter => $value) {
      if (is_array($value)) {
        // If two or more parameters share the same name, they are sorted by their value
        // Ref: Spec: 9.1.1 (1)
        natsort($value);
        foreach ($value as $duplicate_value) {
          $pairs[] = $parameter . '=' . $duplicate_value;
        }
      } else {
        $pairs[] = $parameter . '=' . $value;
      }
    }
    // For each parameter, the name is separated from the corresponding value by an '=' character (ASCII code 61)
    // Each name-value pair is separated by an '&' character (ASCII code 38)
    return implode('&', $pairs);
  }
}

Sharepress::load();

#
# Don't be a dick. I like to eat, too.
# http://aaroncollegeman/sharepress/
#
if (Sharepress::unlocked()) require('pro.php');