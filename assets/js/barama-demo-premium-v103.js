/* BARAMA_DEMO_PREMIUM_V103: layout enhancement, truthful status, screenshot scroll. */
(() => {
  'use strict';
  const root=document.querySelector('#barama-system-tool.bdemo');
  if(!root || root.dataset.premiumV103==='1')return;
  const shell=root.querySelector('.btool-shell'),body=shell?.querySelector('.btool-content');
  if(!shell||!body)return;
  root.dataset.premiumV103='1';root.classList.add('bdemo-v103');shell.classList.add('bdemo-v103-shell');
  // BARAMA_DEMO_FEEDBACK_TOAST_V106: server-rendered, accessible and dismissible.
  const feedbackToast=root.querySelector('[data-demo-feedback-toast]');
  feedbackToast?.querySelector('[data-demo-toast-close]')?.addEventListener('click',()=>feedbackToast.remove());
  root.querySelector('.btool-header')?.classList.add('bdemo-v103-head');
  const mode=root.querySelector('.btool-tab[href*="mode=auto"].is-active')?'auto':'manual';
  const link=root.querySelector('.bdemo-source a'),url=link?.href&&new URL(link.href).hostname==='baramarealestate.tonsamith.com'?link.href:'https://baramarealestate.tonsamith.com/';
  const grid=document.createElement('div');grid.className='bdemo-v103-grid';
  const preview=document.createElement('section');preview.className='bdemo-v103-card bdemo-v103-preview';
  preview.innerHTML='<div class="bdemo-v103-card-head"><span class="bdemo-v103-icon">▣</span><div><h2>Live Demo Preview</h2><p>See the official Barama homepage before importing.</p></div></div><a class="bdemo-v103-shot" target="_blank" rel="noopener noreferrer" aria-label="Open official homepage; hover to scroll the screenshot"><img src="https://baramarealestate.tonsamith.com/assets/images/barama-demo-preview-v103.webp?v=103" loading="eager" decoding="async" alt="Barama Real Estate homepage, listings, projects, companies and footer"><span class="bdemo-v103-preview-label">◉ Preview ↗</span></a><div class="bdemo-v103-action-slot"></div><p class="bdemo-v103-helper">Official signed ZIP verification and private staging only. Database/media installation is disabled until staging acceptance.</p>';
  preview.querySelector('.bdemo-v103-shot').href=url;
  const form=Array.from(body.querySelectorAll('form')).find(f=>f.action.endsWith('/dashboard/system/demo-importer/'+mode));
  if(form){preview.querySelector('.bdemo-v103-action-slot').append(form);form.querySelector('.btool-actions')?.classList.add('bdemo-v103-actions');if(mode==='auto')form.querySelector('[type=submit]').textContent='⇩  Download & Verify official ZIP';}
  const oldCard=body.querySelector('.btool-card:not(.bdemo-review):not(.bdemo-api-check)');
  if(oldCard&&!oldCard.querySelector('form'))oldCard.remove();
  const oldHead=body.querySelector('.btool-content-head');if(oldHead)oldHead.hidden=true;
  const good=!!body.querySelector('.bdemo-review'),bad=!!root.querySelector('.btool-flash--error,.bdemo-notice-toast--error');
  const ring=document.createElement('section');ring.className='bdemo-v103-card bdemo-v103-ring';
  ring.dataset.state=good?'verified':bad?'rejected':'idle';
  const labels=mode==='auto'?['License check','Published package','Download ZIP','Verify signature','Private staging','Review package']:['Select ZIP','Check publisher','Match checksum','Verify signature','Private staging','Review package'];
  const title=good?'Signed ZIP verified':bad?'Verification stopped':'Ready to verify';
  const detail=good?'Server-confirmed signature and private staging. Nothing installed.':bad?'The server did not confirm success. Review the message above.':'Ring motion begins only for a real verification request.';
  const head=document.createElement('div');head.className='bdemo-v103-card-head';head.innerHTML='<span class="bdemo-v103-icon">◉</span><div><h2>Import Progress</h2><p>Server-confirmed verification steps.</p></div>';
  const orbit=document.createElement('div');orbit.className='bdemo-v103-orbit-wrap';
  orbit.innerHTML='<div class="bdemo-v103-orbit"><span class="bdemo-v103-orbit-mark">B</span></div>';
  labels.forEach((label,i)=>{const el=document.createElement('div');el.className='bdemo-v103-step';el.dataset.index=String(i+1);const num=document.createElement('strong');num.textContent=String(i+1).padStart(2,'0');const words=document.createElement('span');words.textContent=label;el.append(num,words);orbit.append(el);});
  const readout=document.createElement('div');readout.className='bdemo-v103-readout';readout.setAttribute('role','status');readout.setAttribute('aria-live','polite');
  readout.innerHTML='<span class="bdemo-v103-readout-dot">●</span><div><strong data-progress-label></strong><p data-progress-detail></p></div>';
  readout.querySelector('[data-progress-label]').textContent=title;readout.querySelector('[data-progress-detail]').textContent=detail;
  ring.append(head,orbit,readout);grid.append(preview,ring);body.prepend(grid);
  root.querySelector('.bdemo-orbit-panel[data-bdemo-orbit]')?.remove();
  const about=document.createElement('section');about.className='bdemo-v103-bottom';
  const aboutTitle=document.createElement('h2');aboutTitle.textContent='About the Official Barama Live Demo';about.append(aboutTitle);
  const warning=Array.from(root.querySelectorAll('.btool-warning')).find(el=>!el.closest('.bdemo-review'));if(warning)about.append(warning);
  const summary=root.querySelector('.bdemo-summary');if(summary)about.append(summary);shell.after(about);
  const shot=preview.querySelector('.bdemo-v103-shot'),image=shot.querySelector('img');
  const calibrate=()=>{if(!image.naturalWidth)return;const height=image.naturalHeight*shot.clientWidth/image.naturalWidth;shot.style.setProperty('--dp-preview-scroll',Math.max(0,Math.round(height-shot.clientHeight))+'px');};
  image.addEventListener('load',calibrate);if(image.complete)calibrate();window.addEventListener('resize',calibrate,{passive:true});

  /* BARAMA_DEMO_PREVIEW_RECOVERY_V105
   * The 0.9.25 customer ZIP intentionally does not embed the publisher's
   * full-page screenshot. Display its public HTTPS asset from the official
   * demo. If unavailable, show the packaged homepage hero, never a blank box.
   */
  let previewFallback=false;
  image.addEventListener('error',()=>{
    if(previewFallback)return;
    previewFallback=true;
    shot.classList.add('bdemo-preview-fallback-v105');
    image.src='/assets/images/barama-homepage-hero-v1.webp?v=1';
    const notice=document.createElement('span');
    notice.className='bdemo-preview-notice-v105';
    notice.textContent='Full-page preview unavailable. Open the official demo for the live experience.';
    shot.append(notice);
  });
  const steps=Array.from(ring.querySelectorAll('.bdemo-v103-step'));let ticker;
  const reset=()=>{if(ticker)clearInterval(ticker);steps.forEach(x=>x.classList.remove('is-current'));};
  if(form)form.addEventListener('submit',event=>{if(!form.checkValidity())return;window.setTimeout(()=>{
    if(event.defaultPrevented)return;reset();ring.dataset.state='working';
    readout.querySelector('[data-progress-label]').textContent=mode==='auto'?'Downloading and verifying official ZIP':'Checking signed ZIP upload';
    readout.querySelector('[data-progress-detail]').textContent='Request submitted. Labels are a visual guide; final verification comes from the server.';
    let index=0;const tick=()=>{steps.forEach((x,i)=>x.classList.toggle('is-current',i===index));index=(index+1)%steps.length;};
    tick();ticker=window.setInterval(tick,1200);
  },0);});
  // Protected forms use native form.submit() after receiving the grant, so
  // no second submit event fires. Animate *authorization*, never fake imports.
  const dialog=document.getElementById('btool-passcode-dialog');
  const input=document.getElementById('btool-passcode');
  const button=document.getElementById('btool-passcode-submit');
  const feedback=document.getElementById('btool-passcode-feedback');
  const beginAuthorization=()=>{
    if(!form||!dialog?.open||!/^[0-9]{6}$/.test(input?.value.trim()||''))return;
    reset();ring.dataset.state='working';
    readout.querySelector('[data-progress-label]').textContent='Requesting protected authorization';
    readout.querySelector('[data-progress-detail]').textContent='Change Confirmation is being checked. No signed package verification or installation is claimed yet.';
    let index=0;const tick=()=>{steps.forEach((el,i)=>el.classList.toggle('is-current',i===index));index=(index+1)%steps.length;};
    tick();ticker=window.setInterval(tick,1200);
  };
  if(root.dataset.passcodeEnabled==='1'){
    button?.addEventListener('click',beginAuthorization);
    input?.addEventListener('keydown',event=>{if(event.key==='Enter')beginAuthorization();});
    if(feedback)new MutationObserver(()=>{
      if(feedback.textContent.trim()&&ring.dataset.state==='working'){
        reset();ring.dataset.state='rejected';
        readout.querySelector('[data-progress-label]').textContent='Authorization not confirmed';
        readout.querySelector('[data-progress-detail]').textContent='Security check rejected or stopped the request. No content import is claimed.';
      }
    }).observe(feedback,{childList:true,subtree:true,characterData:true});
  }
  window.addEventListener('pageshow',event=>{if(event.persisted){reset();window.location.reload();}});
})();