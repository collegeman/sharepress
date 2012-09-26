/*
 * jQuery postMessage - v0.5 - 9/11/2009
 * http://benalman.com/projects/jquery-postmessage-plugin/
 * 
 * Copyright (c) 2009 "Cowboy" Ben Alman
 * Dual licensed under the MIT and GPL licenses.
 * http://benalman.com/about/license/
 */
(function($){var g,d,j=1,a,b=this,f=!1,h="postMessage",e="addEventListener",c,i=b[h]&&!$.browser.opera;$[h]=function(k,l,m){if(!l){return}k=typeof k==="string"?k:$.param(k);m=m||parent;if(i){m[h](k,l.replace(/([^:]+:\/\/[^\/]+).*/,"$1"))}else{if(l){m.location=l.replace(/#.*$/,"")+"#"+(+new Date)+(j++)+"&"+k}}};$.receiveMessage=c=function(l,m,k){if(i){if(l){a&&c();a=function(n){if((typeof m==="string"&&n.origin!==m)||($.isFunction(m)&&m(n.origin)===f)){return f}l(n)}}if(b[e]){b[l?e:"removeEventListener"]("message",a,f)}else{b[l?"attachEvent":"detachEvent"]("onmessage",a)}}else{g&&clearInterval(g);g=null;if(l){k=typeof m==="number"?m:typeof k==="number"?k:100;g=setInterval(function(){var o=document.location.hash,n=/^#?\d+&/;if(o!==d&&n.test(o)){d=o;l({data:o.replace(n,"")})}},k)}}}})(jQuery);

!function($) {

  var app = {

    _media: false,

    setMedia: function(id) {
      $.ajax({
        type: 'GET',
        url: _sp.api + 'media/' + id,
        dataType: 'json',
        success: $.proxy(function(media) {
          this._media = media;
          $('#share-media').show().find('img').attr('src', media.full.url);
        }, this),
        error: $.proxy(function() {

        }, this)
      });
    },

    removeMedia: function() {
      this._media = false;
      $('#share-media').hide();
    },

    close: function() {
      // TODO: cleanup

      $.postMessage('sp-buf-close', _sp.host, parent);
    },

    showShare: function() {
      $('.modal').modal('hide');
      $('#share').modal('show');
    },

    showUpload: function() {
      $('#upload iframe').attr('src', $('#upload iframe').data('target'));
      $('.modal').modal('hide');
      $('#upload').modal('show');
    },

    onUpload: function(html) {
      var data = $('img', html).attr('class').match(/wp-image-(\d+)/);
      if (data.length) {
        var id = data[1];
        this.setMedia(id);
      } else {
        // TODO: pretty serious error here
      }
      this.showShare();
    }

  };

  window.send_to_editor = $.proxy(app.onUpload, app);

  $(function() {
    
    $('#share [data-dismiss="host"]').click(function() {
      if (confirm('Are you sure you want to cancel?')) {
        app.close();
      }
      return false;
    });

    $('#share [data-profile-id]').click(function() {
      $this = $(this);
      $this.toggleClass('selected');
      return false;
    }).tooltip();

    $('[data-action="remove-media"]').click(function() {
      app.removeMedia();
      return false;
    });

    $('[data-action="upload"]').click(function() {
      app.showUpload();
      return false;
    });

    $('[data-action="share"]').click(function() {
      app.showShare();
      return false;
    });

    $('#share').modal().find('textarea').focus();

    $('[rel="tooltip"]').tooltip();

    !function() {
      var i = setInterval(function() {
        if (!$('.modal:visible').size()) {
          clearInterval(i);
          app.close();
        }
      }, 250); 
    }();

  });

}(jQuery);