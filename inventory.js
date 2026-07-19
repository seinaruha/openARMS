
const API = '';
const DEFAULT_CATEGORIES = ['Food', 'Medicine', 'Supplies'];
let allItems = [], allShelters = [], allCategories = [];
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
  return `<span class="cell-small">${fmt(d)}</span>`;
}

function progressBar(qty, min) {
  const max = Math.max(qty, min * 2, 1);
  const pct = Math.min((qty / max) * 100, 100);
  const cls = qty <= min ? (qty <= min * 0.5 ? 'critical' : 'low') : '';
  return `<div class="progress-wrap"><div class="progress-bar"><div class="progress-fill ${cls}" style="width:${pct}%"></div></div><span class="progress-num">${fmtNumber(qty)}/${fmtNumber(min)}</span></div>`;
}

async function loadInventory() {
  const [itemData, shelterData, categoryData] = await Promise.all([
    api('/inventory.php'),
    api('/shelters.php'),
    api('/inventory_categories.php')
  ]);
  allItems = itemData && itemData.items ? itemData.items : [];
  allShelters = shelterData && shelterData.shelters ? shelterData.shelters : [];
  allCategories = categoryData && categoryData.categories ? categoryData.categories : [];
  if (!allCategories.length) {
    allCategories = DEFAULT_CATEGORIES.map(name => ({ category_id: null, category_name: name }));
  }
  populateCategoryOptions();
  populateShelterFilters();
  renderInventory(allItems);
}

function renderInventory(items) {
  document.getElementById('inv-tbody').innerHTML = items.length ? items.map(i => `
    <tr>
      <td><strong class="strong-regular">${i.name}</strong></td>
      <td><span class="badge badge-${i.category.toLowerCase()}">${i.category}</span></td>
      <td class="cell-muted">${i.shelter_name || '—'}</td>
      <td>${progressBar(i.quantity, i.min_stock)}</td>
      <td class="cell-regular">${i.unit}</td>
      <td>${expiryBadge(i.expiry_date)}</td>
      <td>${stockBadge(i.quantity, i.min_stock)}</td>
      <td>
        <button class="btn btn-sm btn-secondary" onclick="openTransfer(${i.item_id})">Transfer</button>
        <button class="btn btn-sm btn-secondary" onclick="editItem(${i.item_id})">Edit</button>
        <button class="btn btn-sm btn-danger" onclick="deleteItem(${i.item_id}, ${JSON.stringify(i.name)})">Del</button>
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

function populateCategoryOptions(selectedCategory) {
  const filter = document.getElementById('inv-cat-filter');
  if (filter) {
    filter.innerHTML = '<option value="">All Categories</option>' +
      allCategories.map(c => `<option value="${c.category_name}">${c.category_name}</option>`).join('');
    if (selectedCategory && allCategories.some(c => c.category_name === selectedCategory)) {
      filter.value = selectedCategory;
    }
  }
  const categorySelect = document.getElementById('item-category');
  if (categorySelect) {
    categorySelect.innerHTML = allCategories.map(c => `<option value="${c.category_name}">${c.category_name}</option>`).join('');
  }
}

function openCategoryModal() {
  document.getElementById('category-name').value = '';
  refreshCategoryList();
  document.getElementById('modal-categories').classList.add('open');
}

function refreshCategoryList() {
  const list = document.getElementById('category-list');
  if (!list) return;
  list.innerHTML = allCategories.map(cat => `
    <div class="list-row">
      <span>${cat.category_name}</span>
      ${cat.category_id ? `<button class="btn btn-sm btn-danger" type="button" onclick="removeCategory(${cat.category_id})">Remove</button>` : ''}
    </div>
  `).join('');
}

async function addCategory() {
  const input = document.getElementById('category-name');
  if (!input) return;
  const categoryName = input.value.trim();
  if (!categoryName) return toast('Enter a category name', 'error');
  if (allCategories.some(c => c.category_name === categoryName)) return toast('Category already exists', 'error');
  try {
    const result = await api('/inventory_categories.php', 'POST', { category_name: categoryName });
    if (result && result.category) {
      allCategories.push(result.category);
      populateCategoryOptions(categoryName);
      refreshCategoryList();
      input.value = '';
      toast('Category added');
    }
  } catch (err) {
    toast(err.message || 'Failed to add category', 'error');
  }
}

async function removeCategory(categoryId) {
  if (categoryId === null) return toast('Cannot remove this category', 'error');
  try {
    await api('/inventory_categories.php', 'DELETE', { category_id: categoryId });
    allCategories = allCategories.filter(c => c.category_id !== categoryId);
    populateCategoryOptions();
    refreshCategoryList();
    toast('Category removed');
  } catch (err) {
    toast(err.message || 'Failed to remove category', 'error');
  }
}

function populateShelterFilters() {
  ['inv-shelter-filter'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = '<option value="">All Shelters</option>' +
      allShelters.map(s => `<option value="${s.shelter_id}">${s.shelter_name}</option>`).join('');
  });
  ['item-shelter','transfer-destination'].forEach(id => {
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

function openTransfer(id) {
  const item = allItems.find(i => i.item_id === id);
  if (!item) return;
  document.getElementById('transfer-item-id').value = id;
  document.getElementById('transfer-item-name').textContent = item.name;
  document.getElementById('transfer-item-shelter').textContent = item.shelter_name || '—';
  const destination = document.getElementById('transfer-destination');
  if (destination) {
    destination.innerHTML = '<option value="">Select destination shelter...</option>' +
      allShelters
        .filter(s => s.shelter_id !== item.shelter_id)
        .map(s => `<option value="${s.shelter_id}">${s.shelter_name}</option>`)
        .join('');
  }
  document.getElementById('transfer-qty').value = '';
  document.getElementById('transfer-max-qty').textContent = `Available: ${fmtNumber(item.quantity)}`;
  document.getElementById('transfer-note').value = '';
  document.getElementById('modal-transfer').classList.add('open');
}

async function saveTransfer() {
  const itemId = parseInt(document.getElementById('transfer-item-id').value, 10);
  const item = allItems.find(i => i.item_id === itemId);
  const destinationShelterId = parseInt(document.getElementById('transfer-destination').value, 10);
  const quantity = parseFloat(document.getElementById('transfer-qty').value);
  const notes = document.getElementById('transfer-note').value.trim() || null;

  if (!itemId || !destinationShelterId || !quantity || quantity <= 0) {
    return toast('Select destination and quantity to transfer', 'error');
  }
  if (!item) return toast('Transfer item not found', 'error');
  if (destinationShelterId === item.shelter_id) {
    return toast('Destination shelter cannot be the same as source shelter', 'error');
  }
  if (quantity > item.quantity) {
    return toast('Transfer quantity cannot exceed available stock', 'error');
  }

  try {
    await api('/inventory_movements.php', 'POST', {
      item_id: itemId,
      transaction_type: 'TRANSFER',
      quantity,
      shelter_id: item.shelter_id,
      destination_shelter_id: destinationShelterId,
      transaction_notes: notes
    });
    closeModal('modal-transfer');
    toast('Transfer recorded');
    loadInventory();
  } catch (err) {
    toast(err.message || 'Failed to transfer item', 'error');
  }
}

loadInventory();
