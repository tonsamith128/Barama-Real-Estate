/* BARAMA_POLICY_CENTER_V104 */
(()=>{'use strict';

const slugify=v=>String(v||'')
  .toLowerCase().trim()
  .replace(/[^a-z0-9]+/g,'-')
  .replace(/^-+|-+$/g,'');

const title=document.querySelector('[data-pc-title]');
const slug=document.querySelector('[data-pc-slug]');

if(title&&slug){
  let edited=slug.value.trim()!=='';
  slug.addEventListener('input',()=>edited=slug.value.trim()!=='');
  title.addEventListener('input',()=>{
    if(!edited) slug.value=slugify(title.value);
  });
}

const editor=document.querySelector('[data-pc-editor]');
const contentInput=document.querySelector('[data-pc-content]');
const builder=document.querySelector('[data-pc-builder]');

if(editor&&contentInput&&builder){
  builder.addEventListener('submit',()=>contentInput.value=editor.innerHTML.trim());

  document.querySelectorAll('[data-pc-cmd]').forEach(button=>{
    button.addEventListener('click',()=>{
      const command=button.dataset.pcCmd||'';

      if(command.startsWith('formatBlock:')){
        document.execCommand('formatBlock',false,command.split(':')[1]);
      }else if(command==='createLink'){
        const value=prompt('Enter an HTTPS, mailto, or internal link');
        if(value) document.execCommand('createLink',false,value);
      }else{
        document.execCommand(command,false,null);
      }

      editor.focus();
    });
  });
}

const faqList=document.querySelector('[data-pc-faq-list]');
const addFaq=document.querySelector('[data-pc-add-faq]');

if(faqList&&addFaq){
  addFaq.addEventListener('click',()=>{
    const row=document.createElement('div');
    row.className='pc-faq-row';
    row.setAttribute('data-pc-faq-row','');
    row.innerHTML='<div class="pc-field"><label>Question</label><input name="faq_question[]" maxlength="240"></div><div class="pc-field"><label>Answer</label><textarea name="faq_answer[]" rows="3" maxlength="1200"></textarea></div><button type="button" data-pc-remove-faq>Remove</button>';
    faqList.append(row);
    row.querySelector('input')?.focus();
  });
}

document.addEventListener('click',event=>{
  const remove=event.target.closest('[data-pc-remove-faq]');
  if(remove) remove.closest('[data-pc-faq-row]')?.remove();
});

const rows=[...document.querySelectorAll('[data-pc-row]')];
const search=document.querySelector('[data-pc-search]');
const complianceSelect=document.querySelector('[data-pc-compliance-filter]');
let publicationFilter='all';

const applyFilters=()=>{
  const query=String(search?.value||'').toLowerCase().trim();
  const compliance=String(complianceSelect?.value||'all');

  rows.forEach(row=>{
    const matchesQuery=!query||String(row.dataset.search||'').includes(query);
    const matchesPublication=
      publicationFilter==='all'||row.dataset.status===publicationFilter;
    const matchesCompliance=
      compliance==='all'||row.dataset.compliance===compliance;

    row.hidden=!(matchesQuery&&matchesPublication&&matchesCompliance);
  });
};

search?.addEventListener('input',applyFilters);
complianceSelect?.addEventListener('change',applyFilters);

document.querySelectorAll('[data-pc-filter]').forEach(button=>{
  button.setAttribute(
    'aria-pressed',
    button.classList.contains('is-active')?'true':'false'
  );

  button.addEventListener('click',()=>{
    publicationFilter=button.dataset.pcFilter||'all';

    document.querySelectorAll('[data-pc-filter]').forEach(item=>{
      item.classList.remove('is-active');
      item.setAttribute('aria-pressed','false');
    });

    button.classList.add('is-active');
    button.setAttribute('aria-pressed','true');
    applyFilters();
  });
});

document.querySelectorAll('[data-pc-delete]').forEach(form=>{
  form.addEventListener('submit',event=>{
    if(!confirm('Delete this policy and its version history? This action cannot be undone.')){
      event.preventDefault();
    }
  });
});

const closeMenus=(except=null)=>{
  document.querySelectorAll('[data-pc-menu].is-open').forEach(menu=>{
    if(menu===except) return;
    menu.classList.remove('is-open');
    menu.querySelector('[data-pc-menu-trigger]')?.setAttribute('aria-expanded','false');
  });
};

document.querySelectorAll('[data-pc-menu-trigger]').forEach(trigger=>{
  trigger.addEventListener('click',event=>{
    event.stopPropagation();
    const menu=trigger.closest('[data-pc-menu]');
    if(!menu) return;

    const open=!menu.classList.contains('is-open');
    closeMenus(menu);
    menu.classList.toggle('is-open',open);
    trigger.setAttribute('aria-expanded',open?'true':'false');

    if(open){
      menu.querySelector('[role="menuitem"]')?.focus({preventScroll:true});
    }
  });
});

document.addEventListener('click',event=>{
  if(!event.target.closest('[data-pc-menu]')) closeMenus();
});

document.addEventListener('keydown',event=>{
  if(event.key==='Escape'){
    const active=document.querySelector('[data-pc-menu].is-open');
    const trigger=active?.querySelector('[data-pc-menu-trigger]');
    closeMenus();
    trigger?.focus();
  }
});

const toast=document.querySelector('[data-pc-copy-toast]');
let toastTimer=0;

const showToast=message=>{
  if(!toast) return;
  toast.textContent=message;
  toast.classList.add('is-visible');
  window.clearTimeout(toastTimer);
  toastTimer=window.setTimeout(()=>toast.classList.remove('is-visible'),1800);
};

const fallbackCopy=value=>{
  const textarea=document.createElement('textarea');
  textarea.value=value;
  textarea.setAttribute('readonly','');
  textarea.style.position='fixed';
  textarea.style.opacity='0';
  document.body.append(textarea);
  textarea.select();
  const ok=document.execCommand('copy');
  textarea.remove();
  return ok;
};

document.querySelectorAll('[data-pc-copy-slug]').forEach(button=>{
  button.addEventListener('click',async()=>{
    const value=String(button.dataset.pcCopySlug||'');

    if(!value) return;

    let copied=false;

    try{
      if(navigator.clipboard?.writeText){
        await navigator.clipboard.writeText(value);
        copied=true;
      }else{
        copied=fallbackCopy(value);
      }
    }catch(_error){
      copied=fallbackCopy(value);
    }

    showToast(copied?'Slug copied':'Unable to copy slug');
    closeMenus();
  });
});

document.querySelectorAll('[data-pc-restore-version]').forEach(button=>{
  button.addEventListener('click',event=>{
    const label=button.dataset.pcRestoreVersion||'this version';

    if(!confirm(`Restore ${label} as a new current version? Unsaved changes on this page will be discarded.`)){
      event.preventDefault();
    }
  });
});

const complianceChecks=[...document.querySelectorAll('[data-pc-compliance-check]')];
const complianceBar=document.querySelector('[data-pc-compliance-progress]');
const complianceText=document.querySelector('[data-pc-compliance-progress-text]');

const updateComplianceProgress=()=>{
  if(!complianceChecks.length) return;

  const checked=complianceChecks.filter(input=>input.checked).length;
  const progress=Math.round((checked/complianceChecks.length)*100);

  if(complianceBar) complianceBar.style.width=`${progress}%`;
  if(complianceText) complianceText.textContent=`${progress}% complete`;
};

complianceChecks.forEach(input=>input.addEventListener('change',updateComplianceProgress));
updateComplianceProgress();

})();