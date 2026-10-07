const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict'), path = require('node:path');
const root = path.resolve(__dirname,'..');
const source = fs.readFileSync(path.join(root,'frontend/js/transport-dashboard.js'),'utf8');
async function checks() {
    const elements = new Map(), events = {}, requests = [], redirects = [];
    const get = id => { if (!elements.has(id)) elements.set(id,{hidden:false,disabled:false,textContent:'',addEventListener:(k,fn)=>{events[id+':'+k]=fn;}});return elements.get(id); };
    let role='STAFF', status=1, network=false, httpStatus=200;
    const sandbox = {document:{getElementById:get},window:{addEventListener:(k,fn)=>events[k]=fn},location:{href:'http://localhost/TRANSPORT_MANAGEMENT/frontend/transport/dashboard.html',replace:u=>redirects.push(u)},URL,URLSearchParams,sessionStorage:{removeItem(){throw new Error('Storage disabled');}},fetch:async (url,options)=>{
        requests.push({route:url.searchParams.get('route'),options});assert.equal(url.origin,'http://localhost');assert.equal(options.credentials,'same-origin');assert.equal(options.cache,'no-store');
        if(network)throw new TypeError('network');return {status:httpStatus,ok:httpStatus===200,json:async()=>({success:httpStatus===200,data:{role,status,full_name:'Nhân viên thử nghiệm'},message:'Không có quyền'})};
    }};
    const settle=async()=>{for(let i=0;i<8;i++)await new Promise(resolve=>setImmediate(resolve));};
    vm.runInNewContext(source,sandbox);assert.equal(get('staffContent').hidden,true);await settle();assert.equal(get('staffContent').hidden,false);assert.equal(get('currentUser').textContent,'Nhân viên thử nghiệm');
    events.pagehide();assert.equal(get('staffContent').hidden,true);const before=requests.length;events.pageshow({persisted:true});await settle();assert.equal(requests.length,before+1);assert.equal(get('staffContent').hidden,false);
    role='CUSTOMER';events.pageshow({persisted:true});await settle();assert.equal(get('staffContent').hidden,true);assert.match(get('pageMessage').textContent,/STAFF\/ADMIN/);
    role='STAFF';status=0;await events['retrySession:click']();assert.equal(get('staffContent').hidden,true);
    status=1;network=true;await events['retrySession:click']();assert.equal(get('retrySession').hidden,false);assert.match(get('pageMessage').textContent,/kết nối/);
    network=false;httpStatus=401;await events['retrySession:click']();assert.equal(redirects.at(-1),'../customer/login.html');assert.equal(get('staffContent').hidden,true);
    httpStatus=403;await events['retrySession:click']();assert.equal(get('staffContent').hidden,true);
    httpStatus=200;role='ADMIN';await events['retrySession:click']();assert.equal(get('staffContent').hidden,false);
    await events['logoutButton:click']();assert.equal(requests.at(-1).route,'/api/logout');assert.equal(requests.at(-1).options.method,'POST');assert.equal(get('staffContent').hidden,true);assert.equal(redirects.at(-1),'../customer/login.html');
    // Login role mapping must stay identical; no PHP session simulated by storage.
    const auth=fs.readFileSync(path.join(root,'frontend/js/auth.js'),'utf8');
    for (const [role,target] of [['STAFF','../transport/dashboard.html'],['CUSTOMER','dashboard.html'],['ADMIN','../admin/dashboard.html'],['DRIVER','../driver/dashboard.html']]) {
        let submit;const location={href:'http://localhost/TRANSPORT_MANAGEMENT/frontend/customer/login.html'};
        vm.runInNewContext(auth,{window:{location},URL,document:{getElementById:id=>id==='loginForm'?{addEventListener:(k,fn)=>submit=fn}:id==='loginMessage'?{replaceChildren(){}}:{value:'test'},createElement:()=>({})},sessionStorage:{setItem(){}},fetch:async()=>({ok:true,status:200,json:async()=>({success:true,data:{role,status:1}})})});
        await submit({preventDefault(){}});assert.equal(location.href,target);
    }
    console.log('PASS: shared navigation and dashboard session mock: STAFF/ADMIN, denied/inactive/401/403/network, refresh/bfcache, logout; all existing login role targets preserved. No database writes.');
}
checks().catch(e=>{console.error(e);process.exitCode=1;});
