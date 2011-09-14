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
          
        <?php } ?>

        
        <script>

          (function($) {
            var api_key = $('#<?php echo self::OPTION_API_KEY ?>').focus();
            var app_secret = $('#<?php echo self::OPTION_APP_SECRET ?>');
            var btn = $('#btnConnect');

            $('#settings_form').submit(function() {
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

              $.post(ajaxurl, { action: 'fb_save_keys', current_url: '<?php echo self::facebook()->getCurrentUrl() ?>', api_key: api_key.val(), app_secret: app_secret.val() }, function(url) {
                btn.attr('disabled', true).val('Connecting...');
                document.location = url;  
              });

              return false;
            });
       
            
          })(jQuery);
        </script> 

      <?php } else { ?>

        <p>Blah, blah, blah. Contact network admin.</p>

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
      <p>Which OG meta tags should SharePress insert into your site?</p>            
      <p>
        Open Graph meta data is required for SharePress to function. If you don't know what this
        is, leave this feature enabled. If, however, you already have a custom solution for OG
        meta data, you may selectively enable/disable any tag.
      </p>
      <p>You can override any/all of the <code>og:*</code> meta tags on a case-by-case basis by creating Custom Fields.
        For example, to set the <code>og:type</code> property, just create a Custom Field named <em>og:type</em> and set its value
        to the desired type.</p>
      <p>
        Learn more about <a href="http://codex.wordpress.org/Custom_Fields">Custom Fields</a>.
        Learn more about <a href="http://ogp.me">OG meta data</a>.
        Test the way your pages will appear on Facebook with Facebook's <a href="http://developers.facebook.com/tools/lint/?url=<?php urlencode(bloginfo('siteurl')) ?>" target="_blank">URL Linter</a>.
      </p>
      
      <table class="form-table">
        <tr>
          <th>Open Graph meta data</th>
          <td>
            <?php
              // backward-compat with old page_og_tags setting
              if ($page_og_tags = $this->setting('page_og_tags')) {
                $page_og_tag = $this->setting('page_og_tag', array(
                  'og:title' => true,
                  'og:type' => true,
                  'og:image' => true,
                  'og:url' => true,
                  'fb:app_id' => true,
                  'og:site_name' => true,
                  'og:description' => true
                ));

                if ($page_og_tags == 'imageonly') {
                  $page_og_tag = array(
                    'og:image' => true
                  );
                } else if ($page_og_tags == 'off') {
                  $page_og_tag = array();
                }
              } else {
                $page_og_tag = $this->setting('page_og_tag', array(
                  'og:title' => false,
                  'og:type' => false,
                  'og:image' => false,
                  'og:url' => false,
                  'fb:app_id' => false,
                  'og:site_name' => false,
                  'og:description' => false
                ));
              }

              $page_og_tag = array_merge(array(
                'og:title' => false,
                'og:type' => false,
                'og:image' => false,
                'og:url' => false,
                'fb:app_id' => false,
                'og:site_name' => false,
                'og:description' => false
              ), $page_og_tag);
            ?>
            <input type="hidden" name="<?php echo self::OPTION_SETTINGS ?>[page_og_tags]" value="" />
            
            <?php foreach($page_og_tag as $tag => $checked) { ?>
              <div <?php if ($tag != 'og:type') echo 'style="margin-bottom:5px;"' ?>>
                <input type="checkbox" id="page_og_tag_<?php echo $tag ?>" name="<?php echo self::OPTION_SETTINGS ?>[page_og_tag][<?php echo $tag ?>]" value="1" <?php if ($checked) echo 'checked="checked"' ?> />
                <label for="page_og_tag_<?php echo $tag ?>"><code><?php echo $tag ?></code></label>
                <?php if ($tag == 'og:type') { ?>
                  <span style="margin-left:50px;">
                    <label for="sharepress_home_og_type" style="cursor:help;" title="Select the Content Type that best expresses the content of your site">=</label>
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
                <?php } ?>
              </div>
            <?php } ?>
          </td>
        </tr>
      </table>
      
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