<?php if (!defined('ABSPATH')) die("Cheatin', uh?"); ?>

<div class="wrap">
  <?php screen_icon() ?>
  <div class="pagetitle">
    <h2>SharePress</h2>
  </div>

  <div class="container">
    <div class="rail">
      
      <div class="panel">
        <div class="inner">
          <iframe class="autoscale" scrolling="no" src="<?php echo admin_url('options-general.php') ?>?page=sharepress-3.0&settings=general&noheader=1" onload="SharePress.autoscale(this);"></iframe>
        </div>
      </div>

      <div rel="3" class="panel">
        <div class="inner">
          <h3>
            <span>Facebook</span>
          </h3>
        </div>
      </div>

    
      <div style="clear:both;"></div>
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

  var saveSearch = function(h3, e) {
    h3.find('.search-editor').hide();
    h3.find('label').show();  
    e.stopPropagation();
  }

  var removeSearch = function() {
    
  }

  $('.wrap .rail .panel .title').live('click', function() {
    $this = $(this);
    $this.find('label').hide();
    $this.find('.search-editor').show().submit(function(e) {
      saveSearch($this, e);
      return false;
    }).find('input[type="text"]').focus();
    $this.find('.button').click(function(e) {
      saveSearch($this, e);
      return false;
    });
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