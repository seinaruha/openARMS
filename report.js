
const API = '';
let allShelters = [];
initSidebar();

async function loadReport() {
  if (!allShelters.length) allShelters = (await api('/shelters.php'))?.shelters || [];
  populateShelterFilters();
  const shel = document.getElementById('rpt-shelter').value;
  const rows = (await api('/report.php' + (shel ? '?shelter_id='+shel : '')))?.reports || [];
  document.getElementById('rpt-tbody').innerHTML = rows.length ? rows.map(r => `
    <tr>
      <td><strong class="strong-regular">${r.name}</strong></td>
      <td><span class="badge badge-${r.category.toLowerCase()}">${r.category}</span></td>
      <td class="cell-muted">${r.shelter_name || '—'}</td>
      <td><strong>${fmtNumber(r.quantity)}</strong></td>
      <td class="cell-small">${r.unit}</td>
      <td class="cell-small">${fmtNumber(r.min_stock)}</td>
      <td class="cell-small">${r.expiry_date ? fmt(r.expiry_date) : '—'}</td>
      <td>${r.stock_status === 'Low' ? '<span class="badge badge-low">Low</span>' : '<span class="badge badge-ok">OK</span>'}</td>
      <td>${r.expiry_status === 'Expired' ? '<span class="badge badge-expired">Expired</span>' : r.expiry_status === 'Expiring Soon' ? '<span class="badge badge-expiring">Soon</span>' : '<span class="badge badge-ok">Good</span>'}</td>
    </tr>
  `).join('') : '<tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📊</div><p>No data</p></div></td></tr>';
}

function populateShelterFilters() {
  const el = document.getElementById('rpt-shelter');
  if (!el) return;
  el.innerHTML = '<option value="">All Shelters</option>' +
    allShelters.map(s => `<option value="${s.shelter_id}">${s.shelter_name}</option>`).join('');
}

function printReport() {
  window.print();
}

loadReport();
