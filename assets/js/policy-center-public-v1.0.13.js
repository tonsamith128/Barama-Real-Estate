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
