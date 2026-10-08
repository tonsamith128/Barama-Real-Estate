/* BARAMA_CHANGE_CONFIRMATION_PAGE_JS_V100 */
(function(){
  'use strict';
  function goBack(){
    if(window.history.length>1){window.history.back();return;}
    window.location.assign('/dashboard/policies');
  }
  document.addEventListener('DOMContentLoaded',function(){
    var back=document.querySelector('[data-barama-confirmation-back]');
    if(back) back.addEventListener('click',goBack);
  });
}());
