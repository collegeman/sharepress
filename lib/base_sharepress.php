<?php
class Base_SharePress {

  const OPTION_SESSION_ARG = 'sharepress_%s';

  const META_QUEUE = '%s_sharepress_queue';
  const META_RESULT = '%s_result';
  const META_ERROR = 'sharepress_error';
  const META_POSTED = 'sharepress_posted';
  const META_SCHEDULED = '%s_scheduled';

  // holds a reference to the pro version of the plugin
  static $pro;
  
  function __construct() {
    add_action('init', array($this, 'init'), 11, 1);
  }
  
  function init() {
    if (is_admin()) {
      add_action('admin_notices', array($this, 'admin_notices'));
      add_action('admin_menu', array($this, 'admin_menu'));
      add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
      
      if ($_REQUEST['page'] == 'sharepress' && isset($_REQUEST['log'])) {
        wp_enqueue_style('theme-editor');
      }
    }
    
    add_action('save_post', array($this, 'save_post'));
    add_action('transition_post_status', array($this, 'transition_post_status'), 10, 3);
    add_action('future_to_publish', array($this, 'future_to_publish'));
    add_action('publish_post', array($this, 'publish_post'));
    add_action('wp_head', array($this, 'wp_head'));
    add_filter('cron_schedules', array($this, 'cron_schedules'));

    if (!wp_next_scheduled('sharepress_oneminute_cron')) {
      wp_schedule_event(time(), 'oneminute', 'sharepress_oneminute_cron');
    }

    // triggers sharepress-mu loading, if present:
    do_action('sharepress_init');
  } 

  function cron_schedules($schedules) {
    $schedules['oneminute'] = array(
      'interval' => 60,
      'display' => __('Every Minute')
    );
    
    return $schedules;
  }
  
  function wp_head() {}

  function add_meta_boxes() {
    if (self::installed()) {
      add_meta_box(self::META, 'SharePress', array($this, 'meta_box'), 'post', 'side', 'high');
    }
  }
  
  function get_default_picture() {
    if ($set = get_option(self::OPTION_DEFAULT_PICTURE)) {
      return $set['url'];
    } else {
      return plugins_url('img/wordpress.png', __FILE__);
    }
  }

  function get_first_image_for($post_id) {
    $images = get_children(array( 
      'post_type' => 'attachment',
      'post_mime_type' => 'image',
      'post_parent' => $post_id,
      'orderby' => 'menu_order',
      'order'  => 'ASC',
      'numberposts' => 1,
    ));
    
    if ($images && ( $src = wp_get_attachment_image_src($images[0]->ID, 'thumbnail') )) {
      return $src[0];
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
        'enabled' => Sharepress::setting('twitter_behavior', 'on')
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
    SharePress::log("save_post($post_id)");
    
    // don't do anything on autosave events
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      SharePress::log("DOING_AUTOSAVE is true; ignoring save_post($post_id)");
      return false;
    }
    
    $post = get_post($post_id);
    
    // make sure we're not working with a revision
    if ($parent_post_id = wp_is_post_revision($post)) {
      SharePress::log("Post is a revision; ignoring save_post($post_id)");
      return false;
    }

    $is_xmlrpc = defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
    if ($is_xmlrpc) {
      SharePress::log('In XML-RPC request');
    } else {
      SharePress::log('Not in XML-RPC request');
    }

    $is_cron = defined('DOING_CRON') && DOING_CRON;
    if ($is_cron) {
      SharePress::log('In CRON job');
    } else {
      SharePress::log('Not in CRON job');
    }
    
    // verify permissions
    if (!$is_cron && !current_user_can('edit_post', $post->ID)) {
      SharePress::log("Current user is not allowed to edit posts; ignoring save_post($post_id)");
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
            SharePress::log("Posting to Facebook now; save_post($post_id)");
            $this->share($post);
            
          // otherwise, if $time specified, schedule future publish
          } else if ($time) {
            SharePress::log("Scheduling future repost at {$time}; save_post($post_id)");
            update_post_meta($post->ID, self::META_SCHEDULED, $time);
          
          // otherwise...?
          } else {
            SharePress::log("Not time to post or no post schedule time given, so not posting to Facebook; save_post($post_id)");
          }
        
        } else {
          SharePress::log("Post status is not 'publish'; not posting to Facebook on save_post($post_id)");

        }
        
      } else if (get_post_meta($post->ID, self::META_SCHEDULED, true) && @$_POST[self::META]['cancelled']) {
        SharePress::log("Scheduled repost canceled by save_post($post_id)");
        delete_post_meta($post->ID, self::META_SCHEDULED);

      } else if (isset($_POST[self::META]['enabled']) && $_POST[self::META]['enabled'] == 'off') {
        SharePress::log("User has indicated they do not wish to Post to Facebook; save_post($post_id)");
        update_post_meta($post->ID, self::META, array('enabled' => 'off'));
        update_post_meta($post->ID, self::META_TWITTER, array('enabled' => 'off'));

      } else {
        SharePress::log("Post is already posted or is not allowed to be posted to facebook; save_post($post_id)");
      }

      

    #
    # When save_post is invoked by XML-RPC or CRON the SharePress nonce won't be 
    # available to test. So, we evaluate whether or not to post based on several
    # criteria:
    # 1. SharePress must be configured to post to Facebook by default
    # 2. The Post must not already have been posted by SharePress
    # 3. The Post must not be scheduled for future posting
    #
    } else if (($is_xmlprc || $is_cron) && $this->setting('default_behavior') == 'on' && !$already_posted && !$is_scheduled) {
      // remove any past failures
      delete_post_meta($post->ID, self::META_ERROR);

      // setup meta with defaults
      $meta = apply_filters('filter_'.self::META, array(
        'message' => $post->post_title,
        'title_is_message' => true,
        'picture' => $this->get_default_picture(),
        'let_facebook_pick_pic' => self::setting('let_facebook_pick_pic_default', 0),
        'link' => get_permalink($post),
        'description' => $this->get_excerpt($post),
        'excerpt_is_description' => true,
        'targets' => array_keys(self::targets()),
        'enabled' => Sharepress::setting('default_behavior')
      ), $post); 

      update_post_meta($post->ID, self::META, $meta);

      $meta = apply_filters('filter_'.self::META_TWITTER, array(
        'enabled' => Sharepress::setting('twitter_behavior')
      ));

      update_post_meta($post->ID, self::META_TWITTER, $meta);

      if ($post->post_status == 'publish') {
        SharePress::log("Sharing with SharePress now; save_post($post_id)");
        $this->share($post);
      }

    } else {
      SharePress::log("SharePress nonce was invalid; ignoring save_post($post_id)");
      
    }
  }
  
  function future_to_publish($post) {
    if (SHAREPRESS_DEBUG) {
      SharePress::log(sprintf("future_to_publish(%s)", is_object($post) ? $post->post_title : $post));
    }
    
    $this->transition_post_status('publish', 'future', $post);
  }
  
  function transition_post_status($new_status, $old_status, $post) {
    if (SHAREPRESS_DEBUG) {
      SharePress::log(sprintf("transition_post_status(%s, %s, %s)", $new_status, $old_status, is_object($post) ? $post->post_title : $post));
    }

    if (@$_POST[self::META]) {
      if (SHAREPRESS_DEBUG) {
        SharePress::log(sprintf("Saving operation in progress; ignoring transition_post_status(%s, %s, %s)", $new_status, $old_status, is_object($post) ? $post->post_title : $post));
      }
      return;
    }
    
    // value of $post here is inconsistent
    if (!is_object($post)) {
      $post = get_post($post);
    }
    
    if ($new_status == 'publish' && $old_status != 'publish' && $post) {
      $this->share($post);
    }
  }
  
  function publish_post($post_id) {
    SharePress::log("publish_post($post_id)");
    
    if (@$_POST[self::META]) {
      SharePress::log("Saving operation in progress; ignoring publish_post($post_id)");
      // saving operation... don't execute this
      return;
    }
    
    if ($post = get_post($post_id)) {
      $this->share($post);
    }
  }

  public function strip_shortcodes($text) {
    // the WordPress way:
    $text = strip_shortcodes($text);
    // the manual way:
    return preg_replace('#\[/[^\]]+\]#', '', $text);

  }
  
  public function get_excerpt($post = null, $text = null) {
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
    
    } else if (@$meta['let_facebook_pick_pic'] == 2) {
      $meta['picture'] = $this->get_default_picture();
    }
    
    return $meta;
  }
  
  function can_post($post) {
    return false;
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
  
  function sort_by_posted_date($result1, $result2) {
    $date1 = $result1['posted'];
    $date2 = $result2['posted'];
    return ($date1 == $date2) ? 0 : ( $date1 < $date2 ? 1 : -1);
  }
  
  function share($post) {
    if (SHAREPRESS_DEBUG) {
      SharePress::log(sprintf("share(%s)", is_object($post) ? $post->post_title : $post));
    }

    if (!is_object($post)) {
      $post = get_post($post);
    }

    $this->handle_share($post);   
  }

  function handle_share($post) {}

  function settings() {
    /*
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
          unset($settings['twitter_access_token']);
          unset($settings['twitter_access_token_secret']);
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
    */

    if ( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'reset' ) {
      $this->reset();
    }

    register_setting(__CLASS__, sprintf('%s_settings', strtolower(__CLASS__)), array($this, 'sanitize_settings'));
  }

  function reset() {}

  function sanitize_settings($settings) {
    return $settings;
  }

  function admin_notices() {}
  function admin_menu() {}
  
  function success($post, $meta) {
    if ($this->notify_on_success()) {
      $link = admin_url('post.php?action=edit&post='.$post->ID);
      wp_mail(
        $this->get_success_email(),
        "SharePress Success",
        "Sent message \"{$meta['message']}\" for post {$post->ID}\n\nNeed to edit your post? Click here:\n{$link}"
      );
    }
  }
  
  function error($post, $meta, $error) {
    if ($error instanceof Exception) {
      $error = $error->getMessage();
    } else {
      $error = (string) $error;
    }
    
    // normalize error meta

    update_post_meta($post->ID, self::META_ERROR, $error);
    
    if ($this->notify_on_error()) {
      $link = admin_url('post.php?action=edit&post='.$post->ID); 
      wp_mail(
        $this->get_error_email(),
        "SharePress Error",
        "SharePress Error: $error; while sending \"{$meta['message']}\" to Facebook for post {$post->ID}\n\nTo retry, simply edit your post and save it again:\n{$link}"
      );
    }
    
    error_log("SharePress Error: $error; {$meta['message']} for post {$post->ID}");
  }
  
  // ===========================================================================
  // Helper functions - Provided to your plugin, courtesy of wp-kitchensink
  // http://github.com/collegeman/wp-kitchensink
  // ===========================================================================
  
  /**
   * This function provides a convenient way to access your plugin's settings.
   * The settings are serialized and stored in a single WP option. This function
   * opens that serialized array, looks for $name, and if it's found, returns
   * the value stored there. Otherwise, $default is returned.
   * @param string $name
   * @param mixed $default
   * @return mixed
   */
  function setting($name, $default = null) {
    $settings = get_option(sprintf('%s_settings', strtolower(__CLASS__)), array());
    return isset($settings[$name]) ? $settings[$name] : $default;
  }

  /**
   * Use this function in conjunction with Settings pattern #3 to generate the
   * HTML ID attribute values for anything on the page. This will help
   * to ensure that your field IDs are unique and scoped to your plugin.
   *
   * @see settings.php
   */
  function id($name, $echo = true) {
    $id = sprintf('%s_settings_%s', strtolower(__CLASS__), $name);
    if ($echo) {
      echo $id;
    }
    return $id;
  }

  /**
   * Use this function in conjunction with Settings pattern #3 to generate the
   * HTML NAME attribute values for form input fields. This will help
   * to ensure that your field names are unique and scoped to your plugin, and
   * named in compliance with the setting storage pattern defined above.
   * 
   * @see settings.php
   */
  function field($name, $echo = true) {
    $field = sprintf('%s_settings[%s]', strtolower(__CLASS__), $name);
    if ($echo) {
      echo $field;
    }
    return $field;
  }
  
  /**
   * A helper function. Prints 'checked="checked"' under two conditions:
   * 1. $field is a string, and $this->setting( $field ) == $value
   * 2. $field evaluates to true
   */
  function checked($field, $value = null) {
    if ( is_string($field) ) {
      if ( $this->setting($field) == $value ) {
        echo 'checked="checked"';
      }
    } else if ( (bool) $field ) {
      echo 'checked="checked"';
    }
  }

  /**
   * A helper function. Prints 'selected="selected"' under two conditions:
   * 1. $field is a string, and $this->setting( $field ) == $value
   * 2. $field evaluates to true
   */
  function selected($field, $value = null) {
    if ( is_string($field) ) {
      if ( $this->setting($field) == $value ) {
        echo 'selected="selected"';
      }
    } else if ( (bool) $field ) {
      echo 'selected="selected"';
    }
  }

}

class Core_SharePress extends Base_SharePress {

  const OPTION_DEFAULT_PICTURE = 'sharepress_default_picture';
  const OPTION_NOTIFICATIONS = 'sharepress_notifications';
  
  function admin_menu() {
    add_menu_page('SharePress', 'SharePress', 'administrator', 'sharepress', array($this, 'settings'));
    add_submenu_page('sharepress', 'General Settings', 'General Settings', 'administrator', 'sharepress', array($this, 'settings'));   
  }

  function admin_notices() {
    if (current_user_can('administrator')) {
      if (!SharePress::isMigrated()) {
        ?>
          <div class="error">
            <p>Migrate your SharePress data to version 3.0!</p>
          </div>
        <?php
      
      } else if (preg_match('/^sharepress/', $_REQUEST['page'])) {
        if ($this->setting('license_key') && strlen($this->setting('license_key')) != 32) {
          ?>
            <div class="error">
              <p>Hmm... looks like there's something wrong with your <a href="<?php echo get_admin_url() ?>options-general.php?page=sharepress">SharePress</a> license key.</p>
            </div>
          <?php
        } else if (!$this->pro) {
          ?>
            <div class="updated">
              <p><b>Go pro!</b> This plugin can do more: a lot more. <a href="http://aaroncollegeman.com/sharepress?utm_source=sharepress&utm_medium=in-app-promo&utm_campaign=learn-more">Learn more</a>.</p>
            </div>
          <?php
        }
      }      
    }
  }

  function settings() {
    register_setting(__CLASS__, self::OPTION_NOTIFICATIONS);
    parent::settings();
  }

  function sanitize_settings() {
    if (!empty($settings['license_key'])) {
      $settings['license_key'] = trim($settings['license_key']);
    }
    return $settings;
  }

  function get_error_email() {
    $options = get_option(self::OPTION_NOTIFICATIONS);
    return (@$options['on_error_email']) ? $options['on_error_email'] : get_option('admin_email');
  }
  
  function notify_on_error() {
    $options = get_option(self::OPTION_NOTIFICATIONS);
    return $options ? $options['on_error'] == '1' : true;
  }
  
  function get_success_email() {
    $options = get_option(self::OPTION_NOTIFICATIONS);
    return (@$options['on_success_email']) ? $options['on_success_email'] : get_option('admin_email');
  }
  
  function notify_on_success() {
    $options = get_option(self::OPTION_NOTIFICATIONS);
    return $options ? $options['on_success'] == '1' : true;
  }
  

}