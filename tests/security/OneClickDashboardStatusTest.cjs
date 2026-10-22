'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const code = fs.readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'barama-oneclick-status-v100.js'), 'utf8');
const statuses = [
  'not_started', 'checking', 'backing_up', 'installing', 'verifying',
  'completed', 'blocked', 'failed', 'recovery_review_required', 'unavailable'
];
function fixture(opts={}) {
  const elements={state:{textContent:''}, worker:{textContent:''}, details:{textContent:''}};
  const host={
    getAttribute(k) {return ({'data-oneclick-endpoint':opts.endpoint??'/dashboard/system/auto-updater/status',
      'data-oneclick-initial-worker-ready':opts.worker??'0',
      'data-oneclick-initial-pending':opts.pending??'0',
      'data-oneclick-initial-state':opts.initial??'not_started'})[k]??null;},
    querySelector(s) {return ({'[data-oneclick-state]':elements.state,
      '[data-oneclick-worker]':elements.worker,
      '[data-oneclick-stage-message]':elements.details})[s]||null;}
  };
  const calls=[];const reloads=[];const intervals=[];const events=[];
  const response=opts.response??{schema:'barama-oneclick-dashboard-status-v1',state:'checking',worker_ready:false,pending:false};
  const doc={hidden:false,querySelector(){return opts.noHost?null:host;},addEventListener(k,fn){events.push({k,fn});}};
  const win={setInterval(fn,ms){intervals.push({fn,ms});},location:{reload(){reloads.push(true);}}};
  const fetcher=async (url, settings) => {calls.push({url,settings}); return {ok:opts.httpOk??true,json:async()=>response};};
  vm.runInNewContext(code,{document:doc,window:win,fetch:fetcher}, {timeout:1000});
  return {elements,calls,reloads,intervals,events,doc};
}
const flush = () => new Promise(resolve=>setImmediate(resolve));
for(const state of statuses) {
  test('initial allowlisted state: '+state, async()=>{
    const f=fixture({initial:state,response:{schema:'barama-oneclick-dashboard-status-v1',state,worker_ready:false,pending:false}});
    await flush();
    assert.equal(f.elements.state.textContent,state.replaceAll('_',' '));
    assert.ok(f.elements.details.textContent.length>20);
    assert.equal(f.reloads.length,0);
    assert.equal(f.intervals[0].ms,5000);
  });
}
test('recovery required prominently blocks retries',async()=>{
  const f=fixture({initial:'recovery_review_required',response:{schema:'barama-oneclick-dashboard-status-v1',state:'recovery_review_required',worker_ready:false,pending:false}});
  await flush();assert.match(f.elements.details.textContent,/Do not request another update/);
});
test('completed is explicitly previous job, not a new active install',async()=>{
  const f=fixture({initial:'completed',response:{schema:'barama-oneclick-dashboard-status-v1',state:'completed',worker_ready:false,pending:false}});
  await flush();assert.match(f.elements.details.textContent,/last update job completed/);assert.equal(f.reloads.length,0);
});
test('worker readiness transition reloads server-rendered gate',async()=>{
  const f=fixture({initial:'not_started',response:{schema:'barama-oneclick-dashboard-status-v1',state:'not_started',worker_ready:true,pending:false}});
  await flush();assert.equal(f.reloads.length,1);assert.match(f.elements.worker.textContent,/online/);
});
test('queue status transition reloads',async()=>{
  const f=fixture({response:{schema:'barama-oneclick-dashboard-status-v1',state:'checking',worker_ready:false,pending:true}});
  await flush();assert.equal(f.reloads.length,1);
});
test('terminal result transition reloads',async()=>{
  const f=fixture({initial:'verifying',pending:'0',response:{schema:'barama-oneclick-dashboard-status-v1',state:'completed',worker_ready:false,pending:false}});
  await flush();assert.equal(f.reloads.length,1);
});
test('invalid schema and field types cannot change status or reload',async()=>{
  for(const response of [
    {schema:'unknown',state:'completed',worker_ready:true,pending:false},
    {schema:'barama-oneclick-dashboard-status-v1',state:'root_override',worker_ready:true,pending:false},
    {schema:'barama-oneclick-dashboard-status-v1',state:'completed',worker_ready:'true',pending:false},
    {schema:'barama-oneclick-dashboard-status-v1',state:'completed',worker_ready:false,pending:1}
  ]){const f=fixture({response});await flush();assert.equal(f.reloads.length,0);assert.equal(f.elements.state.textContent,'not started');}
});
test('trusted poll is read-only same-origin, no-store, no redirects',async()=>{
  const f=fixture();await flush();assert.equal(f.calls.length,1);
  assert.equal(f.calls[0].settings.method,'GET');assert.equal(f.calls[0].settings.credentials,'same-origin');
  assert.equal(f.calls[0].settings.cache,'no-store');assert.equal(f.calls[0].settings.redirect,'error');
  assert.equal(f.calls[0].url,'/dashboard/system/auto-updater/status');
});
test('cross-origin/protocol-relative endpoint denied',()=>{
  for(const endpoint of ['https://wrong.example/status','//wrong.example/status','']){
    const f=fixture({endpoint});assert.equal(f.calls.length,0);assert.equal(f.intervals.length,0);
  }
});
test('invalid initial readiness and unknown initial status denied',()=>{
  for(const opts of [{worker:'yes'},{pending:'yes'},{initial:'root_override'}]){
    const f=fixture(opts);assert.equal(f.calls.length,0);
  }
});
test('no host leaves other dashboard pages untouched',()=>{
  const f=fixture({noHost:true});assert.equal(f.calls.length,0);
});
test('source never submits or enables the update button',()=>{
  assert.doesNotMatch(code,/\.submit\s*\(/);assert.doesNotMatch(code,/\.disabled\s*=/);
  assert.doesNotMatch(code,/(?:POST|fetch\s*\(\s*['"]\/dashboard\/system\/auto-updater\/update-now)/);
});
