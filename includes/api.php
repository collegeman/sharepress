<?php
# RESTful API - not to be confused with AJAX stuff
class SpApi_v1 extends AbstractSpApi {

  function modal() {
    $this->_assertLoggedIn(); 
    $text = '';

    if (!empty($_REQUEST['url'])) {
      $crawled = sp_crawl($url = trim($_REQUEST['url']), array_key_exists('flush', $_REQUEST));
      if (is_wp_error($crawled)) {
        @error_log($crawled->get_error_code().': '.$crawled->get_error_message());
        if ($crawled->get_error_code() == 'shorten') {
          $text = 'Oops! I failed to shorten your URL. '.$url;
        } else {
          $text = $crawled->get_error_message().' '.$url;
        }
      } else {
        $text = implode(' ', array_filter(array($crawled->title, $crawled->short)));
      }
    } else if (!empty($_REQUEST['p'])) {
      if (!$post = get_post($_REQUEST['p'])) {
        $text = "Oops! I couldn't find that post.";
      } else {
        if (is_wp_error($short = sp_shorten($url = site_url('?p='.$post->ID)))) {
          $text = 'Oops! I failed to shorten your URL. '.$url;
        } else {
          $text = get_the_title($post->ID).' '.$short;
        }
      }
    }

    include(SP_DIR.'/views/buf-modal.php');
    exit;
  }

  function shorten() {
    $this->_assertLoggedIn();
    return sp_shorten($_REQUEST['url'], array_key_exists('flush', $_REQUEST));
  }

  function crawl() {
    $this->_assertLoggedIn();
    return sp_crawl($_REQUEST['url'], array_key_exists('flush', $_REQUEST));
  }

  function media($id) {
    if ($this->_isGet()) {
      $thumb = wp_get_attachment_image_src($id, 'thumbnail');
      $full = wp_get_attachment_image_src($id, 'full');
      
      return (object) array(
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

    } else {
      $this->_assertLoggedIn();
    }
  }

  function preview() {
    $this->_assertLoggedIn();
    
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

    if (!$client = buf_get_client($service)) {
      return false;
    }

    if (is_wp_error($profile = $client->profile())) {
      return $profile;
    }

    if ($profile === false) {
      return wp_redirect( $client->loginUrl() );
    } else if (is_wp_error($profile = buf_update_profile($profile))) {
      return $profile;
    } 

    if (empty($_REQUEST['redirect_uri'])) {
      return $profile->toJSON();
    }

    if (!$host = parse_url($_REQUEST['redirect_uri'], PHP_URL_HOST)) {
      wp_redirect( admin_url($_REQUEST['redirect_uri']) );
    } else {
      wp_redirect( $_REQUEST['redirect_uri'] );
    }
  }

  function _addUpdateActions(&$update) {
    if ($this->_isAdmin() || $update->user_id === get_current_user_id()) {
      $update->actions = array(
        'update' => site_url('/sp/1/updates/'.$update->id.'/update'),
        'delete' => site_url('/sp/1/updates/'.$update->id.'/destroy')
      );
    }
  }

  function _addProfileActions(&$profile) {
    $profile->actions = (object) array(
      'test' => site_url('/sp/1/profiles/'.$profile->id.'/test?_method=post'),
      'profiles' => site_url('/sp/1/profiles/'.$profile->id.'/profiles'),
      'delete' => site_url('/sp/1/profiles/'.$profile->id.'?_method=delete'),
      'schedules' => site_url('/sp/1/profiles/'.$profile->id.'/schedules'),
      'updates' => (object) array(
        'create' => site_url('/sp/1/updates/create?profile_ids[]='.$profile->id.'&text='),
        'pending' => site_url('/sp/1/profiles/'.$profile->id.'/updates/pending'),
        'sent' => site_url('/sp/1/profiles/'.$profile->id.'/updates/sent'),
        'errors' => site_url('/sp/1/profiles/'.$profile->id.'/updates/errors')
      )
    );
  }

  function profiles($id = null, $action = false, $update = false) {
    $this->_assertLoggedIn();
    
    if (!$id) {
      // create a new profile
      if ($this->_isPost()) {
        unset($_REQUEST['id']);
        return buf_update_profile($_REQUEST);
      // get profiles for current user
      } else {
        if (!$this->_isAdmin()) {
          $_GET['user_id'] = get_current_user_id();
        }
        $profiles = buf_get_profiles($_GET);
        if ($this->_isAdmin()) {
          array_map(array($this, '_addProfileActions'), $profiles);
        }
        return $profiles;
      }

    } else {
      // look up requested profile
      $profile = buf_get_profile($id);
      if ($profile->user_id != get_current_user_id()) {
        if (!$this->_isAdmin() && !in_array(get_current_user_id(), $profile->team_members)) {
          return new WP_Error('access-denied', "You are not allowed to post to this Profile [{$profile_id}]");
        }
      }

      if (!$action) {
        // update profile
        if ($this->_isPut()) {

        // delete profile
        } else if ($this->_isDelete()) {
          return array('success' => buf_delete_profile($profile));
        // load profile data
        } else {
          return $profile->toJSON();
        }
      }

      if ($action === 'team_members') {
        if ($update === 'add') {
          foreach($_REQUEST['user_ids'] as $user_id) {
            buf_add_team_member($profile, $user_id);
          }
        } else if ($update === 'remove') {
          foreach($_REQUEST['user_ids'] as $user_id) {
            buf_remove_team_member($profile, $user_id);
          }
        }
        return buf_get_profile($profile->id)->toJSON();
      }

      // lookup subprofiles for this profile
      if ($action === 'profiles') {
        $client = buf_get_client($profile);
        $profiles = array();

        if ($source = $client->profiles()) {
          foreach($source as $profile) {
            // does the profile already exist?
            if ($exists = buf_get_profile($profile)) {
              $profile = $exists->toJSON();

            // otherwise, if admin, add create URL for debugging
            } else if ($this->_isAdmin()) {
              $args = (array) $profile;
              $args['_method'] = 'post';
              $profile->actions = array(
                'create' => site_url('/sp/1/profiles?').http_build_query($args)
              );
            }
            $profiles[] = $profile;
          }     
        }

        return $profiles;
      }

      if ($action === 'schedules') {
        // update the schedules
        if ($update || $this->_isPut()) {
          $profile = buf_update_profile(array(
            'service' => $profile->service,
            'service_id' => $profile->service_id,
            'schedules' => $_REQUEST['schedules']
          ));
          if (is_wp_error($profile)) {
            return $profile;
          }
          return array('success' => true);
        } else {
          return $profile->schedules ? $profile->schedules : array();
        }        
      }

      if ($action === 'updates') {
        if ($update === 'reorder') {
          if (is_wp_error($result = buf_update_buffer($profile, $_REQUEST['order'], $_REQUEST['offset']))) {
            return $result;
          }
          // TODO: consider using offset her to limit size of reply
          $result = buf_get_updates(array('profile_id' => $id));
          array_map(array($this, '_addUpdateActions'), $result->updates);
          return array(
            'success' => true,
            'updates' => $result->updates
          );

        } else {
          $args = $_GET;
          if ($update === 'pending') {
            $args['status'] = 'buffer';
          } else if ($update === 'sent') {
            $args['status'] = 'sent';
          } else if ($update === 'errors') {
            $args['status'] = 'error';
          } else {
            return new WP_Error("Invalid updates query [{$update}]");
          }

          $args['profile_id'] = $id;

          if (is_wp_error($result = buf_get_updates($args))) {
            return $result;
          }
          array_map(array($this, '_addUpdateActions'), $result->updates);
          return $result;
        }
      }

      if ($action === 'test' && $this->_isPost()) {
        $this->_assertIsAdmin();
        $client = buf_get_client($profile);
        return $client->test($_REQUEST['message'], $_REQUEST['url']);
      }
    }
  }

  function updates($id, $action = null) {
    $this->_assertLoggedIn();

    if ($id === 'create') {
      $action = 'create';
      $id = null;
    }

    if ($this->_isPost() || $action === 'create') {
      unset($_REQUEST['id']);
      if (is_wp_error($result = buf_update_update($_REQUEST))) {
        return $result;
      }
      if ($result->updates) {
        array_map(array($this, '_addUpdateActions'), $result->updates);
      }
      return $result;

    } else if ($id && ( $this->_isPut() || $action === 'update' )) {
      $_REQUEST['id'] = $id;
      if (is_wp_error($result = buf_update_update($_REQUEST))) {
        return $result;
      }
      $this->_addUpdateActions($result->update);
      return $result;

    } else if ($id && ( $this->_isDelete() || $action === 'destroy' )) {
      return array('success' => buf_delete_update($id));

    } else if ($id) {
      if (is_wp_error($update = buf_get_update($id))) {
        return $update;
      }
      $this->_addUpdateActions($update);
      return $update->toJSON();
    }
  }

  function debug($fx) {
    $this->_assertLoggedIn();
    
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

  function _assertLoggedIn() {
    if (!is_user_logged_in()) {
      wp_redirect(wp_login_url($_SERVER['RESTfulQUEST_URI']));
      exit;
    }
  }

  function _isAdmin() {
    return buf_current_user_is_admin();
  }

  function _assertIsAdmin() {
    if (!$this->_isAdmin()) {
      wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
      exit;
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
define('SP_API_REWRITE_RULE', '(sp)/(\d)/([a-z]+)(/.*?)?(.json)?$');

function sp_rewrite_rules_array($rules) {
  return array(
    SP_API_REWRITE_RULE => 'index.php?_sp=$matches[3]&_v=$matches[2]&_args=$matches[4]'
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
    $class = "SpApi_v{$wp->query_vars['_v']}";
    if (!class_exists($class)) {
      return;
    }
    $api = new $class();
    $fx = array($api, $wp->query_vars['_sp']);
    if (is_callable($fx)) {
      $result = call_user_func_array($fx, array_filter(explode('/', $wp->query_vars['_args'])));
      header('Content-Type: application/json');
      echo json_encode($result);
      exit(0);
    }  
  }
}