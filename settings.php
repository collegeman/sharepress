<?php 
/*
sharepress
Copyright (C)2010-2011  Fat Panda LLC

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

if (!defined('ABSPATH')) exit; /* silence is golden */ ?>

<div class="wrap">
  <div id="icon-general" class="icon32" style="background:url('<?php echo plugins_url('sharepress/img/icon32.png') ?>') no-repeat;"><br /></div>
  <?php if (@$_REQUEST['step'] != 'config') { ?>
    <h2 style="border-bottom: 1px solid #ccc; height:43px;">
      <span style="margin-right:40px; position:relative; top:-5px;">Sharepress Setup</span>
      <a href="options-general.php?page=sharepress&amp;step=1" class="nav-tab <?php if (@$_REQUEST['step'] == '1') echo 'nav-tab-active' ?>">Step 1</a>
      <a href="<?php echo (Sharepress::api_key() && Sharepress::app_secret()) ? 'options-general.php?page=sharepress&amp;step=2' : '#' ?>" class="nav-tab <?php if (@$_REQUEST['step'] == '2') echo 'nav-tab-active' ?>">Step 2</a>
    </h2>
  <?php } else { ?>
    <h2 style="border-bottom: 1px solid #ccc; height:43px;">
      <span style="margin-right:40px; position:relative; top:-5px;">Sharepress</span>
      <a href="options-general.php?page=sharepress" class="nav-tab <?php if (@$_REQUEST['action'] != 'upgrade') echo 'nav-tab-active' ?>">Settings</a>
      <a href="options-general.php?page=sharepress&amp;action=clear_session" class="nav-tab">Run Setup Again</a>
      <?php if (!Sharepress::$pro) { ?>
        <a href="http://getwpapps.com/plugins/sharepress" class="nav-tab <?php if (@$_REQUEST['action'] == 'upgrade') echo 'nav-tab-active' ?>" style="float:right;">Upgrade</a>
      <?php } ?>
    </h2>
  <?php } ?>
  <form method="post" action="options.php" id="settings_form">
    
    <?php if (empty($_REQUEST['step']) || $_REQUEST['step'] == '1') { ?>
    
      <?php settings_fields('fb-step1') ?>
      
      <div style="float:left; width:300px; margin-right: 50px;">
        <p>Before you continue, you'll need to create your own Facebook Application. <a href="http://www.facebook.com/developers/createapp.php" target="_blank">Do this now &raquo;</a></p>
        
        <p>If you've never created a Facebook application before, you'll be asked to authorize the <b>Developer</b> application. This is very safe.</p>
        <a href="<?php echo plugins_url('img/create_app_step1.jpg', __FILE__); ?>"><img src="<?php echo plugins_url('img/create_app_step1_thumb.jpg', __FILE__) ?>" style="border: 1px solid #ccc;" /></a>
        
        <p>To match your blog, you should consider naming your application <b><?php bloginfo('name') ?></b>.</p>
        <a href="<?php echo plugins_url('img/create_app_step2.jpg', __FILE__); ?>"><img src="<?php echo plugins_url('img/create_app_step2_thumb.jpg', __FILE__) ?>" style="border: 1px solid #ccc;" /></a>
        
        <p>Your Site URL is <b><?php echo preg_replace('#/+$#', '/', get_option('siteurl').'/') ?></b>, and your domain is <b><?php $url = parse_url(get_option('siteurl')); echo $url['host'] ?></b>.</p>
        <a href="<?php echo plugins_url('img/create_app_step3.jpg', __FILE__); ?>"><img src="<?php echo plugins_url('img/create_app_step3_thumb.jpg', __FILE__) ?>" style="border: 1px solid #ccc;" /></a>
      </div>
      
      <br />
      <table class="form-table" style="width:500px; float:left; clear:none;">
        <tr>
          <th>API Key</th>
          <td><input type="text" style="width:25em;" id="<?php echo Sharepress::OPTION_API_KEY ?>" name="<?php echo Sharepress::OPTION_API_KEY ?>" value="<?php echo htmlentities(Sharepress::api_key()) ?>" /></td>
        </tr>
        <tr>
          <th>Application Secret</th>
          <td><input type="text" style="width:25em;" id="<?php echo Sharepress::OPTION_APP_SECRET ?>" name="<?php echo Sharepress::OPTION_APP_SECRET ?>" value="<?php echo htmlentities(Sharepress::app_secret()) ?>" /></td>
        </tr>
        <tr>
          <td></td>
          <td>
            <p class="submit">
              <input id="btnContinue" type="submit" name="Submit" class="button-primary" value="Continue &raquo;" disabled="disabled" />
            </p>
          </td>
        </tr>
      </table>
      
      <script>
        (function($) {
          var api_key = $('#<?php echo Sharepress::OPTION_API_KEY ?>').focus();
          var app_secret = $('#<?php echo Sharepress::OPTION_APP_SECRET ?>');
          setInterval(function() {
            if (api_key.val() && app_secret.val()) {
              $('#btnContinue').attr('disabled', '');
            } else {
              $('#btnContinue').attr('disabled', 'disabled');
            }
          }, 100);
        })(jQuery);
      </script>
      
      
    <?php } elseif (@$_REQUEST['step'] == '2') { ?> 
      
      <div id="fb-root"></div>
      <script>
        window.fbAsyncInit = function() {
          FB.init({
            appId: '<?php echo Sharepress::api_key() ?>', 
            status: true, 
            cookie: true
          });
        };
        
        (function() {
          var e = document.createElement('script'); e.async = true;
          e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
          document.getElementById('fb-root').appendChild(e);
        }());
        
        (function($) {
          function do_with_session(session, dont_retry) {
            if (session) {
              // do we have the permissions we need?
              var q = FB.Data.query("SELECT read_stream, publish_stream, offline_access, manage_pages FROM permissions WHERE uid = {0}", session.uid);
              // wait for the query to load
              q.wait(function(rows) {
                // check the permissions
                if (rows[0].offline_access != '1' || rows[0].publish_stream != '1' || rows[0].read_stream != '1' || rows[0].manage_pages != '1') {
                  // unless we're not allowed to retry
                  if (!dont_retry) {
                    // try logging in again
                    FB.login(function(response) {
                      // but this time, don't allow a retry
                      do_with_session(response.session, true);
                    }, {perms: 'read_stream,publish_stream,offline_access,manage_pages'});
                  } else {
                    // didn't have the permissions, and not allowed to retry
                    $('#fb_connect p').hide();
                    $('#fb_connect .connect').show();
                  }
                } else {
                  // connected! right on
                  $('#fb_connect p').hide();
                  $('#fb_connect .connected').show();
                  // post session data to the back-end and then redirect to the settings screen
                  setTimeout(function() {
                    $.post(ajaxurl, { action: 'fb_save_session', session: session }, function(response) {
                      document.location = '<?php echo get_option('siteurl') ?>/wp-admin/options-general.php?page=sharepress';
                    });
                  }, 1000);
                }
              });
            } else {
              // didn't get a session...
              $('#fb_connect p').hide();
              $('#fb_connect .connect').show();
            }
          }
          
          window.fb_connect = function() {
            $('#fb_connect .connect').hide();
            $('#fb_connect .connecting').show();
            FB.getLoginStatus(function(response) {
              if (!response.session) {
                FB.login(function(response) {
                  do_with_session(response.session);
                }, {perms: 'read_stream,publish_stream,offline_access,manage_pages'});
              } else {
                do_with_session(response.session);
              }
            });
          }
        })(jQuery);
      </script> 
      
      <?php settings_fields('fb-step2') ?>
      
      <div style="float:left; width:300px; margin-right: 50px;">
        <p>Next, you need to connect to your Facebook Application! Click <b>Connect to Facebook</b> to begin.
          <b>Don't forget to turn off pop-up blockers.</b></p>
      </div>
      
      <br />
      <table class="form-table" style="width:500px; float:left; clear:none;">
        <tr>
          <th></th>
          <td id="fb_connect">
            <p class="connect"><a href="#" onclick="fb_connect();" style="display:block; padding-left:22px; background:url('<?php echo plugins_url('img/icon16.png', __FILE__) ?>') no-repeat;">Connect to Facebook</a></p>
            <p class="connecting" style="display:none;"><img src="<?php echo plugins_url('img/wait.gif', __FILE__) ?>" /> Connecting...</p>
            <p class="connected" style="display:none;">Connected!</p>
          </td>
        </tr>
      </table>
      
    <?php } else { ?>
      
      <?php settings_fields('fb-settings') ?>
      
      <h3 class="title">Sharepress Settings</h3>
      
      <table class="form-table">
        <tr>
          <th>Default behavior</th>
          <td>
            <div style="margin-bottom:5px;">
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[default_behavior]" value="on" <?php if (Sharepress::setting('default_behavior') == 'on') echo 'checked="checked"' ?> />
                Send all of my Posts to Facebook
              </label>
            </div>
            <div>
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[default_behavior]" value="off" <?php if (Sharepress::setting('default_behavior') == 'off') echo 'checked="checked"' ?> />
                Send to Facebook only those Posts I tell you to
              </label>
            </div>
          </td>
        </tr>
          
        <tr>
          <th>
            <label for="<?php echo self::OPTION_SETTINGS ?>_excerpt_length">Description max length</label>
          </th>
          <td>
            <input style="width:3em;" type="text" id="<?php echo self::OPTION_SETTINGS ?>_excerpt_length" name="<?php echo self::OPTION_SETTINGS ?>[excerpt_length]" value="<?php echo htmlentities(Sharepress::setting('excerpt_length')) ?>" />
            &nbsp;Maximum number of words to include in automatically generated Descriptions
          </td>
        </tr>
        
        <tr>
          <th>
            <label for="<?php echo self::OPTION_SETTINGS ?>_excerpt_more">Description suffix</label>
          </th>
          <td>
            <input style="width:3em;" type="text" id="<?php echo self::OPTION_SETTINGS ?>_excerpt_more" name="<?php echo self::OPTION_SETTINGS ?>[excerpt_more]" value="<?php echo htmlentities(Sharepress::setting('excerpt_more')) ?>" />
            &nbsp;Symbol(s) to include at the end of <strong>automatically generated</strong> Descriptions, when longer than max length (above)
          </td>
        </tr>
      
      </table>
      
      <br />
      <h3 class="title">Facebook Open Graph Tags</h3>
      
      <h4 class="title">For individual blog posts</h4>
      
      <p>You can configure <code>og:type</code> meta data on a post-by-post basis.
        Set the default here.</p>
        
      <table class="form-table">
        <tr>
          <th>Default OG:TYPE for posts</th>
          <td>
            <label for="sharepress_post_og_type"><code>og:type</code>&nbsp;=</label></label>
            <select id="sharepress_post_og_type" name="<?php echo self::OPTION_SETTINGS ?>[default_post_og_type]">
              <?php require('og-types.php') ?>
            </select>
            <script>
              (function($) {
                $('option[value="<?php echo Sharepress::setting('default_post_og_type', 'article') ?>"]', $('#sharepress_post_og_type')).attr('selected', true);
              })(jQuery);
            </script>
          </td>
        </tr>
      </table>
      
      <h4 class="title">For your other pages</h4>
      
      <p>
        Optionally, sharepress can add Facebook open graph tags to your other pages.
      </p>
      <p> 
        If your theme already does this, you can safely disable this feature of Sharepress.
      </p>
      <p>
        If you want to test the way your pages appears to Facebook, you can do so with 
        Facebook's <a href="http://developers.facebook.com/tools/lint/?url=<?php urlencode(bloginfo('siteurl')) ?>" target="_blank">URL Linter</a>.
      </p>
      
      <table class="form-table">
        <tr>
          <th>OG:TYPE for your pages</th>
          <td>
            <div style="margin-bottom:5px;">
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[page_og_tags]" value="on" <?php if (Sharepress::setting('page_og_tags') != 'off') echo 'checked="checked"' ?> />
                Let Sharepress insert it
              </label>
              
              <span style="margin-left:50px;">
                <label for="sharepress_home_og_type" style="cursor:help;" title="Select the Content Type that best expresses the content of your site"><code>og:type</code>&nbsp;=</label>
                <select id="sharepress_home_og_type" name="<?php echo self::OPTION_SETTINGS ?>[page_og_type]">
                  <?php require('og-types.php') ?>
                </select>
                <script>
                  (function($) {
                    $('option[value="<?php echo Sharepress::setting('page_og_type', 'blog') ?>"]', $('#sharepress_home_og_type')).attr('selected', true);
                  })(jQuery);
                </script>
              </span>
            </div>
            <div>
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[page_og_tags]" value="off" <?php if (Sharepress::setting('page_og_tags') == 'off') echo 'checked="checked"' ?> />
                  My Theme does this for me
              </label>
            </div>
          </td>
        </tr>
      </table>
      
      <br />
      <h3 class="title">Default Publishing Targets</h3>
      
      <p>
        When you publish a new post, where should we announce it?
        <?php if (Sharepress::$pro) { ?>
          You'll be able to change this for each post: these are just the defaults.
        <?php } else { ?>
          If you <a href="http://getwpapps.com/plugins/sharepress">upgrade to Sharepress Pro</a>, you can also choose to post to your Facebook pages.
        <?php } ?>
         
      <div style="max-height: 365px; overflow:auto; border:1px solid #ccc;">
        <table class="widefat post fixed" cellspacing="0">
          <thead>
           	<tr>
           	  <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox"></th>
           	  <th scope="col" id="title" class="manage-column column-title" style="">Target</th>
           	</tr>
          </thead>

          <tfoot>
           	<tr>
           	  <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox"></th>
           	  <th scope="col" id="title" class="manage-column column-title" style="">Target</th>
            </tr>
         	</tfoot>

          <tbody>
           	<!-- our blog owner's wall -->
           	<tr id="" class="alternate">
              <th scope="row" class="check-column">
                <input type="checkbox" name="sharepress_publishing_targets[wall]" value="1" <?php if (Sharepress::targets('wall')) echo 'checked="checked"' ?>>
              </th>
              <td><a target="_blank" href="http://facebook.com/profile.php?id=<?php echo Sharepress::me('id') ?>">
                <?php echo (preg_match('/s$/i', trim($name = Sharepress::me('name')))) ? $name.'&apos;' : $name.'&apos;s' ?> Wall</a></td>
            </tr>
            <!-- /blog owner's wall -->
          
            <!-- all of the blog owner's pages -->
            <?php foreach(Sharepress::pages() as $i => $page) { ?>
              <tr class="<?php if ($i % 2) echo 'alternate' ?>">
                <th scope="row" class="check-column">
                  <input type="checkbox" name="sharepress_publishing_targets[<?php echo $page['id'] ?>]" value="1" <?php if (Sharepress::targets($page['id'])) echo 'checked="checked"' ?>>
                </th>
                <td><a target="_blank" href="http://facebook.com/profile.php?id=<?php echo $page['id'] ?>"><?php echo $page['name'] ?></a></td>
              </tr>
            <?php } ?>
          </tbody>
        
        </table>
      </div>
      
      <br />
      <h3 class="title">Notifications</h3>
      
      <p>Sharepress can e-mail you when errors and/or successes in posting to Facebook happen.</p>
      
      <table class="form-table">
        <tr>
          <th>When errors happen:</th>
          <td>
            <label>
              <input type="checkbox" id="notify_on_error" onclick="if (this.checked) jQuery('#on_error_email').focus();" name="<?php echo Sharepress::OPTION_NOTIFICATIONS ?>[on_error]" <?php if (Sharepress::notify_on_error()) echo 'checked="checked"' ?> value="1" />
              Send an e-mail to:
            </label>
            <input style="width:25em;" type="text" id="on_error_email" name="<?php echo Sharepress::OPTION_NOTIFICATIONS ?>[on_error_email]" value="<?php echo htmlentities(Sharepress::get_error_email()) ?>" />
            <div style="color:red; display:none;" id="on_error_email_error">Please use a valid e-mail address</div>
          </td>
        </tr>
        
        <tr>
          <th>When successes happen:</th>
          <td>
            <label>
              <input type="checkbox" id="notify_on_success" onclick="if (this.checked) jQuery('#on_success_email').focus();" name="<?php echo Sharepress::OPTION_NOTIFICATIONS ?>[on_success]" <?php if (Sharepress::notify_on_success()) echo 'checked="checked"' ?> value="1" />
              Send an e-mail to:
            </label>
            <input style="width:25em;" type="text" id="on_success_email" name="<?php echo Sharepress::OPTION_NOTIFICATIONS ?>[on_success_email]" value="<?php echo htmlentities(Sharepress::get_success_email()) ?>" />
            <div style="color:red; display:none;" id="on_success_email_error">Please use a valid e-mail address</div>
          </td>
        </tr>
      </table>  
      
      <p class="submit">
        <input id="btnSaveSettings" class="button-primary" value="Save Settings" type="submit" />
      </p>
      
      <script>
        (function($) {
          $(function() {
            var on_error_email = $('#on_error_email');
            var on_success_email = $('#on_success_email');
            $('#settings_form').submit(function() {
              var valid = true;
              var email = /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i;
              
              if ($('#notify_on_error:checked').size() && on_error_email.val() && !$.trim(on_error_email.val()).match(email)) {
                valid = false;
                $('#on_error_email_error').show();
              } else {
                $('#on_error_email_error').hide();
              }
              
              if ($('#notify_on_success:checked').size() && on_success_email.val() && !$.trim(on_success_email.val()).match(email)) {
                valid = false;
                $('#on_success_email_error').show();
              } else {
                $('#on_success_email_error').hide();
              }
              
              return valid;
            });
          });
        })(jQuery);
      </script>
      
    <?php } ?>
    
  </form>
  
  <?php if (@$_REQUEST['step'] == 'config') { ?>
  
    <?php if (Sharepress::$pro) { ?>
      <br />
      <h3 class="title">Default Picture</h3>
  
      <p>Each message posted to Facebook can be accompanied by a picture. You can set the default below.</p>
      
      <table class="form-table">
        <tr>
          <th>Default picture:</th>
          <td>
            <?php PostImage::ui('sharepress', Sharepress::OPTION_DEFAULT_PICTURE, null, 90, 90, Sharepress::load()->get_default_picture()) ?>
          </td>
        </tr>
      </table>
    <?php } ?>
    
    <br />
    <h3 class="title">Clear Cache</h3>
  
    <p>Most of our conversations with the Facebook API are cached into your WordPress blog. To clear this cache, click the button below.
      Don't worry - your history and posts are safe!</p>
  
    <a id="btnClearCache" href="options-general.php?page=sharepress&amp;action=clear_cache" class="button" onclick="jQuery(this).addClass('disabled');">Clear Cache</a>
  
  <?php } else { ?>
    <div style="clear:left; border-bottom:1px solid #ccc; padding-top:20px;"></div>
  <?php } ?>
  
  <br /><br /><br />
  
</div>