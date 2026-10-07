// Pure DOM/fetch doubles. No browser cookies, HTTP CRUD, or database writes.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname,'../frontend/js/vehicles.js'),'utf8');
class Element {
    constructor() { this.value='';this.textContent='';this.children=[];this.events={};this.attributes={};this.dataset={};this.disabled=false;this.hidden=false; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children=children; }
    setAttribute(k,v) { this.attributes[k]=v; }
    addEventListener(k,cb) { this.events[k]=cb; }
    showModal() { this.open=true; }
    close() { this.open=false; }
    focus() { this.focused=true; }
}
async function run() {
    const elements=new Map();const $=id=>{if(!elements.has(id))elements.set(id,new Element());return elements.get(id);};
    const fields=Object.fromEntries(['license_plate','vehicle_type','brand','capacity','status'].map(k=>[k,new Element()]));
    const form=$('vehicleForm');form.elements={namedItem:k=>fields[k]};form.reset=()=>{for(const field of Object.values(fields))field.value='';fields.status.value='available';};
    const windowEvents={},requests=[],writes=[],redirects=[];
    let role='STAFF',listMode='normal',denied=false,expired=false,writeError=null;
    const fixture={vehicle_id:'9007199254740993',license_plate:'51C-123.45',vehicle_type:'<script>alert(1)</script>',brand:null,capacity:'1250.50',status:'available',has_active_assignment:false,has_active_trip:false,created_at:'2026-10-07',updated_at:'2026-10-07'};
    const sandbox={document:{getElementById:$,createElement:()=>new Element(),createTextNode:t=>({textContent:t}),querySelector:()=>$('tableWrap')},window:{addEventListener:(k,cb)=>{windowEvents[k]=cb;}},location:{href:'http://localhost/TRANSPORT_MANAGEMENT/frontend/transport/vehicles.html',replace:u=>redirects.push(u)},sessionStorage:{removeItem:()=>{}},URL,URLSearchParams,console,
        fetch:async(url,options)=>{
            assert.equal(options.credentials,'same-origin');assert.equal(options.cache,'no-store');
            const route=url.searchParams.get('route');requests.push({route,options,params:url.searchParams});
            if(expired) return {status:401,ok:false,json:async()=>({success:false,message:'Phiên hết hạn'})};
            if(denied) return {status:403,ok:false,json:async()=>({success:false,message:'Không có quyền'})};
            if(options.method!=='GET') {
                writes.push({route,method:options.method,body:options.body?JSON.parse(options.body):null,id:url.searchParams.get('id')});
                if(writeError) return {status:409,ok:false,json:async()=>({success:false,message:'Xung đột dữ liệu',errors:writeError})};
                return {status:200,ok:true,json:async()=>({success:true,data:fixture})};
            }
            let data;
            if(route==='/api/me') data={role,status:1,full_name:'Nhân viên thử nghiệm'};
            else if(route==='/api/vehicles/stats')data={total:10,available:5,on_trip:2,maintenance:2,inactive:1};
            else if(route==='/api/vehicles/show')data=fixture;
            else if(listMode==='error')return {status:500,ok:false,json:async()=>({success:false,message:'Không tải được phương tiện'})};
            else data=listMode==='empty'?[]:[fixture];
            return {status:200,ok:true,json:async()=>({success:true,data})};
        }};
    const settle=async()=>{for(let i=0;i<12;i++)await new Promise(resolve=>setImmediate(resolve));};
    vm.runInNewContext(source,sandbox);await settle();
    assert.equal($('addButton').disabled,false);assert.equal($('currentUser').textContent,'Nhân viên thử nghiệm');
    assert.equal($('metric-total').textContent,10);assert.equal($('metric-maintenance').textContent,2);
    assert.equal($('vehicleRows').children[0].children[1].textContent,fixture.vehicle_type); // safe text, not HTML
    $('search').value='Xe tải';$('statusFilter').value='maintenance';$('filterForm').events.submit({preventDefault(){}});await settle();
    assert.equal(requests.at(-1).params.get('status'),'maintenance');assert.equal(requests.at(-1).params.get('search'),'Xe tải');assert.equal($('metric-total').textContent,10);
    listMode='empty';await $('refreshButton').events.click();assert.match($('vehicleRows').children[0].children[0].textContent,/Không có phương tiện/);
    listMode='error';await $('refreshButton').events.click();assert.equal($('listRetry').hidden,false);assert.match($('resultCount').textContent,/Không tải/);
    listMode='normal';await $('refreshButton').events.click();
    $('addButton').events.click();assert.equal($('vehicleDialog').open,true);
    fields.license_plate.value=' 51c - 123.45\u00a0'.replace('\\u00a0','\u00a0');fields.vehicle_type.value='Xe tải';fields.capacity.value='0';
    await form.events.submit({preventDefault(){}});assert.equal(writes.length,0);assert.match($('error-capacity').textContent,/lớn hơn 0/);
    fields.capacity.value='1500.25';await form.events.submit({preventDefault(){}});
    assert.equal(writes.at(-1).method,'POST');assert.equal(writes.at(-1).body.license_plate,'51C-123.45');assert.equal(writes.at(-1).body.brand,null);assert.equal(writes.at(-1).body.capacity,'1500.25');assert.equal($('vehicleDialog').open,false);
    const button=action=>({dataset:{action,id:fixture.vehicle_id,plate:fixture.license_plate},disabled:false});
    const click=async action=>{const b=button(action);await $('vehicleRows').events.click({target:{closest:()=>b}});};
    await click('view');assert.equal($('vehicleFields').hidden,true);assert.equal($('saveButton').hidden,true);assert.equal($('vehicleDetails').children.length,7);
    $('cancelDialog').events.click();fixture.has_active_trip=true;fixture.status='on_trip';
    await click('edit');assert.equal(fields.status.disabled,true);fields.capacity.value='2000';await form.events.submit({preventDefault(){}});
    assert.equal(writes.at(-1).method,'PUT');assert.equal(writes.at(-1).id,'9007199254740993');assert.equal(writes.at(-1).body.status,'on_trip');
    await click('edit');writeError={license_plate:'Biển số đã được sử dụng.'};await form.events.submit({preventDefault(){}});assert.match($('error-license_plate').textContent,/đã được sử dụng/);assert.equal($('vehicleDialog').open,true);writeError=null;$('cancelDialog').events.click();
    const before=writes.length;await click('delete');assert.equal(writes.length,before);assert.equal($('deleteDialog').open,true);
    writeError={};await $('confirmDelete').events.click();assert.equal($('deleteDialog').open,true);assert.match($('deleteMessage').textContent,/Xung đột/);
    writeError=null;await $('confirmDelete').events.click();assert.equal(writes.at(-1).method,'DELETE');assert.equal(writes.at(-1).id,fixture.vehicle_id);assert.equal($('deleteDialog').open,false);
    windowEvents.pagehide();assert.equal($('addButton').disabled,true);windowEvents.pageshow({persisted:true});await settle();assert.equal($('addButton').disabled,false);
    denied=true;await $('refreshButton').events.click();assert.equal($('addButton').disabled,true);assert.equal($('vehicleDialog').open,false);
    denied=false;role='CUSTOMER';windowEvents.pageshow({persisted:true});await settle();assert.equal($('addButton').disabled,true);assert.match($('pageMessage').textContent,/ADMIN\/STAFF/);
    role='STAFF';expired=true;windowEvents.pageshow({persisted:true});await settle();assert.equal(redirects.at(-1),'../customer/login.html');
    expired=false;windowEvents.pageshow({persisted:true});await settle();await $('logoutButton').events.click();assert.equal(writes.at(-1).route,'/api/logout');assert.equal($('addButton').disabled,true);
    console.log('PASS: Vehicle frontend mock: session/roles, API stats independent of filter, loading/empty/error, add/view/edit/delete confirmation, validation/409 fields, BIGINT, XSS text, status lock, bfcache and logout. No database writes.');
}
run().catch(error=>{console.error(error);process.exitCode=1;});
