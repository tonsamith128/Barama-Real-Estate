/* BARAMA_POLICY_CENTER_FIRST_VISIT_GUIDANCE_V100 */
(()=>{'use strict';

const dialog=document.querySelector('[data-pc-guide-dialog]');
const trigger=document.querySelector('[data-pc-guide-open]');

if(!dialog||typeof dialog.showModal!=='function') return;

const kind=dialog.dataset.pcGuideKind==='demo'?'demo':'general';
const storageKey='barama.policy-center.publishing-guide.v100.'+kind;
const alreadySeen=()=>{
  try{return window.localStorage.getItem(storageKey)==='1';}
  catch(_error){return false;}
};
const rememberDismissal=()=>{
  try{window.localStorage.setItem(storageKey,'1');}
  catch(_error){/* Browsing with storage disabled must not block editing. */}
};
const open=()=>{
  if(!dialog.open) dialog.showModal();
};
const close=()=>{
  if(dialog.open) dialog.close();
};

dialog.querySelectorAll('[data-pc-guide-dismiss]').forEach(button=>{
  button.addEventListener('click',close);
});
// Native dialog handles keyboard focus trapping and Escape.
dialog.addEventListener('close',rememberDismissal);
dialog.addEventListener('click',event=>{
  if(event.target===dialog) close();
});
trigger?.addEventListener('click',open);

// Publishing errors need to remain immediately visible and actionable.
// Never obscure a rejected POST or a legacy compliance/save error.
const errorOnPage=!!document.querySelector(
  '[data-pc-publish-feedback],.pc-error,.pc-warning[role="alert"]'
);
if(!errorOnPage&&!alreadySeen()){
  open();
}

})();
