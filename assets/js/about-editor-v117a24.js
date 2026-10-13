/* About A24 repeatable editors: delegated controls, no global Contact changes. */
(function(){
  'use strict';
  var descriptors={
    values:[['title','Title',110,'text'],['description','Description',360,'textarea']],
    stats:[['value','Value',30,'text'],['label','Label',100,'text']],
    team:[['name','Name',120,'text'],['role','Role',120,'text'],['bio','Bio',500,'textarea'],['image_url','Image URL',1000,'url']]
  };
  function element(tag,className,text){var node=document.createElement(tag);if(className)node.className=className;if(text!==undefined)node.textContent=text;return node;}
  function renumber(group){
    var kind=group.dataset.kind, rows=Array.from(group.querySelectorAll('[data-a24-item]'));
    rows.forEach(function(row,i){
      var heading=row.querySelector('[data-a24-item-title]');if(heading)heading.textContent=(kind==='team'?'Member':kind==='values'?'Value':'Stat')+' '+(i+1);
      row.querySelectorAll('[data-a24-field]').forEach(function(field){field.name='items['+i+']['+field.dataset.a24Field+']';});
      // Existing PHP-generated fields do not carry data-a24-field.
      row.querySelectorAll('[name^="items["]').forEach(function(field){var match=field.name.match(/\]\[([^\]]+)\]$/);if(match)field.name='items['+i+']['+match[1]+']';});
    });
    var parent=group.closest('form'), btn=parent&&parent.querySelector('[data-a24-add]');if(btn)btn.disabled=rows.length>=Number(group.dataset.max||8);
  }
  function add(group){
    var kind=group.dataset.kind, max=Number(group.dataset.max||8), rows=group.querySelectorAll('[data-a24-item]');
    if(rows.length>=max || !descriptors[kind])return;
    var row=element('section','barama-about-admin-a24__item');row.dataset.a24Item='';
    var head=element('div','barama-about-admin-a24__item-head'), title=element('strong','','New item');title.dataset.a24ItemTitle='';
    var actions=element('div');[['up','↑'],['down','↓']].forEach(function(pair){var b=element('button','btn btn-outline-secondary',pair[1]);b.type='button';b.dataset.a24Move=pair[0];b.setAttribute('aria-label','Move '+pair[0]);actions.appendChild(b);});
    var del=element('button','btn btn-outline-secondary','Remove');del.type='button';del.dataset.a24Remove='';actions.appendChild(del);head.append(title,actions);row.appendChild(head);
    descriptors[kind].forEach(function(desc){var label=element('label'),span=element('span','',desc[1]);var input=element(desc[3]==='textarea'?'textarea':'input','form-control');if(desc[3]!=='textarea')input.type=desc[3];else input.rows=3;input.maxLength=desc[2];input.dataset.a24Field=desc[0];label.append(span,input);row.appendChild(label);});
    group.appendChild(row);renumber(group);var first=row.querySelector('input,textarea');if(first)first.focus();
  }
  document.addEventListener('click',function(event){
    if(!(event.target instanceof Element))return;
    var btn=event.target.closest('[data-a24-add],[data-a24-remove],[data-a24-move]');if(!btn)return;
    var group=btn.closest('[data-a24-items]')||(btn.closest('form')&&btn.closest('form').querySelector('[data-a24-items]'));
    if(!group||!group.closest('.barama-about-admin-a24'))return;
    if(btn.matches('[data-a24-add]'))add(group);
    else{var row=btn.closest('[data-a24-item]');if(!row)return;
      if(btn.matches('[data-a24-remove]'))row.remove();
      else if(btn.dataset.a24Move==='up'&&row.previousElementSibling)group.insertBefore(row,row.previousElementSibling);
      else if(btn.dataset.a24Move==='down'&&row.nextElementSibling)group.insertBefore(row.nextElementSibling,row);
      renumber(group);
    }
  });
  document.querySelectorAll('.barama-about-admin-a24 [data-a24-items]').forEach(renumber);
})();
