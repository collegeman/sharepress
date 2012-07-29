!function($) {

  $(function() {
    
    var $title = $('#titlediv');
    if ($title.size()) {
      $.get(ajaxurl, { action: 'sp', _view: 'buf-profile-ui' }, function(html) {
        $title.after(html);
        tb_init('a.lazythickbox');
      });
    }

  });

}(jQuery);