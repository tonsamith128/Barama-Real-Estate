/* BARAMA_POLICY_CENTER_PUBLIC_V113 */
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
