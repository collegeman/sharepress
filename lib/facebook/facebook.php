<?php
/**
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

require_once "base_facebook.php";

// we don't care about certificate verification
spBaseFacebook::$CURL_OPTS = spBaseFacebook::$CURL_OPTS + array(
  CURLOPT_SSL_VERIFYPEER => false
);

class Facebook_SharePress extends Base_SharePress {
  
  const OPTION_API_KEY = 'sharepress_api_key';
  const OPTION_APP_SECRET = 'sharepress_app_secret';
  const OPTION_PUBLISHING_TARGETS = 'sharepress_publishing_targets';
  const OPTION_NOTIFICATIONS = 'sharepress_notifications';
  const TRANSIENT_IS_BUSINESS = 'sharepress_is_business';
  
  // function admin_menu() {
  //   add_submenu_page('SharePress', 'Facebook', 'administrator', 'sharepress', array($this, 'settings'));
  // }

  // /**
  //  * As part of setup, save the client-side session data - we don't trust cookies
  //  */
  // function ajax_fb_save_keys() {
  //   if (current_user_can('activate_plugins')) {
  //     if (!self::is_mu()) {
  //       update_option(self::OPTION_API_KEY, $_REQUEST['api_key']);
  //       update_option(self::OPTION_APP_SECRET, $_REQUEST['app_secret']);
  //     }
  //     echo self::facebook()->getLoginUrl(array(
  //       'redirect_uri' => $_REQUEST['current_url'],
  //       'scope' => 'read_stream,publish_stream,offline_access,manage_pages,share_item'
  //     ));
        
  //   } else {
  //     header('Status: 403 Not Allowed');
  //   }
  //   exit;
  // }

  function pages() {
    $pages = array();
    $pages = apply_filters('sharepress_facebook_pages', $pages);
    return $pages;
  }

  function api_key() {
    return get_option(self::OPTION_API_KEY, '');
  }
  
  function app_secret() {
    return get_option(self::OPTION_APP_SECRET, '');
  }

  function targets($id = null) {
    $targets = get_option(self::OPTION_PUBLISHING_TARGETS, false);
    if ($targets === false) {
      $targets = array('wall' => 1);
    }
    
    return ($id) ? isset($targets[$id]) : $targets;
  }

  function register_settings() {
    register_setting(__CLASS__, self::OPTION_PUBLISHING_TARGETS);
  }
  
  function admin_notices() {}
  function admin_menu() {
    add_submenu_page('sharepress', 'Facebook', 'Facebook', 'administrator', 'sharepress-facebook', array($this, 'settings'));
  }

  function can_post($post) {
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
    
    return ($can_post_on_facebook ? $meta : false);
  }

  function handle_share($post) {
      // TODO: make configurable
      $meta['message'] .= ' - ' . get_permalink($post->ID);
    
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
          
          SharePress::log(sprintf("posted to the wall: %s", serialize($result)));
          
          // store the ID and published date for queuing 
          $result['published'] = time();
          $result['message'] = $meta['message'];
          add_post_meta($post->ID, Sharepress::META_RESULT, $result);
        }
        
        // next, fire the sharepress_post action
        // the pro version picks this up
        do_action('sharepress_post', $meta, $post);

      } catch (Exception $e) {
        SharePress::err(sprintf("Exception thrown while in share: %s", $e->getMessage()));
        
        $this->error($post, $meta, $e);
      }
  }

  static function has_keys() {
    return ( self::api_key() && self::app_secret() );
  }
  
  static function installed() {
    return ( self::has_keys() && self::session() );
  }

  function session() {
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
        $picture = '';

        $meta = get_post_meta($post->ID, self::META, true);
        
        if (!$meta['let_facebook_pick_pic']) { // use featured image, fallback on first image in post, come to rest on global default
        
          // this prevents users from changing featured image after the fact: if (!($picture = $meta['picture'])) {
            if ($src = wp_get_attachment_image_src( get_post_meta( $post->ID, '_thumbnail_id', true ), 'thumbnail' )) {
              $picture = $src[0];
            }
          // }

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

        global $post;
        if (!($excerpt = $post->post_excerpt)) {
          $excerpt = preg_match('/^.{1,256}\b/s', preg_replace("/\s+/", ' ', strip_tags($post->post_content)), $matches) ? $matches[0].'...' : get_bloginfo('descrption');
        }

        $defaults = array(
          'og:type' => 'article',
          'og:url' => get_permalink(),
          'og:title' => get_the_title(),
          'og:image' => $picture,
          'og:site_name' => get_bloginfo('name'),
          'fb:app_id' => get_option(self::OPTION_API_KEY),
          'og:description' => $this->strip_shortcodes($excerpt),
          'og:locale' => 'en_US'
        );

      } else {
        $defaults = array(
          'og:type' => self::setting('page_og_type', 'blog'),
          'og:url' => is_front_page() ? get_bloginfo('siteurl') : get_permalink(),
          'og:title' => get_the_title(),
          'og:site_name' => get_bloginfo('name'),
          'og:image' => $this->get_default_picture(),
          'fb:app_id' => get_option(self::OPTION_API_KEY),
          'og:description' => $this->strip_shortcodes(get_bloginfo('description')),
          'og:locale' => 'en_US'
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

  static $facebook;
  static function facebook() {
    if (!self::$facebook) {
      if (($api_key = self::api_key()) && ($app_secret = self::app_secret())) {
        self::$facebook = new SharePress_Facebook(array(
          'appId' => $api_key,
          'secret' => $app_secret
        ));
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
  function api($path, $method = 'GET', $params = array(), $cache_for = false) {
    SharePress::log(sprintf("api(%s, %s, %s, %s)", $path, $method, serialize($params), $cache_for));
    
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
  function me($param = null, $rethrow = false) {
    try {
      if (self::is_business()) {
        $accounts = self::api('/me/accounts', 'GET', array(), '10 minutes');
        $me = $accounts['data'][0];
        return ($param) ? $me[$param] : $me;
      } else {
        $me = self::api('/me', 'GET', array(), '10 minutes');
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

  function is_business() {
    if (is_string($is_business = get_transient(self::TRANSIENT_IS_BUSINESS))) {
      return $is_business == '1';
    } else {
      $me = self::api('/me');
      $is_business = !$me;
      set_transient(self::TRANSIENT_IS_BUSINESS, $is_business ? '1' : '0', 3600); 
      return $is_business;
    }
  }

  function handleFacebookException($e) {
    if (is_a($e, 'SharepressFacebookSessionException') || $e->getMessage() == 'Invalid OAuth access token signature.') {
      if ($client = self::facebook()) {
        $client->clearAllPersistentData();
      }
      delete_transient(self::TRANSIENT_IS_BUSINESS);
      wp_die('Your Facebook session is no longer valid. <a href="options-general.php?page=sharepress-facebook">Setup sharepress again</a>, or go to your <a href="index.php">Dashboard</a>.');
    } else if (is_admin()) {
      wp_die(sprintf('There was a problem with SharePress: %s; This is probably an issue with the Facebook API. Check <a href="http://developers.facebook.com/live_status/" target="_blank">Facebook Live Status</a> for more information. You can also <a href="%s">try resetting SharePress</a>. If the problem persists, please <a href="http://aaroncollegeman.com/sharepress/help/#support_form" target="_blank">report it to Fat Panda</a>.', $e->getMessage(), admin_url('options-general.php?page=sharepress-facebook&amp;action=clear_session')));
    } else {
      SharePress::err(sprintf("Exception thrown by Facebook API: %s; This is definitely an issue with the Facebook API. Check http://developers.facebook.com/live_status/ for more information. If the problem persists, please report it at http://aaroncollegeman.com/sharepress/help/.", $e->getMessage()));
      throw new Exception("There is a problem with SharePress. Check the log.");
    }
  }

}

class SharepressFacebookSessionException extends Exception {}

/**
 * Extends the BaseFacebook class with the intent of using
 * PHP sessions to store user ids and access tokens.
 */
class spFacebook extends spBaseFacebook {
  /**
   * Identical to the parent constructor, except that
   * we start a PHP session to store the user ID and
   * access token if during the course of execution
   * we discover them.
   *
   * @param Array $config the application configuration.
   * @see BaseFacebook::__construct in facebook.php
   */
  public function __construct($config) {
    parent::__construct($config);
  }

  protected static $kSupportedKeys =
    array('state', 'code', 'access_token', 'user_id');

  /**
   * Provides the implementations of the inherited abstract
   * methods.  The implementation uses PHP sessions to maintain
   * a store for authorization codes, user ids, CSRF states, and
   * access tokens.
   */
  protected function setPersistentData($key, $value) {
    if (!in_array($key, self::$kSupportedKeys)) {
      SharePress::errorLog('Unsupported key passed to setPersistentData.');
      return;
    }

    $session_var_name = $this->constructSessionVariableName($key);
    update_option($session_var_name, $value);
  }

  protected function getPersistentData($key, $default = false) {
    if (!in_array($key, self::$kSupportedKeys)) {
      SharePress::errorLog('Unsupported key passed to getPersistentData.');
      return $default;
    }

    $session_var_name = $this->constructSessionVariableName($key);
    return get_option($session_var_name, $default);
  }

  protected function clearPersistentData($key) {
    if (!in_array($key, self::$kSupportedKeys)) {
      SharePress::errorLog('Unsupported key passed to clearPersistentData.');
      return;
    }

    $session_var_name = $this->constructSessionVariableName($key);
    delete_option($session_var_name);
  }

  function clearAllPersistentData() {
    foreach (self::$kSupportedKeys as $key) {
      $this->clearPersistentData($key);
    }
  }

  protected function constructSessionVariableName($key) {
    $arg = implode('_', array('fb', $this->getAppId(), $key));
    return sprintf(Sharepress::OPTION_SESSION_ARG, $arg);
  }
}