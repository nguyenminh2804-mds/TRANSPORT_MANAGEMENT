(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const endpoint = new URL('../../backend/public/index.php', location.href);
    const labels = {available:'Sẵn sàng', on_trip:'Đang thực hiện chuyến', maintenance:'Bảo trì', inactive:'Ngừng sử dụng'};
    const fields = {license_plate:'Biển số', vehicle_type:'Loại xe', brand:'Hãng xe', capacity:'Tải trọng (kg)', status:'Trạng thái'};
    const form = $('vehicleForm');
    let ready = false, saving = false, selected = null, pendingDelete = null, listRequest = 0, statsRequest = 0, authRequest = 0, detailRequest = 0;
    const normalizePlate = value => value.replace(/[\s\p{Z}]+/gu, '').toUpperCase();
    const input = name => form.elements.namedItem(name);
    function message(id, text = '', error = false) {
        $(id).textContent = text;
        $(id).className = text ? `drivers-message${error ? ' error' : ''}` : '';
    }
    function tableMessage(text, loading = false) {
        const tr = document.createElement('tr'); const td = document.createElement('td');
        td.colSpan = 6; td.textContent = text; td.className = 'drivers-empty' + (loading ? ' loading' : '');
        tr.append(td); $('vehicleRows').replaceChildren(tr);
    }
    function lock() {
        ready = false; listRequest++; statsRequest++; detailRequest++;
        for (const id of ['addButton','refreshButton','searchButton','resetFilter','saveButton','confirmDelete']) $(id).disabled = true;
        $('vehicleDialog').close(); $('deleteDialog').close();
        for (const key of ['total',...Object.keys(labels)]) $('metric-' + key).textContent = '—';
        tableMessage('Vui lòng xác thực phiên đăng nhập để xem phương tiện.');
        document.querySelector('.drivers-table-wrap').setAttribute('aria-busy','false');
    }
    async function api(route, method = 'GET', body, params = {}) {
        const url = new URL(endpoint); url.search = new URLSearchParams({route,...params});
        let response;
        try { response = await fetch(url, {method, credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json', ...(body ? {'Content-Type':'application/json'} : {})}, ...(body ? {body:JSON.stringify(body)} : {})}); }
        catch { throw new Error('Không thể kết nối máy chủ. Vui lòng thử lại.'); }
        if (response.status === 401 || response.status === 403) {
            lock();
            if (response.status === 401) location.replace('../customer/login.html');
        }
        let result;
        try { result = await response.json(); } catch { throw new Error('Không tải được dữ liệu. Vui lòng thử lại hoặc liên hệ quản trị viên.'); }
        if (!response.ok || !result?.success) throw Object.assign(new Error(result?.message || 'Yêu cầu thất bại.'), {status:response.status, fields:result?.errors || {}});
        return result.data;
    }
    function cell(row, value, label) {
        const td = document.createElement('td'); td.dataset.label = label; td.textContent = value === null || value === undefined || value === '' ? '—' : String(value); row.append(td); return td;
    }
    function render(vehicles) {
        $('vehicleRows').replaceChildren();
        for (const vehicle of vehicles) {
            const tr = document.createElement('tr');
            for (const key of ['license_plate','vehicle_type','brand','capacity']) cell(tr, vehicle[key], fields[key]);
            const badge = document.createElement('span'); badge.className = 'drivers-badge ' + (Object.hasOwn(labels, vehicle.status) ? vehicle.status : ''); badge.textContent = labels[vehicle.status] || 'Không xác định';
            cell(tr, '', fields.status).replaceChildren(badge);
            const actions = document.createElement('div'); actions.className = 'drivers-row-actions'; cell(tr, '', 'Thao tác').replaceChildren(actions);
            for (const [action,label,icon] of [['view','Xem','eye'],['edit','Sửa','pen-to-square'],['delete','Xóa','trash-can']]) {
                const button = document.createElement('button'); button.type = 'button'; button.dataset.action = action; button.dataset.id = String(vehicle.vehicle_id); button.dataset.plate = vehicle.license_plate;
                const i = document.createElement('i'); i.className = 'fa-solid fa-' + icon; i.setAttribute('aria-hidden','true'); button.append(i,document.createTextNode(label));
                button.setAttribute('aria-label', label + ': ' + vehicle.license_plate); if (action === 'delete') button.className = 'drivers-danger'; actions.append(button);
            }
            $('vehicleRows').append(tr);
        }
        if (!vehicles.length) tableMessage('Không có phương tiện phù hợp. Hãy thay đổi bộ lọc hoặc thêm phương tiện.');
    }
    async function load() {
        if (!ready) return;
        const seq = ++listRequest; tableMessage('Đang tải danh sách…', true); $('resultCount').textContent = 'Đang tải danh sách…'; $('listRetry').hidden = true;
        document.querySelector('.drivers-table-wrap').setAttribute('aria-busy','true');
        try {
            const data = await api('/api/vehicles','GET',null,{search:$('search').value.trim(), status:$('statusFilter').value});
            if (seq !== listRequest || !ready) return;
            if (!Array.isArray(data)) throw new Error('Danh sách không hợp lệ. Vui lòng thử lại.');
            render(data); $('resultCount').textContent = `Có ${data.length} phương tiện phù hợp.`;
        } catch (error) {
            if (seq !== listRequest) return;
            tableMessage(error.message); $('resultCount').textContent = 'Không tải được danh sách.'; $('listRetry').hidden = false;
        } finally { if (seq === listRequest) document.querySelector('.drivers-table-wrap').setAttribute('aria-busy','false'); }
    }
    async function stats() {
        if (!ready) return;
        const seq = ++statsRequest;
        for (const key of ['total',...Object.keys(labels)]) $('metric-' + key).textContent = '—';
        $('statsMessage').textContent = 'Đang tải thống kê…';
        try {
            const data = await api('/api/vehicles/stats');
            if (seq !== statsRequest || !ready) return;
            for (const key of ['total',...Object.keys(labels)]) {
                if (!Number.isSafeInteger(data?.[key]) || data[key] < 0) throw new Error('Thống kê không hợp lệ.');
                $('metric-' + key).textContent = data[key];
            }
            $('statsMessage').textContent = 'Thống kê toàn bộ phương tiện, không phụ thuộc bộ lọc danh sách.';
        } catch (error) { if (seq === statsRequest) $('statsMessage').textContent = 'Không tải được thống kê: ' + error.message; }
    }
    async function refresh() {
        if (!ready) return;
        $('refreshButton').disabled = true;
        try { await Promise.all([load(),stats()]); } finally { $('refreshButton').disabled = !ready; }
    }
    function clearErrors() {
        for (const name of Object.keys(fields)) { $('error-' + name).textContent = ''; input(name).setAttribute('aria-invalid','false'); }
        message('formMessage');
    }
    function fieldError(name, text) {
        if (!Object.hasOwn(fields,name)) return;
        $('error-' + name).textContent = text; input(name).setAttribute('aria-invalid','true');
    }
    function validate() {
        clearErrors(); const data = {};
        for (const name of Object.keys(fields)) data[name] = input(name).value.trim();
        data.license_plate = normalizePlate(data.license_plate); input('license_plate').value = data.license_plate;
        const errors = {};
        if (!/^[A-Z0-9.-]{1,30}$/.test(data.license_plate)) errors.license_plate = 'Biển số bắt buộc, tối đa 30 ký tự: chữ, số, chấm hoặc gạch ngang.';
        if (!data.vehicle_type || [...data.vehicle_type].length > 100 || /[\x00-\x1F\x7F]/u.test(data.vehicle_type)) errors.vehicle_type = 'Loại xe bắt buộc, tối đa 100 ký tự, không chứa ký tự điều khiển.';
        if ([...data.brand].length > 100 || /[\x00-\x1F\x7F]/u.test(data.brand)) errors.brand = 'Hãng xe tối đa 100 ký tự, không chứa ký tự điều khiển.';
        if (!/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/.test(data.capacity) || Number(data.capacity) <= 0) errors.capacity = 'Tải trọng phải lớn hơn 0, tối đa 99999999.99 kg, tối đa 2 chữ số thập phân.';
        if (!Object.hasOwn(labels,data.status)) errors.status = 'Vui lòng chọn trạng thái hợp lệ.';
        for (const [name,text] of Object.entries(errors)) fieldError(name,text);
        if (Object.keys(errors).length) { input(Object.keys(errors)[0]).focus(); return null; }
        data.brand ||= null; return data;
    }
    function openForm(vehicle = null, view = false) {
        if (!ready || saving) return;
        selected = vehicle ? String(vehicle.vehicle_id) : null; form.reset(); clearErrors();
        $('dialogTitle').textContent = view ? 'Chi tiết phương tiện' : selected ? 'Sửa phương tiện' : 'Thêm phương tiện';
        $('vehicleFields').hidden = view; $('vehicleDetails').hidden = !view; $('requiredNote').hidden = view; $('saveButton').hidden = view;
        $('vehicleDetails').replaceChildren();
        for (const name of Object.keys(fields)) { input(name).disabled = view; input(name).value = vehicle?.[name] ?? (name === 'status' ? 'available' : ''); }
        input('status').disabled = view || Boolean(vehicle?.has_active_assignment || vehicle?.has_active_trip);
        if (input('status').disabled && !view) $('error-status').textContent = 'Giữ nguyên trạng thái khi còn chuyến/phân công hoạt động.';
        if (view) for (const [name,label] of Object.entries({...fields, created_at:'Ngày tạo', updated_at:'Cập nhật gần nhất'})) {
            const div = document.createElement('div'), dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = label; dd.textContent = name === 'status' ? labels[vehicle[name]] || 'Không xác định' : vehicle[name] ?? '—'; div.append(dt,dd); $('vehicleDetails').append(div);
        }
        $('saveButton').disabled = false; $('vehicleDialog').showModal();
    }
    $('addButton').addEventListener('click',() => { detailRequest++; openForm(); });
    for (const id of ['closeDialog','cancelDialog']) $(id).addEventListener('click',() => { if (!saving) { detailRequest++; $('vehicleDialog').close(); } });
    for (const id of ['closeDelete','cancelDelete']) $(id).addEventListener('click',() => { if (!saving) $('deleteDialog').close(); });
    for (const id of ['vehicleDialog','deleteDialog']) $(id).addEventListener('cancel',event => { if (saving) event.preventDefault(); });
    $('vehicleRows').addEventListener('click',async event => {
        const button = event.target.closest('button[data-action]'); if (!button || !ready || saving) return;
        if (button.dataset.action === 'delete') { pendingDelete = button.dataset.id; $('deleteVehicle').textContent = 'Biển số: ' + button.dataset.plate; message('deleteMessage'); $('confirmDelete').disabled = false; $('deleteDialog').showModal(); return; }
        const seq = ++detailRequest; button.disabled = true;
        try {
            const vehicle = await api('/api/vehicles/show','GET',null,{id:button.dataset.id});
            if (seq === detailRequest && ready) openForm(vehicle,button.dataset.action === 'view');
        } catch (error) { message('pageMessage',error.message,true); } finally { button.disabled = !ready; }
    });
    form.addEventListener('submit',async event => {
        event.preventDefault(); if (!ready || saving || $('saveButton').hidden) return;
        const data = validate(); if (!data) return;
        saving = true; $('saveButton').disabled = true;
        try {
            await api('/api/vehicles',selected ? 'PUT' : 'POST',data,selected ? {id:selected} : {});
            $('vehicleDialog').close(); message('pageMessage',selected ? 'Cập nhật phương tiện thành công.' : 'Thêm phương tiện thành công.'); await refresh();
        } catch (error) {
            for (const [name,text] of Object.entries(error.fields || {})) fieldError(name,text);
            message('formMessage',error.message,true);
        } finally { saving = false; $('saveButton').disabled = !ready; }
    });
    $('confirmDelete').addEventListener('click',async () => {
        if (!ready || saving || !pendingDelete) return; saving = true; $('confirmDelete').disabled = true;
        try { await api('/api/vehicles','DELETE',null,{id:pendingDelete}); $('deleteDialog').close(); pendingDelete = null; message('pageMessage','Xóa phương tiện thành công.'); await refresh(); }
        catch (error) { message('deleteMessage',error.message,true); }
        finally { saving = false; $('confirmDelete').disabled = !ready; }
    });
    $('filterForm').addEventListener('submit',event => { event.preventDefault(); load(); });
    $('statusFilter').addEventListener('change',load);
    $('resetFilter').addEventListener('click',() => { $('search').value = ''; $('statusFilter').value = ''; load(); });
    $('refreshButton').addEventListener('click',async () => { if (ready) await refresh(); else await boot(); });
    $('listRetry').addEventListener('click',load);
    $('logoutButton').addEventListener('click',async () => {
        if (saving) return;
        $('logoutButton').disabled = true;
        try { await api('/api/logout','POST'); authRequest++; lock(); sessionStorage.removeItem('user'); location.replace('../customer/login.html'); }
        catch (error) { message('pageMessage',error.message,true); }
        finally { $('logoutButton').disabled = false; }
    });
    async function boot() {
        const seq = ++authRequest; lock(); message('pageMessage');
        try {
            const user = await api('/api/me');
            if (seq !== authRequest) return;
            if (!['ADMIN','STAFF'].includes(user?.role) || Number(user.status) !== 1) throw new Error('Chỉ ADMIN/STAFF đang hoạt động được quản lý phương tiện.');
            ready = true; $('currentUser').textContent = user.full_name || user.username;
            for (const id of ['addButton','refreshButton','searchButton','resetFilter']) $(id).disabled = false;
            await refresh();
        } catch (error) { if (seq === authRequest) { message('pageMessage',error.message,true); $('refreshButton').disabled = error.status === 401 || error.status === 403; $('resultCount').textContent = 'Chưa xác thực quyền quản lý phương tiện.'; } }
    }
    window.addEventListener('pagehide',() => { authRequest++; lock(); });
    window.addEventListener('pageshow',event => { if (event.persisted) boot(); });
    boot();
})();
