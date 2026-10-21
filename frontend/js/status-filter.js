(() => {
'use strict';
function icon(name){const node=document.createElement('i');node.className='fa-solid fa-'+name;node.setAttribute('aria-hidden','true');return node;}
function enhance(select){
 const wrap=document.createElement('div');wrap.className='status-filter';
 const trigger=document.createElement('button');trigger.type='button';trigger.className='status-filter__trigger';trigger.setAttribute('aria-expanded','false');
 const caption=document.createElement('span');caption.className='status-filter__caption';trigger.append(caption,icon('chevron-down'));
 const menu=document.createElement('div');menu.className='status-filter__menu';menu.hidden=true;menu.id=select.id+'-menu';menu.setAttribute('role','group');menu.setAttribute('aria-label','Lọc trạng thái');trigger.setAttribute('aria-controls',menu.id);
 wrap.append(trigger,menu);select.after(wrap);select.hidden=true;
 const options=Array.from(select.options),children=options.filter(o=>['pending','accepted','on_trip','running'].includes(o.value));
 function sync(){caption.textContent=select.selectedOptions[0]?.textContent||'Tất cả trạng thái';trigger.setAttribute('aria-label','Lọc trạng thái: '+caption.textContent);trigger.disabled=select.disabled;for(const b of menu.querySelectorAll('[data-value]'))b.setAttribute('aria-pressed',String(b.dataset.value===select.value));for(const group of menu.querySelectorAll('.status-filter__parent'))group.classList.toggle('has-selection',children.some(o=>o.value===select.value));}
 function position(){
  if(menu.hidden)return;menu.style.left='0px';const rect=wrap.getBoundingClientRect();const width=menu.getBoundingClientRect().width;
  const left=Math.max(12,Math.min(rect.left,window.innerWidth-width-12));menu.style.left=(left-rect.left)+'px';
  wrap.classList.toggle('opens-left',window.innerWidth>640&&left+width+260>window.innerWidth-12);
 }
 function close(){menu.hidden=true;trigger.setAttribute('aria-expanded','false');for(const group of menu.querySelectorAll('.status-filter__parent')){group.classList.remove('is-open');group.querySelector('.status-filter__expand').setAttribute('aria-expanded','false');group.querySelector('.status-filter__expand').setAttribute('aria-label','Mở trạng thái con Đã phân công');}}
 function reveal(){menu.hidden=false;trigger.setAttribute('aria-expanded','true');sync();position();}
 function button(option,container){const b=document.createElement('button');b.type='button';b.textContent=option.textContent;b.dataset.value=option.value;b.addEventListener('click',()=>{select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));sync();close();trigger.focus();});container.append(b);return b;}
 for(const option of options){
  if(children.includes(option))continue;
  if(option.value!=='assigned'){button(option,menu);continue;}
  const group=document.createElement('div');group.className='status-filter__parent';button(option,group);
  const expand=document.createElement('button');expand.type='button';expand.className='status-filter__expand';expand.append(icon('chevron-right'));expand.setAttribute('aria-label','Mở trạng thái con Đã phân công');expand.setAttribute('aria-expanded','false');
  const submenu=document.createElement('div');submenu.className='status-filter__submenu';submenu.id=select.id+'-children';submenu.setAttribute('role','group');submenu.setAttribute('aria-label','Trạng thái đã phân công');expand.setAttribute('aria-controls',submenu.id);
  for(const child of children)button(child,submenu);group.append(expand,submenu);menu.append(group);
  const expanded=()=>group.classList.contains('is-open');const open=value=>{position();group.classList.toggle('is-open',value);expand.setAttribute('aria-expanded',String(value));expand.setAttribute('aria-label',(value?'Đóng':'Mở')+' trạng thái con Đã phân công');};
  group.addEventListener('pointerenter',event=>{if(event.pointerType==='mouse')open(true);});
  group.addEventListener('pointerleave',event=>{if(event.pointerType==='mouse'&&!group.contains(document.activeElement))open(false);});
  group.addEventListener('focusout',()=>queueMicrotask(()=>{if(!group.contains(document.activeElement))open(false);}));
  expand.addEventListener('click',()=>open(!expanded()));
  group.addEventListener('keydown',event=>{if(event.key==='ArrowRight'){event.preventDefault();open(true);submenu.querySelector('button').focus();}if(event.key==='ArrowLeft'){event.preventDefault();open(false);expand.focus();}});
 }
 trigger.addEventListener('click',()=>menu.hidden?reveal():close());
 wrap.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();close();trigger.focus();}if(event.key==='ArrowDown'&&event.target===trigger){event.preventDefault();reveal();menu.querySelector('button').focus();}});
 document.addEventListener('click',event=>{if(!wrap.contains(event.target))close();});window.addEventListener('resize',position);
 select.addEventListener('change',sync);select.form?.addEventListener('reset',()=>queueMicrotask(sync));document.getElementById('resetFilter')?.addEventListener('click',()=>queueMicrotask(sync));sync();
}
document.querySelectorAll('select[data-status-filter]').forEach(enhance);
})();
