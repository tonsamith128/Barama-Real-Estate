/* BARAMA_PUBLIC_LOCATION_PICKER_V115 */
(() => {
  'use strict';
  const roots = Array.from(document.querySelectorAll('[data-public-location-picker]'));
  if (!roots.length) return;

  const requestJson = async (url) => {
    const response = await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
    const data = await response.json().catch(()=>({}));
    if (!response.ok || data.ok === false) throw new Error(data.message || `Location request failed (${response.status}).`);
    return data;
  };
  const flagFor = (code) => {
    const cc=String(code||'').toUpperCase();
    if(!/^[A-Z]{2}$/.test(cc)) return '🌐';
    return [...cc].map(c=>String.fromCodePoint(127397+c.charCodeAt(0))).join('');
  };
  const humanize = (value) => String(value||'Area').replace(/[_-]+/g,' ').replace(/\b\w/g,m=>m.toUpperCase());

  roots.forEach((root) => {
    if (root.dataset.publicLocationReady === '1') return;
    root.dataset.publicLocationReady='1';

    const endpoint=root.dataset.locationEndpoint||'/location-options';
    const initialValue=String(root.dataset.selectedLocation||'').trim();
    const emptyLabel=root.dataset.emptyLabel||'All locations';
    const trigger=root.querySelector('[data-public-location-trigger]');
    const quick=root.querySelector('[data-public-location-quick]');
    const quickList=root.querySelector('[data-public-location-country-list]');
    const quickSearch=root.querySelector('[data-public-location-country-search]');
    const advanced=root.querySelector('[data-public-location-advanced]');
    const close=root.querySelector('[data-public-location-close]');
    const done=root.querySelector('[data-public-location-done]');
    const clear=root.querySelector('[data-public-location-clear]');
    const countrySelect=root.querySelector('[data-public-location-country-select]');
    const levels=root.querySelector('[data-public-location-levels]');
    const value=root.querySelector('[data-public-location-value]');
    const label=root.querySelector('[data-public-location-label]');
    const status=root.querySelector('[data-public-location-status]');
    const form=root.closest('form');
    const advancedTrigger=form?.querySelector('[data-public-location-advanced-trigger]')||null;
    if(!trigger||!quick||!quickList||!advanced||!countrySelect||!levels||!value||!label) return;

    document.body.appendChild(quick);
    document.body.appendChild(advanced);

    let countries=[];
    let ordered=[];
    let activeCountry='';
    let currentCountryName='';
    let selection=null;
    let openPanel=null;
    let anchorEl=trigger;

    const setStatus=(message='',error=false)=>{if(!status)return;status.textContent=message;status.classList.toggle('is-error',error);};
    const setChoice=(nextValue,nextLabel)=>{value.value=String(nextValue||'');label.textContent=nextLabel||emptyLabel;};
    const findCountry=(code)=>countries.find(r=>String(r.country_code||'').toUpperCase()===String(code||'').toUpperCase())||null;
    const findCountryName=(code)=>String(findCountry(code)?.name||code||'');
    const sortCountries=(rows)=>{
      const list=Array.isArray(rows)?rows.slice():[];
      list.sort((a,b)=>{
        const ad=Number(a.is_default)===1?0:1, bd=Number(b.is_default)===1?0:1;
        if(ad!==bd)return ad-bd;
        return String(a.name||'').localeCompare(String(b.name||''));
      });
      return list;
    };
    const clearLevels=()=>{levels.innerHTML='';requestAnimationFrame(positionPanel);};

    const positionPanel=()=>{
      if(!openPanel||openPanel.hidden||!anchorEl)return;
      const mobile=window.matchMedia('(max-width:760px)').matches;
      if(mobile){Object.assign(openPanel.style,{left:'12px',right:'12px',top:'auto',bottom:'12px',width:'auto'});return;}
      const rect=anchorEl.getBoundingClientRect();
      const vw=document.documentElement.clientWidth||window.innerWidth;
      const vh=document.documentElement.clientHeight||window.innerHeight;
      const margin=12,gap=8;
      const width=Math.min(openPanel===quick?400:430,vw-margin*2);
      openPanel.style.right='auto';openPanel.style.bottom='auto';openPanel.style.width=`${width}px`;
      openPanel.style.left=`${Math.max(margin,Math.min(rect.left,vw-width-margin))}px`;
      const natural=Math.min(openPanel.scrollHeight||420,vh-margin*2);
      const below=vh-rect.bottom-margin-gap,above=rect.top-margin-gap;
      const useAbove=below<Math.min(natural,390)&&above>below;
      openPanel.style.top=useAbove?`${Math.max(margin,rect.top-gap-natural)}px`:`${Math.min(vh-natural-margin,rect.bottom+gap)}px`;
    };
    const shut=()=>{[quick,advanced].forEach(p=>p.hidden=true);root.classList.remove('is-open');trigger.setAttribute('aria-expanded','false');openPanel=null;};
    const open=(panel,anchor)=>{[quick,advanced].forEach(p=>{if(p!==panel)p.hidden=true;});openPanel=panel;anchorEl=anchor||trigger;panel.hidden=false;root.classList.add('is-open');if(panel===quick)trigger.setAttribute('aria-expanded','true');positionPanel();requestAnimationFrame(positionPanel);};

    const renderQuick=(filter='')=>{
      const needle=String(filter||'').trim().toLowerCase();
      quickList.innerHTML='';
      const add=(code,name,isDefault=false)=>{
        if(needle && !String(name).toLowerCase().includes(needle))return;
        const button=document.createElement('button');button.type='button';button.className='barama-public-location-picker__quick-item';button.dataset.countryCode=code;if(code===activeCountry)button.classList.add('is-active');
        const flag=document.createElement('span');flag.className='barama-public-location-picker__flag';flag.textContent=code?flagFor(code):'🌐';
        const text=document.createElement('span');text.className='barama-public-location-picker__country-name';text.textContent=name;button.append(flag,text);
        if(isDefault){const badge=document.createElement('span');badge.className='barama-public-location-picker__default';badge.textContent='Default';button.appendChild(badge);}
        if(code){const arrow=document.createElement('span');arrow.className='barama-public-location-picker__arrow';arrow.textContent='›';button.appendChild(arrow);}
        button.addEventListener('click',()=>{activeCountry=String(code||'').toUpperCase();currentCountryName=code?name:'';if(!code)setChoice('',emptyLabel);else setChoice(`country:${activeCountry}`,name);renderQuick(quickSearch?.value||'');shut();});
        quickList.appendChild(button);
      };
      add('',emptyLabel,false);
      ordered.forEach(r=>add(String(r.country_code||'').toUpperCase(),String(r.name||r.country_code||''),Number(r.is_default)===1));
    };

    const populateCountrySelect=()=>{
      countrySelect.innerHTML='';
      const all=document.createElement('option');all.value='';all.textContent=`🌐 ${emptyLabel}`;countrySelect.appendChild(all);
      ordered.forEach(r=>{const code=String(r.country_code||'').toUpperCase();const option=document.createElement('option');option.value=code;option.textContent=`${flagFor(code)} ${String(r.name||code)}${Number(r.is_default)===1?' — Default':''}`;countrySelect.appendChild(option);});
      countrySelect.value=activeCountry;
    };

    const typeLabel=(rows,depth)=>{const types=[...new Set(rows.map(r=>humanize(r.type)).filter(Boolean))];if(types.length===1)return types[0];if(depth===0)return 'State / Province / Region';if(depth===1)return 'District / County / Area';return `Local area ${depth+1}`;};
    const loadLevel=async(countryCode,parentId,depth,selectedId=0,parentLabel='')=>{
      const params=new URLSearchParams({country:countryCode});if(parentId)params.set('parent_id',String(parentId));
      const data=await requestJson(`${endpoint}?${params.toString()}`);const rows=Array.isArray(data.locations)?data.locations:[];if(!rows.length)return null;
      const field=document.createElement('label');field.className='barama-public-location-picker__field';field.dataset.publicLocationLevel=String(depth);
      const caption=document.createElement('span');caption.textContent=typeLabel(rows,depth);const select=document.createElement('select');
      const blank=document.createElement('option');blank.value='';blank.textContent=parentLabel?`All ${parentLabel}`:`All ${currentCountryName||'areas'}`;select.appendChild(blank);
      rows.forEach(row=>{const opt=document.createElement('option');opt.value=String(row.id||'');opt.textContent=String(row.name||'');opt.dataset.locationName=String(row.name||'');if(Number(row.id)===Number(selectedId))opt.selected=true;select.appendChild(opt);});
      field.append(caption,select);levels.appendChild(field);requestAnimationFrame(positionPanel);
      select.addEventListener('change',async()=>{Array.from(levels.querySelectorAll('[data-public-location-level]')).forEach(n=>{if(Number(n.dataset.publicLocationLevel)>depth)n.remove();});const id=Number(select.value||0);if(!id){if(parentId)setChoice(String(parentId),parentLabel||currentCountryName);else setChoice(`country:${countryCode}`,currentCountryName||countryCode);return;}const opt=select.options[select.selectedIndex];const name=opt?.dataset?.locationName||opt?.textContent||'Selected area';setChoice(String(id),name);try{await loadLevel(countryCode,id,depth+1,0,name);}catch(error){setStatus(error.message||'Unable to load the next area.',true);}});
      return select;
    };
    const advancedCountryChanged=async(code)=>{clearLevels();activeCountry=String(code||'').toUpperCase();currentCountryName=findCountryName(activeCountry);if(!activeCountry){setChoice('',emptyLabel);setStatus('Search all enabled locations.');renderQuick();return;}setChoice(`country:${activeCountry}`,currentCountryName);renderQuick();setStatus('Loading areas…');try{await loadLevel(activeCountry,null,0,0,currentCountryName);setStatus('Choose an area only if you need more detail.');}catch(error){setStatus(error.message||'Unable to load areas.',true);}};
    const openAdvanced=async()=>{populateCountrySelect();clearLevels();if(activeCountry){currentCountryName=findCountryName(activeCountry);try{if(selection&&Array.isArray(selection.path)&&selection.path.length){let parent=0,parentLabel=currentCountryName;for(let depth=0;depth<selection.path.length;depth++){const node=selection.path[depth];await loadLevel(activeCountry,parent||null,depth,Number(node.id),parentLabel);parent=Number(node.id);parentLabel=String(node.name||parentLabel);}if(parent)await loadLevel(activeCountry,parent,selection.path.length,0,parentLabel);}else{await loadLevel(activeCountry,null,0,0,currentCountryName);}}catch(error){setStatus(error.message||'Unable to load areas.',true);}}open(advanced,advancedTrigger||trigger);};

    const initialize=async()=>{try{const numeric=/^\d+$/.test(initialValue)?Number(initialValue):0;const token=/^country:([A-Za-z]{2})$/.exec(initialValue);const data=numeric>0?await requestJson(`${endpoint}?location_id=${encodeURIComponent(String(numeric))}`):await requestJson(endpoint);countries=Array.isArray(data.countries)?data.countries:[];ordered=sortCountries(countries);selection=data.selection&&typeof data.selection==='object'?data.selection:null;activeCountry=String(selection?.country_code||(token?token[1]:'')).toUpperCase();currentCountryName=findCountryName(activeCountry);if(numeric>0&&selection&&Array.isArray(selection.path)&&selection.path.length)setChoice(String(numeric),String(selection.path.at(-1)?.name||currentCountryName||emptyLabel));else if(activeCountry)setChoice(`country:${activeCountry}`,currentCountryName);else setChoice('',emptyLabel);renderQuick();populateCountrySelect();}catch(error){quickList.innerHTML='<div class="barama-public-location-picker__error">Unable to load locations.</div>';setStatus(error.message||'Unable to load locations.',true);}};

    trigger.addEventListener('click',(event)=>{event.preventDefault();event.stopPropagation();if(openPanel===quick&&!quick.hidden)shut();else{if(quickSearch)quickSearch.value='';renderQuick();open(quick,trigger);setTimeout(()=>quickSearch?.focus(),0);}});
    quickSearch?.addEventListener('input',()=>renderQuick(quickSearch.value));
    advancedTrigger?.addEventListener('click',(event)=>{event.preventDefault();event.stopPropagation();openAdvanced();});
    countrySelect.addEventListener('change',()=>advancedCountryChanged(countrySelect.value));
    close?.addEventListener('click',shut);done?.addEventListener('click',shut);
    clear?.addEventListener('click',()=>{selection=null;activeCountry='';currentCountryName='';countrySelect.value='';clearLevels();setChoice('',emptyLabel);renderQuick();setStatus('Search all enabled locations.');});
    [quick,advanced].forEach(panel=>panel.addEventListener('click',e=>e.stopPropagation()));
    document.addEventListener('click',(event)=>{if(openPanel&&!root.contains(event.target)&&!openPanel.contains(event.target)&&event.target!==advancedTrigger)shut();});
    document.addEventListener('keydown',(event)=>{if(event.key==='Escape'&&openPanel){shut();trigger.focus();}});
    window.addEventListener('resize',positionPanel,{passive:true});window.addEventListener('scroll',positionPanel,{passive:true,capture:true});
    if('ResizeObserver'in window){const ro=new ResizeObserver(positionPanel);ro.observe(quick);ro.observe(advanced);}
    initialize();
  });
})();
