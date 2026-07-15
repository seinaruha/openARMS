
const API = '';
let allItems = [], allShelters = [];
initSidebar();

function stockBadge(qty, min) {
  const pct = min > 0 ? qty / min : 1;
  if (pct <= 0) return `<span class="badge badge-expired">Out of Stock</span>`;
  if (pct <= 1) return `<span class="badge badge-low">Low Stock</span>`;
  return `<span class="badge badge-ok">In Stock</span>`;
}

function expiryBadge(d) {
  if (!d) return '—';
  const today = new Date(); today.setHours(0,0,0,0);
  const exp = new Date(d);
  const diff = Math.floor((exp - today) / 86400000);
  if (diff < 0) return `<span class="badge badge-expired">Expired</span>`;
  if (diff <= 30) return `<span class="badge badge-expiring">Expires in ${diff}d</span>`;
  return `<span style="font-size:12.5px;color:var(--gray-600)">${fmt(d)}</span>`;
}

function progressBar(qty, min) {
  const max = Math.max(qty, min * 2, 1);
  const pct = Math.min((qty / max) * 100, 100);
  const cls = qty <= min ? (qty <= min * 0.5 ? 'critical' : 'low') : '';
  return `<div class="progress-wrap"><div class="progress-bar"><div class="progress-fill ${cls}" style="width:${pct}%"></div></div><span class="progress-num">${fmtNumber(qty)}/${fmtNumber(min)}</span></div>`;
}

async function loadInventory() {
  const [itemData, shelterData] = await Promise.all([api('/inventory.php'), api('/shelters.php')]);
  allItems = itemData?.items || [];
  allShelters = shelterData?.shelters || [];
  populateShelterFilters();
  renderInventory(allItems);
}

function renderInventory(items) {
  document.getElementById('inv-tbody').innerHTML = items.length ? items.map(i => `
    <tr>
      <td><strong style="font-size:13.5px">${i.name}</strong></td>
      <td><span class="badge badge-${i.category.toLowerCase()}">${i.category}</span></td>
      <td style="font-size:13px;color:var(--gray-600)">${i.shelter_name || '—'}</td>
      <td>${progressBar(i.quantity, i.min_stock)}</td>
      <td style="font-size:13px">${i.unit}</td>
      <td>${expiryBadge(i.expiry_date)}</td>
      <td>${stockBadge(i.quantity, i.min_stock)}</td>
      <td>
        <button class="btn btn-sm btn-secondary" onclick="editItem(${i.item_id})">Edit</button>
        <button class="btn btn-sm btn-danger" onclick="deleteItem(${i.item_id},'${i.name}')">Del</button>
      </td>
    </tr>
  `).join('') : '<tr><td colspan="8"><div class="empty-state"><div class="empty-icon">📦</div><p>No items found</p></div></td></tr>';
}

function filterInventory() {
  const q = document.getElementById('inv-search').value.toLowerCase();
  const cat = document.getElementById('inv-cat-filter').value;
  const shel = document.getElementById('inv-shelter-filter').value;
  renderInventory(allItems.filter(i =>
    (!q || i.name.toLowerCase().includes(q)) &&
    (!cat || i.category === cat) &&
    (!shel || String(i.shelter_id) === shel)
  ));
}

function populateShelterFilters() {
  ['inv-shelter-filter'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = '<option value="">All Shelters</option>' +
      allShelters.map(s => `<option value="${s.shelter_id}">${s.shelter_name}</option>`).join('');
  });
  ['item-shelter'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = '<option value="">Select shelter...</option>' +
      allShelters.map(s => `<option value="${s.shelter_id}">${s.shelter_name}</option>`).join('');
  });
}

function openAddItem() {
  document.getElementById('item-modal-title').textContent = 'Add Item';
  document.getElementById('item-id').value = '';
  ['item-name','item-unit'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('item-qty').value = 0;
  document.getElementById('item-min').value = 10;
  document.getElementById('item-expiry').value = '';
  document.getElementById('item-category').value = 'Food';
  populateShelterFilters();
  document.getElementById('modal-item').classList.add('open');
}

async function editItem(id) {
  const item = allItems.find(i => i.item_id === id);
  if (!item) return;
  document.getElementById('item-modal-title').textContent = 'Edit Item';
  document.getElementById('item-id').value = id;
  document.getElementById('item-name').value = item.name;
  document.getElementById('item-category').value = item.category;
  document.getElementById('item-unit').value = item.unit;
  document.getElementById('item-qty').value = item.quantity;
  document.getElementById('item-min').value = item.min_stock;
  document.getElementById('item-expiry').value = item.expiry_date || '';
  populateShelterFilters();
  document.getElementById('item-shelter').value = item.shelter_id || '';
  document.getElementById('modal-item').classList.add('open');
}

async function saveItem() {
  const id = document.getElementById('item-id').value;
  const data = {
    name: document.getElementById('item-name').value.trim(),
    category: document.getElementById('item-category').value,
    unit: document.getElementById('item-unit').value.trim(),
    quantity: parseFloat(document.getElementById('item-qty').value) || 0,
    min_stock: parseFloat(document.getElementById('item-min').value) || 10,
    expiry_date: document.getElementById('item-expiry').value || null,
    shelter_id: document.getElementById('item-shelter').value || null
  };
  if (!data.name || !data.unit || !data.shelter_id) return toast('Fill in required fields and select a shelter', 'error');
  if (id) await api('/inventory.php?id=' + id, 'PUT', data);
  else await api('/inventory.php', 'POST', data);
  closeModal('modal-item');
  toast(id ? 'Item updated' : 'Item added');
  loadInventory();
}

async function deleteItem(id, name) {
  if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
  await api('/inventory.php?id=' + id, 'DELETE');
  toast('Item deleted');
  loadInventory();
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

loadInventory();
