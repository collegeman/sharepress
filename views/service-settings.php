<div class="wrap">
  <?php screen_icon(); ?>
  <h2><?php echo $client->getName() ?> Settings</h2>   
  <?php if ($error = sp_flash('error')) { ?>
    <div id="message" class="error fade">
      <p><?php echo $error->get_error_message() ?> (<?php echo $error->get_error_code() ?>)</p>
    </div>
  <?php } ?>
  <form method="post" action="<?php echo admin_url('options.php') ?>">
    <input type="hidden" name="sp_service" value="<?php echo $service ?>">
    <?php 
      settings_fields($option_group);
      do_settings_sections('sp-settings');
    ?>
    <p class="submit">
      <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
      <?php if (!$client->has_errors) { ?>
        <a class="button" href="<?php echo site_url("/sp/1/auth/{$service}/profiles") ?>">Add Profile &rarr;</a>
      <?php } ?>
    </p>
  </form>
</div>