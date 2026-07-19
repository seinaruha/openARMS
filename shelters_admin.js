const API = '';
let allShelters = [];
initSidebar();

async function loadShelters() {
  allShelters = (await api('/shelters.php'))?.shelters || [];
  document.getElementById('sh-tbody').innerHTML = allShelters.length ? allShelters.map(s => `
    <tr>
      <td><strong class="strong-regular">${s.shelter_name}</strong></td>
      <td class="cell-muted">${s.address || '—'}</td>
      <td class="cell-regular">${s.contact_person || s.contact_number || '—'}</td>
      <td class="cell-regular">${s.capacity || '—'}</td>
      <td class="cell-regular">${s.shelter_type || '—'}</td>
      <td class="cell-small">${fmt(s.created_at)}</td>
      <td class="cell-right"><button class="btn" onclick="openEditShelter(${s.shelter_id})">Edit</button> <button class="btn btn-danger" onclick="deleteShelter(${s.shelter_id})">Delete</button></td>
    </tr>
  `).join('') : '<tr><td colspan="7"><div class="empty-state"><div class="empty-icon">🏠</div><p>No shelters yet</p></div></td></tr>';
}

function openAddShelter() {
  ['sh-id','sh-name','sh-address','sh-contact','sh-contact-number','sh-capacity','sh-type'].forEach(id => document.getElementById(id).value = '');
  document.querySelector('#modal-shelter .modal-title').textContent = 'Add Shelter';
  document.getElementById('modal-shelter').classList.add('open');
}

async function openEditShelter(id) {
  try {
    const data = await api('/shelters.php?id=' + id);
    const s = data.shelter;
    document.getElementById('sh-id').value = s.shelter_id;
    document.getElementById('sh-name').value = s.shelter_name || '';
    document.getElementById('sh-address').value = s.address || '';
    document.getElementById('sh-contact').value = s.contact_person || '';
    document.getElementById('sh-contact-number').value = s.contact_number || '';
    document.getElementById('sh-capacity').value = s.capacity || '';
    document.getElementById('sh-type').value = s.shelter_type || '';
    document.querySelector('#modal-shelter .modal-title').textContent = 'Edit Shelter';
    document.getElementById('modal-shelter').classList.add('open');
  } catch (e) {
    toast('Failed to load shelter', 'error');
  }
}

async function saveShelter() {
  const id = document.getElementById('sh-id').value;
  const body = {
    shelter_name: document.getElementById('sh-name').value.trim(),
    address: document.getElementById('sh-address').value.trim(),
    contact_person: document.getElementById('sh-contact').value.trim(),
    contact_number: document.getElementById('sh-contact-number').value.trim(),
    capacity: document.getElementById('sh-capacity').value ? Number(document.getElementById('sh-capacity').value) : null,
    shelter_type: document.getElementById('sh-type').value.trim()
  };
  if (!body.shelter_name) return toast('Shelter name is required', 'error');
  try {
    if (id) {
      await api('/shelters.php?id=' + id, 'PUT', body);
      toast('Shelter updated');
    } else {
      await api('/shelters.php', 'POST', body);
      toast('Shelter created');
    }
    closeModal('modal-shelter');
    loadShelters();
  } catch (e) {
    toast(e.message || 'Save failed', 'error');
  }
}

async function deleteShelter(id) {
  if (!confirm('Delete this shelter?')) return;
  try {
    await api('/shelters.php?id=' + id, 'DELETE');
    toast('Shelter deleted');
    loadShelters();
  } catch (e) {
    toast('Delete failed', 'error');
  }
}

loadShelters();
