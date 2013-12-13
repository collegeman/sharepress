<style>
  .social_meta input[type="text"], .social_meta textarea {
    width:100%;
    margin-bottom:10px;
  }
  .shared_img_container {
    margin:6px 0px;
  }
  .shared_img_thumb {
    width:100%;
  }
  .shared_img_thumb img{
    max-width:100%;
  }
</style>
<div class="social_meta">
  <label for="social_title">Title:</label>
  <input placeholder="Social Title" type="text" data-value="social:title" name="social:title" id="social_title" value="<?php echo esc_attr($socialmeta['title']) ?>">
  <label for="social_image">Image:</label>
  <div class="shared_img_container">
    <div class="shared_img_thumb">
      <?php if ( !empty($socialmeta['image']) ) { ?>
        <img src="<?php echo esc_attr($socialmeta['image']) ?>" alt="">
      <?php } ?>
      
    </div>
    <a href="#" style="<?php echo (empty($socialmeta['image'])) ? 'display:none;' : null ?>" data-action="remove-social-image">Remove Social Image</a>
    <a href="#" style="<?php echo (empty($socialmeta['image'])) ? null : 'display:none;' ?>" data-action="set-social-image">Set Social Image</a>
    <input type="hidden" id="social_image" data-value="social:image" name="social:image" value="<?php echo esc_attr($socialmeta['image']) ?>">
  </div>
  <label for="social_description">Description:</label>
  <textarea placeholder="Social Description" data-value="social:description" name="social:description" id="social_description" cols="30" rows="5"><?php echo esc_attr($socialmeta['description']) ?></textarea>
</div>
<script>
  jQuery(function($) {
    new sp.views.SocialMetabox({ el: $('#sp_metabox_og') });
  });
</script>
