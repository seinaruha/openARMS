
const API = '';
let allSuppliers = [];
initSidebar();

async function loadSuppliers() {
  allSuppliers = (await api('/suppliers.php'))?.suppliers || [];
  document.getElementById('sup-tbody').innerHTML = allSuppliers.length ? allSuppliers.map(s => `
    <tr>
      <td><strong class="strong-regular">${s.supplier_name}</strong></td>
      <td class="cell-regular">${s.contact || '—'}</td>
      <td class="cell-regular text-accent">${s.email || '—'}</td>
      <td class="cell-small">${s.address || '—'}</td>
      <td class="cell-small">${fmt(s.created_at)}</td>
    </tr>
  `).join('') : '<tr><td colspan="5"><div class="empty-state"><div class="empty-icon">🏭</div><p>No suppliers yet</p></div></td></tr>';
}

function openAddSupplier() {
  ['sup-name','sup-contact','sup-email','sup-address'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('modal-supplier').classList.add('open');
}

async function saveSupplier() {
  const data = {
    supplier_name: document.getElementById('sup-name').value.trim(),
    contact: document.getElementById('sup-contact').value,
    email: document.getElementById('sup-email').value,
    address: document.getElementById('sup-address').value
  };
  if (!data.supplier_name) return toast('Supplier name is required', 'error');
  await api('/suppliers.php', 'POST', data);
  closeModal('modal-supplier');
  toast('Supplier added');
  loadSuppliers();
}

loadSuppliers();
