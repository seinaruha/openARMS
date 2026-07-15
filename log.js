
const API = '';
let allLogs = [], allItems = [];
initSidebar();

async function loadLogs() {
  const [logData, itemData] = await Promise.all([api('/inventory_movements.php'), api('/inventory.php')]);
  allLogs = logData?.logs || [];
  allItems = itemData?.items || [];
  renderLogs(allLogs);
}

function renderLogs(rows) {
  document.getElementById('log-tbody').innerHTML = rows.length ? rows.map(l => `
    <tr>
      <td><strong>${l.item_name}</strong></td>
      <td><span class="badge badge-${l.action.toLowerCase()}">${l.action === 'IN' ? '↓ Stock In' : '↑ Stock Out'}</span></td>
      <td>${fmtNumber(l.quantity)}</td>
      <td style="font-size:12.5px;color:var(--gray-600)">${l.unit}</td>
      <td style="font-size:12.5px">${l.reason || '—'}</td>
      <td style="font-size:12.5px;color:var(--gray-600)">${l.performed_by || '—'}</td>
      <td style="font-size:12.5px;color:var(--gray-600)">${fmt(l.logged_at)}</td>
    </tr>
  `).join('') : '<tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📋</div><p>No logs yet</p></div></td></tr>';
}

function filterLogs() {
  const act = document.getElementById('log-action-filter').value;
  renderLogs(allLogs.filter(l => !act || l.action === act));
}

function openAddLog() {
  document.getElementById('log-qty').value = '';
  document.getElementById('log-reason').value = '';
  document.getElementById('log-item').innerHTML = '<option value="">Select item...</option>' +
    allItems.map(i => `<option value="${i.item_id}">${i.name}</option>`).join('');
  document.getElementById('modal-log').classList.add('open');
}

async function saveLog() {
  const itemId = document.getElementById('log-item').value;
  const selectedItem = allItems.find(i => String(i.item_id) === String(itemId));
  const data = {
    item_id: parseInt(itemId, 10),
    transaction_type: document.getElementById('log-action').value,
    quantity: parseFloat(document.getElementById('log-qty').value),
    shelter_id: selectedItem ? selectedItem.shelter_id : null,
    transaction_notes: document.getElementById('log-reason').value.trim() || null
  };
  if (!data.item_id || !data.quantity || !data.shelter_id) return toast('Fill required fields and select an item', 'error');

  try {
    await api('/inventory_movements.php', 'POST', data);
    closeModal('modal-log');
    toast('Movement logged');
    loadLogs();
  } catch (err) {
    toast(err.message || 'Failed to log movement', 'error');
  }
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

loadLogs();
