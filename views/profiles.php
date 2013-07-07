<div class="wrap">
  <?php screen_icon(); ?>
  <h2><?php echo $client->getName() ?> Profiles</h2>     
  
  <h3>Connected Profiles</h3>

  <ul class="sp_profiles">
    <?php 
      foreach($profiles as $profile) {
        $c = buf_get_client($service, $profile);
        if ($children = $c->profiles()) {
          $subprofiles[$profile->id] = $children;
        }
        ?>
          <li class="profile media">
            <div class="img">
              <img src="<?php echo $profile->avatar ?>">
            </div>
            <div class="bd">
              <h4 class="formatted_username"><a href="<?php echo $profile->link ?>" target="_blank"><?php echo $profile->formatted_username ?></a></h4>
              <button class="button" data-remove="<?php echo $profile->id ?>">Remove</button>
              <?php /*
              <select>
                <option>Only I may post to this profile</option>
                <option>All editors can post to this profile</option>
                <option>All users can post to this profile</option>
              </select> */ ?>
            </div>
            <?php // print_r($profile) ?>
          </li>
        <?php 
      } 
    ?>
  </ul>

  <?php if ($subprofiles) { ?>

    <h3>Available Profiles</h3>

    <ul class="sp_profiles">
      <?php foreach($subprofiles as $parent => $children) { if (!$children) continue; ?>
        <?php foreach($children as $profile) { if (buf_get_profile($profile)) continue; ?>
          <li class="profile media">
            <div class="img">
              <img src="<?php echo $profile->avatar ?>">
            </div>
            <div class="bd">
              <h4 class="formatted_username"><a href="<?php echo $profile->link ?>" target="_blank"><?php echo $profile->formatted_username ?></a></h4>
              <button class="button button-primary" data-parent="<?php echo $parent ?>" data-service_id="<?php echo $profile->service_id ?>">Connect</button>
            </div>
            <?php // print_r($profile) ?>
          </li>
        <?php } ?>
      <?php } ?>
    </ul>

  <?php } ?>

  <p class="submit">
    <?php if (apply_filters('sp_show_settings_screens', true, $service)) { ?>
      <a class="button" href="<?php echo site_url("/sp/1/auth/{$service}/config") ?>">&larr; Settings</a>
    <?php } ?>
    <a class="button button-primary" href="javascript:window.close();">Done</a>
  </p>
</div>

<script>
  !function($) {
    $('[data-remove]').click(function() {
      var $this = $(this);
      $.post('<?php echo site_url('/sp/1/profiles') ?>/' + $this.data('remove'), { _method: 'delete' });
      $this.attr('disabled', true).text('Removed');
      return false;
    });

    $('[data-parent]').click(function() {
      var $this = $(this), data = $this.data();
      $.post('<?php echo site_url('/sp/1/profiles') ?>', data);
      $this.removeClass('button-primary').attr('disabled', true).text('Connected');
      return false;
    });
  }(jQuery);
</script>
