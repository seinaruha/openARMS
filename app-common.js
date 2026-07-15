
function getAuthHeaders() {
  const token = localStorage.getItem('asrms_token') || sessionStorage.getItem('asrms_token');
  return token ? { 'Authorization': 'Bearer ' + token } : {};
}

function toast(msg, type='success') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = 'show ' + type;
  setTimeout(() => t.className = '', 2500);
}

function ensureToastElement() {
  if (document.getElementById('toast')) return;
  const d = document.createElement('div');
  d.id = 'toast';
  document.body.appendChild(d);
}

if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', ensureToastElement);
}

function fmt(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'});
}

function fmtNumber(value) {
  if (value === null || value === undefined || value === '') return '';
  const num = Number(value);
  if (Number.isNaN(num)) return String(value);
  if (Number.isInteger(num)) return num.toString();
  return num.toLocaleString('en-PH', { maximumFractionDigits: 12 }).replace(/\.0+$/, '').replace(/(\.[0-9]*?)0+$/, '$1');
}

async function api(path, method='GET', body=null) {
  const base = (typeof API !== 'undefined') ? API : '';
  const opts = { method, headers: Object.assign({'Content-Type':'application/json'}, getAuthHeaders()) };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(base + path, opts);
  const ct = r.headers.get('content-type') || '';
  if (ct.includes('application/json')) {
    const data = await r.json();
    if (!r.ok) {
      if (r.status === 401) { window.location.href = 'login.html'; return null; }
      throw new Error(data.error || 'Request failed');
    }
    return data;
  }
  
  const text = await r.text();
  const snippet = text ? text.slice(0, 1000) : '';
  if (r.ok) {
    throw new Error('Unexpected server response (non-JSON): ' + (snippet || r.statusText));
  }
  throw new Error('Request failed: ' + (snippet || r.statusText));
}

function initSidebar() {
  fetch('sidebar.html')
    .then(r => r.text())
    .then(html => {
      document.getElementById('sidebar-container').innerHTML = html;
      
      const currentPage = location.pathname.split('/').pop() || 'dashboard.html';
      document.querySelectorAll('.nav-item').forEach(item => {
        if (item.getAttribute('href') === currentPage) item.classList.add('active');
      });

      
      const userJson = localStorage.getItem('asrms_user') || sessionStorage.getItem('asrms_user');
      if (userJson) {
        let user = {};
        try { user = JSON.parse(userJson); } catch (e) { user = { username: userJson }; }
        const su = document.getElementById('sidebar-user');
        if (su) su.innerHTML = `<div style="display:flex;align-items:center;justify-content:space-between;gap:8px"><div style="font-size:13px">${user.display_name||user.username||'User'}</div><button id="sidebar-logout" class="btn" style="font-size:12px;">Logout</button></div>`;
        const lb = document.getElementById('sidebar-logout');
        if (lb) lb.addEventListener('click', () => { localStorage.removeItem('asrms_user'); localStorage.removeItem('asrms_token'); sessionStorage.removeItem('asrms_user'); sessionStorage.removeItem('asrms_token'); window.location.href = 'login.html'; });
      }
    });
}

window.appCommon = { getAuthHeaders, toast, fmt, fmtNumber, api, initSidebar };
