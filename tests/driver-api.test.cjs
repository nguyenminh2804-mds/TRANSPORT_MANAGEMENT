// Chạy node tests/driver-api.test.cjs: không tạo database, không ghi dữ liệu.
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const php = 'C:/xampp/php/php.exe';
const root = path.resolve(__dirname, '..');
console.log(execFileSync(php, [path.join(__dirname, 'driver-readonly.test.php')], { encoding: 'utf8' }).trim());
console.log(execFileSync(php, [path.join(__dirname, 'driver-account.test.php')], { encoding: 'utf8' }).trim());
function probe(code) {
    const output = execFileSync(php, ['-r', "register_shutdown_function(function(){echo '\\nHTTP_STATUS=' . http_response_code();});" + code], { cwd: root, encoding: 'utf8' });
    const [body, status] = output.split('\\nHTTP_STATUS=');
    return { body: JSON.parse(body), status: Number(status) };
}
let probes = 0;
for (const action of ['index','show','store','update','status','destroy']) {
    const result = probe(`require 'backend/app/controllers/DriverController.php'; $_SESSION=[]; (new DriverController())->handle('${action}');`);
    assert.equal(result.status, 401);
    assert.equal(result.body.success, false);
    probes++;
}
const accounts = JSON.parse(execFileSync(php, ['-r', "require 'backend/app/models/User.php'; echo json_encode((new User())->getAll());"], { cwd:root, encoding:'utf8' }));
const denied = accounts.find(user => !['ADMIN','STAFF'].includes(user.role) || Number(user.status)!==1);
if (denied) {
    for (const action of ['index','show','store','update','status','destroy']) {
        // Phiên giả trong process test không làm role ADMIN giả vượt tài khoản thật.
        const result = probe(`require 'backend/app/controllers/DriverController.php'; $_SESSION=['user_id'=>${Number(denied.id)},'role'=>'ADMIN']; (new DriverController())->handle('${action}');`);
        assert.equal(result.status, 403);
        assert.equal(result.body.success, false);
        probes++;
    }
    const me = probe(`require 'backend/app/controllers/AuthController.php'; $_SESSION=['user_id'=>${Number(denied.id)}]; (new AuthController())->me();`);
    assert.equal(me.status, 200);
    assert.equal(Object.hasOwn(me.body.data, 'password'), false);
    probes++;
}
const missing = probe("require 'backend/app/controllers/DriverController.php'; $_SESSION=['user_id'=>2147483647,'role'=>'ADMIN']; (new DriverController())->handle('index');");
assert.equal(missing.status, 403);
probes++;
const manager = accounts.find(user => ['ADMIN','STAFF'].includes(user.role) && Number(user.status)===1);
if (manager) {
    const allowed = probe(`require 'backend/app/controllers/DriverController.php'; $_SESSION=['user_id'=>${Number(manager.id)}]; (new DriverController())->handle('index');`);
    assert.equal(allowed.status, 200);
    assert.equal(Array.isArray(allowed.body.data), true);
    probes++;
} else {
    console.log('BLOCKED: chưa có ADMIN/STAFF hoạt động; không tự tạo hoặc đổi quyền tài khoản. CRUD qua HTTP chưa kiểm thử thành công.');
}
console.log(`PASS: ${probes} controller probes theo users thực tế, không ghi database.`);
const fs = require('node:fs');
// Unit test frontend: DOM/fetch trong bộ nhớ, không gửi HTTP hoặc ghi database.
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
    const roster = [fixture, {...fixture,driver_id:'2',driver_code:'TX-AVAILABLE',status:'available'}, {...fixture,driver_id:'3',driver_code:'TX-INACTIVE',status:'inactive'}];
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
    assert.equal(saved.status, 'on_trip'); // disabled select vẫn giữ đúng trạng thái
    assert.equal(saved.user_id, fixture.user_id); // Không Number() làm mất BIGINT
    assert.equal(saved.address, null);
    assert.equal(saved.license_expiry, null);
    get('addButton').events.click();
    assert.equal(fields.status.disabled, false);
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
    await clickAction('status');
    assert.equal(get('statusDialog').open, true);
    get('newStatus').value = 'inactive';
    await get('statusForm').events.submit({preventDefault(){},submitter:get('statusSaveButton')});
    assert.equal(writes.at(-1).route, '/api/drivers/status');
    assert.equal(saved.status, 'inactive');
    assert.equal(get('statusDialog').open, false);
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
    await windowEvents.pageshow({persisted:true});
    assert.equal(get('addButton').disabled, false);
    assert.equal(get('driverRows').children.length, 3);
    assert.equal(redirects.length, 0);
    assert.equal(get('staffOverview').hidden, false);
    assert.equal(get('staffOverviewSeparator').hidden, false);
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

async function httpChecks() {
    if (!process.argv.includes('--http')) return;
    const base = (process.env.TRANSPORT_TEST_BASE_URL || 'http://localhost/TRANSPORT_MANAGEMENT').replace(/\/$/, '') + '/backend/public/index.php';
    let count = 0;
    async function request(route, method, data, expected) {
        const url = new URL(base);
        url.search = new URLSearchParams({ route, id:'1' });
        const response = await fetch(url, { method, headers:{'Content-Type':'application/json'}, signal:AbortSignal.timeout(10000), ...(data===undefined ? {} : {body:JSON.stringify(data)}) });
        assert.equal(response.status, expected);
        assert.match(response.headers.get('content-type'), /application\/json/);
        const body = await response.json();
        assert.equal(body.success, false);
        count++;
    }
    // Không gửi cookie. Mọi thao tác Driver phải bị chặn trước khi ghi dữ liệu.
    for (const [route,method] of [['/api/drivers','GET'],['/api/drivers/show','GET'],['/api/drivers','POST'],['/api/drivers','PUT'],['/api/drivers','DELETE'],['/api/drivers/status','PUT']]) {
        await request(route,method,['POST','PUT'].includes(method) ? {} : undefined,401);
    }
    await request('/api/me','GET',undefined,401);
    await request('/api/login','POST',{username:'',password:''},400);
    await request('/api/login','POST',{username:'__driver_probe_' + Date.now(),password:'not-a-real-account-password'},401);
    console.log(`PASS: ${count} HTTP requests qua Apache; JSON/401/400, không cookie hoặc dữ liệu thử.`);
}
httpChecks().catch(error => { console.error(error); process.exitCode = 1; });
