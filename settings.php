<?php  if (!defined('ABSPATH')) exit; /* silence is golden */ ?>

<style>
.wrap h2 span { font-size: 0.75em; padding-left: 20px; }
</style>

<div class="wrap">

  <div id="icon-general" class="icon32" style="background:url('<?php echo plugins_url('img/icon32.png', __FILE__) ?>') no-repeat;"><br /></div>
  <h2>
    SharePress
    <span>a WordPress plugin from <a href="http://aaroncollegeman.com/fatpanda/" target="_blank">Fat Panda</a></span>
  </h2>
  
  <form method="post" action="options.php" id="settings_form">
    
    <?php if (!self::session()) { ?>

      <?php if (!defined('SHAREPRESS_MU_SHARED_ACCESS_TOKEN') || !SHAREPRESS_MU_SHARED_ACCESS_TOKEN) { ?>
    
        <?php settings_fields('fb-step1') ?>
        
        <h3 class="title">Facebook Application</h3>
        
        <?php if (!defined('SHAREPRESS_MU') || !SHAREPRESS_MU || current_user_can('manage_network')) { ?>

          <p>
            Before you continue, you'll need to create your own Facebook Application. 
            <a href="http://www.facebook.com/developers/createapp.php" target="_blank">Do this now</a>.
            <span>( <a href="#" onclick="jQuery('#sharepress_help').show(); jQuery(this).parent().hide(); return false;">Help me</a>! )</span>
          </p>

          <div id="sharepress_help" style="display:none;">
            <iframe width="480" height="390" src="http://www.youtube.com/embed/pI9IqJFQNF8" frameborder="0" allowfullscreen></iframe>
          </div>

          <p>
            <b>Note:</b> Your Site URL is <b><?php echo preg_replace('#/+$#', '/', get_option('siteurl').'/') ?></b>, 
            and your domain is <b><?php $url = parse_url(get_option('siteurl')); echo $url['host'] ?></b>.
          </p>  
          
          <p><span style="color:red;">Turn off all pop-up blockers before continuing.</span></p> 
          
          <table class="form-table">
            <tr>
              <th><label for="<?php echo self::OPTION_API_KEY ?>">App ID</label></th>
              <td><input type="text" style="width:25em;" id="<?php echo self::OPTION_API_KEY ?>" name="<?php echo self::OPTION_API_KEY ?>" value="<?php echo htmlentities(self::api_key()) ?>" /></td>
            </tr>
            <tr>
              <th><label for="<?php echo self::OPTION_APP_SECRET ?>">App Secret</label></th>
              <td><input type="text" style="width:25em;" id="<?php echo self::OPTION_APP_SECRET ?>" name="<?php echo self::OPTION_APP_SECRET ?>" value="<?php echo htmlentities(self::app_secret()) ?>" /></td>
            </tr>
            <tr>
              <td></td>
              <td>
                <p class="submit">
                  <input id="btnConnect" type="submit" name="Submit" class="button-primary" value="Connect" />
                </p>
                <div id="sharepress_fail" style="width:400px; display:none;">
                  <p>
                    It seems like there was a failure to connect to Facebook,
                    here are some things to check.
                  </p>
                  <p>
                    <b>1. Make sure your keys are correct.</b> If you made a mistake,
                    refresh this page, re-enter the keys, and try connecting again.
                  </p>
                  <p>
                    <b>2. Make sure your Facebook Application is configured with the
                    correct values for Site URL and Domain.</b> If you made a mistake,
                    refresh this page, re-enter the keys, and try connecting again.
                  </p>
                  <p>
                    <b>3. Facebook is being stupid.</b> This happens. Nobody is perfect.
                    Please wait a few minutes, refresh this page, and try again.
                  </p>
                  <p>
                    If the problems persist, please <a href="http://aaroncollegeman/sharepress/help">visit the help page</a>.
                  </p>
                </div>
              </td>
            </tr>
          </table>

        <?php } else { ?>

          <p><span style="color:red;">Turn off all pop-up blockers before continuing.</span></p> 
          
          <table class="form-table">
            <tr>
              <td></td>
              <td>
                <p class="submit">
                  <input id="btnConnect" type="submit" name="Submit" class="button-primary" value="Connect" />
                </p>
                <div id="sharepress_fail" style="width:400px; display:none;">
                  <p>
                    It seems like there was a failure to connect to Facebook,
                    here are some things to check.
                  </p>
                  <p>
                    <b>1. Make sure your keys are correct.</b> If you made a mistake,
                    refresh this page, re-enter the keys, and try connecting again.
                  </p>
                  <p>
                    <b>2. Make sure your Facebook Application is configured with the
                    correct values for Site URL and Domain.</b> If you made a mistake,
                    refresh this page, re-enter the keys, and try connecting again.
                  </p>
                  <p>
                    <b>3. Facebook is being stupid.</b> This happens. Nobody is perfect.
                    Please wait a few minutes, refresh this page, and try again.
                  </p>
                  <p>
                    If the problems persist, please <a href="http://aaroncollegeman/sharepress/help">visit the help page</a>.
                  </p>
                </div>
              </td>
            </tr>
          </table>

        <?php } ?>
        
        <div id="fb-root"></div>
        <script>
          (function() {
            var e = document.createElement('script'); e.async = true;
            e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
            document.getElementById('fb-root').appendChild(e);
          }());

          (function($) {
            var api_key = $('#<?php echo self::OPTION_API_KEY ?>').focus();
            var app_secret = $('#<?php echo self::OPTION_APP_SECRET ?>');
            var btn = $('#btnConnect');

            var fail_timeout = null;
            var session_saved = false;

            $('#settings_form').submit(function() {
              if (session_saved) {
                return true;
              }

              api_key.val($.trim(api_key.val()));
              app_secret.val($.trim(app_secret.val()));  

              <?php if (!defined('SHAREPRESS_MU') || !SHAREPRESS_MU || current_user_can('manage_network')) { ?>

                if (!api_key.val()) {
                  alert('App ID is required.');
                  return false;
                }

                if (!app_secret.val()) {
                  alert('App Secret is required.');
                  return false;
                }

              <?php } ?>

              btn.attr('disabled', true).val('Connecting...');

              fail_timeout = setTimeout(function() {
                $('#sharepress_fail').fadeIn();
              }, 3000);

              FB.init({
                appId: $('#<?php echo self::OPTION_API_KEY ?>').val(),
                status: true, 
                cookie: true
              });

              fb_connect();

              return false;
            });
       
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
                      btn.attr('disabled', false).val('Connect');
                    }
                  } else {
                    // post session data to the back-end and then redirect to the settings screen
                    setTimeout(function() {
                      <?php if (!defined('SHAREPRESS_MU') || !SHAREPRESS_MU || current_user_can('manage_network')) { ?>
                        
                        var data = {
                          session: session, 
                          api_key: api_key.val(), 
                          app_secret: app_secret.val()
                        };
                      
                      <?php } else { ?>
                      
                        var data = {
                          session: session
                        };
                      
                      <?php } ?>

                      data.action = 'fb_save_session';

                      $.post(ajaxurl, data, function(response) {
                        btn.attr('disabled', true).val('Connected!');
                        session_saved = true;
                        $('#settings_form').submit();
                      });
                    }, 1000);
                  }
                });
              } else {
                btn.attr('disabled', false).val('Connect');
              }
            }
            
            function fb_connect() {
              FB.getLoginStatus(function(response) {
                clearTimeout(fail_timeout);
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

      <?php } else { ?>

        <p>Blah, blah, blah. Contact network admin.

      <?php } ?>
      
      
    <?php } else { ?> 
      
      <?php settings_fields('fb-settings') ?>
      
      <h3 class="title">SharePress Settings</h3>
      
      <table class="form-table">
        <tr>
          <th>Default behavior</th>
          <td>
            <div style="margin-bottom:5px;">
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[default_behavior]" value="on" <?php if (self::setting('default_behavior') == 'on') echo 'checked="checked"' ?> />
                Send all of my Posts to Facebook
              </label>
            </div>
            <div>
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[default_behavior]" value="off" <?php if (self::setting('default_behavior') == 'off') echo 'checked="checked"' ?> />
                Send to Facebook only those Posts I tell you to
              </label>
            </div>
          </td>
        </tr>
      </table>
      
      <br />
      <h3 class="title">Facebook Open Graph Tags</h3>
      
      <p>
        Open Graph meta data is required for SharePress to function. If you don't know what this
        is, leave this feature enabled. If, however, you already have a custom solution for OG
        meta data, you may disable this feature.
      </p>
      <p>
        If you want to test the way your pages appears to Facebook, you can do so with 
        Facebook's <a href="http://developers.facebook.com/tools/lint/?url=<?php urlencode(bloginfo('siteurl')) ?>" target="_blank">URL Linter</a>.
      </p>
      
      <table class="form-table">
        <tr>
          <th>Open Graph meta data</th>
          <td>
            <div style="margin-bottom:5px;">
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[page_og_tags]" value="on" <?php if (self::setting('page_og_tags') != 'off') echo 'checked="checked"' ?> />
                Let SharePress insert all required tags (recommended)
              </label>
              
              <span style="margin-left:50px;">
                <label for="sharepress_home_og_type" style="cursor:help;" title="Select the Content Type that best expresses the content of your site"><code>og:type</code>&nbsp;=</label>
                <select id="sharepress_home_og_type" name="<?php echo self::OPTION_SETTINGS ?>[page_og_type]">
                  <option value="blog">blog</option>
                  <option value="website">website</option>
                </select>
                <script>
                  (function($) {
                    $('option[value="<?php echo self::setting('page_og_type', 'blog') ?>"]', $('#sharepress_home_og_type')).attr('selected', true);
                  })(jQuery);
                </script>
              </span>
            </div>
            <div>
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[page_og_tags]" value="imageonly" <?php if (self::setting('page_og_tags') == 'imageonly') echo 'checked="checked"' ?> />
                  SharePress should only insert the <code>og:image</code> tag
              </label>
            </div>
            <div style="padding-top:8px;">
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[page_og_tags]" value="off" <?php if (self::setting('page_og_tags') == 'off') echo 'checked="checked"' ?> />
                  SharePress should not insert any tags (make sure something else does!)
              </label>
            </div>
          </td>
        </tr>
      </table>
      
      <p><b>Note:</b> you can override any/all of the <code>og:*</code> meta tags for your posts and pages by creating Custom Fields.
        For example, to set the <code>og:type</code> property, just create a Custom Field named <em>og:type</em> and set its value
        to the desired type. Learn more about <a href="http://ogp.me">OG meta data</a>. Learn more about <a href="http://codex.wordpress.org/Custom_Fields">Custom Fields</a>.</p>
      
      <br />
      <h3 class="title">Default Publishing Targets</h3>
      
      <p>
        When you publish a new post, where should we announce it?
        <?php if (self::$pro) { ?>
          You'll be able to change this for each post: these are just the defaults.
        <?php } else { ?>
          If you <a href="http://aaroncollegeman.com/sharepress">unlock the pro features</a>, you will also be able to select from your Facebook pages.
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
                <input type="checkbox" name="sharepress_publishing_targets[wall]" value="1" <?php if (self::targets('wall')) echo 'checked="checked"' ?>>
              </th>
              <td><a target="_blank" href="http://facebook.com/profile.php?id=<?php echo self::me('id') ?>">
                <?php echo (preg_match('/s$/i', trim($name = self::me('name')))) ? $name.'&apos;' : $name.'&apos;s' ?> Wall</a></td>
            </tr>
            <!-- /blog owner's wall -->
          
            <!-- all of the blog owner's pages -->
            <?php foreach(self::pages() as $i => $page) { if (self::$pro && self::$pro->is_excluded_page($page)) continue; ?>
              <tr class="<?php if ($i % 2) echo 'alternate' ?>">
                <th scope="row" class="check-column">
                  <input type="checkbox" name="sharepress_publishing_targets[<?php echo $page['id'] ?>]" value="1" <?php if (self::targets($page['id'])) echo 'checked="checked"' ?>>
                </th>
                <td><a target="_blank" href="http://facebook.com/profile.php?id=<?php echo $page['id'] ?>"><?php echo $page['name'] ?></a></td>
              </tr>
            <?php } ?>
          </tbody>
        
        </table>
      </div>
      
      <br />
      <h3 class="title">Notifications</h3>
      
      <p>SharePress can e-mail you when errors and/or successes in posting to Facebook happen.</p>
      
      <table class="form-table">
        <tr>
          <th>When errors happen:</th>
          <td>
            <label>
              <input type="checkbox" id="notify_on_error" onclick="if (this.checked) jQuery('#on_error_email').focus();" name="<?php echo self::OPTION_NOTIFICATIONS ?>[on_error]" <?php if (self::notify_on_error()) echo 'checked="checked"' ?> value="1" />
              Send an e-mail to:
            </label>
            <input style="width:25em;" type="text" id="on_error_email" name="<?php echo self::OPTION_NOTIFICATIONS ?>[on_error_email]" value="<?php echo htmlentities(self::get_error_email()) ?>" />
            <div style="color:red; display:none;" id="on_error_email_error">Please use a valid e-mail address</div>
          </td>
        </tr>
        
        <tr>
          <th>When successes happen:</th>
          <td>
            <label>
              <input type="checkbox" id="notify_on_success" onclick="if (this.checked) jQuery('#on_success_email').focus();" name="<?php echo self::OPTION_NOTIFICATIONS ?>[on_success]" <?php if (self::notify_on_success()) echo 'checked="checked"' ?> value="1" />
              Send an e-mail to:
            </label>
            <input style="width:25em;" type="text" id="on_success_email" name="<?php echo self::OPTION_NOTIFICATIONS ?>[on_success_email]" value="<?php echo htmlentities(self::get_success_email()) ?>" />
            <div style="color:red; display:none;" id="on_success_email_error">Please use a valid e-mail address</div>
          </td>
        </tr>
      </table>  

      <br />
      <h3 class="title">License Key</h3>

      <?php 
        #
        # Don't be a dick. I like to eat, too.
        # http://aaroncollegeman/sharepress/
        #
        if (!self::unlocked()) { ?>
        <p>Unlock pro features, get access to documentation and support from the developer of SharePress! <a href="http://aaroncollegeman.com/sharepress">Buy a license</a> key today.</p>
      <?php } else { ?>
        <p>Awesome, tamales! Need support? Need documentation? <a href="http://aaroncollegeman.com/sharepress/help/">Go here</a>.
      <?php } ?>

      <table class="form-table">
        <tr>
          <th><label for="sharepress_license_key">License Key:</label></th>
          <td>
            <input style="width:25em;" type="text" id="sharepress_license_key" name="<?php echo self::OPTION_SETTINGS ?>[license_key]" value="<?php echo htmlentities(self::setting('license_key')) ?>" />
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
  
  <?php if (self::session()) { ?>

    <?php if (self::$pro) { ?>
      <br />
      <h3 class="title">Default Picture</h3>

      <p>Each message posted to Facebook can be accompanied by a picture. You can set the default below.</p>
      
      <table class="form-table">
        <tr>
          <th>Default picture:</th>
          <td>
            <?php PostImage::ui('sharepress', self::OPTION_DEFAULT_PICTURE, null, 90, 90, self::load()->get_default_picture()) ?>
          </td>
        </tr>
      </table>
    <?php } ?>
    
    <br />
    <h3 class="title">Clear Cache</h3>

    <p>If you become the manager of a new Facebook Page, but do not see it in the list above or in the target list on the Edit Posts screen, reset the cache below.</p>

    <p><a id="btnClearCache" href="options-general.php?page=sharepress&amp;action=clear_cache" class="button" onclick="jQuery(this).addClass('disabled');">Clear Cache</a></p>
    
    <?php if (!defined('SHAREPRESS_MU_SHARED_ACCESS_TOKEN') || !SHAREPRESS_MU_SHARED_ACCESS_TOKEN) { ?>
    
      <br />
      <h3 class="title">Run Setup Again</h3>

      <p>If you need to change Facebook Application keys, you can run setup again by clicking the button below.</p>

      <p><a href="options-general.php?page=sharepress&amp;action=clear_session" class="button">Run Setup Again</a></p>    

    <?php } ?>
    
  <?php } ?>
    
</div>