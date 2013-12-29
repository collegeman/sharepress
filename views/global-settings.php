<div class="wrap" style="max-width:970px;">

  <?php screen_icon(); ?>
  <h2>SharePress</h2>
  
  <form method="post" action="options.php">

    <?php settings_fields('sp-global-settings') ?>

    <!-- clear old settings fields -->
    <input type="hidden" name="sharepress_settings" value="">
    <input type="hidden" name="sharepress_fb_a_state" value="">
    <input type="hidden" name="sharepress_publishing_targets" value="">
    <input type="hidden" name="sharepress_notifications" value="">
    <input type="hidden" name="sharepress_api_key" value="">
    <input type="hidden" name="sharepress_api_secret" value="">

    <h3 class="title">Open Graph Tags</h3>
    <p>
      <a href="https://developers.facebook.com/docs/opengraph/property-types/" target="_blank">Open Graph</a> meta data tells Facebook what your content is all about.
    </p>
    <table class="form-table">
      <tr>
        <th>Basic open graph metadata</th>
        <td>
          <p>If your Theme or another Plugin (like Yoast SEO) is also inserting Open Graph tags into your pages, you may want to disable SharePress' influence
            over certain tags by unchecking them below.
            When you uncheck any of the boxes below, you are telling SharePress that it is <em>not</em> allowed to influence this
            meta data, which may make some features of SharePress unreliable.</p>
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
              'og:locale' => false
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
          <label>og:type</label>
        </th>
        <td>  
          <select id="sharepress_home_og_type" name="<?php echo sp_get_opt_name('og_site_type') ?>">
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
          <label for="sharepress_og_locale">og:locale</label>
        </th>
        <td>
          <input id="sharepress_og_locale" name="<?php echo sp_get_opt_name('og_locale') ?>" type="text" value="<?php echo esc_attr(sp_get_og_locale()) ?>" placeholder="en_US" style="width:7em;" />
          &nbsp; &nbsp; <span class="description">Enter the proper locale for your site.
            &nbsp; <a href="https://developers.facebook.com/docs/internationalization/#locales" target="_blank">Learn more &rarr;</a></span>
        </td>
      </tr>    
      <tr>
        <th>
          <label for="fb_publisher_url">article:publisher</label>
        </th>
        <td>
          <input type="text" class="regular-text" name="<?php echo sp_get_opt_name('og_article_publisher') ?>" id="fb_publisher_url" value="<?php echo esc_attr(sp_get_og_article_publisher()) ?>" placeholder="http://www.facebook.com/your-facebook-page">
          &nbsp; &nbsp; <span class="description">
            Set this to the URL of your Facebook page. <a href="https://developers.facebook.com/blog/post/2013/06/19/platform-updates--new-open-graph-tags-for-media-publishers-and-more/" target="_blank">Learn more &rarr;</a>
          </span>
        </td>
      </tr>
      
    </table>

    <?php submit_button(); ?>

  </form>

</div>