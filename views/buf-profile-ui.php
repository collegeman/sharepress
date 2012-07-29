<div id="sp-buf-profile-ui">
  <a class="button lazythickbox" href="#TB_inline?width=480&inlineId=buf-connect-account">Connect Account</a>
  <div id="buf-connect-account" style="display:none;">
    <div class="wrap sp-buf">
      <div id="icon-users" class="icon32"><br></div>
      <h2>Connect an Account</h2>
      <?php if (!buf_has_facebook()) { ?>

      <?php } else { ?>
        <form>
          <a href="#" class="fb_login">Facebook Profile</a>
          <span>Connect your personal Facebook profile.</span>
        </form>
        <form>
          <a href="#" class="fb_login">Facebook Page</a>
          <span>Connect any Facebook page that you manage.</span>
        </form>
      <?php } ?>
    </div>
  </div>
</div>


