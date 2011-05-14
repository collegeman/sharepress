<?php
/*
getwpapps.com Plugin Update Client
Copyright (C)2010-2011  Fat Panda, LLC

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

if (!class_exists('PluginUpdateClient')): 

  class PluginUpdateClient {
  
    private static $one_time = false;
    private static $plugins = array();
  
    const OPTION_LICENSE_KEY = '_license-key-%s';
    const OPTION_LAST_CHECKED = '_last-checked-for-upgrade-%s';
    const OPTION_NEEDS_UPGRADE = '_needs-upgrade-%s';
    const LAST_CHECKED_INTERVAL = 86400; // 24 hours
  
    static function init($args = '') {
      extract(wp_parse_args($args));
      
      if ($path) {
        $meta = self::get_file_data($path, array('Name' => 'Plugin Name'));
        $name = $meta['Name'];
      }
      
      if (!$name) {
        wp_die("[name] arg to PluginUpdateClient::init should be a descriptive name for your plugin, e.g., &quot;My Plugin&quot;");
      }
      
      if (!$plugin) {
        wp_die("[plugin] arg to PluginUpdateClient::init should be the URL slug of your plugin, e.g., &quot;my-plugin&quot;");
      }
      
      if (!$file) {
        wp_die("[file] arg to PluginUpdateClient::init should specify the plugin to be updated, e.g., my-plugin/my-plugin.php");
      }
      
      if (!$server) {
        $server = 'http://getwpapps.com';
      }
      
      if (!self::$one_time) {
        if (is_admin()) {
          $dir_path = explode(DIRECTORY_SEPARATOR, $file);
          array_pop($dir_path);
          $dir_path = implode(DIRECTORY_SEPARATOR, $dir_path);
        
          add_action('admin_head', array('PluginUpdateClient', 'admin_head'));
          add_action('wp_ajax_plugin_update_get_key', array('PluginUpdateClient', 'ajax_get_key'));
          add_action('wp_ajax_plugin_update_set_key', array('PluginUpdateClient', 'ajax_set_key'));
          wp_enqueue_script('update-client', plugins_url("{$dir_path}/update-client.js"), array('jquery'));
        }
        
        self::$one_time = true;
      }

      $client = new PluginUpdateClient($name, $plugin, $file, $server);
    }
    
    static function ajax_get_key() {
      if (current_user_can('administrator')) {
        echo get_option(sprintf(self::OPTION_LICENSE_KEY, $_POST['plugin']), '');
      }
      exit;
    }
    
    static function ajax_set_key() {
      if (current_user_can('administrator')) {
        update_option(sprintf(self::OPTION_LICENSE_KEY, $_POST['plugin']), trim($_POST['key']));
        exit;
      } else {
        echo 'You are not allowed to do that.';
      }
    }
    
    private $name;
    private $plugin;
    private $version;
    private $file;
    private $server;
  
    private function __construct($name, $plugin, $file, $server) {
      $this->name = $name;
      $this->plugin = $plugin;
      $this->file = $file;
      $this->server = $server;
      
      // extract version from the file
      if (!file_exists($path = WP_PLUGIN_DIR.'/'.$file)) {
        wp_die("Plugin file [$file] does not exist");
      } else {
        $data = self::get_file_data($path, array('Version' => 'Version'));
        $this->version = $data['Version'];
      }

      self::$plugins[md5($this->file)] = $this;
      
      add_filter('plugins_api', array($this, 'plugins_api'), 10, 3);
      add_filter('site_transient_update_plugins', array($this, 'site_transient_update_plugins'));
      add_filter(sprintf('plugin_action_links_%s', $this->file), array($this, 'plugin_action_links'), 10, 4);
    }
    
    function plugins_api($bool, $action, $args) {
      if ($action == 'plugin_information' && $args->slug == $this->plugin) {
        // see wp-admin/includes/plugin-install.php@487
        return (object) array(
          'author' => '',
          'requires' => 3.0,
          'tested' => 3.0,
          'last_updated' => '2011/03/01',
          'sections' => array(
            'Description' => 'Foo'
          )
        );
      } else {
        return false;
      }
    }
    
    static function admin_head() {
      ?>
        <style>
          .plugin-update-onion { display:none; z-index:50000; position:fixed; top:0; left:0; width:100%; height:100%; background-color: black; opacity: 0.7; filter:alpha(opacity=70); }
          .plugin-update-key-editor-container { display:none; width:100%; position:absolute; z-index:50001 }
          .plugin-update-key-editor { -moz-border-radius: 10px; -webkit-border-radius: 10px; border-radius: 10px; display:none; position:relative; background-color:white; width:410px; height:230px; margin:0 auto; }
          .plugin-update-key-editor h2 { padding: 0; margin: 0; font: italic normal normal 24px/29px Georgia, 'Times New Roman', 'Bitstream Charter', Times, serif; }
          .plugin-update-key-editor .content { font-weight: normal; padding: 25px; }
          .plugin-update-key-editor .buttons { -moz-border-radius-bottomright: 10px; -moz-border-radius-bottomleft: 10px; -webkit-border-radius-bottomleft: 10px; -webkit-border-radius-bottomright: 10px; border-bottom-right-radius: 10px; border-bottom-left-radius: 10px; position: absolute; bottom: 0px; background-color: #ddd; width: 360px; padding: 15px 25px; }
          .plugin-update-key-editor .buttons a { position:relative; top:-1px; font-size: 13px !important; }
          .plugin-update-key-editor .buttons .cancel { margin-left: 10px; }
          .plugin-update-key-editor label { font-weight: normal; color: #ccc; text-transform: uppercase; font-size: 12px; display: block; margin-bottom: 5px; }
          .plugin-update-key-editor input.text { font-size: 18px; padding: 2px; width: 355px; }
        </style>
        
        <div class="plugin-update plugin-update-onion"></div>
        <div class="plugin-update plugin-update-key-editor-container">
          <div class="plugin-update plugin-update-key-editor">
            <form action="#" onsubmit="PluginUpdate.setKey(jQuery(this)); return false;">
              <input type="hidden" name="plugin" value="" />
              <div class="content">
                <h2 class="name"></h2>
                <p>To activate some features of this plugin, including automatic updates, you must enter your 
                  <a href="#" class="server" target="_blank" title="Learn more about this software, and purchase a license key">license key</a> below.</p>
                <p>
                  <label for="plugin-update-key">License Key</label>
                  <input type="text" class="text" name="key" id="plugin-update-key" value="" />
                </p>
              </div>
              <div class="buttons">
                <a href="#" class="button" onclick="PluginUpdate.setKey(jQuery(this).closest('form')); return false;">Save</a>
                <a href="#" class="cancel" onclick="PluginUpdate.cancel(); return false;">Cancel</a>
              </div>
            </form>
          </div>
        </div>
      <?php
    }
    
    function plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
      $plugin = md5($plugin_file);
      $is_set = get_option(sprintf(self::OPTION_LICENSE_KEY, $plugin), false);
      
      $actions['settings'] = sprintf(
        '<a href="#" onclick="PluginUpdate.editKey(\'%s\', \'%s\', \'%s\'); return false;" %s>License Key</a>', 
        preg_replace('/\'/', '&#39;', htmlentities($this->name)), 
        $plugin, 
        $this->server, 
        (!$is_set ? 'style="color:red !important;"' : '')
      );
      
      return $actions;
    }
    
    function site_transient_update_plugins($upgrades) {
      // if our plugin exists in $upgrades->response, remove it
      unset($upgrades->response[$this->file]);
      
      $plugin = md5($this->file);
      $last_checked = get_option(sprintf(self::OPTION_LAST_CHECKED, $plugin), 0);
      $license = get_option(sprintf(self::OPTION_LICENSE_KEY, $plugin), false);
      
      if ($license && ( !$last_checked || time() - $last_checked >= self::LAST_CHECKED_INTERVAL) ) {
        $feed = "{$this->server}/plugin/{$this->plugin}/feed";
        if (($rss = simplexml_load_string(@file_get_contents($feed))) === false) {
          error_log("Failed to connect to [$feed]");
          update_option(sprintf(self::OPTION_LAST_CHECKED, $plugin), time());
          return $upgrades;
        }
      
        $version = false;
        if ($rss->channel->item && version_compare($this->version, (string) $rss->channel->item->version) == -1) {
          $version = (string) $rss->channel->item->version;
        }
        update_option(sprintf(self::OPTION_NEEDS_UPGRADE, $plugin), $version);
        
        update_option(sprintf(self::OPTION_LAST_CHECKED, $plugin), time());
      }
      
      if ($version = get_option(sprintf(self::OPTION_NEEDS_UPGRADE, $plugin), false)) {
        $upgrades->response[$this->file] = (object) array(
          'new_version' => $version,
          'package' => "{$this->server}/package/{$license}/{$plugin}/{$version}",
          'slug' => $this->plugin,
          'url' => "{$this->server}/plugin/{$this->plugin}"
        );
      }
    
      return $upgrades;
    }
    
    function get_file_data( $file, $default_headers = array(), $context = '' ) {
    	// We don't need to write to the file, so just open for reading.
    	$fp = fopen( $file, 'r' );

    	// Pull only the first 8kiB of the file in.
    	$file_data = fread( $fp, 8192 );

    	// PHP will close file handle, but we are good citizens.
    	fclose( $fp );

    	if ( $context != '' ) {
    		$extra_headers = array_flip( $extra_headers );
    		foreach( $extra_headers as $key=>$value ) {
    			$extra_headers[$key] = $key;
    		}
    		$all_headers = array_merge( $extra_headers, (array) $default_headers );
    	} else {
    		$all_headers = $default_headers;
    	}

    	foreach ( $all_headers as $field => $regex ) {
    		preg_match( '/^[ \t\/*#]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, ${$field});
    		if ( !empty( ${$field} ) )
    			${$field} = self::_cleanup_header_comment( ${$field}[1] );
    		else
    			${$field} = '';
    	}

    	$file_data = compact( array_keys( $all_headers ) );

    	return $file_data;
    }
    
    /**
     * Strip close comment and close php tags from file headers used by WP
     * See http://core.trac.wordpress.org/ticket/8497
     *
     * @since 2.8.0
     *
     * @param string $str
     * @return string
     */
    function _cleanup_header_comment($str) {
    	return trim(preg_replace("/\s*(?:\*\/|\?>).*/", '', $str));
    }
    

  } 

endif;