
const API = '';
let allSuppliers = [];
initSidebar();

async function loadSuppliers() {
  allSuppliers = (await api('/suppliers.php'))?.suppliers || [];
  document.getElementById('sup-tbody').innerHTML = allSuppliers.length ? allSuppliers.map(s => `
    <tr>
      <td><strong>${s.supplier_name}</strong></td>
      <td style="font-size:13px">${s.contact || '—'}</td>
      <td style="font-size:13px;color:var(--text-accent)">${s.email || '—'}</td>
      <td style="font-size:12.5px;color:var(--gray-600)">${s.address || '—'}</td>
      <td style="font-size:12.5px;color:var(--gray-600)">${fmt(s.created_at)}</td>
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

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

loadSuppliers();
