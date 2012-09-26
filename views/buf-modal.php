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
          <?php foreach(buf_get_profiles() as $profile) { ?>
            <li class="profile">
              <a href="#" class="thumbnail" data-profile-service="<?php echo $profile->service ?>" data-profile-id="<?php echo $profile->id ?>" title="<?php echo esc_attr($profile->formatted_username) ?>">
                <img src="<?php echo $profile->avatar ?>">
                <span class="<?php echo $profile->service ?>"></span>
              </a>
            </li>
          <?php } ?>
          <li class="profile btn-group add-profile">
            <a href="#" class="thumbnail" data-toggle="dropdown" title="Add new Profile">
              <img src="<?php echo plugins_url('img/add-new-account.png', SHAREPRESS) ?>" alt="">
            </a>
            <ul class="dropdown-menu" role="menu" aria-labelledby="dropdownMenu">
              <?php do_action('sp_add_new_account_menu') ?>
              <?php if (!buf_has_keys('linkedin')) { ?>
                <li class="divider"></li>
                <?php if (!buf_has_keys('linkedin')) { ?>
                  <li><a tabindex="-1" href="#">Get LinkedIn Support</a></li>
                <?php } ?>
              <?php } ?>
            </ul>
          </li>
        </ul>
      </div>
      <div id="share-media" class="hide modal-section border-bottom">
        <div class="buttons">
          <button type="button" class="close" data-action="remove-media">&times;</button>
          <button type="button" class="close" data-toggle-media="hide">&minus;</button>
        </div>
        <img src="">
      </div>
      <div class="modal-body">
        <textarea id="text" tabindex="1" placeholder="Write a post here, then Share Now or SharePress it later..."><?php echo trim(esc_html($text)) ?></textarea>
        <span class="length-limit">140</span>
      </div>
      <div class="error">
        <span></span>
        <button type="button" class="close" data-dismiss="error">&times;</button>
      </div>
      <div class="modal-footer">
        <div class="pull-left">
          <a href="#" tabindex="3" class="btn" data-action="upload"><i class="icon-picture"></i></a>
        </div>
        <button type="button" href="#" tabindex="4" class="btn" data-action="post-now">Share Now</button>
        <button type="button" href="#" tabindex="2" class="btn btn-primary" data-action="sharepress"><i class="icon-time icon-white"></i> SharePress</button>
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
      _sp = <?php 
        echo json_encode(array(
          'api' => site_url('/sp/1/'),
          'host' => filter_var($_REQUEST['host'], FILTER_VALIDATE_URL)
        ));
      ?>;
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
    <script src="<?php echo plugins_url('js/bootstrap.min.js', SHAREPRESS); ?>"></script>
    <script src="<?php echo plugins_url('js/modal.js', SHAREPRESS); ?>"></script>
    <div id="wait"></div>
  </body>
</html>