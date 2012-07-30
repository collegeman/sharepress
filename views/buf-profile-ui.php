<div id="sp-buf-profile-ui">
  <a class="button lazythickbox" href="#TB_inline?width=480&inlineId=buf-connect-account">Connect Account</a>
  <div id="buf-connect-account" style="display:none;">
    <div class="wrap sp-buf">
      <div id="icon-users" class="icon32"><br></div>
      <h2>Connect an Account</h2>
      <?php if (!buf_has_facebook()) { ?>

      <?php } else { ?>
        <form action="/sp/1/oauth/facebook">
          <input type="hidden" name="redirect_uri" value="<?php echo htmlentities($_REQUEST['redirect_uri']) ?>" />
          <button type="submit" href="#" class="button_fb">Facebook Profile</button>
          <span>Connect your personal Facebook profile.</span>
        </form>
        <form action="/sp/1/oauth/facebook">
          <input type="hidden" name="redirect_uri" value="<?php echo htmlentities($_REQUEST['redirect_uri']) ?>#sp-buf-facebook-pages" />
          <button type="submit" href="#" class="button_fb">Facebook Page</button>
          <span>Connect any Facebook page that you manage.</span>
        </form>
        <form action="/sp/1/oauth/twitter">
          <input type="hidden" name="redirect_uri" value="<?php echo htmlentities($_REQUEST['redirect_uri']) ?>#sp-buf-facebook-pages" />
          <button type="submit" href="#" class="button_twitter">Connect Twitter</button>
          <span>Connect any Twitter account.</span>
        </form>
      <?php } ?>
    </div>
  </div>
</div>


