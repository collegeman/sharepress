<!doctype html>
<html>
  <head>
    <link rel="stylesheet" href="<?php echo plugins_url('css/bootstrap.min.css', SHAREPRESS) ?>">
    <link rel="stylesheet" href="<?php echo plugins_url('css/modal.css', SHAREPRESS) ?>">
  </head>
  <body>
    <div class="modal hide" id="share">
      <div class="modal-header">
        <button type="button" class="close" rel="tooltip" title="Cancel this post" data-dismiss="host">&times;</button>
        <ul class="thumbnails">
          <li>
            <a href="#" class="thumbnail" data-account="" title="Aaron Collegeman">
              <img src="http://graph.facebook.com/aaroncollegeman/picture" alt="">
              <span class="fb"></span>
            </a>
          </li>
          <li>
            <a href="#" class="thumbnail" data-account="" title="Fat Panda">
              <img src="http://graph.facebook.com/fatpandadev/picture" alt="">
              <span class="fb"></span>
            </a>
          </li>
          <li>
            <a href="#" class="thumbnail" data-account="" title="@collegeman">
              <img src="https://api.twitter.com/1/users/profile_image/collegeman" alt="">
              <span class="twitter"></span>
            </a>
          </li>
          <li>
            <a href="#" class="thumbnail" data-action="add-new-account" title="Add new Account">
              <img src="<?php echo plugins_url('img/add-new-account.png', SHAREPRESS) ?>" alt="">
            </a>
          </li>
        </ul>
      </div>
      <div id="share-media" class="hide modal-section border-bottom">
        <button type="button" class="close" rel="tooltip" title="Remove this media" data-action="remove-media">&times;</button>
        <button type="button" class="close" rel="tooltip" title="Hide" data-action="remove-media">&minus;</button>
        <img src="">
      </div>
      <div class="modal-body">
        <textarea tabindex="1">&quot;Any man who can drive safely while kissing a pretty girl is simply not giving the kiss the attention it deserves.&quot; - Albert Einstein http://bit.ly/P7PCgQ</textarea>
      </div>
      <div class="fb-preview modal-section border-top">
        <div class="media">
          <div class="img">
            <img src="http://i1.squidoocdn.com/resize/squidoo_images/250/draft_lens2738682_8d8d832fd6f39d53ad74feab6567e81a.jpg" width="100">
          </div>
          <div class="bd">
            <div class="title">Aaron Collegeman</div>
            <div class="url">http://www.squidoo.com/aaroncollegeman</div>
            <p>I'm Aaron and I'm the Lead Developer here at Squidoo. (That's right: you're using Squidoo to read this Lens. ) I'm putting this Lens together for a couple of reasons, among them, to introduce myself to the rest of the team here at Squidoo, and to introduce...</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="pull-left">
          <a href="#" tabindex="3" class="btn" data-action="upload"><i class="icon-picture"></i></a>
        </div>
        <a href="#" tabindex="4" class="btn" data-dismiss="host">Post Now</a>
        <a href="#" tabindex="2" class="btn btn-primary" data-dismiss="host"><i class="icon-time icon-white"></i> Add to Schedule</a>
      </div>
    </div>

    <div class="modal hide" id="connect">
    </div>

    <div class="modal hide" id="upload">
      <div class="modal-body">
        <iframe src="#" data-target="http://dev.wp.fatpandadev.com/wp-admin/media-upload.php?post_id="></iframe>
      </div>
      <div class="modal-footer">
        <a href="#" class="btn" data-action="share">Cancel</a>
      </div>
    </div>

    <script>
      _sp = { 
        api: '<?php echo site_url('/sp/1/') ?>',
        host: '<?php echo filter_var($_REQUEST['host'], FILTER_VALIDATE_URL) ?>' 
      };
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
    <script src="<?php echo plugins_url('js/bootstrap.min.js', SHAREPRESS); ?>"></script>
    <script src="<?php echo plugins_url('js/modal.js', SHAREPRESS); ?>"></script>
  </body>
</html>