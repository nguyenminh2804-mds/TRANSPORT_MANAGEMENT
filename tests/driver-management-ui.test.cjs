// DOM/fetch only. Excludes the original PHP/MySQL/API probes.
const path=require('node:path'),assert=require('node:assert/strict'),fs=require('node:fs');
const root=path.resolve(__dirname,'..');
async function frontendChecks() {
    const fs = require('node:fs');
    const vm = require('node:vm');
    class Element {
        constructor() { this.value = ''; this.disabled = false; this.children = []; this.dataset = {}; this.events = {}; this.attributes = {}; this.validity = { valid:true }; this.required = false; this.maxLength = -1; }
        setAttribute(name, value) { this.attributes[name] = value; }
        focus() { this.focused = true; }
        addEventListener(type, callback) { this.events[type] = callback; }
        append(...items) { this.children.push(...items); }
        replaceChildren(...items) { this.children = items; }
        showModal() { this.open = true; }
        close() { this.open = false; }
    }
    const elements = new Map();
    const get = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
    const fields = Object.fromEntries(['driver_code','full_name','phone','address','license_number','license_class','license_expiry','status','user_id','username','password'].map(key => [key, new Element()]));
    const form = get('driverForm');
    form.elements = { namedItem: key => fields[key] };
    form.reset = () => { for (const field of Object.values(fields)) field.value = ''; fields.status.value = 'available'; };
    const fixture = { driver_id:'9007199254740993', user_id:'9007199254740995', driver_code:'TX-BIG', full_name:'<b>Tài xế</b>', phone:'0901234567', address:null, license_number:'12345', license_class:'C', license_expiry:null, status:'on_trip', has_active_assignment:true, has_active_trip:false };
    for (const name of ['driver_code','full_name','phone','license_number','license_class']) fields[name].required = true;
    let saved;
    let listMode = 'normal';
    let writeError = false;
    let accountError = false;
    const requests = [];
    const roster = [fixture, {...fixture,driver_id:'2',driver_code:'TX-AVAILABLE',status:'available',has_active_assignment:false,has_active_trip:false}, {...fixture,driver_id:'3',driver_code:'TX-INACTIVE',status:'inactive'}];
    const writes = [];
    const windowEvents = {};
    const redirects = [];
    let cacheCleared = false;
    const sandbox = {
        document: { getElementById:get, createElement:() => new Element(), createTextNode:text => ({textContent:text}), querySelector:() => get('tableWrap') },
        sessionStorage:{removeItem:()=>{cacheCleared=true;}},
        window: { addEventListener:(name, callback) => { windowEvents[name] = callback; } },
        URL, URLSearchParams, TextEncoder, location:{href:'http://localhost/TRANSPORT_MANAGEMENT/frontend/transport/drivers.html', replace:url => redirects.push(url)},
        FormData:class { constructor() { } *[Symbol.iterator]() { for (const [key, field] of Object.entries(fields)) if (!field.disabled) yield [key, field.value]; } },
        fetch:async (url, options) => {
            const route = url.searchParams.get('route');
            requests.push({url,options});
            if (accountError && options.method === 'POST') return {ok:false,status:409,json:async()=>({success:false,message:'Tên đăng nhập đã tồn tại.'})};
            if (writeError && options.method === 'DELETE') return {ok:false,status:409,json:async()=>({success:false,message:'Không thể xóa do lịch sử chuyến.'})};
            if (route === '/api/drivers' && options.method === 'GET' && url.searchParams.has('search')) {
                if (listMode === 'error') return {ok:false, status:500, json:async () => ({success:false, message:'Lỗi API thử nghiệm'})};
                if (listMode === 'empty') return {ok:true, status:200, json:async () => ({success:true, data:[]})};
            }
            let data;
            if (route === '/api/me') data = { full_name:'Nhân viên unit test', role:'STAFF', status:1 };
            else if (['PUT','POST','DELETE'].includes(options.method)) { saved = options.body ? JSON.parse(options.body) : null; writes.push({method:options.method, route, id:url.searchParams.get('id')}); data = fixture; }
            else if (route === '/api/drivers/show') data = fixture;
            else data = roster;
            return { ok:true, json:async () => ({ success:true, data }) };
        }
    };
    vm.runInNewContext(fs.readFileSync(path.join(root, 'frontend/js/drivers.js'), 'utf8'), sandbox);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(get('addButton').disabled, false);
    assert.equal(get('driverRows').children.length, 3);
    const row = get('driverRows').children[0];
    assert.equal(row.children[1].textContent, fixture.full_name); // textContent, không innerHTML
    const editButton = row.children.at(-1).children[0].children.find(button => button.dataset.action === 'edit');
    assert.equal(editButton.dataset.id, fixture.driver_id);
    await get('driverRows').events.click({ target:{ closest:() => editButton } });
    assert.equal(fields.status.disabled, true);
    assert.equal(fields.license_expiry.value, '');
    await form.events.submit({ preventDefault(){} });
    assert.equal(Object.hasOwn(saved,'username'),false);
    assert.equal(Object.hasOwn(saved,'password'),false);
    assert.equal(saved.status, undefined); // disabled select vẫn giữ đúng trạng thái
    assert.equal(saved.user_id, fixture.user_id); // Không Number() làm mất BIGINT
    assert.equal(saved.address, null);
    assert.equal(saved.license_expiry, null);
    get('addButton').events.click();
    assert.equal(fields.status.disabled, true);
    assert.equal(fields.status.value, 'available');
    fixture.has_active_assignment = false;
    fixture.has_active_trip = true;
    await get('driverRows').events.click({ target:{ closest:() => editButton } });
    assert.equal(fields.status.disabled, true);
    assert.equal(get('totalDrivers').textContent, 3);
    assert.equal(get('availableDrivers').textContent, 1);
    assert.equal(get('busyDrivers').textContent, 1);
    assert.equal(get('inactiveDrivers').textContent, 1);
    assert.equal(get('tableWrap').attributes['aria-busy'], 'false');
    fields.phone.value = 'abc';
    const beforeInvalid = writes.length;
    await form.events.submit({preventDefault(){}});
    assert.equal(writes.length, beforeInvalid);
    assert.match(get('error-phone').textContent, /9–15/);
    assert.equal(fields.phone.attributes['aria-invalid'], 'true');
    fields.phone.value = '0901234567';
    const clickAction = async action => {
        const button = get('driverRows').children[0].children.at(-1).children[0].children.find(button => button.dataset.action === action);
        await get('driverRows').events.click({target:{closest:() => button}});
    };
    await clickAction('view');
    assert.equal(get('saveButton').hidden, true);
    assert.equal(fields.full_name.disabled, true);
    assert.equal(get('driverFields').hidden, true);
    assert.equal(get('driverDetails').hidden, false);
    assert.equal(get('driverDetails').children[1].children[1].textContent, fixture.full_name);
    await form.events.submit({preventDefault(){}});
    assert.equal(writes.length, beforeInvalid);
    await clickAction('delete');
    assert.equal(writes.length, beforeInvalid); // Hủy xác nhận không gọi DELETE.
    assert.equal(get('deleteDialog').open, true);
    get('cancelDelete').events.click();
    assert.equal(get('deleteDialog').open, false);
    await clickAction('delete');
    writeError = true;
    await get('confirmDelete').events.click();
    assert.equal(get('deleteDialog').open, true);
    assert.match(get('deleteMessage').textContent, /lịch sử chuyến/);
    assert.equal(get('confirmDelete').disabled, false);
    assert.equal(writes.length, beforeInvalid);
    writeError = false;
    await get('confirmDelete').events.click();
    assert.equal(get('deleteDialog').open, false);
    assert.equal(get('driverRows').children.length, 3);
    assert.equal(writes.at(-1).method, 'DELETE');
    assert.equal(writes.at(-1).id, fixture.driver_id);
    get('addButton').events.click();
    for (const [name, value] of Object.entries(fixture)) if (fields[name]) fields[name].value = value ?? '';
    fields.status.value = 'available';
    assert.equal(fields.user_id.disabled,true);
    assert.equal(get('accountFields').hidden,false);
    fields.username.value='driver-new';
    const createWrites = writes.length;
    fields.password.value='Đ'.repeat(37);
    await form.events.submit({preventDefault(){}});
    assert.equal(writes.length,createWrites);
    assert.match(get('error-password').textContent,/72 byte/);
    fields.password.value='  initial-test  ';
    accountError = true;
    await form.events.submit({preventDefault(){}});
    assert.equal(writes.length,createWrites);
    assert.match(get('error-username').textContent,/đã tồn tại/);
    assert.equal(fields.password.value,'  initial-test  ');
    accountError = false;
    await form.events.submit({preventDefault(){}});
    assert.equal(writes.at(-1).method, 'POST');
    assert.equal(saved.status, 'available');
    assert.equal(saved.username,'driver-new');
    assert.equal(saved.password,'  initial-test  ');
    assert.equal(Object.hasOwn(saved,'user_id'),false);
    assert.equal(fields.password.value,'');
    fixture.has_active_trip = false;
    get('search').value = 'TX-BIG';
    await get('filterForm').events.submit({preventDefault(){}});
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests.at(-1).url.searchParams.get('search'), 'TX-BIG');
    assert.equal(requests.at(-1).options.credentials, 'same-origin');
    listMode = 'empty';
    get('statusFilter').events.change();
    await new Promise(resolve => setImmediate(resolve));
    assert.match(get('driverRows').children[0].children[0].textContent, /Không có tài xế/);
    assert.equal(get('totalDrivers').textContent, 3); // Lọc không thay đổi tổng đội ngũ.
    listMode = 'error';
    await get('refreshButton').events.click();
    assert.match(get('driverRows').children[0].children[0].textContent, /Không tải được/);
    assert.match(get('pageMessage').textContent, /Lỗi API/);
    assert.equal(get('totalDrivers').textContent, 3);
    listMode = 'normal';
    for (const [state,label] of [['pending','Chờ tiếp nhận phân công'],['accepted','Đã tiếp nhận – Chưa bắt đầu'],['on_trip','Đang thực hiện chuyến'],['available','Sẵn sàng']]) {
        fixture.display_status=state;fixture.status=state==='on_trip'?'on_trip':'available';fixture.has_active_assignment=state!=='available';
        await get('refreshButton').events.click();
        assert.equal(get('driverRows').children[0].children[6].children[0].children[1].textContent,label);
        const stat={pending:'pendingDrivers',accepted:'acceptedDrivers',on_trip:'busyDrivers'}[state];
        if(stat)assert.equal(get(stat).textContent,1);
        if(['pending','accepted'].includes(state)){await clickAction('edit');assert.equal(fields.status.disabled,true);assert.equal(fields.status.value,'__effective');assert.equal(fields.status.children.at(-1).textContent,label);await form.events.submit({preventDefault(){}});assert.equal(saved.status,undefined);}
        assert.equal(get('availableDrivers').textContent,state==='available'?2:1);
    }
    fixture.display_status='available';fixture.has_active_assignment=false;await get('refreshButton').events.click();assert.equal(get('pendingDrivers').textContent,0);
    fixture.status='available';fixture.display_status='available';fixture.has_active_assignment=false;fixture.has_active_trip=false;await clickAction('edit');assert.equal(fields.status.disabled,false);fields.status.value='inactive';get('statusReason').value='';const beforeCorrection=writes.length;await form.events.submit({preventDefault(){}});assert.equal(writes.length,beforeCorrection);get('statusReason').value='Báo lỗi hệ thống';await form.events.submit({preventDefault(){}});assert.equal(saved.status,'inactive');assert.equal(saved.status_reason,'Báo lỗi hệ thống');assert.equal(saved.expected_status,'available');
    await windowEvents.pageshow({persisted:true});
    assert.equal(get('addButton').disabled, false);
    assert.equal(get('driverRows').children.length, 3);
    assert.equal(redirects.length, 0);
    // All transport pages share one navigation; only aria-current differs.
    let canonicalNavigation;
    for (const page of ['dashboard', 'drivers', 'vehicles', 'assignment']) {
        const html = fs.readFileSync(path.join(root, 'frontend/transport', page + '.html'), 'utf8');
        const navigation = html.match(/<nav class="transport-nav"[\s\S]*?<\/nav>/)[0];
        assert.equal((navigation.match(/aria-current="page"/g) || []).length, 1);
        assert.ok(navigation.includes('href="' + page + '.html" aria-current="page"'));
        const canonical = navigation.replace(' aria-current="page"', '');
        if (canonicalNavigation) assert.equal(canonical, canonicalNavigation);
        canonicalNavigation = canonical;
        assert.ok(html.includes('../css/transport-navigation.css?v='));
    }
    // Re-execute as a fresh load: /api/me must be read again before CRUD is enabled.
    const meBefore = requests.filter(request=>request.url.searchParams.get('route')==='/api/me').length;
    vm.runInNewContext(fs.readFileSync(path.join(root, 'frontend/js/drivers.js'), 'utf8'), sandbox);
    assert.equal(get('addButton').disabled, true);
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(requests.filter(request=>request.url.searchParams.get('route')==='/api/me').length,meBefore+1);
    assert.equal(get('addButton').disabled,false);
    assert.equal(redirects.length,0);
    assert.equal(sandbox.location.href,'http://localhost/TRANSPORT_MANAGEMENT/frontend/transport/drivers.html');
    await get('logoutButton').events.click();
    assert.equal(requests.at(-1).url.searchParams.get('route'),'/api/logout');
    assert.equal(requests.at(-1).options.method,'POST');
    assert.equal(sandbox.location.href,'../customer/login.html');
    assert.equal(cacheCleared,true);
    console.log('PASS: frontend mock: CRUD, xác nhận xóa, validation cạnh trường, thống kê độc lập bộ lọc, rỗng/lỗi, bfcache, khóa trạng thái, nullable, BIGINT. Không kiểm chứng trình duyệt hoặc ghi MySQL.');
}
frontendChecks().catch(error => { console.error(error); process.exitCode = 1; });

