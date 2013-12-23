<?php wp_nonce_field( 'sp_metabox_og', 'sp_metabox_og_nonce' ); ?>
<div class="social_meta">
  <p class="howto">Influence how your posts appear in social media newsfeeds.
    Want more control? Install the <b>Advanced Social Metadata</b> add-on. <a href="<?php echo admin_url('options-general.php?page=sp-addons') ?>" target="_blank">Learn more &rarr;</a></p>
  <div class="shared_img_container">
    <div class="shared_img_thumb">
      <?php if ( !empty($socialmeta['image']) ) { ?>
        <img src="<?php echo esc_attr($socialmeta['image']) ?>" alt="">
      <?php } ?>
      
    </div>
    <a href="#" style="<?php echo (empty($socialmeta['image'])) ? 'display:none;' : null ?>" data-action="remove-social-image">Remove social image</a>
    <a href="#" style="<?php echo (empty($socialmeta['image'])) ? null : 'display:none;' ?>" data-action="set-social-image">Set social image</a>
    <input type="hidden" id="social_image" data-value="social:image" name="social:image" value="<?php echo esc_attr($socialmeta['image']) ?>">
  </div>
  <label for="social_title">Social Title:</label>
  <input placeholder="defaults to the title of your post" type="text" data-value="social:title" name="social:title" id="social_title" value="<?php echo esc_attr($socialmeta['title']) ?>">
  <label for="social_description">Social Description:</label>
  <textarea placeholder="defaults to a snip of your content" data-value="social:description" name="social:description" id="social_description" cols="30" rows="5"><?php echo esc_attr($socialmeta['description']) ?></textarea>
</div>
<script>
  jQuery(function($) {
    new sp.views.SocialMetabox({ el: $('#sp_metabox_og') });
  });
</script>
