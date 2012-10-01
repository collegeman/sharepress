/*
 * jQuery postMessage - v0.5 - 9/11/2009
 * http://benalman.com/projects/jquery-postmessage-plugin/
 * 
 * Copyright (c) 2009 "Cowboy" Ben Alman
 * Dual licensed under the MIT and GPL licenses.
 * http://benalman.com/about/license/
 */
(function($){var g,d,j=1,a,b=this,f=!1,h="postMessage",e="addEventListener",c,i=b[h]&&!$.browser.opera;$[h]=function(k,l,m){if(!l){return}k=typeof k==="string"?k:$.param(k);m=m||parent;if(i){m[h](k,l.replace(/([^:]+:\/\/[^\/]+).*/,"$1"))}else{if(l){m.location=l.replace(/#.*$/,"")+"#"+(+new Date)+(j++)+"&"+k}}};$.receiveMessage=c=function(l,m,k){if(i){if(l){a&&c();a=function(n){if((typeof m==="string"&&n.origin!==m)||($.isFunction(m)&&m(n.origin)===f)){return f}l(n)}}if(b[e]){b[l?e:"removeEventListener"]("message",a,f)}else{b[l?"attachEvent":"detachEvent"]("onmessage",a)}}else{g&&clearInterval(g);g=null;if(l){k=typeof m==="number"?m:typeof k==="number"?k:100;g=setInterval(function(){var o=document.location.hash,n=/^#?\d+&/;if(o!==d&&n.test(o)){d=o;l({data:o.replace(n,"")})}},k)}}}})(jQuery);

//fgnass.github.com/spin.js#v1.2.6
!function(e,t,n){function o(e,n){var r=t.createElement(e||"div"),i;for(i in n)r[i]=n[i];return r}function u(e){for(var t=1,n=arguments.length;t<n;t++)e.appendChild(arguments[t]);return e}function f(e,t,n,r){var o=["opacity",t,~~(e*100),n,r].join("-"),u=.01+n/r*100,f=Math.max(1-(1-e)/t*(100-u),e),l=s.substring(0,s.indexOf("Animation")).toLowerCase(),c=l&&"-"+l+"-"||"";return i[o]||(a.insertRule("@"+c+"keyframes "+o+"{"+"0%{opacity:"+f+"}"+u+"%{opacity:"+e+"}"+(u+.01)+"%{opacity:1}"+(u+t)%100+"%{opacity:"+e+"}"+"100%{opacity:"+f+"}"+"}",a.cssRules.length),i[o]=1),o}function l(e,t){var i=e.style,s,o;if(i[t]!==n)return t;t=t.charAt(0).toUpperCase()+t.slice(1);for(o=0;o<r.length;o++){s=r[o]+t;if(i[s]!==n)return s}}function c(e,t){for(var n in t)e.style[l(e,n)||n]=t[n];return e}function h(e){for(var t=1;t<arguments.length;t++){var r=arguments[t];for(var i in r)e[i]===n&&(e[i]=r[i])}return e}function p(e){var t={x:e.offsetLeft,y:e.offsetTop};while(e=e.offsetParent)t.x+=e.offsetLeft,t.y+=e.offsetTop;return t}var r=["webkit","Moz","ms","O"],i={},s,a=function(){var e=o("style",{type:"text/css"});return u(t.getElementsByTagName("head")[0],e),e.sheet||e.styleSheet}(),d={lines:12,length:7,width:5,radius:10,rotate:0,corners:1,color:"#000",speed:1,trail:100,opacity:.25,fps:20,zIndex:2e9,className:"spinner",top:"auto",left:"auto"},v=function m(e){if(!this.spin)return new m(e);this.opts=h(e||{},m.defaults,d)};v.defaults={},h(v.prototype,{spin:function(e){this.stop();var t=this,n=t.opts,r=t.el=c(o(0,{className:n.className}),{position:"relative",width:0,zIndex:n.zIndex}),i=n.radius+n.length+n.width,u,a;e&&(e.insertBefore(r,e.firstChild||null),a=p(e),u=p(r),c(r,{left:(n.left=="auto"?a.x-u.x+(e.offsetWidth>>1):parseInt(n.left,10)+i)+"px",top:(n.top=="auto"?a.y-u.y+(e.offsetHeight>>1):parseInt(n.top,10)+i)+"px"})),r.setAttribute("aria-role","progressbar"),t.lines(r,t.opts);if(!s){var f=0,l=n.fps,h=l/n.speed,d=(1-n.opacity)/(h*n.trail/100),v=h/n.lines;(function m(){f++;for(var e=n.lines;e;e--){var i=Math.max(1-(f+e*v)%h*d,n.opacity);t.opacity(r,n.lines-e,i,n)}t.timeout=t.el&&setTimeout(m,~~(1e3/l))})()}return t},stop:function(){var e=this.el;return e&&(clearTimeout(this.timeout),e.parentNode&&e.parentNode.removeChild(e),this.el=n),this},lines:function(e,t){function i(e,r){return c(o(),{position:"absolute",width:t.length+t.width+"px",height:t.width+"px",background:e,boxShadow:r,transformOrigin:"left",transform:"rotate("+~~(360/t.lines*n+t.rotate)+"deg) translate("+t.radius+"px"+",0)",borderRadius:(t.corners*t.width>>1)+"px"})}var n=0,r;for(;n<t.lines;n++)r=c(o(),{position:"absolute",top:1+~(t.width/2)+"px",transform:t.hwaccel?"translate3d(0,0,0)":"",opacity:t.opacity,animation:s&&f(t.opacity,t.trail,n,t.lines)+" "+1/t.speed+"s linear infinite"}),t.shadow&&u(r,c(i("#000","0 0 4px #000"),{top:"2px"})),u(e,u(r,i(t.color,"0 0 1px rgba(0,0,0,.1)")));return e},opacity:function(e,t,n){t<e.childNodes.length&&(e.childNodes[t].style.opacity=n)}}),function(){function e(e,t){return o("<"+e+' xmlns="urn:schemas-microsoft.com:vml" class="spin-vml">',t)}var t=c(o("group"),{behavior:"url(#default#VML)"});!l(t,"transform")&&t.adj?(a.addRule(".spin-vml","behavior:url(#default#VML)"),v.prototype.lines=function(t,n){function s(){return c(e("group",{coordsize:i+" "+i,coordorigin:-r+" "+ -r}),{width:i,height:i})}function l(t,i,o){u(a,u(c(s(),{rotation:360/n.lines*t+"deg",left:~~i}),u(c(e("roundrect",{arcsize:n.corners}),{width:r,height:n.width,left:n.radius,top:-n.width>>1,filter:o}),e("fill",{color:n.color,opacity:n.opacity}),e("stroke",{opacity:0}))))}var r=n.length+n.width,i=2*r,o=-(n.width+n.length)*2+"px",a=c(s(),{position:"absolute",top:o,left:o}),f;if(n.shadow)for(f=1;f<=n.lines;f++)l(f,-2,"progid:DXImageTransform.Microsoft.Blur(pixelradius=2,makeshadow=1,shadowopacity=.3)");for(f=1;f<=n.lines;f++)l(f);return u(t,a)},v.prototype.opacity=function(e,t,n,r){var i=e.firstChild;r=r.shadow&&r.lines||0,i&&t+r<i.childNodes.length&&(i=i.childNodes[t+r],i=i&&i.firstChild,i=i&&i.firstChild,i&&(i.opacity=n))}):s=l(t,"animation")}(),typeof define=="function"&&define.amd?define(function(){return v}):e.Spinner=v}(window,document);

!function($) {

  $.fn.spin = function(opts) {
    this.each(function() {
      var $this = $(this),
          data = $this.data();

      if (data.spinner) {
        data.spinner.stop();
        delete data.spinner;
      }
      if (opts !== false) {
        data.spinner = new Spinner($.extend({color: $this.css('color')}, opts)).spin(this);
      }
    });
    return this;
  };

  // Moves the select elements to the highest z-order, and makes them visible
  $.fn.front = function(reveal) {
    if (reveal === undefined) {
      reveal = 'show';
    }

    var top = Math.max.apply(null, $.map($('body > *'), function(el, n) {
      var pos = $(el).css('position');
      if ( pos == 'absolute' || pos == 'fixed' ) {
        return parseInt($(el).css('z-index')) || 1;
      } else {
        return 0;
      }
    }));
    
    this.each(function(i, e) {
      $(e).css('z-index', ++top);
      if (reveal) {
        $(e)[reveal](reveal == 'fadeIn' ? 'slow' : null);
      }
    });
    
    return this;
  };


  var app = {

    _media: false,
    _tweetMode: false,
    _dirty: false,

    setMedia: function(id) {
      this._dirty = true;
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

    updateTweetMode: function() {
      var $share = $('#share');
      this._tweetMode = $share.find('.selected[data-profile-service="twitter"]').size() > 0;
      $share[this._tweetMode ? 'addClass' : 'removeClass']('is_tweet');
    },

    close: function() {
      // TODO: cleanup
      $('#share').modal('hide');
      this.wait(false);
      $.postMessage('sp-buf-close', _sp.host, parent);
    },

    post: function(now) {
      this.error(false);

      var $form = $('#share');
      var profile_ids = [];
      $.each( $form.find('.selected[data-profile-id]'), function(i, profile) {
        profile_ids.push($(profile).data('profile-id'));
      });
      
      if (!profile_ids.length) {
        this.error('<b>Oops!</b> Please choose one or more profiles to post to.');
        return false;
      }

      var $text = $('#text');
      $text.val($text.val().trim()).trigger('keyup');

      if (this._tweetMode && $text.val().length > 140) {
        this.error('<b>Oops!</b> Your post is too long for Twitter.');
        return false;
      }

      if ($text.val().length < 1) {
        this.error('<b>Oops!</b> Please write something first.');
        return false; 
      }

      this.wait();
      $.post(_sp.api + 'updates/create', {
        'text': $text.val(),
        'profile_ids': profile_ids,
        'now': now,
        'post_id': _sp.post_id
      }, function() {
        app.close();
      });
    },

    error: function(msg) {
      var $error = $('#share .error');
      clearTimeout(this._clearErrorTimeout);
      $error.stop();
      if (msg === false) {
        $error.slideUp();
      } else {
        $error.show().find('span').html(msg);
        this._clearErrorTimeout = setTimeout(function() {
          app.error(false);
        }, 5000);
      }
    },

    showShare: function() {
      $('.modal').modal('hide');
      $('#share').modal('show');
    },

    showUpload: function() {
      $('#upload iframe').attr('src', $('#upload iframe').data('target'));
      $('#share').modal('hide');
      $('#upload').modal({ 
        keyboard: false,
        backdrop: 'static'
      }).modal('show');
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
    },

    wait: function(toggle) {
      if (toggle !== false) {
        $('#wait').front().spin();
      } else {
        $('#wait').spin(false).hide();
      }
    }

  };

  window.send_to_editor = $.proxy(app.onUpload, app);

  $(function() {

    var $share = $('#share');
    
    $share.find('[data-dismiss="host"]').click(function() {
      if (!app._dirty || confirm('Are you sure you want to cancel?')) {
        app.close();
      }
      return false;
    });

    $share.find('[data-profile-id]').click(function() {
      app._dirty = true;
      $this = $(this);
      $this.toggleClass('selected');
      app.updateTweetMode();
      return false;
    }).tooltip();

    $('[data-action="remove-media"]').click(function() {
      app.removeMedia();
      return false;
    });

    $('[data-toggle-media]').click(function() {
      var $this = $(this), action = $this.data('next-action') || $this.data('toggle-media');
      if (action === 'hide') {
        $('#share-media').animate({ height: 100 });
        $this.html('&plus;');
        $this.data('next-action', 'show');
      } else if (action === 'show') {
        $('#share-media').css({ height: 'auto' });
        $this.html('&minus;');
        $this.data('next-action', 'hide');
      }
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

    $('[data-dismiss="error"]').click(function() {
      $(this).parent().hide();
      return false;
    });

    $('[data-action="post-now"]').click(function() {
      app.post(true);
      return false;
    });

    $('[data-action="sharepress"]').click(function() {
      app.post();
      return false;
    });

    var $limit = $share.find('.length-limit');

    $('#text').keyup(function() {
      app._dirty = true;
      var $this = $(this);
      var limit = 140 - $this.val().length;
      $limit.text(limit);
      $limit[limit < 0 ? 'addClass' : 'removeClass']('over');
    });

    $share.modal({
      keyboard: false,
      backdrop: 'static'
    });

    $share.draggable({
      'axis': 'y',
      'containment': 'document'
    });

    $('[rel="tooltip"]').tooltip();

    app.updateTweetMode();

    $('#text').trigger('keyup');

    // we have to reset this because we just triggered keyup
    app._dirty = false;

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