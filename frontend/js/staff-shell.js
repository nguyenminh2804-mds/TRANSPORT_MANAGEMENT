(() => {
'use strict';
const endpoint=new URL('../../backend/public/index.php',location.href);
async function api(route,method='GET'){const url=new URL(endpoint);url.searchParams.set('route',route);const response=await fetch(url,{method,credentials:'same-origin',cache:'no-store'});if(response.status===401){location.replace('../customer/login.html');throw new Error('Vui lòng đăng nhập.');}const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Không xác thực được phiên.');return result.data;}
api('/api/me').then(user=>{if(!['STAFF','ADMIN'].includes(user?.role)||Number(user.status)!==1)throw new Error('Cần tài khoản STAFF/ADMIN đang hoạt động.');document.getElementById('currentUser').textContent=user.full_name||user.username;}).catch(error=>{document.getElementById('currentUser').textContent=error.message;});
document.getElementById('logoutButton').addEventListener('click',async()=>{try{await api('/api/logout','POST');location.replace('../customer/login.html');}catch(error){document.getElementById('currentUser').textContent=error.message;}});
})();
