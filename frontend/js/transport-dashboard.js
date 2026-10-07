(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const endpoint = new URL('../../backend/public/index.php', location.href);
    let revision = 0;
    function message(text, error = false) {
        $('pageMessage').textContent = text;
        $('pageMessage').className = text ? `drivers-message${error ? ' error' : ''}` : '';
    }
    async function api(route, method = 'GET') {
        const url = new URL(endpoint); url.search = new URLSearchParams({ route });
        let response;
        try { response = await fetch(url, { method, credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'} }); }
        catch { throw new Error('Không thể kết nối máy chủ. Vui lòng thử lại.'); }
        if (response.status === 401) { location.replace('../customer/login.html'); throw new Error('Vui lòng đăng nhập.'); }
        let result;
        try { result = await response.json(); } catch { throw new Error('Không tải được thông tin phiên. Vui lòng thử lại.'); }
        if (!response.ok || !result?.success) throw new Error(response.status >= 500 ? 'Không thể xác thực phiên lúc này. Vui lòng thử lại.' : result?.message || 'Không thể xác thực phiên.');
        return result.data;
    }
    function hideContent() { $('staffContent').hidden = true; $('currentUser').textContent = 'Đang kiểm tra phiên…'; }
    async function initialize() {
        const current = ++revision; hideContent(); $('retrySession').hidden = true; message('Đang kiểm tra phiên đăng nhập…');
        try {
            const user = await api('/api/me');
            if (current !== revision) return;
            if (!['STAFF','ADMIN'].includes(user?.role) || Number(user.status) !== 1) throw new Error('Trang tổng quan nhân viên yêu cầu tài khoản STAFF/ADMIN đang hoạt động.');
            $('currentUser').textContent = user.full_name || user.username;
            $('staffContent').hidden = false; message('');
        } catch (error) { if (current === revision) { message(error.message,true); $('retrySession').hidden = false; } }
    }
    $('retrySession').addEventListener('click',initialize);
    $('logoutButton').addEventListener('click',async () => {
        $('logoutButton').disabled = true; revision++; hideContent();
        try { await api('/api/logout','POST'); try { sessionStorage.removeItem('user'); } catch {} location.replace('../customer/login.html'); }
        catch (error) { message(error.message,true); $('retrySession').hidden = false; }
        finally { $('logoutButton').disabled = false; }
    });
    window.addEventListener('pagehide',() => { revision++; hideContent(); });
    window.addEventListener('pageshow',event => { if (event.persisted) initialize(); });
    initialize();
})();
