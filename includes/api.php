<?php
# RESTful API - not to be confused with AJAX stuff
class SpApi_v1 extends AbstractSpApi {

  function modal() {
    include(SP_DIR.'/views/buf-modal.php');
    exit;
  }

  function media($id) {
    if ($this->_isGet()) {
      $thumb = wp_get_attachment_image_src($id, 'thumbnail');
      $full = wp_get_attachment_image_src($id, 'full');
      return array(
        'id' => $id,
        'thumb' => array(
          'url' => $thumb[0],
          'width' => $thumb[1],
          'height' => $thumb[2]
        ),
        'full' => array(
          'url' => $full[0],
          'width' => $full[1],
          'height' => $full[2]
        )
      );
    }
  }

  function preview() {
    $url = @filter_var($_REQUEST['url'], FILTER_VALIDATE_URL);
    if (!$url) {
      return false;
    }

    $key = '_fb-'.md5($url);
    if (!isset($_REQUEST['flush']) && ($cached = get_transient($key))) {
      return $cached;
    }

    $result = _wp_http_get_object()->post(sprintf('https://graph.facebook.com?id=%s&scrape=true', urlencode($url)));

    $preview = json_decode($result['body']);
      
    if ($result['response']['code'] == 200) {
      set_transient($key, $preview, 60 * 60 * 24 * 7);
    }

    return $preview;
  }

  function oauth($service) {
    if (!$service = trim($service)) {
      return false;
    }

    $error = $user = false;

    if (!$client = buf_client($service)) {
      return false;
    }

    try {
      $profile = $client->profile();
    } catch (Exception $e) {
      $error = $e;
      // TODO: figure out all of the possibilities
    }
    
    if (!$profile && !$error) {
      wp_redirect( $client->loginUrl() );

    } else {
      $profile_ID = buf_update_profile($profile);
    } 

    if (empty($_REQUEST['redirect_uri'])) {
      unset($profile->access_token);
      return $profile;
    }

    if (!$host = parse_url($_REQUEST['redirect_uri'], PHP_URL_HOST)) {
      wp_redirect( admin_url($_REQUEST['redirect_uri']) );
    } else {
      wp_redirect( $_REQUEST['redirect_uri'] );
    }
  }

  function updates() {

  }

  function profiles() {

  }

  function debug($fx) {
    if (constant('SP_DEBUG') && current_user_can('admin')) {
      // isolate to SharePress namespace
      list($ns) = explode('_', $fx);
      if (!in_array($ns, array('sp', 'buf'))) {
        wp_die('Not allowed to do that.');
      }
      if (!isset($_REQUEST['args']) || !is_array($_REQUEST['args'])) {
        $_REQUEST['args'] = array();
      }
      return @call_user_func_array($fx, $_REQUEST['args']);
    }
  }

}

abstract class AbstractSpApi {
  static function _method() {
    $headers = apache_request_headers();
    if (!empty($headers['X-HTTP-Method-Override'])) {
      return strtolower($headers['X-HTTP-Method-Override']);
    } else if (!empty($_REQUEST['_method'])) {
      return strtolower(trim($_REQUEST['_method']));
    } else {
      return strtolower($_SERVER['REQUEST_METHOD']);
    }
  }

  function _isGet() {
    return self::_method() === 'get';
  }

  function _isPost() {
    return self::_method() === 'post';
  }

  function _isPut() {
    return self::_method() === 'put';
  }

  function _isDelete() {
    return self::_method() === 'delete';
  }
}

add_filter('rewrite_rules_array', 'sp_rewrite_rules_array');
add_filter('query_vars', 'sp_query_vars');
add_action(constant('WP_DEBUG') || constant('SP_DEBUG') ? 'wp_loaded' : 'sp_activated', 'sp_flush_rewrite_rules');
add_action('parse_request', 'sp_parse_request');

// e.g., sp/1/profiles/:id
define('SP_API_REWRITE_RULE', '(sp)/(\d)/([a-z]+)(/(.*?))*(.json)?$');

function sp_rewrite_rules_array($rules) {
  return array(
    SP_API_REWRITE_RULE => 'index.php?_sp=$matches[3]&_v=$matches[2]&_args=$matches[5]'
  ) + $rules;
}

function sp_query_vars($vars) {
  $vars[] = '_sp';
  $vars[] = '_v';
  $vars[] = '_args';
  return $vars;
}

function sp_flush_rewrite_rules() {
  $rules = get_option( 'rewrite_rules' );
  if (!isset($rules[SP_API_REWRITE_RULE])) {
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
  }
}

function sp_parse_request($wp) {
  if (isset($wp->query_vars['_sp'])) {
    if (!is_user_logged_in()) {
      // TODO: more sophisticated error here
      wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
      exit;
    }
    
    $class = "SpApi_v{$wp->query_vars['_v']}";
    if (!class_exists($class)) {
      return;
    }
    $api = new $class();
    $fx = array($api, $wp->query_vars['_sp']);
    if (is_callable($fx)) {
      $result = call_user_func_array($fx, explode('/', $wp->query_vars['_args']));
      header('Content-Type: application/json');
      echo json_encode($result);
      exit(0);
    }  
  }
}