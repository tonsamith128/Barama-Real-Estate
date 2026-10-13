/* Barama About A26: repeatable editor, exact field names, responsive actions. */
(function(){
  'use strict';
  const fields={
    values:[['title','Title',110,'text'],['description','Description',360,'textarea']],
    stats:[['value','Value',30,'text'],['label','Label',100,'text']],
    team:[['name','Name',120,'text'],['role','Role',120,'text'],['bio','Bio',500,'textarea'],['image_url','Image URL',1000,'url']]
  };
  function node(tag,cls,text){const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;}
  function rows(group){return Array.from(group.children).filter(el=>el.hasAttribute('data-a24-item'));}
  function action(name,text){const b=node('button','btn btn-outline-secondary',text);b.type='button';if(name==='remove')b.dataset.a24Remove='';else b.dataset.a24Move=name;b.setAttribute('aria-label',name==='remove'?'Remove item':'Move item '+name);return b;}
  function refresh(group){
    const list=rows(group),kind=group.dataset.kind,max=Number(group.dataset.max||8),form=group.closest('form');
    list.forEach((row,index)=>{
      const h=row.querySelector('[data-a24-item-title]');if(h)h.textContent=(kind==='team'?'Member':kind==='values'?'Value':'Stat')+' '+(index+1);
      row.querySelectorAll('[data-a24-field], [name^="items["]').forEach(field=>{
        const key=field.dataset.a24Field||(field.name.match(/\]\[([^\]]+)\]$/)||[])[1];
        if(key)field.name='items['+index+']['+key+']';
      });
      row.querySelectorAll('[data-a24-move]').forEach(btn=>{
        const direction=btn.dataset.a24Move;
        btn.disabled=direction==='up'?index===0:index===list.length-1;
      });
    });
    const add=form&&form.querySelector('[data-a24-add]');if(add)add.disabled=list.length>=max;
    const status=form&&form.querySelector('[data-a26-count]');if(status)status.textContent=list.length+' / '+max+' items';
  }
  function add(group){
    const kind=group.dataset.kind,limit=Number(group.dataset.max||8);
    if(!fields[kind]||rows(group).length>=limit)return;
    const row=node('section','barama-about-admin-a24__item');row.dataset.a24Item='';
    const head=node('div','barama-about-admin-a24__item-head');
    const h=node('strong','', 'New item');h.dataset.a24ItemTitle='';
    const actions=node('div','barama-about-a26__actions');
    actions.append(action('up','↑'),action('down','↓'),action('remove','Remove'));head.append(h,actions);row.append(head);
    fields[kind].forEach(([key,label,max,type])=>{
      const wrap=node('label'),span=node('span','',label),el=node(type==='textarea'?'textarea':'input','form-control');
      if(type==='textarea')el.rows=3;else el.type=type;
      el.maxLength=max;el.dataset.a24Field=key;
      wrap.append(span,el);row.append(wrap);
    });
    group.append(row);refresh(group);row.querySelector('input,textarea')?.focus();
  }
  function init(root){
    root.querySelectorAll('.barama-about-admin-a24 [data-a24-items]').forEach(group=>{
      if(!group.dataset.a26Ready){
        group.dataset.a26Ready='1';
        const form=group.closest('form'),addButton=form&&form.querySelector('[data-a24-add]');
        if(addButton){
          const count=node('span','barama-about-a26__count');count.dataset.a26Count='';count.setAttribute('aria-live','polite');addButton.insertAdjacentElement('afterend',count);
        }
      }
      refresh(group);
    });
  }
  document.addEventListener('click',event=>{
    const target=event.target;if(!(target instanceof Element))return;
    const btn=target.closest('[data-a24-add], [data-a24-remove], [data-a24-move]');if(!btn)return;
    const form=btn.closest('form'),group=btn.closest('[data-a24-items]')||(form&&form.querySelector('[data-a24-items]'));
    if(!group||!group.closest('.barama-about-admin-a24'))return;
    if(btn.matches('[data-a24-add]')){add(group);return;}
    const row=btn.closest('[data-a24-item]');if(!row)return;
    if(btn.matches('[data-a24-remove]')){row.remove();refresh(group);return;}
    if(btn.dataset.a24Move==='up'&&row.previousElementSibling)group.insertBefore(row,row.previousElementSibling);
    else if(btn.dataset.a24Move==='down'&&row.nextElementSibling)group.insertBefore(row.nextElementSibling,row);
    refresh(group);
  });
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>init(document),{once:true});
  else init(document);
  // The workspace can swap its content after an Ajax navigation. Initialize
  // newly inserted panels without duplicating click handlers.
  document.addEventListener('focusin',event=>{
    if(event.target instanceof Element&&event.target.closest('.barama-about-admin-a24'))init(document);
  });
})();
