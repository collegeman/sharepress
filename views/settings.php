<?php if (!defined('ABSPATH')) die("Cheatin', uh?"); ?>

<div class="wrap">
  <?php screen_icon() ?>
  <div class="pagetitle">
    <h2>
      SharePress
      <span>a plugin from <a href="http://fatpandadev.com" target="_blank">Fat Panda</a></span>
    </h2>
  </div>

  <form>

    <div class="container wp_bootstrap">
      <div class="rail">
        
        <div class="panel">
          <div class="inner">
            <div class="accordion" id="accordion2">
              <div class="accordion-group">
                <div class="accordion-heading">
                  <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion2" href="#collapseOne">
                    Collapsible Group Item #1
                  </a>
                </div>
                <div id="collapseOne" class="accordion-body in collapse" style="height: auto; ">
                  <div class="accordion-inner">
                    Anim pariatur cliche reprehenderit, enim eiusmod high life accusamus terry richardson ad squid. 3 wolf moon officia aute, non cupidatat skateboard dolor brunch. Food truck quinoa nesciunt laborum eiusmod. Brunch 3 wolf moon tempor, sunt aliqua put a bird on it squid single-origin coffee nulla assumenda shoreditch et. Nihil anim keffiyeh helvetica, craft beer labore wes anderson cred nesciunt sapiente ea proident. Ad vegan excepteur butcher vice lomo. Leggings occaecat craft beer farm-to-table, raw denim aesthetic synth nesciunt you probably haven't heard of them accusamus labore sustainable VHS.
                  </div>
                </div>
              </div>
              <div class="accordion-group">
                <div class="accordion-heading">
                  <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion2" href="#collapseTwo">
                    Collapsible Group Item #2
                  </a>
                </div>
                <div id="collapseTwo" class="accordion-body collapse" style="height: 0px; ">
                  <div class="accordion-inner">
                    Anim pariatur cliche reprehenderit, enim eiusmod high life accusamus terry richardson ad squid. 3 wolf moon officia aute, non cupidatat skateboard dolor brunch. Food truck quinoa nesciunt laborum eiusmod. Brunch 3 wolf moon tempor, sunt aliqua put a bird on it squid single-origin coffee nulla assumenda shoreditch et. Nihil anim keffiyeh helvetica, craft beer labore wes anderson cred nesciunt sapiente ea proident. Ad vegan excepteur butcher vice lomo. Leggings occaecat craft beer farm-to-table, raw denim aesthetic synth nesciunt you probably haven't heard of them accusamus labore sustainable VHS.
                  </div>
                </div>
              </div>
              <div class="accordion-group">
                <div class="accordion-heading">
                  <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion2" href="#collapseThree">
                    Collapsible Group Item #3
                  </a>
                </div>
                <div id="collapseThree" class="accordion-body collapse" style="height: 0px; ">
                  <div class="accordion-inner">
                    Anim pariatur cliche reprehenderit, enim eiusmod high life accusamus terry richardson ad squid. 3 wolf moon officia aute, non cupidatat skateboard dolor brunch. Food truck quinoa nesciunt laborum eiusmod. Brunch 3 wolf moon tempor, sunt aliqua put a bird on it squid single-origin coffee nulla assumenda shoreditch et. Nihil anim keffiyeh helvetica, craft beer labore wes anderson cred nesciunt sapiente ea proident. Ad vegan excepteur butcher vice lomo. Leggings occaecat craft beer farm-to-table, raw denim aesthetic synth nesciunt you probably haven't heard of them accusamus labore sustainable VHS.
                  </div>
                </div>
              </div>
            </div>
            <script>
              jQuery('#accordion2').collapse();
            </script>
          </div>
        </div>

        <!--
        <div class="panel">
          <div class="inner">
            <div class="btn-group">
              <a class="btn dropdown-toggle" data-toggle="dropdown" href="#">Facebook <span class="caret"></span></a>
              <ul class="dropdown-menu">
                <li><a href="#" onclick="jQuery('#sharepress-facebook-settings').modal(); return false;">Run Setup Again...</a></li>
                <li class="divider"></li>
                <li><a href="#">Disconnect</a></li>
              </ul>
            </div>
          </div>
        </div>
      -->

        <div class="add panel">
          <div class="inner">
            <a class="btn" href="#" onclick="jQuery('#sharepress-facebook-settings').modal(); return false;"><i class="icon-share"></i> Connect Facebook</a>
          </div>
        </div>

        <div class="add panel">
          <div class="inner">
            <a class="btn" href="#" onclick="jQuery('#sharepress-twitter-settings').modal(); return false;"><i class="icon-share"></i> Connect Twitter</a>
          </div>
        </div>
      
        <div style="clear:both;"></div>
      </div>

    </div>

    
  </form>

  <div class="wp_bootstrap">

    <div class="modal" id="sharepress-twitter-settings" style="display:none;">
      <div class="modal-header">
        <a class="close" data-dismiss="modal">×</a>
        <h3>Twitter Application</h3>
      </div>
      <div class="modal-body">
        <p>To use Twitter with SharePress, you'll need to configure
          a Twitter application, and provide the keys for that application
          below.</p>
        <input tabindex="1" type="text" class="span3" placeholder="App ID" title="App ID">
        <span tabindex="5" class="help-inline">Need an app? <a href="#">Get one here</a></span>
        <input tabindex="2" type="text" class="span3" placeholder="App Secret" title="App Secret">
      </div>
      <div class="modal-footer">
        <a href="#" tabindex="3" class="btn btn-primary">Save and Connect</a>
        <a href="#" tabindex="4" class="btn" data-dismiss="modal">Cancel</a>
      </div>
    </div>

    <div class="modal" id="sharepress-facebook-settings" style="display:none;">
      <div class="modal-header">
        <a class="close" data-dismiss="modal">×</a>
        <h3>Facebook Application</h3>
      </div>
      <div class="modal-body">
        <p>To use Facebook with SharePress, you'll need to configure
          a Facebook application, and provide the keys for that application
          below.</p>
        <input tabindex="1" type="text" class="span3" placeholder="App ID" title="App ID">
        <span tabindex="5" class="help-inline">Need an app? <a href="#">Get one here</a></span>
        <input tabindex="2" type="text" class="span3" placeholder="App Secret" title="App Secret">
      </div>
      <div class="modal-footer">
        <a href="#" tabindex="3" class="btn btn-primary">Save and Connect</a>
        <a href="#" tabindex="4" class="btn" data-dismiss="modal">Cancel</a>
      </div>
    </div>

  </div>
    
</div>

<script>
(function($) {
  var con = $('.wrap .container');
  var rail = $('.wrap .rail');

  var redraw = function() {
    con.css('height', $(window).height()-con.offset().top-50);
    rail.width(( $('.wrap .panel').outerWidth() + 2 ) * $('.wrap .panel').size());
  }

  $('.wrap .rail .panel').live('mouseenter', function() {
    $(this).find('.remove').show();
  }).live('mouseleave', function() {
    $(this).find('.remove').hide();
  });

 
  redraw();

  (function() {
    var t;
    $(window).resize(function() {
      clearTimeout(t);
      setTimeout(function() {
        redraw();
      }, 100);
    });
  })();
})(jQuery);
</script>