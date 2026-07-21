
function getAuthHeaders() {
    const token = localStorage.getItem('asrms_token') || sessionStorage.getItem('asrms_token');
    return token ? { 'Authorization': 'Bearer ' + token } : {};
  }
  
  function getCurrentUser() {
    const userJson = localStorage.getItem('asrms_user') || sessionStorage.getItem('asrms_user');
    if (!userJson) return null;
    try {
      return JSON.parse(userJson);
    } catch (e) {
      return null;
    }
  }
  
  function getAssignedShelterId() {
    const user = getCurrentUser();
    if (!user) return null;
    const shelter = user.assigned_shelter || null;
    if (!shelter) return null;
    if (typeof shelter === 'object') {
      return shelter.shelter_id ? Number(shelter.shelter_id) : null;
    }
    return Number(shelter) || null;
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
    document.addEventListener('DOMContentLoaded', () => {
      ensureToastElement();
      initModalOverlays();
    });
  }
  
  // Modal helpers: centralize modal behavior so page scripts don't duplicate this logic.
  function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('open');
  }
  
  function initModalOverlays() {
    try {
      document.querySelectorAll('.modal-overlay').forEach(m => {
        // ensure we don't attach duplicates
        if (m.__modal_inited) return;
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
        m.__modal_inited = true;
      });
    } catch (e) {
      // ignore if DOM not ready or selectors not available
    }
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
  
          try {
            user = JSON.parse(userJson);
          } catch (e) {
            user = { username: userJson };
          }
  
          // Get user roles
          const rolesArr = Array.isArray(user.roles)
            ? user.roles.map(r => (r.role_name || r).toString()).filter(Boolean)
            : [];
  
          // Hide Register menu for guests
          if (rolesArr.includes('guest')) {
            const registerLink = document.getElementById('nav-register');
            if (registerLink) {
              registerLink.style.display = 'none';
            }
          }
  
          const su = document.getElementById('sidebar-user');
  
          if (su) {
            let roleDisplay = 'User';
            let shelterDisplay = '';
  
            if (rolesArr.length) {
              if (rolesArr.includes('superadmin')) {
                roleDisplay = 'Administrator';
              } else if (rolesArr.includes('shelter_manager')) {
                roleDisplay = 'Shelter Manager';
              } else {
                roleDisplay = rolesArr[0]
                  .replace(/_/g, ' ')
                  .replace(/\b\w/g, c => c.toUpperCase());
              }
            }
  
            if (user.assigned_shelter && user.assigned_shelter.shelter_name) {
              shelterDisplay = user.assigned_shelter.shelter_name;
            }
  
            su.innerHTML = `
              <div class="sidebar-user-container">
                <div class="sidebar-user-wrap">
                  <div class="sidebar-user-info">
                    <div class="sidebar-user-name">${user.personnel_name || user.display_name || 'User'}</div>
                    <div class="sidebar-user-meta">
                      ${roleDisplay}${shelterDisplay ? ` • ${shelterDisplay}` : ''}
                    </div>
                  </div>
                  <button id="sidebar-logout" class="btn sidebar-logout">Logout</button>
                </div>
              </div>
            `;
          }
  
          const lb = document.getElementById('sidebar-logout');
          if (lb) {
            lb.addEventListener('click', () => {
              localStorage.removeItem('asrms_user');
              localStorage.removeItem('asrms_token');
              sessionStorage.removeItem('asrms_user');
              sessionStorage.removeItem('asrms_token');
              window.location.href = 'login.html';
            });
          }
        }
      });
  }
  
  window.appCommon = { getAuthHeaders, toast, fmt, fmtNumber, api, initSidebar, getCurrentUser, getAssignedShelterId, closeModal, initModalOverlays };
  