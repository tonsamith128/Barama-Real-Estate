/* Policy Center first-visit guide: executable zero-dependency browser behavior fixture. */
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const root=require('node:path').resolve(__dirname,'..');
const script=fs.readFileSync(root+'/assets/js/policy-center-guidance-v100.js','utf8');
const view=fs.readFileSync(root+'/application/resources/views/workspace/policy-builder.php','utf8');
const css=fs.readFileSync(root+'/assets/css/policy-center-guidance-v100.css','utf8');

assert.equal(view.includes('class="pc-legal-review"'),false,'permanent notice bars must be removed');
assert.match(view,/<dialog id="pc-policy-publishing-guide"/);
assert.ok(view.indexOf('</form>')<view.indexOf('<dialog id="pc-policy-publishing-guide"'),'modal outside save form');
assert.match(view,/data-pc-guide-open/);
assert.match(view,/data-pc-guide-kind=/);
assert.match(view,/asset\('js\/policy-center-guidance-v100\.js'\)/);
assert.match(view,/asset\('css\/policy-center-guidance-v100\.css'\)/);
assert.match(view,/Review your imported legal template/); // Optional content exists only in the dialog guidance text.
assert.match(view,/data-pc-publish-feedback/); // Actual errors remain visible on page.
assert.match(css,/\.pc-guide-dialog::backdrop/);
assert.match(css,/focus-visible/);

class Element {
  constructor(){this.handlers={};this.dataset={};this.open=false;this.calls=0;}
  addEventListener(event,cb){(this.handlers[event]??=[]).push(cb);}
  emit(event,arg={}){for(const handler of this.handlers[event]||[])handler(arg);}
  click(arg={}){this.emit('click',arg);}
  showModal(){this.open=true;this.calls++;}
  close(){this.open=false;this.emit('close');}
}
function setup({kind='general',blocked=false,store=new Map(),unsupported=false,storageDenied=false}={}){
  const dialog=new Element();
  const open=new Element();
  const x=new Element();
  const confirm=new Element();
  dialog.dataset.pcGuideKind=kind;
  dialog.querySelectorAll=selector=>{
    assert.equal(selector,'[data-pc-guide-dismiss]');
    return [x,confirm];
  };
  if(unsupported)dialog.showModal=undefined;
  const localStorage={
    getItem:key=>{if(storageDenied)throw Error('denied');return store.get(key)||null;},
    setItem:(key,value)=>{if(storageDenied)throw Error('denied');store.set(key,value);}
  };
  const document={
    querySelector:selector=>{
      if(selector==='[data-pc-guide-dialog]')return dialog;
      if(selector==='[data-pc-guide-open]')return open;
      if(selector.includes('[data-pc-publish-feedback]'))return blocked?{}:null;
      throw Error('Unknown query '+selector);
    }
  };
  vm.runInNewContext(script,{document,window:{localStorage}}, {filename:'policy-center-guidance-v100.js'});
  return {dialog,open,x,confirm,store};
}
const store=new Map();
const first=setup({kind:'general',store});
assert.equal(first.dialog.calls,1,'first general visit opens dialog');
assert.equal(store.size,0,'opening guide does not preemptively mark as dismissed');
first.x.click();
assert.equal(first.dialog.open,false,'X dismisses dialog');
assert.equal(store.get('barama.policy-center.publishing-guide.v100.general'),'1','dismissal persists');
const next=setup({kind:'general',store});
assert.equal(next.dialog.calls,0,'no repeat general popups');
next.open.click();
assert.equal(next.dialog.calls,1,'manual publishing guide can always reopen');
next.confirm.click();
assert.equal(next.dialog.open,false,'Got it closes dialog');
const demo=setup({kind:'demo',store});
assert.equal(demo.dialog.calls,1,'first imported-template guide is separate from general guide');
demo.dialog.close();
assert.equal(setup({kind:'demo',store}).dialog.calls,0,'demo guide dismissed once');
const blockedStore=new Map();
const blocked=setup({kind:'demo',blocked:true,store:blockedStore});
assert.equal(blocked.dialog.calls,0,'do not obscure a publication failure');
assert.equal(blockedStore.size,0,'skipped error page does not mark guide dismissed');
blocked.open.click();
assert.equal(blocked.dialog.calls,1,'user may manually open guide after error');
blocked.dialog.emit('click',{target:blocked.dialog});
assert.equal(blocked.dialog.open,false,'backdrop dismisses');
assert.equal(blockedStore.size,1,'manual dismiss stores preference');
assert.equal(setup({unsupported:true}).dialog.calls,0,'unsupported browsers do not crash');
const denied=setup({storageDenied:true});
assert.equal(denied.dialog.calls,1,'blocked localStorage must not block editing');
denied.x.click();
console.log('POLICY_CENTER_FIRST_VISIT_GUIDANCE_V100=PASS');
