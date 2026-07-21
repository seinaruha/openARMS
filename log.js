
const API = '';
let allLogs = [], allItems = [];
initSidebar();

async function loadLogs() {
  const [logData, itemData] = await Promise.all([api('/inventory_movements.php'), api('/inventory.php')]);
  allLogs = logData && logData.logs ? logData.logs : [];
  allItems = itemData && itemData.items ? itemData.items : [];
  populateLogShelterFilters();
  filterLogs();
}

function renderLogs(rows) {
  document.getElementById('log-tbody').innerHTML = rows.length ? rows.map(l => `
    <tr>
      <td><strong>${l.item_name}</strong></td>
      <td><span class="badge badge-${l.action.toLowerCase()}">${l.action === 'IN' ? '↓ Stock In' : l.action === 'OUT' ? '↑ Stock Out' : l.action === 'TRANSFER' ? '↔ Transfer' : '⚙️ Adjust'}</span></td>
      <td class="cell-small">${l.shelter_name || '—'}</td>
      <td>${fmtNumber(l.quantity)}</td>
      <td class="cell-small">${l.unit}</td>
      <td class="cell-regular">${l.reason || '—'}</td>
      <td class="cell-small">${l.performed_by || '—'}</td>
      <td class="cell-small">${fmt(l.logged_at)}</td>
    </tr>
  `).join('') : '<tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📋</div><p>No logs yet</p></div></td></tr>';
}

function filterLogs() {
  const act = document.getElementById('log-action-filter').value;
  const shelter = document.getElementById('log-shelter-filter').value;
  renderLogs(allLogs.filter(l => {
    const matchesAction = !act || l.action === act;
    const matchesShelter = !shelter || String(l.shelter_id) === shelter;
    return matchesAction && matchesShelter;
  }));
}

function populateLogShelterFilters() {
  const assignedShelter = getAssignedShelterId();
  const filter = document.getElementById('log-shelter-filter');
  const shelterOptions = allLogs
    .map(l => l.shelter_id ? { shelter_id: l.shelter_id, shelter_name: l.shelter_name || '' } : null)
    .filter(Boolean)
    .reduce((acc, item) => {
      const key = String(item.shelter_id);
      if (!acc.map.has(key)) {
        acc.map.set(key, true);
        acc.options.push(item);
      }
      return acc;
    }, { map: new Map(), options: [] })
    .options;
  filter.innerHTML = '<option value="">All Shelters</option>' +
    shelterOptions.map(s => `<option value="${s.shelter_id}">${s.shelter_name}</option>`).join('');
  if (assignedShelter && shelterOptions.some(s => Number(s.shelter_id) === assignedShelter)) {
    filter.value = String(assignedShelter);
  } else {
    filter.value = '';
  }
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

loadLogs();
