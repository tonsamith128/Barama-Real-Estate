/* BARAMA_POLICY_CENTER_V109 */
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
