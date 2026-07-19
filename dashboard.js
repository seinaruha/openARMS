
const API = '';
initSidebar();

const CATEGORY_COLORS = { food: '#1a6b4a', medicine: '#5b5fc7', supplies: '#e67e22' };
const FALLBACK_COLORS = ['#2d9166', '#d68910', '#c0392b', '#1a6b4a', '#e67e22', '#5b5fc7'];
let currentAlertShelter = null;
const categoryItemCache = {};

function getStoredUser() {
  const userJson = localStorage.getItem('asrms_user') || sessionStorage.getItem('asrms_user');
  if (!userJson) return null;
  try { return JSON.parse(userJson); } catch (err) { return null; }
}

function getAssignedShelterId() {
  const user = getStoredUser();
  return user?.assigned_shelter?.shelter_id || null;
}

async function loadShelterSelector() {
  const shelterData = await api('/shelters.php');
  const select = document.getElementById('alert-shelter');
  if (!select) return;

  const shelters = shelterData?.shelters || [];
  select.innerHTML = `<option value="">All Shelters</option>` + shelters.map(s => `<option value="${s.shelter_id}">${s.shelter_name}</option>`).join('');

  const assigned = getAssignedShelterId();
  if (assigned && shelters.some(s => s.shelter_id === assigned)) {
    select.value = assigned;
    currentAlertShelter = assigned;
  }

  select.addEventListener('change', () => {
    currentAlertShelter = select.value || null;
    Object.keys(categoryItemCache).forEach(key => delete categoryItemCache[key]);
    loadDashboard();
  });
}

function renderCategoryDonut(categories) {
  const el = document.getElementById('category-breakdown');
  if (!categories || !categories.length) {
    el.innerHTML = '<div class="empty-state"><div class="empty-icon">📦</div><p>No inventory data yet</p></div>';
    return;
  }
  const total = categories.reduce((s, c) => s + (c.excess_qty || 0), 0) || 1;
  const colorFor = (c, i) => CATEGORY_COLORS[c.category.toLowerCase()] || FALLBACK_COLORS[i % FALLBACK_COLORS.length];

  let cumulative = 0;
  const stops = categories.map((c, i) => {
    const pct = ((c.excess_qty || 0) / total) * 100;
    const start = cumulative;
    cumulative += pct;
    return `${colorFor(c, i)} ${start}% ${cumulative}%`;
  }).join(', ');

  const legend = categories.map((c, i) => {
    const current = c.total_qty || 0;
    const minimum = c.min_qty || 0;
    const isDanger = minimum > 0 && current < minimum;
    const isWarning = minimum > 0 && current === minimum;
    const statusClass = isDanger ? 'danger' : (isWarning ? 'warning' : '');
    return `
    <div class="donut-legend-item ${statusClass}" data-category-index="${i}" data-category="${c.category}">
      <div class="legend-header">
        <div class="li-left">
          <span class="legend-dot" style="background:${colorFor(c, i)}"></span>
          <div>
            <div class="li-name">${c.category}</div>
            <div class="li-sub">${c.count} types · ${fmtNumber(c.total_qty)} units · min ${fmtNumber(minimum)}</div>
          </div>
        </div>
        <div class="li-right">
          <div class="li-status">${fmtNumber(current)} / ${fmtNumber(minimum)}</div>
          <span class="legend-chevron">›</span>
        </div>
      </div>
      <div class="category-details" id="category-details-${i}"></div>
    </div>
  `;
  }).join('');

  el.innerHTML = `
    <div class="donut-wrap">
      <div class="donut" style="background: conic-gradient(${stops});">
        <div class="donut-center"><strong>${categories.length}</strong><span>Types</span></div>
      </div>
      <div class="donut-legend">${legend}</div>
    </div>
  `;

  attachCategoryToggles();
}

function escapeHtml(text) {
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function attachCategoryToggles() {
  document.querySelectorAll('.donut-legend-item').forEach(item => {
    item.addEventListener('click', async () => {
      const detailsEl = item.querySelector('.category-details');
      if (!detailsEl) return;
      const category = item.dataset.category;
      if (detailsEl.classList.contains('expanded')) {
        detailsEl.classList.remove('expanded');
        item.classList.remove('expanded');
        return;
      }
      await loadCategoryDetails(category, detailsEl, item);
    });
  });
}

async function loadCategoryDetails(category, detailsEl, itemEl) {
  if (detailsEl.dataset.loaded === '1') {
    detailsEl.classList.add('expanded');
    itemEl.classList.add('expanded');
    return;
  }

  detailsEl.innerHTML = '<div class="category-details-loading">Loading items...</div>';
  const shelterQuery = currentAlertShelter ? `?shelter_id=${encodeURIComponent(currentAlertShelter)}` : '';
  try {
    const response = categoryItemCache[category] || await api('/inventory.php' + shelterQuery);
    if (!categoryItemCache[category]) {
      categoryItemCache[category] = response;
    }
    const items = (response?.items || []).filter(i => String(i.category) === category);
    if (!items.length) {
      detailsEl.innerHTML = '<div class="empty-state"><div class="empty-icon">📦</div><p>No items in this category</p></div>';
      detailsEl.dataset.loaded = '1';
      detailsEl.classList.add('expanded');
      itemEl.classList.add('expanded');
      return;
    }

    detailsEl.innerHTML = `
      <div class="category-items">
        ${items.map(item => {
          const current = Number(item.quantity || 0);
          const minStock = Number(item.min_stock || 0);
          const statusClass = minStock > 0 && current < minStock ? 'danger' : (minStock > 0 && current === minStock ? 'warning' : '');
          return `
            <div class="category-item-row ${statusClass}">
              <div>
                <div class="category-item-name">${escapeHtml(item.name)}</div>
                <div class="category-item-meta">${escapeHtml(item.unit)} · ${escapeHtml(item.shelter_name || '')}</div>
              </div>
              <div class="category-item-stats">${fmtNumber(current)} / ${fmtNumber(minStock)}</div>
            </div>
          `;
        }).join('')}
      </div>
    `;
    detailsEl.dataset.loaded = '1';
    detailsEl.classList.add('expanded');
    itemEl.classList.add('expanded');
  } catch (err) {
    detailsEl.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><p>Unable to load items</p></div>';
    console.warn(err);
  }
}

async function loadDashboard() {
  const statsPath = '/stats.php' + (currentAlertShelter ? `?shelter_id=${encodeURIComponent(currentAlertShelter)}` : '');
  const alertsPath = '/alerts.php' + (currentAlertShelter ? `?shelter_id=${encodeURIComponent(currentAlertShelter)}` : '');

  const [stats, alerts] = await Promise.all([
    api(statsPath),
    api(alertsPath)
  ]);
  document.getElementById('s-items').textContent = fmtNumber(stats.total_items);
  document.getElementById('s-qty').textContent = fmtNumber(stats.total_quantity);
  document.getElementById('s-low').textContent = fmtNumber(stats.low_stock_count);
  document.getElementById('s-exp').textContent = fmtNumber(stats.expired_count);
  document.getElementById('s-don').textContent = fmtNumber(stats.total_donations);
  document.getElementById('s-sup').textContent = fmtNumber(stats.total_suppliers);

  const totalAlerts = alerts.low_stock.length + alerts.expired.length + alerts.expiring_soon.length;
  const badge = document.getElementById('alert-count-badge');
  if (totalAlerts > 0) { badge.style.display=''; badge.textContent = totalAlerts + ' alerts'; }
  else badge.style.display = 'none';

  renderCategoryDonut(stats.by_category);

  document.getElementById('recent-activity').innerHTML = stats.recent_logs.length ? stats.recent_logs.map(l => `
    <div class="feed-item">
      <div class="feed-dot ${l.action.toLowerCase()}">${l.action === 'IN' ? '↓' : '↑'}</div>
      <div class="feed-body">
        <strong>${l.action === 'IN' ? '+' : '-'}${fmtNumber(l.quantity)} units — ${l.item_name}</strong>
        <time>${fmt(l.logged_at)}</time>
      </div>
    </div>
  `).join('') : '<div class="empty-state"><div class="empty-icon">⚡</div><p>No recent activity</p></div>';

  const allA = [
    ...alerts.expired.map(a => ({...a, type:'danger', label:'Expired', msg:`<strong>${a.name}</strong><span>${a.shelter_name}</span>`})),
    ...alerts.low_stock.map(a => ({...a, type:'warning', label:'Low Stock', msg:`<strong>${a.name}</strong><span>${fmtNumber(a.quantity)} left · min ${fmtNumber(a.min_stock)}</span>`})),
    ...alerts.expiring_soon.map(a => ({...a, type:'warning', label:'Expiring', msg:`<strong>${a.name}</strong><span>expires ${fmt(a.expiry_date)} · ${a.shelter_name}</span>`}))
  ];
  document.getElementById('dashboard-alerts').innerHTML = allA.length ? allA.map(a => `
    <div class="alert-item">
      <div class="alert-dot ${a.type}"></div>
      <div class="alert-text">${a.msg}</div>
      <span class="alert-severity ${a.type}">${a.label}</span>
    </div>
  `).join('') : '<div class="empty-state"><div class="empty-icon">✅</div><p>No active alerts</p></div>';

  if (totalAlerts > 0) {
    sendAlertNotification(allA);
  }
}

loadShelterSelector().then(loadDashboard);

function sendAlertNotification(alerts) {
  if (!('Notification' in window)) {
    return;
  }

  const title = 'openARMS Alert';
  const body = alerts.length === 1
    ? `${alerts[0].label}: ${alerts[0].name}`
    : `${alerts.length} alerts need attention`;
  const icon = 'openARMS.png';

  const show = () => {
    try {
      new Notification(title, { body, icon });
    } catch (err) {
      console.warn('Notification failed', err);
    }
  };

  if (Notification.permission === 'granted') {
    show();
  } else if (Notification.permission === 'default') {
    Notification.requestPermission().then(permission => {
      if (permission === 'granted') show();
    });
  }
}
