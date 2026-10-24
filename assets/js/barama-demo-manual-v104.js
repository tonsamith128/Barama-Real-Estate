/* BARAMA_DEMO_MANUAL_UI_V104: additive enhancement, preserves v1.0.3 preview and progress ring exactly. */
(() => {
  'use strict';
  const root=document.querySelector('#barama-system-tool.bdemo.bdemo-v103');
  if(!root || root.dataset.manualV104==='1')return;
  const manualTab=root.querySelector('.btool-tab[href*="mode=manual"]');
  if(!manualTab?.classList.contains('is-active'))return;
  const body=root.querySelector('.btool-content');
  const preview=body?.querySelector('.bdemo-v103-preview');
  const ring=body?.querySelector('.bdemo-v103-ring');
  const form=preview?.querySelector('form[action$="/dashboard/system/demo-importer/manual"]');
  const input=form?.querySelector('input[type=file][name=package]');
  const button=form?.querySelector('[type=submit]');
  // Fail gracefully to the original UI if any expected v1.0.3 component drifted.
  if(!body || !preview || !ring || !form || !input || !button)return;
  root.dataset.manualV104='1';root.classList.add('bdemo-manual-v104');
  form.classList.add('bdemo-manual-v104-upload');
  const help=form.querySelector('.btool-help');
  const oldAction=form.querySelector('.btool-actions');
  // Move the same original upload input, CSRF field and submit button. No form
  // clone or change to the endpoint, validation, or Change Confirmation flow.
  const drop=document.createElement('label');drop.className='bdemo-manual-v104-drop';
  drop.innerHTML='<span class="bdemo-manual-v104-icon" aria-hidden="true">⇪</span><span class="bdemo-manual-v104-copy"><strong>Drag & Drop</strong><span>Drop the official publisher ZIP here, or <b>Upload ZIP</b> from your device.</span></span><span class="bdemo-manual-v104-meta">ZIP file only<br>Maximum 64 MB</span>';
  drop.appendChild(input);
  const fileline=document.createElement('div');fileline.className='bdemo-manual-v104-fileline';
  fileline.innerHTML='<strong>Live Demo package</strong><span class="bdemo-manual-v104-filename" aria-live="polite">No file selected</span>';
  const actions=document.createElement('div');actions.className='bdemo-manual-v104-actions';
  if(oldAction)actions.append(...oldAction.childNodes);else actions.append(button);
  const small=document.createElement('p');small.className='bdemo-manual-v104-smallnote';
  small.textContent='The exact signed ZIP is checked against the publisher catalog and staged privately. Uploading does not import website data.';
  // Preserve hidden CSRF/authorization inputs, if present.
  Array.from(form.children).forEach(el=>{if(el!==drop&&el!==actions&&el!==help&&el!==oldAction&&el.tagName==='LABEL')el.remove();});
  if(oldAction)oldAction.remove();
  form.prepend(drop,fileline,actions,small);
  if(help)form.append(help);
  const filename=fileline.querySelector('.bdemo-manual-v104-filename');
  const update=()=>{
    const file=input.files?.[0];
    filename.textContent=file?file.name:'No file selected';
    if(file && (!/\.zip$/i.test(file.name)||file.size>67108864)){
      filename.textContent=file.name+' — select a ZIP under 64 MB';
      input.setCustomValidity('Select a valid ZIP file under 64 MB.');
    } else input.setCustomValidity('');
  };
  input.addEventListener('change',update);update();
  ['dragenter','dragover'].forEach(type=>drop.addEventListener(type,event=>{
    event.preventDefault();event.stopPropagation();drop.classList.add('drag-active');
  }));
  ['dragleave','dragend'].forEach(type=>drop.addEventListener(type,event=>{
    event.preventDefault();event.stopPropagation();drop.classList.remove('drag-active');
  }));
  drop.addEventListener('drop',event=>{
    event.preventDefault();event.stopPropagation();drop.classList.remove('drag-active');
    const file=event.dataTransfer?.files?.[0];
    if(!file || !/\.zip$/i.test(file.name)||file.size>67108864)return;
    try{const dt=new DataTransfer();dt.items.add(file);input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));}
    catch(_){input.focus();} // Browser restrictions: keep click-to-upload intact.
  });
  const workflow=document.createElement('section');
  workflow.className='bdemo-manual-v104-flow';workflow.setAttribute('aria-label','Manual Import Workflow');
  workflow.innerHTML='<div class="bdemo-manual-v104-flowhead"><div><span class="btool-eyebrow">Guided import workflow</span><h2>Manual Import</h2><p>A clear, licensed workflow from the publisher ZIP through verification to a controlled installation review.</p></div><span class="bdemo-manual-v104-chip">3 protected steps</span></div><div class="bdemo-manual-v104-flowgrid"><article class="bdemo-manual-v104-flowitem"><span class="bdemo-manual-v104-flownum">01</span><h3>Download from publisher</h3><p>Obtain the exact published Barama Live Demo package from TonSamith.com.</p><ul class="bdemo-manual-v104-points"><li>Keep the original signed ZIP intact.</li><li>Use the licensed official publisher package.</li></ul></article><article class="bdemo-manual-v104-flowitem"><span class="bdemo-manual-v104-flownum">02</span><h3>Upload and verify</h3><p>Use Drag & Drop above. The server verifies publisher signature, checksum, format and media references.</p><ul class="bdemo-manual-v104-points"><li>Verification is server-side.</li><li>Only a valid ZIP is privately staged.</li></ul></article><article class="bdemo-manual-v104-flowitem"><span class="bdemo-manual-v104-flownum">03</span><h3>Review and import</h3><p>Inspect verified package details and the planned import scope before installing on an approved fresh site.</p><ul class="bdemo-manual-v104-points"><li>Installation is currently disabled.</li><li>Staging backup and rollback acceptance required.</li></ul></article></div>';
  const oldGuide=body.querySelector('.btool-two');
  const review=body.querySelector('.bdemo-review');
  if(oldGuide)oldGuide.replaceWith(workflow);
  else if(review)body.insertBefore(workflow,review);
  else body.append(workflow);
  // Preview and Progress Ring are the existing v1.0.3 elements, not rebuilt.
})();
