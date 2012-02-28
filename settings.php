<?php  if (!defined('ABSPATH')) exit; /* silence is golden */ ?>

<style>
.wrap h2 span { font-size: 0.75em; padding-left: 20px; }
p.submit.floating input { position: fixed; top: 40px; right: 20px; font-size: 18px !important; line-height:22px; }
</style>

<div class="wrap">

  <div id="icon-general" class="icon32" style="background:url('<?php echo plugins_url('img/icon32.png', __FILE__) ?>') no-repeat;"><br /></div>
  <h2>
    SharePress
    <span>a WordPress plugin from <a href="http://aaroncollegeman.com/fatpanda/" target="_blank">Fat Panda</a></span>
  </h2>

  <form method="post" action="options.php" id="settings_form">

    <?php if (!self::session()) { ?>

      <?php settings_fields('fb-step1') ?>
      
      <?php if (!self::is_mu()) { ?>

        <h3 class="title">Your Facebook Application</h3>
      
        <p>
          Before you continue, you'll need to create a Facebook Application. 
          <a href="http://www.facebook.com/developers/createapp.php" target="_blank">Do this now</a>.
          &nbsp;&nbsp;<b><a href="https://developers.facebook.com/docs/appsonfacebook/tutorial/#create" target="_blank">Need more help?</a></b>
        </p>

        <p>
          <b>Note:</b> Your Site URL is <b><?php echo preg_replace('#/+$#', '/', get_option('siteurl').'/') ?></b>, 
          and your domain is <b><?php $url = parse_url(get_option('siteurl')); echo $url['host'] ?></b>.
        </p>  
        
        <?php /* ?>
        <p style="padding:10px; background-color:#ffffcc;">
          <b style="color:red;">Breaking Change</b>
          &nbsp;&nbsp;<a href="http://aaroncollegeman.com/2012/02/03/breaking-change-configuring-your-facebook-application-for-offline_access-deprecation" target="_blank">Read this</a> to learn how to properly configure your Facebook application to avoid disruption of service.
        </p> */ ?>

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
            </td>
          </tr>
        </table>

      <?php } else if (self::has_keys()) { ?>

        <h3 class="title">Connect to Facebook</h3>
      
        <p>Click the button below, authorize the Facebook application, and you're in.</p>

        <table class="form-table">
          <tr>
            <td></td>
            <td>
              <p class="submit">
                <input id="btnConnect" type="submit" name="Submit" class="button-primary" value="Connect" />
              </p>
            </td>
          </tr>
        </table>

      <?php } else { ?>

        <h3 class="title">SharePress is not setup properly.</h3>

        <p>This copy of SharePress is running in MU mode, but the Facebook App Id and App Secret have not been configured.</p>

        <p>Please contact your network admin.</p>
        
      <?php } ?>

      
      <script>
        (function($) {
          var api_key = $('#<?php echo self::OPTION_API_KEY ?>').focus();
          var app_secret = $('#<?php echo self::OPTION_APP_SECRET ?>');
          var btn = $('#btnConnect');

          $('#settings_form').submit(function() {
            api_key.val($.trim(api_key.val()));
            app_secret.val($.trim(app_secret.val()));  

            <?php if (!self::is_mu()) { ?>

              if (!api_key.val()) {
                alert('App ID is required.');
                return false;
              }

              if (!app_secret.val()) {
                alert('App Secret is required.');
                return false;
              }

            <?php } ?>

            $.post(ajaxurl, { 
              action: 'fb_save_keys', 
              current_url: '<?php echo self::getCurrentUrl() ?>', 
              api_key: api_key.val(), 
              app_secret: app_secret.val() 
            }, function(url) {
              btn.attr('disabled', true).val('Connecting...');
              document.location = url;  
            });

            return false;
          });
          
        })(jQuery);
      </script> 

    <?php } else { ?> 
      
      <?php settings_fields('fb-settings') ?>

      <?php if (!self::is_mu()) { ?>

        <h3 class="title">Your License Key</h3>

        <?php 
          #
          # Don't be a dick. I like to eat, too.
          # http://aaroncollegeman/sharepress/
          #
          if (!self::unlocked()) { ?>
          <p>
            <a href="http://aaroncollegeman.com/sharepress?utm_source=sharepress&utm_medium=in-app-promo&utm_campaign=buy-a-license">Buy a license</a> key today.
            Unlock pro features, get access to documentation and support from the developer of SharePress!
          </p>
        <?php } else { ?>
          <p>Awesome, tamales! Need support? <a href="http://aaroncollegeman.com/sharepress/help/">Go here</a>.
        <?php } ?>

        <table class="form-table">
          <tr>
            <th><label for="sharepress_license_key">License Key:</label></th>
            <td>
              <input style="width:25em;" type="text" id="sharepress_license_key" name="<?php echo self::OPTION_SETTINGS ?>[license_key]" value="<?php echo htmlentities(self::setting('license_key')) ?>" />
            </td>
          </tr>
        </table>

      <?php } ?>

      <br />
      <h3 class="title">Share by Default?</h3>

      <p>You always get to choose whether or not a post gets shared. This setting sets up the default choice.</p>
      
      <table class="form-table">
        <tr>
          <td>
            <div style="margin-bottom:5px;">
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[default_behavior]" value="on" <?php if (self::setting('default_behavior') == 'on') echo 'checked="checked"' ?> />
                Yes, share by default
                &nbsp; &nbsp; <span class="description">Use this setting if you rely on XML-RPC, e.g., the WordPress iPhone/iPad apps</span>
              </label>
            </div>
            <div>
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[default_behavior]" value="off" <?php if (self::setting('default_behavior') == 'off') echo 'checked="checked"' ?> />
                No, do not share by default
              </label>
            </div>
          </td>
        </tr>
      </table>

      <br />
      <h3 class="title">Post Link</h3>
      <p>Append post link to the end of Facebook messages?</p>

      <table class="form-table">
        <tr>
          <td>
            <div style="margin-bottom:5px;">
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[append_link]" value="on" <?php if (self::setting('append_link', 'on') == 'on') echo 'checked="checked"' ?> />
                Yes, by default
              </label>
            </div>
            <div>
              <label>
                <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[append_link]" value="off" <?php if (self::setting('append_link', 'on') == 'off') echo 'checked="checked"' ?> />
                No
              </label>
            </div>
          </td>
        </tr>
      </table>
      
      <br />
      <h3 class="title">Open Graph Tags</h3>
      <p>
        <a href="http://ogp.me" target="_blank">Open Graph</a> meta data tells Facebook what your content is all about. If you don't know what this
        is, leave theses features enabled.</p>
      <p> 
        If your Theme or another plugin already inserts Open Graph tags, you may want to disable certain tags by unchecking them below.
      </p>
      <p>You can override any of the tags by creating <a href="http://codex.wordpress.org/Custom_Fields" target="_blank">Custom Fields</a> in your posts.
        For example, to override the <code>og:type</code> property, just create a Custom Field named <code>og:type</code> and give it the desired value.</p>
      
      <table class="form-table">
        <tr>
          <td>
            <?php
              // backward-compat with old page_og_tags setting
              if (($page_og_tags = $this->setting('page_og_tags')) && ($page_og_tags == 'imageonly' || $page_og_tags == 'off')) {
                if ($page_og_tags == 'imageonly') {
                  $page_og_tag = array(
                    'og:image' => true
                  );
                } else { // off
                  $page_og_tag = array();
                } 
              } else {
                $page_og_tag = $this->setting('page_og_tag', array(
                  'og:title' => true,
                  'og:type' => true,
                  'og:image' => true,
                  'og:url' => true,
                  'fb:app_id' => true,
                  'og:site_name' => true,
                  'og:description' => true,
                  'og:locale' => true
                ));
              }

              $page_og_tag = array_merge(array(
                'og:title' => false,
                'og:type' => false,
                'og:image' => false,
                'og:url' => false,
                'fb:app_id' => false,
                'og:site_name' => false,
                'og:description' => false,
                'og:locale' => false
              ), $page_og_tag);
            ?>
            <input type="hidden" name="<?php echo self::OPTION_SETTINGS ?>[page_og_tag][__PLACEHOLDER__]" value="__PLACEHOLDER__" />
            
            <?php foreach($page_og_tag as $tag => $checked) { if ($tag == '__PLACEHOLDER__') continue; ?>
              <div style="height:<?php echo $tag == 'og:type' ? 35 : 30 ?>px;">
                <input type="checkbox" id="page_og_tag_<?php echo $tag ?>" name="<?php echo self::OPTION_SETTINGS ?>[page_og_tag][<?php echo $tag ?>]" value="1" <?php if ($checked) echo 'checked="checked"' ?> />
                <label for="page_og_tag_<?php echo $tag ?>"><code><?php echo $tag ?></code></label>
                <?php if ($tag == 'og:type') { ?>
                  <span style="margin-left:50px;">
                    <label for="sharepress_home_og_type" style="cursor:help;" title="Select the Content Type that best expresses the content of your site">=</label>
                    <select id="sharepress_home_og_type" name="<?php echo self::OPTION_SETTINGS ?>[page_og_type]">
                      <option value="blog">blog</option>
                      <option value="website">website</option>
                    </select>
                    &nbsp; &nbsp; <span class="description">The homepage gets this type; all other pages are considered <b>articles</b></span>
                    <script>
                      (function($) {
                        $('option[value="<?php echo self::setting('page_og_type', 'blog') ?>"]', $('#sharepress_home_og_type')).attr('selected', true);
                      })(jQuery);
                    </script>
                  </span>
                <?php } ?>
                <?php if ($tag == 'og:locale') { ?>
                  <span style="margin-left:50px;">
                    <label for="sharepress_og_locale" style="cursor:help;" title="Enter the proper locale for your site">=</label>
                    <input id="sharepress_og_locale" name="<?php echo self::OPTION_SETTINGS ?>[og_locale]" type="text" value="<?php echo esc_attr(self::setting('og_locale', 'en_US')) ?>" style="width:7em;" />
                  </span>
                <?php } ?>
              </div>
            <?php } ?>
          </td>
        </tr>
      </table>
      
      <br />
      <h3 class="title">Facebook Pages and Walls</h3>
      
      <?php if (self::is_business()) { ?>

        <p>You setup SharePress using a Facebook page account.</p>

        <p>What you share with Facebook will be posted on <a target="_blank" href="http://www.facebook.com/profile.php?id=<?php echo self::me('id') ?>"><?php echo self::me('name') ?></a>'s wall.</p>

      <?php } else { ?>
        <p>
          When you publish new post, where should it be announced?
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

      <?php } // !self::is_business() ?>

      <?php if (!self::unlocked()) { ?>

        <br />
        <h3 class="title">Twitter</h3>
   
        <p>If you <a href="http://aaroncollegeman.com/sharepress">unlock the pro features</a>, you'll be able to post to Twitter, too.</p>
     
        <input type="hidden" name="<?php echo self::OPTION_SETTINGS ?>[twitter_is_ready]" value="<?php echo self::setting('twitter_is_ready', 0) ?>" />
        <input type="hidden" class="twitter_setting" name="<?php echo self::OPTION_SETTINGS ?>[twitter_consumer_key]" value="<?php echo esc_attr(self::setting('twitter_consumer_key')) ?>" />
        <input type="hidden" class="twitter_setting" name="<?php echo self::OPTION_SETTINGS ?>[twitter_consumer_secret]" value="<?php echo esc_attr(self::setting('twitter_consumer_secret')) ?>" />
        <input type="hidden" class="twitter_setting" name="<?php echo self::OPTION_SETTINGS ?>[twitter_access_token]" value="<?php echo esc_attr(self::setting('twitter_access_token')) ?>" />
        <input type="hidden" class="twitter_setting" name="<?php echo self::OPTION_SETTINGS ?>[twitter_access_token_secret]" value="<?php echo esc_attr(self::setting('twitter_access_token_secret')) ?>" />
        <input type="hidden" name="<?php echo self::OPTION_SETTINGS ?>[bitly_login]" value="<?php echo esc_attr(self::setting('bitly_login')) ?>" />
        <input type="hidden" name="<?php echo self::OPTION_SETTINGS ?>[bigly_apikey]" value="<?php echo esc_attr(self::setting('bigly_apikey')) ?>" />
      
      <?php } else { ?>

        <br />
        <h3 class="title">Twitter</h3>

        <?php if (self::twitter_ready()) { ?>
        
          <table class="form-table">
            <tr>
              <td>
                <div style="margin-bottom:5px;">
                  <label>
                    <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[twitter_behavior]" value="on" <?php if (self::setting('twitter_behavior', 'on') == 'on') echo 'checked="checked"' ?> />
                    Share all of my Posts to Twitter by default
                    &nbsp; &nbsp; <span class="description">Use this setting if you rely on XML-RPC</span>
                  </label>
                </div>
                <div>
                  <label>
                    <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[twitter_behavior]" value="off" <?php if (self::setting('twitter_behavior', 'on') == 'off') echo 'checked="checked"' ?> />
                    No, do not share to Twitter by default
                  </label>
                </div>
              </td>
            </tr>
          </table>
          <br />

          <input type="hidden" name="<?php echo self::OPTION_SETTINGS ?>[twitter_is_ready]" value="<?php echo self::setting('twitter_is_ready', 1) ?>" />
          <input type="hidden" class="twitter_setting" name="<?php echo self::OPTION_SETTINGS ?>[twitter_consumer_key]" value="<?php echo esc_attr(self::setting('twitter_consumer_key')) ?>" />
          <input type="hidden" class="twitter_setting" name="<?php echo self::OPTION_SETTINGS ?>[twitter_consumer_secret]" value="<?php echo esc_attr(self::setting('twitter_consumer_secret')) ?>" />
          <input type="hidden" class="twitter_setting" name="<?php echo self::OPTION_SETTINGS ?>[twitter_access_token]" value="<?php echo esc_attr(self::setting('twitter_access_token')) ?>" />
          <input type="hidden" class="twitter_setting" name="<?php echo self::OPTION_SETTINGS ?>[twitter_access_token_secret]" value="<?php echo esc_attr(self::setting('twitter_access_token_secret')) ?>" />
          
        <?php } else { ?>

          <input type="hidden" name="<?php echo self::OPTION_SETTINGS ?>[twitter_is_ready]" value="1" />
          <input type="hidden" name="<?php echo self::OPTION_SETTINGS ?>[twitter_behavior]" value="<?php echo esc_attr(self::setting('twitter_behavior', 'on')) ?>" />

          <p>Want to be able to post to Twitter at the same time you post to Facebook? <a href="#" onclick="jQuery('.twitter_help').show(); return false;">Follow these steps</a>.</p>

          <div class="twitter_help" style="display:none;">
            <ol>
              <li>
                <a href="http://twitter.com" target="_blank">Log into Twitter</a> using the Twitter account you want to post to.
                <br />
              </li>
              <li>
                <a href="https://dev.twitter.com/apps/new" target="_blank">Create a Twitter application</a> using the values below:
                <table class="form-table">
                  <tr>
                    <td style="width:160px;">Name</td>
                    <td>
                      <input type="text" class="regular-text" value="<?php echo esc_attr(get_bloginfo('sitename')) ?>" />
                      &nbsp; <span class="description">Just a suggestion, it can be whatever you like...</span>
                    </td>
                  </tr>
                  <tr>
                    <td>Description</td>
                    <td>
                      <input type="text" class="regular-text" value="<?php echo esc_attr(get_bloginfo('description')) ?>" />
                      &nbsp; <span class="description">Just another suggestion...</span>
                    </td>
                  </tr>
                  <tr>
                    <td>WebSite</td>
                    <td>
                      <input type="text" class="regular-text" value="<?php echo esc_attr(get_bloginfo('home')) ?>" readonly="readonly" />
                      &nbsp; <span class="description">You should use this.</span>
                    </td>
                  </tr>
                  <tr>
                    <td>Callback URL</td>
                    <td>
                      <input type="text" class="regular-text" style="width:500px;" value="<?php echo esc_attr(admin_url('options-general.php')) ?>?page=sharepress" readonly="readonly" />
                      &nbsp; <span class="description">You must use this.</span>
                    </td>
                  </tr>
                </table>
                <br />
              </li>
              <li>
                <a href="https://dev.twitter.com/apps" target="_blank">Go to your control panel</a>, and click on your newly minted Twitter application.
                <br />
              </li>
              <li>
                Click on the <b>Settings</b> tab, scroll down to <b>Application Type</b>, check the box labeled <b>Read and Write</b>, 
                check the box labeled <b>t.co links wrapping for all URLs</b>, then scroll down and click the <b>Update this Twitter
                application's settings</b>.
                <br />
              </li>
              <li>
                Click on the <b>Details</b> tab, scroll down to and click on <b>Create my access token</b>. After the page reloads, wait 30 seconds or so,
                and refresh the page. Scroll back down to <b>Your access token</b>, then complete the form below.
                <br />
              </li>
            </ol>
            <br />
          </div>

          <table class="form-table">
            <tr>
              <td style="width:160px;">Consumer key:</td>
              <td>
                <input style="width:500px;" type="text" class="twitter_setting regular-text" name="<?php echo self::OPTION_SETTINGS ?>[twitter_consumer_key]" value="<?php echo esc_attr(self::setting('twitter_consumer_key')) ?>" />
              </td>
            </tr>
            <tr>
              <td>Consumer secret:</td>
              <td>
                <input style="width:500px;" type="text" class="twitter_setting regular-text" name="<?php echo self::OPTION_SETTINGS ?>[twitter_consumer_secret]" value="<?php echo esc_attr(self::setting('twitter_consumer_secret')) ?>" />
              </td>
            </tr>
            <tr>
              <td>Access token:</td>
              <td>
                <input style="width:500px;" type="text" class="twitter_setting regular-text" name="<?php echo self::OPTION_SETTINGS ?>[twitter_access_token]" value="<?php echo esc_attr(self::setting('twitter_access_token')) ?>" />
              </td>
            </tr>
            <tr>
              <td>Access token secret:</td>
              <td>
                <input style="width:500px;" type="text" class="twitter_setting regular-text" name="<?php echo self::OPTION_SETTINGS ?>[twitter_access_token_secret]" value="<?php echo esc_attr(self::setting('twitter_access_token_secret')) ?>" />
              </td>
            </tr>
          </table>
          <br />

        <?php } ?>
        <table class="form-table">
          <tr>
            <td style="width:160px;">Default Hashtag:</td>
            <td>
              <input type="text" class="regular-text" name="<?php echo self::OPTION_SETTINGS ?>[twitter_default_hashtag]" value="<?php echo esc_attr(self::setting('twitter_default_hashtag')) ?>" />
            </td>
          </tr>
        </table>
        <br />
        <p>
          <a href="#" onclick="test_twitter_settings(); return false;" class="button">Test Twitter Settings</a>
          <?php if (self::twitter_ready()) { ?>
            &nbsp; <a href="<?php echo admin_url('options-general.php?page=sharepress&action=reset_twitter_settings') ?>" class="button">Change Twitter Settings</a>
          <?php } else { ?>
            &nbsp; <input id="btnSaveSettings" class="button-primary" value="Save Settings" type="submit" />
          <?php } ?>
        </p>

        <script>
          (function($) {
            window.test_twitter_settings = function() {
              var data = $('input.twitter_setting').serialize();
              data += '&action=sharepress_test_twitter_settings';
              $.post(ajaxurl, data, function(result) {
                alert(result);
              });
            }
          })(jQuery);
        </script>

        <br />
        <h3 class="title">Bit.ly</h3>

        <p>It's often useful to shorten the URLs that are posted to Twitter. Twitter does this automatically... sometimes. 
        If you'd rather use Bit.ly for reliabily, enter your Bit.ly username and API key below. 
        <a href="http://bitly.com/a/your_api_key/">Get an API key here</a>.

        <table class="form-table">
          <tr>
            <td style="width:160px;">Username:</td>
            <td>
              <input type="text" class="regular-text" name="<?php echo self::OPTION_SETTINGS ?>[bitly_login]" value="<?php echo esc_attr(self::setting('bitly_login')) ?>" />
            </td>
          </tr>
          <tr>
            <td>API Key:</td>
            <td>
              <input type="text" class="regular-text" name="<?php echo self::OPTION_SETTINGS ?>[bitly_apikey]" value="<?php echo esc_attr(self::setting('bitly_apikey')) ?>" />
            </td>
          </tr>
        </table>
      
      <?php }  ?>

      <br />
      <h3 class="title">Notifications</h3>
      
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
      
    
      <?php if (self::unlocked()) { ?>
        <br />
        <a name="picture"></a>
        <h3 class="title">Picture</h3>

        <p>Each message posted to Facebook can be accompanied by a picture.</p>
        
        <table class="form-table" style="margin-bottom:15px;">
          <tr>
            <th>The default should be...</th>
            <td>
              <div style="margin-bottom:5px;">
                <label>
                  <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[let_facebook_pick_pic_default]" value="0" <?php if (self::setting('let_facebook_pick_pic_default', 0) == 0) echo 'checked="checked"' ?> />
                  The same as the post's featured image
                </label>
              </div>
              <div style="margin-bottom:5px;">
                <label>
                  <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[let_facebook_pick_pic_default]" value="1" <?php if (self::setting('let_facebook_pick_pic_default', 0) == 1) echo 'checked="checked"' ?> />
                  Use the first image in the post
                </label>
              </div>
              <div style="margin-bottom:5px;">
                <label>
                  <input type="radio" name="<?php echo self::OPTION_SETTINGS ?>[let_facebook_pick_pic_default]" value="2" <?php if (self::setting('let_facebook_pick_pic_default', 0) == 2) echo 'checked="checked"' ?> />
                  The global default
                </label>
              </div>
            </td>
          </tr>
        </table>  

        <div>
          <p><b>Global default picture</b></p>
          <?php PostImage::ui('sharepress', self::OPTION_DEFAULT_PICTURE, null, 150, 150, self::load()->get_default_picture()) ?>
        </div>
        <div style="clear:left;"></div>

      <?php } ?>

      <?php if (self::unlocked()) { ?>
        <br />
        <h3 class="title">Share Delay</h3>

        <p>In some instances it may be necessary or deseriable to delay sharing with SharePress until a certain time <em>after</em>
          a post goes live on your site. You can configure a global default for that delay below.</p>

        <table class="form-table" style="margin-bottom:15px;">
          <tr>
            <th>Delay sharing for:</th>
            <td>
              <input type="number" class="regular-text" style="width:40px;" 
                name="<?php echo self::OPTION_SETTINGS ?>[delay_length]" value="<?php echo esc_attr(self::setting('delay_length', 0)) ?>" />
              <select name="<?php echo self::OPTION_SETTINGS ?>[delay_unit]">
                <?php foreach(array('minutes', 'hours', 'days') as $unit) { ?>
                  <option<?php if (self::setting('delay_unit') == $unit) echo ' selected="selected"' ?>><?php echo $unit ?></option>
                <?php } ?>
              </select>
            </td>
          </tr>
        </table>
        
      <?php } ?>
      
      <br />
      <h3 class="title">Clear Cache</h3>

      <p>
        If you become the manager of a new Facebook Page, but do not see it in the list
        above or in the <em>target</em> list on the Edit Posts screen, clear the cache.
      </p>

      <p><a id="btnClearCache" href="options-general.php?page=sharepress&amp;action=clear_cache" class="button" onclick="jQuery(this).addClass('disabled');">Clear Cache</a>  </p>

      <?php if (!defined('SHAREPRESS_MU_SHARED_ACCESS_TOKEN') || !SHAREPRESS_MU_SHARED_ACCESS_TOKEN) { ?>
      
        <br />
        <h3 class="title">Run Setup Again</h3>

        <p>If you need to change Facebook Application keys, run setup again.</p>        

        <p>
          <a href="options-general.php?page=sharepress&amp;action=clear_session" class="button">Run Setup Again</a>
        </p>

      <?php } ?>

      <br />
      <p class="submit">
        <input id="btnSaveSettings" class="button-primary" value="Save Settings" type="submit" />
      </p>

      <p class="floating submit">
        <input id="btnFloatingSaveSettings" class="button-primary" value="Save Settings" type="submit" />
      </p>
      
    <?php } ?>

  </form>  
    
</div>