<?php
class Twitter_SharePress extends Base_SharePress {


  function admin_notices() {}
  
  function admin_menu() {
    add_submenu_page('sharepress', 'Twitter', 'Twitter', 'administrators', 'sharepress-twitter', array($this, 'settings'));

  }

  static function twitter_ready() {
    return self::unlocked() 
      && self::setting('twitter_consumer_key') 
      && self::setting('twitter_consumer_secret') 
      && self::setting('twitter_access_token') 
      && self::setting('twitter_access_token_secret');
  }
  
  
  function can_post($post) {
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
  

  function share($post) {
    if ($twitter_meta = $this->can_post_on_twitter($post)) {
     
      $client = new SharePress_TwitterClient(get_option(self::OPTION_SETTINGS));
      $tweet = sprintf('%s %s', $post->post_title, get_permalink($post));
      if ($hash_tag = trim($twitter_meta['hash_tag'])) {
        $tweet .= ' '.$hash_tag;
      }

      $result = $client->post($tweet);
      add_post_meta($post->ID, Sharepress::META_TWITTER_RESULT, $result);

    }

    // success:
    update_post_meta($post->ID, self::META_POSTED, gmdate('Y-m-d H:i:s'));
    delete_post_meta($post->ID, self::META_SCHEDULED);
    
    $this->success($post, $meta);

  }
  
}



class SharepressTwitterException extends Exception {}

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