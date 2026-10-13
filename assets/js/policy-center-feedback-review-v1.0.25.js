/* BARAMA_POLICY_FEEDBACK_REVIEW_V1025 */
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
