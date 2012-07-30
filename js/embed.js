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

  var iframe, src = _sp.api + 'modal?host=' + _sp.host;

  window.sharepress = function() {
    if (!iframe) {
      $('body').append( iframe = $('<iframe style="width:100%; height:100%; position:fixed; top: 0; left: 0; z-index:1000001" allowTransparency="true" src="' + src + '"></iframe>') );
    }
  }

  $.receiveMessage(function(e) {
    if ('sp-buf-close' === e.data) {
      iframe.remove();
      iframe = false;
    }
  });

  $(function() {
    $('#wp-admin-bar-sp-buf-schedule a').click(function() {
      sharepress();
      return false;
    });
  });

}(jQuery);

