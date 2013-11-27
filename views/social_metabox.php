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
    height:76px;
    background:#e6e6e6;
    overflow:hidden;
    position:relative;
  }
</style>
<div class="social_meta">
  <form action="">
    <?/*
    og:type
    <select name="social:type" id="social_type">
      <option value="article">article</option>
      <option value="movie">movie</option>
      <option value="music">music</option>
      <option value="book">book</option>
    </select>
    */
    ?>
    <input placeholder="Social Title" type="text" data-value="social:title" name="socialmeta[title]" id="social_title">
    <div class="shared_img_container">
      <input type="radio" name="shared_image" value="featured" id="shared_image_0" checked><label for="shared_image_0"> Featured</label>  <input type="radio" name="shared_image" value="custom" id="shared_image_1"><label for="shared_image_1"> Custom</label>
      <div class="shared_img_thumb">
      </div>
      <input type="hidden" data-value="social:image" name="socialmeta[image]">
    </div>
    <textarea placeholder="Social Description" data-value="social:description" name="socialmeta[description]" id="social_description" cols="30" rows="5"></textarea>
    <button class="button" data-action="save">Save</button>
  </form>
</div>
<script>
  jQuery(function($) {
    new sp.views.SocialMetabox({ el: $('#sp_metabox_og') });
  });
</script>
