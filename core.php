<?php 
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

if (!defined('ABSPATH')) exit;

// we depend on this...
require('lib/facebook-sdk-2.1.2.php');
// we don't care about certificate verification
spFacebook::$CURL_OPTS = spFacebook::$CURL_OPTS + array(
  CURLOPT_SSL_VERIFYPEER => false
);
// debugging
if (!defined('SHAREPRESS_DEBUG')) {
  // by default, on if host is sharepress.dev.wp (my local dev name)
  define('SHAREPRESS_DEBUG', $_SERVER['HTTP_HOST'] == 'dev.getwpapps.com');
}  

class /*@PLUGIN_LITE_CLASS@*/ Sharepress {
  
  const OPTION_API_KEY = 'sharepress_api_key';
  const OPTION_APP_SECRET = 'sharepress_app_secret';
  const OPTION_FB_SESSION = 'sharepress_session';
  const OPTION_PUBLISHING_TARGETS = 'sharepress_publishing_targets';
  const OPTION_NOTIFICATIONS = 'sharepress_notifications';
  const OPTION_DEFAULT_PICTURE = 'sharepress_default_picture';
  const OPTION_ACTIVATED = 'sharepress_activated';
  const OPTION_SETTINGS = 'sharepress_settings';
  
  //const META_MESSAGE_ID = 'sharepress_message_id';
  const META_RESULT = 'sharepress_result';
  const META_ERROR = 'sharepress_error';
  const META_POSTED = 'sharepress_posted';
  const META = 'sharepress_meta';
  
  // holds the singleton instance of your plugin's core
  static $instance;
  // holds a reference to the pro version of the plugin
  static $pro;
  // the path to this plugin
  static $dir_path;
  
  /**
   * Get the singleton instance of this plugin's core, creating it if it does
   * not already exist.
   */
  static function load() {
    if (!self::$instance) {
      self::$instance = new /*@PLUGIN_LITE_CLASS@*/ Sharepress();
      
      #
      # Establish the run-time path for this plugin.
      #
      $dir_path = explode(DIRECTORY_SEPARATOR, __FILE__);
      array_pop($dir_path);
      self::$dir_path = implode(DIRECTORY_SEPARATOR, $dir_path);
    }
    return self::$instance;
  }
  
  /**
   * Create a new instance of this plugin's core. There should only ever
   * be one instance of a plugin, so we make the constructor private, and
   * instead ask all other parts of WordPress to call ::load().
   */
  private function __construct() {
    #
    # All plugins tend to need these basic actions.
    #
    add_action('init', array($this, 'init'), 11, 1);
    add_action("{self::$dir_path}/lite.php_activate", array($this, 'activate'));
    
    # 
    # Add actions and filters here that should be called before the "init" action
    # Note that self::$pro will be null until the "init" action is called
    #
    // add_action($action_name, array($this, $action_name), $priority = 10, $num_args_supported = 1);
    // add_filter($filter_name, array($this, $filter_name), $priority = 10, $num_args_supported = 1);
  }
  
  function init() {
    if (is_admin()) {
      add_action('admin_notices', array($this, 'admin_notices'));
      add_action('admin_menu', array($this, 'admin_menu'));
      add_action('admin_init', array($this, 'admin_init'));
      add_action('admin_head', array($this, 'admin_head'));
      add_action('wp_ajax_fb_save_session', array($this, 'ajax_fb_save_session'));
      add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
      add_filter('contextual_help', array($this, 'contextual_help'));
      add_filter('plugin_action_links_sharepress/lite.php', array($this, 'plugin_action_links'), 10, 4);
    }
    
    add_action('save_post', array($this, 'save_post'));
    add_action('transition_post_status', array($this, 'transition_post_status'), 10, 3);
    add_action('future_to_publish', array($this, 'future_to_publish'));
    add_action('publish_post', array($this, 'publish_post'));
    add_filter('filter_'.self::META, array($this, 'filter_'.self::META), 10, 2);
  }
  
  function activate() {
    if (!get_option(self::OPTION_ACTIVATED)) {
      update_option(self::OPTION_ACTIVATED, date('Y/m/d H:i:s'));
    }
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
      $filename = $dir.'/sharepress-'.date('Ymd').'.log';
      $message = sprintf("%s %s %-5s %s\n", $thread_id, date('H:i:s'), $level, $message);
      if (!file_put_contents($filename, $message, FILE_APPEND)) {
        error_log("Failed to access Sharepress log file [$filename] for writing: add write permissions to directory [$dir]?");
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
    return get_option(self::OPTION_FB_SESSION, '');
  }
  
  static function setting($name = null) {
    $settings = get_option(self::OPTION_SETTINGS, array(
      'default_behavior' => 'on',
      'excerpt_length' => 20,
      'excerpt_more' => '...'
    ));
    
    return (!is_null($name)) ? @$settings[$name] : $settings;
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
      if (($api_key = self::api_key()) && ($app_secret = self::app_secret()) && ($session = self::session())) {
        self::$facebook = new spFacebook(array(
          'appId' => $api_key,
          'secret' => $app_secret
        ));
        
        self::$facebook->setSession($session, false);
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
      throw new Exception("Can't hit FB API without valid session.");
    }
  }
  
  /** 
   * Facebook Query: /me - returns the user data for the blog owner
   * @param string $param (optional) Return only the value at $param
   * @return mixed If $param is defined, only a single value is returned; otherwise, an array - the whole packet
   */
  static function me($param = null) {
    $me = self::api('/me', 'GET', array(), '10 minutes');
    return ($param) ? $me[$param] : $me;
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
      add_meta_box(self::META, 'Sharepress', array($this, 'meta_box'), 'post', 'side', 'high');
    }
  }
  
  function get_default_picture() {
    if ($set = get_option(self::OPTION_DEFAULT_PICTURE)) {
      return $set['url'];
    } else {
      return plugins_url('sharepress/img/wordpress.png');
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
    
    $activated_on = get_option(self::OPTION_ACTIVATED);

    // load the meta data
    $meta = get_post_meta($post->ID, self::META, true);
    if (!$meta) {
      // defaults:
      $meta = array(
        'message' => '',
        'title_is_message' => true,
        'picture' => $this->get_default_picture(),
        'let_facebook_pick_pic' => false,
        'description' => $this->get_excerpt($post),
        'excerpt_is_description' => true,
        'targets' => array_keys(self::targets()),
        'enabled' => Sharepress::setting('default_behavior')
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
    
    // stash $meta globally for access from Sharepress::sort_by_selected
    self::$meta = $meta;
    // allow for pro override
    $meta_box = apply_filters('sharepress_meta_box', $meta_box, $post, $meta);
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
    
    if ($posted = get_post_meta($post->ID, self::META_POSTED, true)) {
      require('published-msg.php');
      echo $meta_box;
    } else {
      require('behavior-picker.php');
    }
  }
  
  function save_post($post_id) {
    // don't do anything on autosave events
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return false;
    }
    
    $post = get_post($post_id);
    
    // make sure we're not working with a revision
    if ($parent_post_id = wp_is_post_revision($post)) {
      return false;
    }
    
    // verify permissions
    if (!current_user_can('edit_post', $post->ID)) {
      return false;
    }
    
    // if the nonce is present, update meta settings for this post
    if (wp_verify_nonce($_POST['sharepress-nonce'], plugin_basename(__FILE__))) {
      // remove any past failures
      delete_post_meta($post->ID, self::META_ERROR);
      
      // update meta?
      if (@$_POST[self::META]['publish_again'] || !get_post_meta($post->ID, self::META_POSTED, true)) {
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
        
        // if the post is published, then post to facebook immediately
        if ($post->post_status == 'publish') {
          $this->post_on_facebook($post);
        }
        
        return true;
      } else {
        return false;
      }
    } else {
      return false;
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

    // value of $post here is inconsistent
    if (!is_object($post)) {
      $post = get_post($post);
    }
    
    if ($new_status == 'publish' && $old_status != 'publish' && $post) {
      do_action('post_on_facebook', $post);
    }
  }
  
  function publish_post($post_id) {
    self::log("publish_post($post_id)");
    
    if ($post = get_post($post_id)) {
      $this->post_on_facebook($post);
    }
  }
  
  public function get_excerpt($post = null, $text = null) {
    if (!is_null($post)) {
      $text = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
    } 
    $text = strip_shortcodes( $text );
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
  
  function filter_sharepress_meta($meta, $post) {
    if (!@$meta['message'] || @$meta['title_is_message']) {
      $meta['message'] = apply_filters('post_title', $post->post_title);
    }
    
    if (!@$meta['description'] || @$meta['excerpt_is_description']) {
      $meta['description'] = $this->get_excerpt($post);
    }
    
    if (!@$meta['link'] || @$meta['link_is_permalink']) {
      $meta['link'] = get_permalink($post->ID);
    }
    
    if (!@$meta['name']) {
      $meta['name'] = apply_filters('post_title', $post->post_title);
    }
    
    if (!@$meta['targets'] && !self::$pro) {
      $meta['targets'] = array('wall');
    }
    
    if (!@$meta['let_facebook_pick_pic']) {
      // default to the post thumbnail
      if ($thumbnail_id = get_post_meta($post->ID, '_thumbnail_id', true)) {
        $thumbnail = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
        $picture = $thumbnail[0];
      } else {
        // fall back on the global default
        $picture = $this->get_default_picture();
      }
      
      $meta['picture'] = $picture;
    }
    
    return $meta;
  }
  
  private function can_post_on_facebook($post) {
    $can_post_on_facebook = (
      // post only if defined
      $post 
      
      // post only if newer than plugin activation date
      // NOTE: this was replaced by setting $meta['enabled'] to 'off' when post is older
      //&& strtotime($post->post_date_gmt) >= $activated 
      
      // post only if sharepress meta data is available
      && ($meta = get_post_meta($post->ID, self::META, true)) 
      
      // post only if enabled
      && ($meta['enabled'] == 'on') 
      
      // post only if never posted before
      && !get_post_meta($post->ID, self::META_POSTED, true) 
      
      // post only if no errors precede this posting
      && !get_post_meta($post->ID, self::META_ERROR)
    );
    
    return ($can_post_on_facebook ? $meta : false);
  }
  
  private function post_on_facebook($post) {
    if (SHAREPRESS_DEBUG) {
      self::log(sprintf("post_on_facebook(%s)", is_object($post) ? $post->post_title : $post));
    }
    
    $activated = strtotime(get_option(self::OPTION_ACTIVATED, 0));
    
    if ($meta = $this->can_post_on_facebook($post)) {
      // prefix the message with the permalink
      $meta['message'] .= ' - ' . get_permalink($post->ID);
    
      try {
        // first, should we post to the wall?
        if (in_array('wall', $meta['targets'])) {
          $result = self::api(self::me('id').'/feed', 'POST', array(
            'name' => $meta['name'],
            'message' => $meta['message'],
            'description' => $meta['description'],
            'picture' => $meta['picture'],
            'link' => $meta['link']
          ));
          
          self::log(sprintf("posted to the wall: %s", serialize($result)));
          
          // store the ID for queuing 
          add_post_meta($post->ID, Sharepress::META_RESULT, $result);
        }
        
        // next, fire the sharepress_post action
        // the pro version picks this up
        do_action('sharepress_post', $meta, $post);
      
        // success:
        update_post_meta($post->ID, self::META_POSTED, date('Y/m/d H:i:s'));
      
        $this->success($post, $meta);
    
      } catch (Exception $e) {
        self::err(sprintf("Exception thrown while in post_on_facebook: %s", $e->getMessage()));
        
        $this->error($post, $meta, $e);
      }
    }
  }
  
  function contextual_help($help) {
    
    return '';
  }
  
  /**
   * Add "Settings" link to the Plugins screen.
   */
  function plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
    $actions['settings'] = '<a href="options-general.php?page=sharepress">Settings</a>';
    if (!self::$pro && self::session()) {
      $actions['go-pro'] = '<a href="http://getwpapps.com/plugins/sharepress">Upgrade to Pro Version</a>';
    }
    return $actions;
  }
  
  /**
   * As part of setup, save the client-side session data - we don't trust cookies
   */
  function ajax_fb_save_session() {
    if (current_user_can('activate_plugins')) {
      update_option(self::OPTION_FB_SESSION, $_REQUEST['session']);
    } else {
      header('Status: 403 Not Allowed');
    }
    exit;
  }
  
  function admin_head() {
    ?>
      <script>
        (function($) {
          $(function() {
            $('a[href$=".jpg"]').fancybox();
          });
        })(jQuery);
      </script>
    <?php
  }
  
  function admin_init() {
    // verify our Facebook session
    if (@$_REQUEST['page'] == 'sharepress' && @$_REQUEST['action'] != 'fb_save_session' && ($session = self::session())) {
      $session_error = 'Your Facebook session is no longer valid. <a href="options-general.php?page=sharepress&step=1">Setup sharepress again</a>, or go to your <a href="admin.php">Dashboard</a>.';
      try {
        if (self::me('id') != $session['uid']) {
          update_option(self::OPTION_FB_SESSION, '');
          wp_die($session_error);
        }
      } catch (Exception $e) {
        update_option(self::OPTION_FB_SESSION, '');
        wp_die($session_error);
      }
    }
    
    if ($action = @$_REQUEST['action']) {
      
      // when the user clicks "Setup" tab on the settings screen:
      if ($action == 'clear_session') {      
        if (current_user_can('administrator')) {
          delete_option(self::OPTION_FB_SESSION);
          self::clear_cache();
          wp_redirect('options-general.php?page=sharepress&step=1');
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
    register_setting('fb-settings', self::OPTION_SETTINGS);

    wp_enqueue_style('fancybox', plugins_url('sharepress/lib/fancybox/jquery.fancybox-1.3.4.css'));
    wp_enqueue_script('fancybox', plugins_url('sharepress/lib/fancybox/jquery.fancybox-1.3.4.pack.js', array('jquery')));
  }
  
  static function installed() {
    return ( self::api_key() && self::app_secret() && self::session() );
  }
  
  function admin_notices() {
    if (current_user_can('administrator')) {
      if ( !self::installed() && @$_REQUEST['page'] != 'sharepress' ) {
        ?>
          <div class="error">
            <p>You haven't finished setting up <a href="<?php echo get_admin_url() ?>options-general.php?page=sharepress">Sharepress</a>.</p>
          </div>
        <?php
      } else if (@$_REQUEST['page'] == 'sharepress' && !self::$pro) {
        ?>
          <div class="updated">
            <p><b>Go pro!</b> This plugin can do more: a lot more. <a href="http://getwpapps.com/plugins/sharepress">Learn more</a>.</p>
          </div>
        <?php
      }
    }
  }
  
  function admin_menu() {
    add_submenu_page('options-general.php', 'Sharepress', 'Sharepress', 'administrator', 'sharepress', array($this, 'settings'));
  }
  
  function settings() {
    if (empty($_REQUEST['step']) || isset($_REQUEST['updated'])) {
      if (!self::api_key() || !self::app_secret()) {
        $_REQUEST['step'] = '1';
      } else if (!self::session()) {
        $_REQUEST['step'] = '2';
      } else {
        $_REQUEST['step'] = 'config';
      }
    }
    
    require('settings.php');
  }
  
  function success($post, $meta) {
    if ($this->notify_on_success()) {
      $link = get_option('siteurl').'/wp-admin/post.php?action=edit&post='.$post->ID;
      wp_mail(
        $this->get_success_email(),
        "Sharepress Success",
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
        "Sharepress Error",
        "Sharepress Error: $error; while sending \"{$meta['message']}\" to Facebook for post {$post->ID}\n\nTo retry, simply edit your post and save it again:\n{$link}"
      );
    }
    
    error_log("Sharepress Error: $error; while sending {$meta['message']} for post {$post->ID}");
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

/*@PLUGIN_LITE_CLASS@*/ Sharepress::load();