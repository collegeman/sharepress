<div class="wrap" style="max-width:970px;">

  <?php screen_icon(); ?>
  <h2>SharePress Settings</h2>
  
  <form method="post" action="options.php">

    <?php settings_fields('sp-global-settings') ?>

    <!-- clear old settings fields -->
    <input type="hidden" name="sharepress_settings" value="">
    <input type="hidden" name="sharepress_fb_a_state" value="">
    <input type="hidden" name="sharepress_publishing_targets" value="">
    <input type="hidden" name="sharepress_notifications" value="">
    <input type="hidden" name="sharepress_api_key" value="">
    <input type="hidden" name="sharepress_api_secret" value="">

    <table class="form-table">
      <tr>
        <th>Basic open graph metadata</th>
        <td>
          <p>
            <b>Open Graph</b> meta data affects how your content looks when it is posted on Facebook.
            You should allow SharePress to control these tags, but in some cases you may want to
            give control of these tags to another Plugin (like Yoast SEO) or your Theme. To give control
            over a particular tag to some other feature of your site, just uncheck the box below.
          </p>
          <br>
          <?php
            $og_tags = array_merge(array(
              'og:title' => false,
              'og:type' => false,
              'og:image' => false,
              'og:url' => false,
              'fb:app_id' => false,
              'og:site_name' => false,
              'og:description' => false,
              'og:locale' => false,
              'article:publisher' => false,
              'article:author' => false
            ), sp_get_allowed_og_tags());
          ?>

          <input type="hidden" name="<?php echo sp_get_opt_name('og_tag') ?>[__PLACEHOLDER__]" value="__PLACEHOLDER__" />
          
          <?php foreach($og_tags as $tag => $checked) { if ($tag == '__PLACEHOLDER__') continue; ?>
            <p>
              <input type="checkbox" id="page_og_tag_<?php echo $tag ?>" name="<?php echo sp_get_opt_name('og_tag') ?>[<?php echo $tag ?>]" value="1" <?php if ($checked) echo 'checked="checked"' ?> />
              <label for="page_og_tag_<?php echo $tag ?>"><b><?php echo $tag ?></b></label>
            </p>
          <?php } ?>
        </td>
      </tr>
      <tr>
        <th>
          <label for="sp_og_site_type">og:type</label>
        </th>
        <td>  
          <select id="sp_og_site_type" name="<?php echo sp_get_opt_name('og_site_type') ?>">
            <?php foreach(array('blog', 'website') as $type) { ?>
              <option value="<?php echo $type ?>" <?php
                if ($type === sp_get_og_site_type()) {
                  echo 'selected="selected"';
                }
              ?>><?php echo $type ?></option>
            <?php } ?>
          </select>
          &nbsp; &nbsp; <span class="description">
            Only the homepage gets this meta data; all other pages are typed <b style="font-style:normal;">article</b>.
            &nbsp; <a href="https://developers.facebook.com/docs/reference/opengraph/object-type/article/" target="_blank">Learn more &rarr;</a>
          </span>
        </td>
      </tr>
      <tr>
        <th>
          <label for="sp_og_locale">og:locale</label>
        </th>
        <td>
          <input id="sp_og_locale" name="<?php echo sp_get_opt_name('og_locale') ?>" type="text" value="<?php echo esc_attr(sp_get_og_locale()) ?>" placeholder="en_US" style="width:7em;" />
          &nbsp; &nbsp; <span class="description">Enter the proper locale for your site.
            &nbsp; <a href="https://developers.facebook.com/docs/internationalization/#locales" target="_blank">Learn more &rarr;</a></span>
        </td>
      </tr>    
      <tr>
        <th>
          <label for="sp_og_article_publisher">article:publisher</label>
        </th>
        <td>
          <input type="text" class="regular-text" name="<?php echo sp_get_opt_name('og_article_publisher') ?>" id="sp_og_article_publisher" value="<?php echo esc_attr(sp_get_og_article_publisher()) ?>" placeholder="http://www.facebook.com/your-facebook-page">
          &nbsp; &nbsp; <span class="description">
            Set this to the URL of your Facebook page. <a href="https://developers.facebook.com/blog/post/2013/06/19/platform-updates--new-open-graph-tags-for-media-publishers-and-more/" target="_blank">Learn more &rarr;</a>
          </span>
        </td>
      </tr>
      <tr>
        <th>
          SharePress Status Notifications
        </th>
        <td>
          <p>
            Choose when and who to notify of SharePress successes and failures by email.
          </p>
          <?
            $notify_settings = array_merge(array(
                'on_success' => false,
                'on_error_email' => null,
                'on_success_email' => null,
                'on_error' => false
              ), 
              sp_get_opt('notify_settings', array())
            );
          ?>
          <p>
            <input type="checkbox" id="notify_on_success" name="<?php echo sp_get_opt_name('notify_settings') ?>[on_success]" value="1" <?php if ($notify_settings['on_success']) echo 'checked="checked"' ?> />
            <label for="notify_on_success"><b>E-mail notification on successful post</b></label><br>
            <input id="notify_on_success_email" class="regular-text" placeholder="E-mail address" type="text" name="<?php echo sp_get_opt_name('notify_settings') ?>[on_success_email]" value="<?php echo $notify_settings['on_success_email'] ?>">
            <br>
            <br>
          </p>
          <p>
            <input type="checkbox" id="notify_on_error" name="<?php echo sp_get_opt_name('notify_settings') ?>[on_error]" value="1" <?php if ($notify_settings['on_error']) echo 'checked="checked"' ?> />
            <label for="notify_on_error"><b>E-mail notification on posting error</b></label><br>
            <input id="notify_on_error_email" class="regular-text" placeholder="E-mail address" type="text" name="<?php echo sp_get_opt_name('notify_settings') ?>[on_error_email]" value="<?php echo $notify_settings['on_error_email'] ?>"> <br><br>
          </p>
        </td>
      </tr>
    </table>

    <?php submit_button(); ?>

  </form>

</div>