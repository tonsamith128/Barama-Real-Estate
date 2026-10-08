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


/* BARAMA_POLICY_CENTER_PUBLIC_V121_FEEDBACK_PREVIEW */
(()=>{
  'use strict';

  const root=document.querySelector('[data-policy-feedback]');
  if(!root) return;

  const buttons=[...root.querySelectorAll('[data-policy-feedback-value]')];
  const note=root.querySelector('[data-policy-feedback-note]');

  buttons.forEach((button)=>{
    button.addEventListener('click',()=>{
      buttons.forEach((item)=>{
        item.setAttribute(
          'aria-pressed',
          item===button?'true':'false'
        );
      });

      if(note){
        note.textContent='Thanks — this preview response is not stored.';
        note.classList.add('is-preview-response');
      }
    });
  });
})();
