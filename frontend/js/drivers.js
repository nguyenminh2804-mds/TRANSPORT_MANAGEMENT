(() => {
    'use strict';
    const apiUrl = new URL('../../backend/public/index.php', location.href);
    const labels = { available: 'Đang rảnh', on_trip: 'Đang thực hiện chuyến', inactive: 'Ngừng làm việc' };
    const $ = id => document.getElementById(id);
    const form = $('driverForm');
    let selectedId = null;
    let selectedStatus = 'available';
    let statusId = null;
    let requestNumber = 0;
    let saving = false;
    let ready = false;
    let formReadonly = false;
    let pendingDelete = null;

    function message(element, text = '', error = false) {
        element.textContent = text;
        element.className = text ? `drivers-message${error ? ' error' : ''}` : '';
    }

    async function api(route, method = 'GET', data, params = {}) {
        const url = new URL(apiUrl);
        url.search = new URLSearchParams({ route, ...params });
        let response;
        try { response = await fetch(url, {
            method, credentials: 'same-origin', cache: 'no-store',
            headers: { Accept: 'application/json', ...(data ? { 'Content-Type': 'application/json' } : {}) },
            ...(data ? { body: JSON.stringify(data) } : {})
        }); } catch { throw new Error('Không thể kết nối API. Kiểm tra kết nối mạng và Apache, rồi nhấn Làm mới.'); }
        if (response.status === 401) {
            ready = false;
            $('addButton').disabled = true;
            location.replace('../customer/login.html');
            throw Object.assign(new Error('Phiên đăng nhập đã hết hạn.'), { status: 401 });
        }
        if (response.status === 403) {
            ready = false;
            $('addButton').disabled = true;
        }
        let result;
        try { result = await response.json(); }
        catch { throw new Error('Máy chủ không trả JSON hợp lệ. Kiểm tra Apache, MySQL và cấu hình database.'); }
        if (!result || typeof result !== 'object') throw new Error('Dữ liệu API không hợp lệ. Nhấn Làm mới để thử lại.');
        if (!response.ok || !result.success) throw Object.assign(new Error(result.message || 'Yêu cầu thất bại.'), { status: response.status });
        if (route === '/api/drivers' && method === 'GET' && !Array.isArray(result.data)) throw new Error('API danh sách tài xế trả dữ liệu không hợp lệ.');
        return result.data;
    }

    function cell(row, value, label = '') {
        const td = document.createElement('td');
        td.textContent = value === null || value === undefined || value === '' ? '—' : value;
        td.dataset.label = label;
        row.append(td);
        return td;
    }

    const fieldNames = ['driver_code', 'full_name', 'phone', 'address', 'license_number', 'license_class', 'license_expiry', 'status', 'user_id'];
    const accountFieldNames = ['username', 'password'];
    const fieldLabels = { driver_code:'Mã tài xế', full_name:'Họ tên', phone:'Số điện thoại', address:'Địa chỉ', license_number:'Số GPLX', license_class:'Hạng GPLX', license_expiry:'Ngày hết hạn GPLX', status:'Trạng thái', user_id:'Tài khoản liên kết' };
    const columnLabels = ['Mã tài xế', 'Họ tên', 'Điện thoại', 'Số GPLX', 'Hạng', 'Hết hạn GPLX'];
    function tableMessage(text, loading = false) {
        const row = document.createElement('tr');
        const td = cell(row, text);
        td.colSpan = 8;
        td.className = 'drivers-empty' + (loading ? ' loading' : '');
        $('driverRows').replaceChildren(row);
    }
    function clearFieldErrors() {
        for (const name of [...fieldNames, ...accountFieldNames]) {
            $('error-' + name).textContent = '';
            form.elements.namedItem(name).setAttribute('aria-invalid', 'false');
        }
    }
    function fieldError(name, text) {
        $('error-' + name).textContent = text;
        form.elements.namedItem(name).setAttribute('aria-invalid', 'true');
    }
    function validateForm() {
        clearFieldErrors();
        let first = null;
        for (const name of [...fieldNames, ...accountFieldNames]) {
            const input = form.elements.namedItem(name);
            if (input.disabled) continue;
            if (name !== 'password') input.value = input.value.trim();
            let error = '';
            if (input.required && !input.value) error = 'Vui lòng nhập thông tin này.';
            else if (name === 'password' && (!input.value.trim() || new TextEncoder().encode(input.value).length > 72 || input.value.includes('\0'))) error = 'Mật khẩu bắt buộc, tối đa 72 byte UTF-8.';
            else if (name === 'full_name' && !selectedId && [...input.value].length > 100) error = 'Họ tên tối đa 100 ký tự khi tạo tài khoản.';
            else if (input.maxLength > 0 && [...input.value].length > input.maxLength) error = `Tối đa ${input.maxLength} ký tự.`;
            else if (/[\x00-\x1F\x7F]/u.test(input.value)) error = 'Không được chứa ký tự điều khiển.';
            else if (name === 'phone' && !/^\+?[0-9]{9,15}$/.test(input.value)) error = 'Nhập 9–15 chữ số, có thể bắt đầu bằng +.';
            else if (name === 'user_id' && input.value && (!/^[1-9][0-9]{0,19}$/.test(input.value) || (input.value.length === 20 && input.value > '18446744073709551615'))) error = 'ID phải là số nguyên dương trong giới hạn BIGINT.';
            else if (name === 'license_expiry' && !input.validity.valid) error = 'Vui lòng nhập ngày hợp lệ từ năm 1000 đến 9999.';
            else if (name === 'status' && !Object.hasOwn(labels, input.value)) error = 'Vui lòng chọn trạng thái hợp lệ.';
            if (error) { fieldError(name, error); first ||= input; }
        }
        if (first) { first.focus(); return false; }
        return true;
    }
    function showFormError(error) {
        const mappings = [['username', /username|tên đăng nhập/i], ['password', /password|mật khẩu/i], ['phone', /phone|điện thoại/i], ['license_expiry', /license_expiry|ngày hết hạn/i], ['license_class', /license_class|hạng GPLX/i], ['user_id', /user_id|tài khoản/i], ['address', /address|địa chỉ/i], ['driver_code', /driver_code/i], ['full_name', /full_name/i], ['license_number', /license_number/i], ['status', /trạng thái/i]];
        const field = mappings.find(([, pattern]) => pattern.test(error.message));
        if (error.status === 409 && /đã được sử dụng/i.test(error.message)) {
            for (const name of ['driver_code', 'license_number', 'user_id']) {
                if (!form.elements.namedItem(name).disabled) fieldError(name, 'Kiểm tra giá trị trùng với hồ sơ khác.');
            }
        } else if (field) fieldError(field[0], error.message);
        message($('formMessage'), error.message, true);
    }
    async function loadStats() {
        const current = ++statsRequest;
        for (const id of ['totalDrivers', 'availableDrivers', 'busyDrivers', 'inactiveDrivers']) $(id).textContent = '—';
        $('statsMessage').textContent = 'Đang tải thống kê…';
        try {
            const drivers = await api('/api/drivers');
            if (current !== statsRequest) return;
            $('totalDrivers').textContent = drivers.length;
            for (const [status, id] of [['available', 'availableDrivers'], ['on_trip', 'busyDrivers'], ['inactive', 'inactiveDrivers']]) $(id).textContent = drivers.filter(driver => driver.status === status).length;
            $('statsMessage').textContent = 'Thống kê toàn bộ tài xế, không phụ thuộc bộ lọc danh sách.';
        } catch (error) {
            if (current === statsRequest) $('statsMessage').textContent = `Không tải được thống kê: ${error.message} Nhấn Làm mới để thử lại.`;
        }
    }
    let statsRequest = 0;
    async function refresh() {
        $('refreshButton').disabled = true;
        $('refreshButton').setAttribute('aria-busy', 'true');
        try { await Promise.all([load(), loadStats()]); }
        finally { $('refreshButton').disabled = false; $('refreshButton').setAttribute('aria-busy', 'false'); }
    }
    async function load() {
        const current = ++requestNumber;
        $('resultCount').textContent = 'Đang tải danh sách…';
        tableMessage('Đang tải danh sách…', true);
        $('listRetry').hidden = true;
        document.querySelector('.drivers-table-wrap').setAttribute('aria-busy', 'true');
        try {
            const drivers = await api('/api/drivers', 'GET', null, { search: $('search').value.trim(), status: $('statusFilter').value });
            if (current !== requestNumber) return;
            $('driverRows').replaceChildren();
            for (const driver of drivers) {
                const row = document.createElement('tr');
                for (const [index, key] of ['driver_code', 'full_name', 'phone', 'license_number', 'license_class', 'license_expiry'].entries()) {
                    const value = key === 'license_expiry' && driver[key] ? driver[key].split('-').reverse().join('/') : driver[key];
                    cell(row, value, columnLabels[index]);
                }
                const badge = document.createElement('span');
                badge.className = `drivers-badge ${driver.status}`;
                badge.textContent = labels[driver.status] || driver.status;
                cell(row, '', 'Trạng thái').replaceChildren(badge);
                const actionCell = cell(row, '', 'Thao tác');
                const actions = document.createElement('div');
                actions.className = 'drivers-row-actions';
                actionCell.replaceChildren(actions);
                for (const [action, label] of [['view', 'Xem'], ['edit', 'Sửa'], ['status', 'Trạng thái'], ['delete', 'Xóa']]) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    const icon = document.createElement('i');
                    icon.className = 'fa-solid fa-' + ({ view: 'eye', edit: 'pen-to-square', status: 'arrows-rotate', delete: 'trash-can' })[action];
                    icon.setAttribute('aria-hidden', 'true');
                    button.append(icon, document.createTextNode(label));
                    button.setAttribute('aria-label', `${label}: ${driver.driver_code} — ${driver.full_name}`);
                    button.dataset.action = action;
                    button.dataset.id = driver.driver_id;
                    if (action === 'delete') button.className = 'drivers-danger';
                    actions.append(button);
                }
                $('driverRows').append(row);
            }
            if (!drivers.length) tableMessage('Không có tài xế phù hợp. Hãy thay đổi bộ lọc hoặc thêm tài xế.');
            $('resultCount').textContent = `${drivers.length} tài xế${drivers.length ? '' : ' — Không có kết quả phù hợp.'}`;
        } catch (error) {
            if (current !== requestNumber) return;
            tableMessage('Không tải được danh sách. Nhấn Thử lại để tải danh sách.');
            $('listRetry').hidden = false;
            $('resultCount').textContent = 'Không tải được danh sách.';
            message($('pageMessage'), error.message, true);
        } finally {
            if (current === requestNumber) document.querySelector('.drivers-table-wrap').setAttribute('aria-busy', 'false');
        }
    }

    function openForm(driver = null, readonly = false) {
        if (saving || !ready) return;
        formReadonly = readonly;
        selectedId = driver?.driver_id ?? null;
        form.reset();
        clearFieldErrors();
        message($('formMessage'));
        for (const field of ['driver_code', 'full_name', 'phone', 'address', 'license_number', 'license_class', 'license_expiry', 'status', 'user_id']) {
            const input = form.elements.namedItem(field);
            input.disabled = readonly;
            if (driver) input.value = driver[field] ?? '';
        }
        $('accountFields').hidden = readonly || Boolean(driver);
        $('linkedAccountField').hidden = readonly || !driver;
        form.elements.namedItem('user_id').disabled = readonly || !driver;
        form.elements.namedItem('full_name').maxLength = driver ? 150 : 100;
        for (const name of accountFieldNames) {
            const input = form.elements.namedItem(name);
            input.value = '';
            input.disabled = readonly || Boolean(driver);
            input.required = !readonly && !driver;
        }
        $('dialogTitle').textContent = readonly ? 'Thông tin tài xế' : driver ? 'Sửa tài xế' : 'Thêm tài xế';
        $('driverCode').textContent = driver ? `Mã tài xế: ${driver.driver_code}` : 'Nhập mã tài xế riêng, không trùng hồ sơ khác.';
        selectedStatus = driver?.status ?? 'available';
        form.elements.namedItem('status').disabled = readonly || Boolean(driver?.has_active_assignment || driver?.has_active_trip);
        if (!readonly && (driver?.has_active_assignment || driver?.has_active_trip)) message($('formMessage'), 'Tài xế còn phân công hoặc chuyến đang hiệu lực; có thể sửa hồ sơ nhưng không đổi trạng thái.');
        $('driverFields').hidden = readonly;
        $('requiredNote').hidden = readonly;
        $('driverDetails').hidden = !readonly;
        $('driverDetails').replaceChildren();
        if (readonly) {
            for (const [key, label] of [...fieldNames.map(key => [key, fieldLabels[key]]), ['created_at','Ngày tạo hồ sơ'], ['updated_at','Cập nhật lần cuối']]) {
                const group = document.createElement('div');
                const term = document.createElement('dt');
                const value = document.createElement('dd');
                term.textContent = label;
                value.textContent = key === 'status' ? labels[driver.status] : key === 'license_expiry' && driver[key] ? driver[key].split('-').reverse().join('/') : driver[key] ?? '—';
                group.append(term, value);
                $('driverDetails').append(group);
            }
        }
        $('cancelDialog').textContent = readonly ? 'Đóng' : 'Hủy';
        $('saveButton').hidden = readonly;
        $('saveButton').textContent = driver ? 'Lưu thay đổi' : 'Tạo hồ sơ và tài khoản';
        $('driverDialog').showModal();
    }

    $('listRetry').addEventListener('click', () => { message($('pageMessage')); if (ready) load(); else initialize(); });
    form.addEventListener('input', event => {
        const name = event.target.name;
        if (![...fieldNames, ...accountFieldNames].includes(name)) return;
        $('error-' + name).textContent = '';
        event.target.setAttribute('aria-invalid', 'false');
    });
    $('refreshButton').addEventListener('click', async () => { if (ready) { message($('pageMessage')); await refresh(); } else await initialize(); });
    $('statusFilter').addEventListener('change', () => { if (ready) { message($('pageMessage')); load(); } });
    $('addButton').addEventListener('click', () => openForm());
    for (const id of ['closeDialog', 'cancelDialog']) $(id).addEventListener('click', () => { if (!saving) { form.elements.namedItem('password').value = ''; $('driverDialog').close(); } });
    $('driverDialog').addEventListener('cancel', event => { if (saving) event.preventDefault(); else form.elements.namedItem('password').value = ''; });
    $('statusDialog').addEventListener('cancel', event => { if (saving) event.preventDefault(); });
    $('closeStatus').addEventListener('click', () => { if (!saving) $('statusDialog').close(); });
    $('filterForm').addEventListener('submit', event => { event.preventDefault(); if (ready) { message($('pageMessage')); load(); } });
    $('resetFilter').addEventListener('click', () => { $('filterForm').reset(); if (ready) { message($('pageMessage')); load(); } });

    $('driverRows').addEventListener('click', async event => {
        const button = event.target.closest('button[data-action]');
        if (!button || saving || !ready) return;
        saving = true;
        button.disabled = true;
        try {
            const id = button.dataset.id;
            const driver = await api('/api/drivers/show', 'GET', null, { id });
            if (button.dataset.action === 'delete') {
                pendingDelete = driver;
                $('deleteDriver').textContent = `${driver.driver_code} — ${driver.full_name}`;
                message($('deleteMessage'));
                $('confirmDelete').disabled = false;
                $('deleteDialog').showModal();
            } else if (button.dataset.action === 'status') {
                if (driver.has_active_assignment || driver.has_active_trip) throw new Error('Không thể đổi trạng thái khi còn phân công assigned hoặc chuyến PLANNED/IN_PROGRESS.');
                statusId = id;
                $('statusDriver').textContent = `${driver.driver_code} — ${driver.full_name}`;
                $('newStatus').value = driver.status;
                message($('statusMessage'));
                $('statusDialog').showModal();
            } else { saving = false; openForm(driver, button.dataset.action === 'view'); }
        } catch (error) { message($('pageMessage'), error.message, true); }
        finally { saving = false; button.disabled = false; }
    });

    for (const id of ['closeDelete', 'cancelDelete']) $(id).addEventListener('click', () => {
        if (!saving) { pendingDelete = null; $('deleteDialog').close(); }
    });
    $('deleteDialog').addEventListener('cancel', event => { if (saving) event.preventDefault(); else pendingDelete = null; });
    $('confirmDelete').addEventListener('click', async () => {
        if (!ready || saving || !pendingDelete) return;
        saving = true;
        $('confirmDelete').disabled = true;
        $('confirmDelete').textContent = 'Đang xóa…';
        message($('deleteMessage'));
        try {
            await api('/api/drivers', 'DELETE', null, { id: pendingDelete.driver_id });
            pendingDelete = null;
            $('deleteDialog').close();
            message($('pageMessage'), 'Xóa tài xế thành công.');
            await refresh();
        } catch (error) { message($('deleteMessage'), error.message, true); }
        finally { saving = false; $('confirmDelete').disabled = false; $('confirmDelete').textContent = 'Xóa tài xế'; }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving || !ready || formReadonly || !validateForm()) return;
        const data = Object.fromEntries(new FormData(form));
        if (selectedId) data.user_id = data.user_id.trim() === '' ? null : data.user_id.trim();
        else delete data.user_id;
        data.address = data.address.trim() || null;
        data.license_expiry = data.license_expiry || null;
        if (form.elements.namedItem('status').disabled) data.status = selectedStatus;
        saving = true;
        $('saveButton').disabled = true;
        $('saveButton').textContent = 'Đang lưu…';
        try {
            await api('/api/drivers', selectedId ? 'PUT' : 'POST', data, selectedId ? { id: selectedId } : {});
            form.elements.namedItem('password').value = '';
            $('driverDialog').close();
            message($('pageMessage'), selectedId ? 'Cập nhật tài xế thành công.' : 'Tạo hồ sơ và tài khoản DRIVER thành công. Nếu không thấy hồ sơ, hãy xóa bộ lọc.');
            await refresh();
        } catch (error) { showFormError(error); }
        finally { saving = false; $('saveButton').disabled = false; $('saveButton').textContent = selectedId ? 'Lưu thay đổi' : 'Tạo hồ sơ và tài khoản'; }
    });

    $('statusForm').addEventListener('submit', async event => {
        event.preventDefault();
        if (saving || !ready) return;
        saving = true;
        const button = event.submitter || $('statusSaveButton');
        button.disabled = true;
        try {
            await api('/api/drivers/status', 'PUT', { status: $('newStatus').value }, { id: statusId });
            $('statusDialog').close();
            message($('pageMessage'), 'Cập nhật trạng thái thành công.');
            await refresh();
        } catch (error) { message($('statusMessage'), error.message, true); }
        finally { saving = false; button.disabled = false; }
    });

    $('logoutButton').addEventListener('click', async () => {
        try { await api('/api/logout', 'POST'); try { sessionStorage.removeItem('user'); } catch {} location.href = '../customer/login.html'; }
        catch (error) { message($('pageMessage'), error.message, true); }
    });

    async function initialize() {
        ++requestNumber;
        ++statsRequest;
        message($('pageMessage'));
        tableMessage('Đang kiểm tra phiên đăng nhập…', true);
        for (const id of ['totalDrivers', 'availableDrivers', 'busyDrivers', 'inactiveDrivers']) $(id).textContent = '—';
        $('statsMessage').textContent = 'Đang kiểm tra phiên đăng nhập…';
        ready = false;
        $('addButton').disabled = true;
        try {
            const user = await api('/api/me');
            if (!['ADMIN', 'STAFF'].includes(user.role) || Number(user.status) !== 1) throw new Error('Tài khoản cần quyền ADMIN hoặc STAFF và đang hoạt động.');
            $('currentUser').textContent = user.full_name;
            ready = true;
            $('addButton').disabled = false;
            await refresh();
        } catch (error) {
            message($('pageMessage'), `${error.message} Đăng nhập bằng trang đăng nhập hiện có của hệ thống.`, true);
            $('resultCount').textContent = 'Chưa có quyền truy cập danh sách.';
            tableMessage('Chưa thể truy cập danh sách. Hãy đăng nhập bằng tài khoản ADMIN/STAFF hoặc nhấn Làm mới để thử lại.');
            $('statsMessage').textContent = 'Chưa tải được thống kê.';
        }
    }
    window.addEventListener('pageshow', async event => {
        if (event.persisted) {
            for (const id of ['driverDialog', 'statusDialog', 'deleteDialog']) $(id).close();
            pendingDelete = null;
            await initialize();
        }
    });
    initialize();
})();
