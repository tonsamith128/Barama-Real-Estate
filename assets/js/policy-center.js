/* Barama Real Estate — Policy Center stable area bundle.
 * Feature modules are isolated in IIFEs and activate only on their corresponding pages.
 * Source history is tracked by Git rather than versioned asset filenames.
 */

/* ===== POLICY CENTER — EDITOR, LIST, CATEGORIES & ACTION MENUS ===== */
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


const copyDynamicUrlButton=document.querySelector('[data-pc-copy-dynamic-url]');

const fallbackCopyText=value=>{
  const textarea=document.createElement('textarea');
  textarea.value=value;
  textarea.setAttribute('readonly','');
  textarea.style.position='fixed';
  textarea.style.left='-9999px';
  textarea.style.top='0';
  document.body.append(textarea);
  textarea.select();

  let copied=false;

  try{
    copied=document.execCommand('copy');
  }catch(_error){
    copied=false;
  }

  textarea.remove();
  return copied;
};

const copyText=async value=>{
  if(navigator.clipboard&&window.isSecureContext){
    await navigator.clipboard.writeText(value);
    return true;
  }

  return fallbackCopyText(value);
};

if(copyDynamicUrlButton&&slug){
  let copyResetTimer=0;

  copyDynamicUrlButton.addEventListener('click',async()=>{
    const slugValue=String(slug.value||'').trim();

    if(!slugValue){
      slug.focus();
      return;
    }

    const path=`/policies?=${encodeURIComponent(slugValue)}`;
    const absoluteUrl=new URL(path,window.location.origin).href;

    try{
      const copied=await copyText(absoluteUrl);

      if(!copied) throw new Error('Clipboard copy failed.');

      window.clearTimeout(copyResetTimer);
      copyDynamicUrlButton.classList.add('is-copied');
      copyDynamicUrlButton.setAttribute('aria-label','Copied dynamic public URL');
      copyDynamicUrlButton.setAttribute('title','Copied');

      const status=copyDynamicUrlButton.querySelector('[data-pc-copy-status]');
      if(status) status.textContent='Copied dynamic public URL';

      copyResetTimer=window.setTimeout(()=>{
        copyDynamicUrlButton.classList.remove('is-copied');
        copyDynamicUrlButton.setAttribute('aria-label','Copy dynamic public URL');
        copyDynamicUrlButton.setAttribute('title','Copy dynamic public URL');

        if(status) status.textContent='Copy dynamic public URL';
      },1800);
    }catch(_error){
      copyDynamicUrlButton.setAttribute('title','Unable to copy');
      slug.focus();
    }
  });
}

const editor=document.querySelector('[data-pc-editor]');
const contentInput=document.querySelector('[data-pc-content]');
const builder=document.querySelector('[data-pc-builder]');

if(editor&&contentInput&&builder){
  builder.addEventListener('submit',()=>contentInput.value=editor.innerHTML.trim());

  const toolbarButtons=[...document.querySelectorAll('[data-pc-cmd]')];

  const updateToolbarState=()=>{
    if(!editor.contains(document.activeElement)&&!editor.contains(window.getSelection()?.anchorNode)){
      return;
    }

    let currentBlock='';

    try{
      currentBlock=String(document.queryCommandValue('formatBlock')||'')
        .toLowerCase()
        .replace(/[<>]/g,'');
    }catch(_error){
      currentBlock='';
    }

    toolbarButtons.forEach(button=>{
      let active=false;
      const state=button.dataset.pcState||'';
      const format=button.dataset.pcFormat||'';

      try{
        if(state){
          active=document.queryCommandState(state);
        }else if(format){
          active=currentBlock===format;
        }
      }catch(_error){
        active=false;
      }

      button.classList.toggle('is-active',Boolean(active));

      if(state||format){
        button.setAttribute('aria-pressed',active?'true':'false');
      }else{
        button.removeAttribute('aria-pressed');
      }
    });
  };

  toolbarButtons.forEach(button=>{
    button.addEventListener('mousedown',event=>{
      event.preventDefault();
    });

    button.addEventListener('click',()=>{
      const command=button.dataset.pcCmd||'';

      editor.focus();

      if(command.startsWith('formatBlock:')){
        document.execCommand(
          'formatBlock',
          false,
          command.split(':')[1]
        );
      }else if(command==='createLink'){
        const value=prompt('Enter an HTTPS, mailto, or internal link');

        if(value){
          document.execCommand('createLink',false,value);
        }
      }else{
        document.execCommand(command,false,null);
      }

      updateToolbarState();
    });
  });

  document.addEventListener('selectionchange',updateToolbarState);
  editor.addEventListener('keyup',updateToolbarState);
  editor.addEventListener('mouseup',updateToolbarState);
}


document.querySelectorAll('[data-pc-date-trigger]').forEach(button=>{
  button.addEventListener('click',()=>{
    const wrapper=button.closest('.pc-date-input');
    const input=wrapper?.querySelector('[data-pc-date-input]');

    if(!(input instanceof HTMLInputElement)) return;

    input.focus({preventScroll:true});

    if(typeof input.showPicker==='function'){
      try{
        input.showPicker();
      }catch(_error){
        input.focus();
      }
    }
  });
});


const policySidebar=document.querySelector('.pc-builder-side');

if(policySidebar){
  const policyId=new URLSearchParams(window.location.search).get('id')||'new';

  const definitions={
    'Publish':{key:'publish',open:true,icon:'publish'},
    'Legal Compliance':{key:'legal-compliance',open:true,icon:'shield'},
    'Version History':{key:'version-history',open:false,icon:'history'},
    'Organization':{key:'organization',open:false,icon:'tag'},
    'SEO':{key:'seo',open:false,icon:'search'},
    'Public link':{key:'public-link',open:false,icon:'link'}
  };

  const iconSvg=name=>{
    const icons={
      publish:'<path d="M5 19h14"></path><path d="M12 4v11"></path><path d="m8 8 4-4 4 4"></path>',
      shield:'<path d="M12 3 5 6v5c0 4.5 2.7 7.5 7 10 4.3-2.5 7-5.5 7-10V6z"></path><path d="m9.5 12 1.7 1.7 3.5-4"></path>',
      history:'<path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v5h5"></path><path d="M12 7v5l3 2"></path>',
      tag:'<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h4.3a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.8l-4.8 4.8a2 2 0 0 1-2.8 0l-7.2-7.2A2 2 0 0 1 4 10.8z"></path><circle cx="8.2" cy="8.2" r="1.2"></circle>',
      search:'<circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4"></path>',
      link:'<path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.1 1.1"></path><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.1-1.1"></path>'
    };

    return icons[name]||icons.link;
  };

  const storageKey=key=>`barama-policy-panel:${policyId}:${key}`;

  const readState=(key,defaultOpen)=>{
    try{
      const stored=window.localStorage.getItem(storageKey(key));
      if(stored==='open') return true;
      if(stored==='closed') return false;
    }catch(_error){}
    return defaultOpen;
  };

  const writeState=(key,isOpen)=>{
    try{
      window.localStorage.setItem(storageKey(key),isOpen?'open':'closed');
    }catch(_error){}
  };

  const headingFor=section=>{
    return section.querySelector(':scope > h2')
      ||section.querySelector(':scope > .pc-side-heading h2');
  };

  const statusFor=(key,section)=>{
    if(key==='publish'){
      const field=section.querySelector('select[name="status"]');
      return field?.selectedOptions?.[0]?.textContent?.trim()||'Draft';
    }

    if(key==='legal-compliance'){
      const field=section.querySelector('select[name="compliance_status"]');
      const progress=section.querySelector('[data-pc-compliance-progress-text]');
      const label=field?.selectedOptions?.[0]?.textContent?.trim()||'Not reviewed';
      const percentage=(progress?.textContent?.trim()||'0% complete').replace(' complete','');
      return `${percentage} · ${label}`;
    }

    if(key==='version-history'){
      return section.querySelector('.pc-version-badge')?.textContent?.trim()||'History';
    }

    if(key==='organization'){
      return section.querySelector('select[name="category_id"]')
        ?.selectedOptions?.[0]?.textContent?.trim()||'General';
    }

    if(key==='seo'){
      const title=section.querySelector('input[name="seo_title"]')?.value?.trim();
      const description=section.querySelector('textarea[name="meta_description"]')?.value?.trim();
      return title||description?'Configured':'Not configured';
    }

    if(key==='public-link') return 'Public URL';

    return '';
  };

  [...policySidebar.children].forEach((section,index)=>{
    if(!(section instanceof HTMLElement)||section.tagName!=='SECTION') return;

    const heading=headingFor(section);
    const title=heading?.textContent?.trim()||'';
    const definition=definitions[title];
    if(!definition) return;

    const contentId=`pc-side-panel-${definition.key}-${index}`;
    const originalNodes=[...section.childNodes];

    const content=document.createElement('div');
    content.className='pc-panel-content';
    content.id=contentId;
    originalNodes.forEach(node=>content.append(node));

    const toggle=document.createElement('button');
    toggle.type='button';
    toggle.className='pc-panel-toggle';
    toggle.setAttribute('aria-controls',contentId);

    const icon=document.createElement('span');
    icon.className='pc-panel-toggle__icon';
    icon.setAttribute('aria-hidden','true');
    icon.innerHTML=`<svg viewBox="0 0 24 24">${iconSvg(definition.icon)}</svg>`;

    const label=document.createElement('span');
    label.className='pc-panel-toggle__title';
    label.textContent=title;

    const status=document.createElement('span');
    status.className='pc-panel-toggle__status';
    status.setAttribute('data-pc-panel-status','');

    const chevron=document.createElement('span');
    chevron.className='pc-panel-toggle__chevron';
    chevron.setAttribute('aria-hidden','true');
    chevron.innerHTML='<svg viewBox="0 0 24 24"><path d="m7 9 5 5 5-5"></path></svg>';

    toggle.append(icon,label,status,chevron);
    section.append(toggle,content);
    section.classList.add('pc-sidebar-panel');
    section.dataset.pcPanel=definition.key;

    const refreshStatus=()=>{
      const value=statusFor(definition.key,section);
      status.textContent=value;
      status.classList.toggle(
        'is-published',
        definition.key==='publish'&&/published/i.test(value)
      );
      status.classList.toggle(
        'is-compliant',
        definition.key==='legal-compliance'&&/compliant/i.test(value)
      );
      status.classList.toggle(
        'is-action-required',
        definition.key==='legal-compliance'&&/action required/i.test(value)
      );
    };

    const setOpen=(open,persist=false)=>{
      section.classList.toggle('is-open',open);
      toggle.setAttribute('aria-expanded',open?'true':'false');
      content.hidden=!open;
      if(persist) writeState(definition.key,open);
    };

    setOpen(readState(definition.key,definition.open),false);
    refreshStatus();

    toggle.addEventListener('click',()=>{
      setOpen(toggle.getAttribute('aria-expanded')!=='true',true);
    });

    section.addEventListener('change',refreshStatus);
    section.addEventListener('input',refreshStatus);
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
const pagination=document.querySelector('[data-pc-pagination]');
const pageNumbers=document.querySelector('[data-pc-page-numbers]');
const pagePrev=document.querySelector('[data-pc-page-prev]');
const pageNext=document.querySelector('[data-pc-page-next]');
const pageInput=document.querySelector('[data-pc-page-input]');
const pageGo=document.querySelector('[data-pc-page-go]');
const pageStart=document.querySelector('[data-pc-page-start]');
const pageEnd=document.querySelector('[data-pc-page-end]');
const pageTotal=document.querySelector('[data-pc-page-total]');
const noResults=document.querySelector('[data-pc-no-results]');

const PAGE_SIZE=Math.max(
  1,
  Number.parseInt(String(pagination?.dataset.pageSize||'10'),10)||10
);

let publicationFilter='all';
let currentPage=1;

const getFilteredRows=()=>{
  const query=String(search?.value||'').toLowerCase().trim();
  const compliance=String(complianceSelect?.value||'all');

  return rows.filter(row=>{
    const matchesQuery=!query||String(row.dataset.search||'').includes(query);
    const matchesPublication=
      publicationFilter==='all'||row.dataset.status===publicationFilter;
    const matchesCompliance=
      compliance==='all'||row.dataset.compliance===compliance;

    return matchesQuery&&matchesPublication&&matchesCompliance;
  });
};

const pageWindow=(current,total)=>{
  if(total<=7) return Array.from({length:total},(_,index)=>index+1);

  const pages=[1];

  if(current>4) pages.push('…');

  const start=Math.max(2,current-1);
  const end=Math.min(total-1,current+1);

  for(let page=start;page<=end;page+=1) pages.push(page);

  if(current<total-3) pages.push('…');

  pages.push(total);
  return pages;
};

const renderPageNumbers=(totalPages)=>{
  if(!pageNumbers) return;

  pageNumbers.replaceChildren();

  if(totalPages<=0) return;

  pageWindow(currentPage,totalPages).forEach(value=>{
    if(value==='…'){
      const ellipsis=document.createElement('span');
      ellipsis.className='pc-page-ellipsis';
      ellipsis.textContent='…';
      ellipsis.setAttribute('aria-hidden','true');
      pageNumbers.append(ellipsis);
      return;
    }

    const button=document.createElement('button');
    button.type='button';
    button.className='pc-page-btn';
    button.textContent=String(value);
    button.dataset.pcPage=String(value);
    button.setAttribute('aria-label',`Go to page ${value}`);

    if(value===currentPage){
      button.classList.add('is-active');
      button.setAttribute('aria-current','page');
    }

    button.addEventListener('click',()=>{
      currentPage=value;
      renderTable();
    });

    pageNumbers.append(button);
  });
};

const renderTable=()=>{
  const filtered=getFilteredRows();
  const total=filtered.length;
  const totalPages=Math.ceil(total/PAGE_SIZE);

  if(totalPages===0){
    currentPage=1;
  }else{
    currentPage=Math.min(Math.max(1,currentPage),totalPages);
  }

  rows.forEach(row=>row.hidden=true);

  const startIndex=total===0?0:(currentPage-1)*PAGE_SIZE;
  const endIndex=Math.min(startIndex+PAGE_SIZE,total);

  filtered.slice(startIndex,endIndex).forEach(row=>row.hidden=false);

  if(noResults) noResults.hidden=total!==0;
  if(pageStart) pageStart.textContent=total===0?'0':String(startIndex+1);
  if(pageEnd) pageEnd.textContent=String(endIndex);
  if(pageTotal) pageTotal.textContent=String(total);

  if(pagePrev) pagePrev.disabled=totalPages===0||currentPage<=1;
  if(pageNext) pageNext.disabled=totalPages===0||currentPage>=totalPages;

  if(pageInput){
    pageInput.min='1';
    pageInput.max=String(Math.max(1,totalPages));
    pageInput.value=String(currentPage);
    pageInput.disabled=totalPages<=1;
  }

  if(pageGo) pageGo.disabled=totalPages<=1;

  renderPageNumbers(totalPages);
  closeMenus();
};

const resetAndRender=()=>{
  currentPage=1;
  renderTable();
};

search?.addEventListener('input',resetAndRender);
complianceSelect?.addEventListener('change',resetAndRender);

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
    resetAndRender();
  });
});

pagePrev?.addEventListener('click',()=>{
  if(currentPage<=1) return;
  currentPage-=1;
  renderTable();
});

pageNext?.addEventListener('click',()=>{
  const totalPages=Math.ceil(getFilteredRows().length/PAGE_SIZE);
  if(currentPage>=totalPages) return;
  currentPage+=1;
  renderTable();
});

const goToRequestedPage=()=>{
  const totalPages=Math.ceil(getFilteredRows().length/PAGE_SIZE);

  if(totalPages<=0) return;

  const requested=Number.parseInt(String(pageInput?.value||''),10);

  if(!Number.isFinite(requested)) return;

  currentPage=Math.min(Math.max(1,requested),totalPages);
  renderTable();
};

pageGo?.addEventListener('click',goToRequestedPage);
pageInput?.addEventListener('keydown',event=>{
  if(event.key==='Enter'){
    event.preventDefault();
    goToRequestedPage();
  }
});

renderTable();

document.querySelectorAll('[data-pc-delete]').forEach(form=>{
  form.addEventListener('submit',event=>{
    if(!confirm('Delete this policy and its version history? This action cannot be undone.')){
      event.preventDefault();
    }
  });
});

function closeMenus(except=null){
  document.querySelectorAll('[data-pc-menu].is-open').forEach(menu=>{
    if(menu===except) return;
    menu.classList.remove('is-open');
    menu.querySelector('[data-pc-menu-trigger]')?.setAttribute('aria-expanded','false');
  });
}

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




const categoryName=document.querySelector('[data-pc-category-name]');
const categorySlug=document.querySelector('[data-pc-category-slug]');

if(categoryName&&categorySlug){
  let editedCategorySlug=categorySlug.value.trim()!=='';

  categorySlug.addEventListener('input',()=>{
    editedCategorySlug=categorySlug.value.trim()!=='';
  });

  categoryName.addEventListener('input',()=>{
    if(!editedCategorySlug) categorySlug.value=slugify(categoryName.value);
  });
}

const categorySearch=document.querySelector('[data-pc-category-search]');
const categoryRows=[...document.querySelectorAll('[data-pc-category-row]')];
const categoryNoResults=document.querySelector('[data-pc-category-no-results]');

const filterCategories=()=>{
  const query=String(categorySearch?.value||'').toLowerCase().trim();
  let visible=0;

  categoryRows.forEach(row=>{
    const matches=!query||String(row.dataset.search||'').includes(query);
    row.hidden=!matches;
    if(matches) visible+=1;
  });

  if(categoryNoResults){
    categoryNoResults.hidden=visible!==0||categoryRows.length===0;
  }
};

categorySearch?.addEventListener('input',filterCategories);
filterCategories();

document.querySelectorAll('[data-pc-category-delete]').forEach(form=>{
  form.addEventListener('submit',event=>{
    if(!confirm('Delete this unused policy category?')){
      event.preventDefault();
    }
  });
});

document.querySelectorAll('[data-pc-category-merge]').forEach(form=>{
  form.addEventListener('submit',event=>{
    const select=form.querySelector('select[name="target_id"]');

    if(!select?.value){
      event.preventDefault();
      select?.focus();
      return;
    }

    if(!confirm('Merge this category into the selected category and reassign every affected policy?')){
      event.preventDefault();
    }
  });
});

renderTable();

})();


/* ===== PUBLISHING GUIDE ===== */
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


/* ===== FEEDBACK ANALYTICS ===== */
(()=>{
  'use strict';

  const root=document.querySelector('[data-pc-feedback-analytics]');
  if(!root) return;

  const body=root.querySelector('[data-pc-feedback-rows]');
  const rows=[...root.querySelectorAll('[data-pc-feedback-row]')];
  const search=root.querySelector('[data-pc-feedback-search]');
  const category=root.querySelector('[data-pc-feedback-category]');
  const signal=root.querySelector('[data-pc-feedback-signal]');
  const sort=root.querySelector('[data-pc-feedback-sort]');
  const empty=root.querySelector('[data-pc-feedback-empty]');
  const visibleCount=root.querySelector('[data-pc-feedback-visible]');
  const exportButton=root.querySelector('[data-pc-feedback-export]');

  if(!body) return;

  const number=(value)=>{
    const parsed=Number(value);
    return Number.isFinite(parsed)?parsed:0;
  };

  const matchesSignal=(row,value)=>{
    const total=number(row.dataset.total);
    const rate=number(row.dataset.rate);

    if(value==='none') return total===0;
    if(total===0) return value==='all';
    if(value==='strong') return rate>=80;
    if(value==='mixed') return rate>=50 && rate<80;
    if(value==='attention') return rate<50;
    return true;
  };

  const compare=(a,b,value)=>{
    if(value==='rate_desc'){
      return number(b.dataset.rate)-number(a.dataset.rate)
        || number(b.dataset.total)-number(a.dataset.total)
        || (a.dataset.title||'').localeCompare(b.dataset.title||'');
    }
    if(value==='rate_asc'){
      return number(a.dataset.rate)-number(b.dataset.rate)
        || number(b.dataset.total)-number(a.dataset.total)
        || (a.dataset.title||'').localeCompare(b.dataset.title||'');
    }
    if(value==='title_asc'){
      return (a.dataset.title||'').localeCompare(b.dataset.title||'');
    }
    if(value==='updated_desc'){
      return number(b.dataset.updated)-number(a.dataset.updated)
        || number(b.dataset.total)-number(a.dataset.total);
    }

    return number(b.dataset.total)-number(a.dataset.total)
      || number(b.dataset.rate)-number(a.dataset.rate)
      || (a.dataset.title||'').localeCompare(b.dataset.title||'');
  };

  const apply=()=>{
    const q=(search?.value||'').trim().toLowerCase();
    const categoryValue=category?.value||'all';
    const signalValue=signal?.value||'all';
    const sortValue=sort?.value||'responses_desc';

    const ordered=[...rows].sort((a,b)=>compare(a,b,sortValue));
    ordered.forEach((row)=>body.insertBefore(row,empty));

    let visible=0;

    ordered.forEach((row)=>{
      const searchMatch=!q || (row.dataset.search||'').includes(q);
      const categoryMatch=categoryValue==='all'
        || (row.dataset.category||'')===categoryValue;
      const signalMatch=matchesSignal(row,signalValue);
      const show=searchMatch && categoryMatch && signalMatch;

      row.hidden=!show;
      if(show) visible++;
    });

    if(empty) empty.hidden=visible!==0;
    if(visibleCount) visibleCount.textContent=String(visible);
  };

  const csvCell=(value)=>{
    const text=String(value??'');
    return /[",\r\n]/.test(text)
      ? `"${text.replace(/"/g,'""')}"`
      : text;
  };

  const exportCsv=()=>{
    const visibleRows=rows.filter((row)=>!row.hidden);
    const lines=[[
      'Policy',
      'Category',
      'Helpful',
      'Not helpful',
      'Responses',
      'Helpful rate',
      'Top issue',
      'Last feedback'
    ]];

    visibleRows.forEach((row)=>{
      const cells=[...row.cells];
      const policyCell=cells[0];
      const title=policyCell?.querySelector('a')?.textContent?.trim()||'';
      const categoryText=policyCell?.querySelector('small')?.textContent?.trim()||'';
      const topIssue=cells[5]?.querySelector('.pc-feedback-top-issue');
      const topIssueText=topIssue
        ? [...topIssue.childNodes]
            .filter((node)=>node.nodeType===Node.TEXT_NODE)
            .map((node)=>node.textContent||'')
            .join(' ')
            .trim()
        : '—';

      lines.push([
        title,
        categoryText,
        row.dataset.helpful||'0',
        row.dataset.notHelpful||'0',
        row.dataset.total||'0',
        `${row.dataset.rate||'0'}%`,
        topIssueText||'—',
        cells[6]?.textContent?.trim()||'—'
      ]);
    });

    const csv='\uFEFF'+lines
      .map((line)=>line.map(csvCell).join(','))
      .join('\r\n');

    const blob=new Blob([csv],{type:'text/csv;charset=utf-8'});
    const url=URL.createObjectURL(blob);
    const link=document.createElement('a');
    const stamp=new Date().toISOString().slice(0,10);

    link.href=url;
    link.download=`barama-policy-feedback-${stamp}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(()=>URL.revokeObjectURL(url),0);
  };

  search?.addEventListener('input',apply);
  category?.addEventListener('change',apply);
  signal?.addEventListener('change',apply);
  sort?.addEventListener('change',apply);
  exportButton?.addEventListener('click',exportCsv);

  apply();
})();


/* ===== FEEDBACK REVIEWS ===== */
(()=>{
  'use strict';

  const root=document.querySelector('[data-pc-feedback-review]');
  if(!root) return;

  const body=root.querySelector('[data-pc-review-rows]');
  const rows=[...root.querySelectorAll('[data-pc-review-row]')];
  const empty=root.querySelector('[data-pc-review-empty]');
  const search=root.querySelector('[data-pc-review-search]');
  const severity=root.querySelector('[data-pc-review-severity]');
  const category=root.querySelector('[data-pc-review-category]');
  const sort=root.querySelector('[data-pc-review-sort]');
  const visible=root.querySelector('[data-pc-review-visible]');

  if(!body) return;

  const number=(value)=>{
    const parsed=Number(value);
    return Number.isFinite(parsed)?parsed:0;
  };

  const compare=(a,b,mode)=>{
    if(mode==='rate_asc'){
      return number(a.dataset.rate)-number(b.dataset.rate)
        || number(b.dataset.total)-number(a.dataset.total)
        || (a.dataset.title||'').localeCompare(b.dataset.title||'');
    }

    if(mode==='responses_desc'){
      return number(b.dataset.total)-number(a.dataset.total)
        || number(b.dataset.priority)-number(a.dataset.priority)
        || (a.dataset.title||'').localeCompare(b.dataset.title||'');
    }

    if(mode==='not_helpful_desc'){
      return number(b.dataset.notHelpful)-number(a.dataset.notHelpful)
        || number(b.dataset.priority)-number(a.dataset.priority)
        || (a.dataset.title||'').localeCompare(b.dataset.title||'');
    }

    if(mode==='title_asc'){
      return (a.dataset.title||'').localeCompare(b.dataset.title||'');
    }

    return number(b.dataset.priority)-number(a.dataset.priority)
      || number(a.dataset.rate)-number(b.dataset.rate)
      || number(b.dataset.total)-number(a.dataset.total)
      || (a.dataset.title||'').localeCompare(b.dataset.title||'');
  };

  const apply=()=>{
    const q=(search?.value||'').trim().toLowerCase();
    const severityValue=severity?.value||'all';
    const categoryValue=category?.value||'all';
    const sortValue=sort?.value||'priority_desc';

    const ordered=[...rows].sort((a,b)=>compare(a,b,sortValue));
    ordered.forEach((row)=>body.insertBefore(row,empty));

    let count=0;

    ordered.forEach((row)=>{
      const searchMatch=!q || (row.dataset.search||'').includes(q);
      const severityMatch=severityValue==='all'
        || (row.dataset.severity||'')===severityValue;
      const categoryMatch=categoryValue==='all'
        || (row.dataset.category||'')===categoryValue;
      const show=searchMatch && severityMatch && categoryMatch;

      row.hidden=!show;
      if(show) count++;
    });

    if(empty) empty.hidden=count!==0;
    if(visible) visible.textContent=String(count);
  };

  search?.addEventListener('input',apply);
  severity?.addEventListener('change',apply);
  category?.addEventListener('change',apply);
  sort?.addEventListener('change',apply);

  apply();
})();


/* ===== PUBLIC POLICIES ===== */
(()=>{
  'use strict';

  const faqGroups=document.querySelectorAll('.pc-faq, .pc-inline-faq');

  faqGroups.forEach(group=>{
    const details=[...group.querySelectorAll('details')];

    details.forEach(item=>{
      item.addEventListener('toggle',()=>{
        if(!item.open) return;

        details.forEach(other=>{
          if(other!==item) other.open=false;
        });
      });
    });
  });
})();


/* BARAMA_POLICY_CENTER_PUBLIC_V119_DETAIL_NAV */
(()=>{
  'use strict';

  const article=document.querySelector('.pc-public-body--detail .pc-detail');
  const prose=article?.querySelector('.pc-prose');
  const toc=document.querySelector('[data-policy-toc]');
  const list=toc?.querySelector('[data-policy-toc-list]');

  if(!article || !prose || !toc || !list) return;

  const headings=[...prose.querySelectorAll('h2,h3')]
    .filter(h=>h.textContent.trim().length>0);

  if(!headings.length) return;

  const used=new Set();

  const slugify=(value)=>{
    const base=value
      .toLowerCase()
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g,'')
      .replace(/[^a-z0-9]+/g,'-')
      .replace(/^-+|-+$/g,'')
      .slice(0,72) || 'section';

    let slug=base;
    let i=2;
    while(used.has(slug) || document.getElementById(slug)){
      slug=`${base}-${i++}`;
    }
    used.add(slug);
    return slug;
  };

  const links=[];

  headings.forEach((heading)=>{
    if(!heading.id){
      heading.id=slugify(heading.textContent.trim());
    }else{
      used.add(heading.id);
    }

    const link=document.createElement('a');
    link.href=`#${encodeURIComponent(heading.id)}`;
    link.textContent=heading.textContent.trim();
    link.dataset.level=heading.tagName==='H3'?'3':'2';

    link.addEventListener('click',(event)=>{
      const target=document.getElementById(heading.id);
      if(!target) return;
      event.preventDefault();
      target.scrollIntoView({
        behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',
        block:'start'
      });
      history.replaceState(null,'',`#${encodeURIComponent(heading.id)}`);
    });

    list.appendChild(link);
    links.push({heading,link});
  });

  toc.hidden=false;

  if('IntersectionObserver' in window){
    const observer=new IntersectionObserver((entries)=>{
      const visible=entries
        .filter(entry=>entry.isIntersecting)
        .sort((a,b)=>a.boundingClientRect.top-b.boundingClientRect.top);

      if(!visible.length) return;
      const id=visible[0].target.id;
      links.forEach(({heading,link})=>{
        link.classList.toggle('is-active',heading.id===id);
      });
    },{
      rootMargin:'-18% 0px -70% 0px',
      threshold:[0,1]
    });

    headings.forEach(h=>observer.observe(h));
  }
})();


/* BARAMA_POLICY_CENTER_PUBLIC_V120_COPY_LINK */
(()=>{
  'use strict';

  const button=document.querySelector('[data-policy-copy-url]');
  if(!button) return;

  const originalLabel=button.getAttribute('aria-label') || 'Copy policy link';
  let resetTimer=null;

  const fallbackCopy=(value)=>{
    const area=document.createElement('textarea');
    area.value=value;
    area.setAttribute('readonly','');
    area.style.position='fixed';
    area.style.opacity='0';
    area.style.pointerEvents='none';
    document.body.appendChild(area);
    area.select();

    let copied=false;
    try{
      copied=document.execCommand('copy');
    }catch(_error){
      copied=false;
    }

    area.remove();
    return copied;
  };

  const markCopied=()=>{
    button.classList.add('is-copied');
    button.setAttribute('aria-label','Policy link copied');
    button.setAttribute('title','Policy link copied');

    window.clearTimeout(resetTimer);
    resetTimer=window.setTimeout(()=>{
      button.classList.remove('is-copied');
      button.setAttribute('aria-label',originalLabel);
      button.setAttribute('title',originalLabel);
    },1800);
  };

  button.addEventListener('click',async()=>{
    const value=window.location.href;

    try{
      if(navigator.clipboard && window.isSecureContext){
        await navigator.clipboard.writeText(value);
        markCopied();
        return;
      }
    }catch(_error){}

    if(fallbackCopy(value)){
      markCopied();
    }
  });
})();

/* BARAMA_POLICY_CENTER_PUBLIC_V122_FEEDBACK_PERSISTENCE */
(()=>{
  'use strict';
  const root=document.querySelector('[data-policy-feedback]');
  const form=root?.querySelector('[data-policy-feedback-form]');
  if(!root||!form)return;
  const policyId=form.dataset.policyId||'';
  const valueInput=form.querySelector('[data-policy-feedback-input]');
  const reasonInput=form.querySelector('[data-policy-feedback-reason-input]');
  const voteButtons=[...form.querySelectorAll('[data-policy-feedback-value]')];
  const reasonWrap=form.querySelector('[data-policy-feedback-reasons]');
  const reasonButtons=[...form.querySelectorAll('[data-policy-feedback-reason]')];
  const sendButton=form.querySelector('[data-policy-feedback-send]');
  const note=form.querySelector('[data-policy-feedback-note]');
  const storageKey=policyId?`barama.policy.feedback.${policyId}`:'';
  const setNote=(m,e=false)=>{if(!note)return;note.textContent=m;note.classList.toggle('is-error',e)};
  const complete=(m)=>{root.classList.remove('is-submitting');root.classList.add('is-complete');setNote(m,false)};
  const markVote=(v)=>{voteButtons.forEach(b=>b.setAttribute('aria-pressed',b.dataset.policyFeedbackValue===v?'true':'false'));if(valueInput)valueInput.value=v};
  const markReason=(r)=>{reasonButtons.forEach(b=>b.setAttribute('aria-pressed',b.dataset.policyFeedbackReason===r?'true':'false'));if(reasonInput)reasonInput.value=r};
  const submit=async()=>{
    if(!valueInput?.value)return;
    root.classList.add('is-submitting');setNote('Saving anonymous aggregate feedback…',false);
    try{
      const response=await fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      const body=(await response.text()).trim();
      if(!response.ok||body!=='OK')throw new Error('feedback failed');
      if(storageKey){try{localStorage.setItem(storageKey,JSON.stringify({value:valueInput.value,reason:reasonInput?.value||''}))}catch(_e){}}
      complete('Thanks — your anonymous feedback was recorded.');
    }catch(_e){root.classList.remove('is-submitting');setNote('We could not save feedback. Please try again.',true)}
  };
  if(storageKey){try{if(localStorage.getItem(storageKey)){complete('Thanks — feedback from this browser was already recorded.');return}}catch(_e){}}
  voteButtons.forEach(b=>b.addEventListener('click',()=>{const v=b.dataset.policyFeedbackValue||'';markVote(v);markReason('');if(v==='helpful'){if(reasonWrap)reasonWrap.hidden=true;submit();return}if(reasonWrap)reasonWrap.hidden=false;setNote('Optional: choose a reason, then send feedback. Only aggregate counts are stored.',false)}));
  reasonButtons.forEach(b=>b.addEventListener('click',()=>markReason(b.dataset.policyFeedbackReason||'')));
  sendButton?.addEventListener('click',()=>{if(valueInput?.value!=='not_helpful')markVote('not_helpful');submit()});
})();

