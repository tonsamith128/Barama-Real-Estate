/* BARAMA_SESSION_EXPIRED_PAGE_JS_V100 */
(function(){
  'use strict';
  document.addEventListener('DOMContentLoaded',function(){
    var btn=document.querySelector('[data-barama-session-back]');
    if(!btn) return;
    btn.addEventListener('click',function(){
      if(window.history.length>1){window.history.back();}
      else{window.location.assign('/dashboard/policies');}
    });
  });
}());
