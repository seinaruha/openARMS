
const API = '';
let allDonations = [], allShelters = [], allSuppliers = [], allItems = [];
let editingId = null;

initSidebar();

async function loadDonations() {
  try {
    const [donData, shelterData, supplierData, itemData] = await Promise.all([
      api('/donations.php'),
      api('/shelters.php'),
      api('/suppliers.php'),
      api('/inventory.php')
    ]);
    allDonations = donData?.donations || [];
    allShelters = shelterData?.shelters || [];
    allSuppliers = supplierData?.suppliers || [];
    allItems = itemData?.items || [];
    renderDonations(allDonations);
  } catch (err) {
    toast(err.message, 'error');
  }
}

function renderDonations(rows) {
  document.getElementById('don-tbody').innerHTML = rows.length ? rows.map(d => `
    <tr>
      <td><strong>${d.donor_name || '—'}</strong></td>
      <td>${d.item_name || '—'}</td>
      <td>${d.item_quantity != null ? fmtNumber(d.item_quantity) : '—'}</td>
      <td style="color:var(--gray-600);font-size:13px">${d.shelter_name || '—'}</td>
      <td style="color:var(--gray-600);font-size:13px">${d.supplier_name || 'Walk-in'}</td>
      <td style="font-size:12.5px;color:var(--gray-600)">${fmt(d.received_date)}</td>
      <td style="font-size:12.5px;color:var(--gray-600)">${d.receipt_notes || '—'}</td>
      <td class="action-btns">
        <button class="btn btn-secondary" onclick="editDonation(${d.donation_id})">Edit</button>
        <button class="btn btn-danger" onclick="deleteDonation(${d.donation_id})">Delete</button>
      </td>
    </tr>
  `).join('') : '<tr><td colspan="8"><div class="empty-state"><div class="empty-icon">🎁</div><p>No donations yet</p></div></td></tr>';
}

function filterDonations() {
  const q = document.getElementById('don-search').value.toLowerCase();
  renderDonations(allDonations.filter(d => !q || (d.donor_name || '').toLowerCase().includes(q)));
}

function populateSelects() {
  document.getElementById('don-shelter').innerHTML = '<option value="">-- Select Shelter --</option>' +
    allShelters.map(s => `<option value="${s.shelter_id}">${s.shelter_name}</option>`).join('');
  document.getElementById('don-supplier').innerHTML = '<option value="">Walk-in / Anonymous</option>' +
    allSuppliers.map(s => `<option value="${s.supplier_id}">${s.supplier_name}</option>`).join('');
  document.getElementById('don-item').innerHTML = '<option value="">Select item...</option>' +
    allItems.map(i => `<option value="${i.item_id}">${i.name} (${i.unit})</option>`).join('');
}

function openAddDonation() {
  editingId = null;
  document.getElementById('modal-title-text').textContent = 'Record Donation';
  document.getElementById('save-btn').textContent = 'Record';
  document.getElementById('don-name').value = '';
  document.getElementById('don-desc').value = '';
  document.getElementById('don-qty').value = '';
  document.getElementById('don-notes').value = '';
  document.getElementById('don-date').value = new Date().toISOString().slice(0, 10);
  populateSelects();
  document.getElementById('modal-donation').classList.add('open');
}

async function editDonation(id) {
  try {
    const data = await api('/donations.php?id=' + id);
    const d = data.donation;
    editingId = id;
    populateSelects();

    document.getElementById('modal-title-text').textContent = 'Edit Donation';
    document.getElementById('save-btn').textContent = 'Save Changes';
    document.getElementById('don-name').value = d.donor_name || '';
    document.getElementById('don-desc').value = d.description || '';
    document.getElementById('don-shelter').value = d.shelter_id || '';
    document.getElementById('don-supplier').value = d.supplier_id || '';
    document.getElementById('don-date').value = d.received_date;
    document.getElementById('don-notes').value = d.receipt_notes || '';

    if (d.lines && d.lines[0]) {
      document.getElementById('don-item').value = d.lines[0].item_id;
      document.getElementById('don-qty').value = d.lines[0].item_quantity;
    }

    document.getElementById('modal-donation').classList.add('open');
  } catch (err) {
    toast(err.message, 'error');
  }
}

async function saveDonation() {
  const payload = {
    donor_name: document.getElementById('don-name').value.trim(),
    description: document.getElementById('don-desc').value.trim(),
    shelter_id: document.getElementById('don-shelter').value || null,
    supplier_id: document.getElementById('don-supplier').value || null,
    received_date: document.getElementById('don-date').value,
    receipt_notes: document.getElementById('don-notes').value.trim(),
    item_id: parseInt(document.getElementById('don-item').value, 10),
    item_quantity: parseFloat(document.getElementById('don-qty').value)
  };

  if (!payload.shelter_id) {
    return toast('Select a shelter for the donation', 'error');
  }
  if (!payload.received_date || !payload.item_id || !payload.item_quantity) {
    return toast('Fill required fields', 'error');
  }

  try {
    if (editingId) {
      payload.donation_id = editingId;
      await api('/donations.php', 'PUT', payload);
      toast('Donation updated');
    } else {
      await api('/donations.php', 'POST', payload);
      toast('Donation recorded — inventory updated');
    }
    closeModal('modal-donation');
    loadDonations();
  } catch (err) {
    toast(err.message, 'error');
  }
}

async function deleteDonation(id) {
  if (!confirm('Delete this donation?')) return;
  try {
    await api('/donations.php', 'DELETE', { donation_id: id });
    toast('Donation deleted');
    loadDonations();
  } catch (err) {
    toast(err.message, 'error');
  }
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

loadDonations();
