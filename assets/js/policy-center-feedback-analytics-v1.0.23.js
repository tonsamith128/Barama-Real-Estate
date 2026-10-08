/* BARAMA_POLICY_FEEDBACK_ANALYTICS_V1023 */
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
