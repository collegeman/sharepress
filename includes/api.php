<?php
// e.g., sp/1/profiles/:id
@define('SP_API_REWRITE_RULE', '(sp)/(\d)/([a-z]+)(/.*?)?(.json)?$');
add_filter('rewrite_rules_array', 'sp_rewrite_rules_array');
add_filter('query_vars', 'sp_query_vars');
add_action(constant('WP_DEBUG') || constant('SP_DEBUG') ? 'wp_loaded' : 'sp_activated', 'sp_flush_rewrite_rules');
add_action('parse_request', 'sp_parse_request');

class SpApi_v1 extends AbstractSpApi {

  function modal() {
    $this->_assertLoggedIn(); 
    $text = '';
    $post = false;

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
    } else if (!empty($_REQUEST['post_id'])) {
      if (!$post = get_post($_REQUEST['post_id'])) {
        $text = "Oops! I couldn't find that post.";
      } else {
        if (is_wp_error($short = sp_shorten($url = site_url('?p='.$post->ID)))) {
          $text = 'Oops! I failed to shorten your URL. '.$url;
        } else {
          $text = get_the_title($post->ID).' '.$short;
        }
      }
    }

    include(SP_DIR.'/views/modal.php');
    exit;
  }

  function cron() {
    ignore_user_abort(true);

    $local_time = microtime( true );

    $doing_cron_transient = get_transient( 'sp_doing_cron');

    // Use global $doing_wp_cron lock otherwise use the GET lock. If no lock, trying grabbing a new lock.
    if ( empty( $doing_wp_cron ) ) {
      if ( empty( $_GET[ 'sp_doing_cron' ] ) ) {
        // Called from external script/job. Try setting a lock.
        if ( $doing_cron_transient && ( $doing_cron_transient + WP_CRON_LOCK_TIMEOUT > $local_time ) )
          return;
        $doing_cron_transient = $doing_wp_cron = sprintf( '%.22F', microtime( true ) );
        set_transient( 'sp_doing_cron', $doing_wp_cron );
      } else {
        $doing_wp_cron = $_GET[ 'sp_doing_cron' ];
      }
    }

    // Check lock
    if ( $doing_cron_transient != $doing_wp_cron )
      return;

    sp_post_pending();

    if ( get_transient('sp_doing_cron') == $doing_wp_cron ) {
      delete_transient( 'sp_doing_cron' );
    }
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

  function auth($service, $action = false) {
    if (!$service = trim($service)) {
      return false;
    }

    if (!$client = sp_get_client($service)) {
      return false;
    }

    $config = $action === 'config';
    $profiles = $action === 'profiles';

    if (is_wp_error($client)) {
      if ($client->get_error_code() === 'keys') {
        $config = true;
      } else {
        return $client;
      }
    }

    if ($config || isset($_POST['config'][$service])) {
      if (apply_filters('sp_show_settings_screens', true, $service)) {
        return wp_redirect(admin_url('admin.php?page=sp-settings&sp_service='.$service));
      } else {
        // TODO: redirect or display error?
      }
      exit;
    }
    
    if (is_wp_error($profile = $client->profile())) {
      return $profile;
    }

    $redirect_uri = site_url('/sp/1/auth/'.$service.($action ? '/'.$action : ''));
    if (!empty($_REQUEST['redirect_uri'])) {
      $redirect_uri .= '?' . http_build_query(array('redirect_uri' => $_REQUEST['redirect_uri']));
    }

    if ($profile === false) {
      if (is_wp_error($login_url = $client->loginUrl($redirect_uri))) {
        if ($profiles) {
          sp_flash('error', $login_url);
        } else {
          return $login_url;
        }
      } else {
        return wp_redirect( $login_url );
      }
    } else if (is_wp_error($profile = sp_update_profile($profile))) {
      // did use request profiles screen? if so, display error message
      if ($profiles) {
        sp_flash('error', $profile);
      // otherwise return error object through API
      } else {
        return $profile;
      }
    } 

    if ($profiles) {
      return wp_redirect(admin_url('admin.php?page=sp-settings&sp_service='.$service.'&sp_profiles=true'));
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

  function _sanitizeProfile($profile) {
    return $profile->toJSON();
  }

  function _addProfileActions(&$profile) {
    $profile = (object) $profile;
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
    return $profile;
  }

  function profiles($id = null, $action = false, $update = false) {
    $this->_assertLoggedIn();
    
    if (!$id) {
      // create a new profile
      if ($this->_isPost()) {
        unset($_REQUEST['id']);
        return sp_update_profile($_REQUEST);
      // get profiles for current user
      } else {
        if (!$this->_isAdmin()) {
          $_GET['user_id'] = get_current_user_id();
        }
        $profiles = array_map(array($this, '_sanitizeProfile'), sp_get_profiles($_GET));
    
        if ($this->_isAdmin()) {
          $profiles = array_map(array($this, '_addProfileActions'), $profiles);
        }
        return $profiles;
      }

    } else {
      // look up requested profile
      $profile = sp_get_profile($id);
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
          return array('success' => sp_delete_profile($profile));
        // load profile data
        } else {
          return $profile->toJSON();
        }
      }

      if ($action === 'team_members') {
        if (!empty($_REQUEST['user_ids'])) {
          if ($update === 'add') {
            foreach($_REQUEST['user_ids'] as $user_id) {
              sp_add_team_member($profile, $user_id);
            }
          } else if ($update === 'remove') {
            foreach($_REQUEST['user_ids'] as $user_id) {
              sp_remove_team_member($profile, $user_id);
            }
          }
        }
        return sp_get_profile($profile->id)->team_members;
      }

      // lookup subprofiles for this profile
      if ($action === 'profiles') {
        $client = sp_get_client($profile);
        $profiles = array();

        if ($children = $client->profiles()) {
          foreach($children as $child) {
            // does the profile already exist?
            if ($exists = sp_get_profile($child)) {
              $child = $exists->toJSON();

            // otherwise, if admin, add create URL for debugging
            } else if ($this->_isAdmin()) {
              $args = array(
                'parent' => $profile->id,
                'service_id' => $child->service_id,
                '_method' => 'post'
              );
              $child->actions = array(
                'create' => site_url('/sp/1/profiles?').http_build_query($args)
              );
            }
            $profiles[] = $child;
          }     
        }

        return $profiles;
      }

      if ($action === 'schedules') {
        // update the schedules
        if ($update || $this->_isPut()) {
          $profile = sp_update_profile(array(
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
          if (is_wp_error($result = sp_update_buffer($profile, $_REQUEST['order'], $_REQUEST['offset']))) {
            return $result;
          }
          // TODO: consider using offset her to limit size of reply
          $result = sp_get_updates(array('profile_id' => $id));
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

          if (is_wp_error($result = sp_get_updates($args))) {
            return $result;
          }
          array_map(array($this, '_addUpdateActions'), $result->updates);
          return $result;
        }
      }

      if ($action === 'test' && $this->_isPost()) {
        $this->_assertIsAdmin();
        $client = sp_get_client($profile);
        return $client->test(SP_TEST_MESSAGE, SP_TEST_URL);
      }
    }
  }

  function update($id = null, $action = null) {
    $this->_assertLoggedIn();
    if (is_wp_error($result = $this->updates($id, $action))) {
      return $result;
    } else {
      return $result->updates[0];
    }
  }

  function updates($id = null, $action = null) {
    $this->_assertLoggedIn();

    if ($id === 'create') {
      $action = 'create';
      $id = null;
    }

    if ($this->_isPost() || $action === 'create') {
      unset($_REQUEST['id']);
      if (is_wp_error($result = sp_update_update($_REQUEST))) {
        return $result;
      }
      if ($result->updates) {
        array_map(array($this, '_addUpdateActions'), $result->updates);
      }
      return $result;

    } else if ($id && ( $this->_isPut() || $action === 'update' )) {
      $_REQUEST['id'] = $id;
      if (is_wp_error($result = sp_update_update($_REQUEST))) {
        return $result;
      }
      $this->_addUpdateActions($result->update);
      return $result;

    } else if ($id && ( $this->_isDelete() || $action === 'destroy' )) {
      return array('success' => sp_set_error_status($id));

    } else if ($id === 'queue') {
      if (!empty($action)) {
        $_REQUEST['post_id'] = $action;
        if (is_wp_error($result = sp_get_updates($_REQUEST))) {
          return $result;
        }
        array_map(array($this, '_addUpdateActions'), $result->updates);
        return $result->updates;
      }

    } else if ($id === 'history') {
      if (empty($action)) {
        return new WP_Error("Request missing Post ID");
      }

    } else if ($id) {
      if (is_wp_error($update = sp_get_update($id))) {
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

  function metadata($post_id) {
    $this->_assertLoggedIn();
    if ( !get_post($post_id) ) {
      return new WP_Error("No post with that ID");
    }
    if ( $this->_isGet() ) {
      $socialmeta = get_post_meta($post_id, 'socialmeta', true);
      if ( $socialmeta ) {
        $post_thumbnail_id = get_post_thumbnail_id( $post_id );
        $socialmeta['image'] = ( !empty($socialmeta['image']) ) ? $socialmeta['image'] : ( ( !empty($post_thumbnail_id) ) ? $this->media($post_thumbnail_id) : false );
      }
      return $socialmeta;
    }
    $socialmeta = array(
      'title' => $_REQUEST['title'],
      'image' => $_REQUEST['image'],
      'description' => $_REQUEST['description']
    );
    return update_post_meta($post_id, 'socialmeta', $socialmeta);
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
      wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
      exit;
    }
  }

  function _isAdmin() {
    return sp_current_user_is_admin();
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
      if ($in = json_decode(file_get_contents('php://input'))) {
        foreach($in as $key => $value) {
          $_REQUEST[$key] = $value;
        }
      }
      $result = call_user_func_array($fx, array_filter(explode('/', $wp->query_vars['_args'])));
      header('Content-Type: application/json');
      echo json_encode($result);
      exit(0);
    }  
  }
}