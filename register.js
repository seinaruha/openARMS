const API = '';
let allShelters = [];

function getCurrentUser() {
  const userJson = localStorage.getItem('asrms_user') || sessionStorage.getItem('asrms_user');
  if (!userJson) return null;
  try {
    return JSON.parse(userJson);
  } catch (e) {
    return null;
  }
}

function hasRole(user, role) {
  return user?.roles?.some(r => r.role_name === role);
}

function isSuperadmin(user) {
  return hasRole(user, 'superadmin');
}

function isShelterManager(user) {
  return hasRole(user, 'shelter_manager');
}

function isManagerOrAbove(user) {
  return isSuperadmin(user) || isShelterManager(user);
}

const currentUser = getCurrentUser();
if (!currentUser) {
  window.location.href = 'login.html';
} else if (!isManagerOrAbove(currentUser)) {
  window.location.href = 'dashboard.html';
}

initSidebar();

async function loadShelters() {
  const shelterData = await api('/shelters.php');
  allShelters = shelterData?.shelters || [];
  if (isShelterManager(currentUser) && !isSuperadmin(currentUser) && currentUser.assigned_shelter?.shelter_id) {
    allShelters = [currentUser.assigned_shelter];
  }
  const sel = document.getElementById('person-shelter');
  if (!sel) return;
  sel.innerHTML = '<option value="">Select shelter...</option>' + allShelters.map(s => `<option value="${s.shelter_id}">${s.shelter_name}</option>`).join('');
}

function getShelterFieldContainer() {
  const shelterSelect = document.getElementById('person-shelter');
  return shelterSelect ? shelterSelect.closest('.form-group') : null;
}

function setShelterFieldVisibility(show) {
  const container = getShelterFieldContainer();
  const label = document.getElementById('shelter-label');
  const shelterSelect = document.getElementById('person-shelter');
  if (!container || !shelterSelect || !label) return;
  container.style.display = show ? '' : 'none';
  if (!show) {
    shelterSelect.value = '';
    label.textContent = 'Shelter (hidden for this role)';
  }
}

function updateShelterFieldVisibility(role) {
  if (role === 'superadmin' || role === 'guest') {
    setShelterFieldVisibility(false);
  } else {
    setShelterFieldVisibility(true);
  }
}

function setupRegisterPermissions() {
  const roleSelect = document.getElementById('person-role');
  const shelterSelect = document.getElementById('person-shelter');
  const label = document.getElementById('shelter-label');

  if (isShelterManager(currentUser) && !isSuperadmin(currentUser)) {
    const superadminOption = roleSelect.querySelector('option[value="superadmin"]');
    if (superadminOption) superadminOption.remove();

    if (currentUser.assigned_shelter?.shelter_id) {
      shelterSelect.value = currentUser.assigned_shelter.shelter_id;
      shelterSelect.disabled = true;
      label.textContent = 'Shelter (locked to your shelter)';
    }
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  await loadShelters();
  setupRegisterPermissions();
  const roleSelect = document.getElementById('person-role');
  updateShelterFieldVisibility(roleSelect.value);

  roleSelect.addEventListener('change', () => {
    const role = roleSelect.value;
    const label = document.getElementById('shelter-label');
    if (role === 'superadmin' || role === 'guest') {
      label.textContent = 'Shelter (optional)';
    } else {
      label.textContent = 'Shelter *';
    }
    updateShelterFieldVisibility(role);
  });
  await loadUsers();
});

async function loadUsers(query = '') {
  try {
    const data = await api('/personnel.php');
    const users = data?.personnel || [];
    renderUsers(users.filter(u => {
      if (!query) return true;
      const text = `${u.personnel_name} ${u.username} ${Array.isArray(u.roles) ? u.roles.map(r => r.role_name).join(' ') : ''}`.toLowerCase();
      return text.includes(query.toLowerCase());
    }));
  } catch (err) {
    console.error('Failed to load personnel', err);
  }
}

function renderUsers(users) {
  const tbody = document.getElementById('users-tbody');
  if (!tbody) return;
  tbody.innerHTML = users.map(u => {
    const active = u.is_active ? 'Yes' : 'No';
    const roles = Array.isArray(u.roles) ? u.roles.map(r => r.role_name + (r.shelter_id ? ` (${escapeHtml(String(r.shelter_id))})` : '')).join(', ') : '';
    const actions = isSuperadmin(currentUser)
      ? `<button class="btn btn-sm btn-secondary" onclick="editUser(${u.personnel_id})">Edit</button> <button class="btn btn-sm btn-danger" onclick="confirmDeleteUser(${u.personnel_id}, '${escapeJs(u.personnel_name || '')}')">Delete</button> <button class="btn btn-sm" onclick="confirmToggle(${u.personnel_id}, ${u.is_active ? 0 : 1}, '${escapeJs(u.personnel_name || '')}')">${u.is_active ? 'Deactivate' : 'Activate'}</button>`
      : '';
    return `<tr><td class="cell-regular">${escapeHtml(u.personnel_name)}</td><td class="cell-small">${escapeHtml(u.username)}</td><td class="cell-small">${escapeHtml(roles)}</td><td class="cell-small">${escapeHtml(u.phone || '')}</td><td class="cell-small">${active}</td><td>${actions}</td></tr>`;
  }).join('');
}

function escapeHtml(s) {
  if (!s) return '';
  return String(s).replace(/[&<>\"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]);
}

function escapeJs(s) {
  if (!s) return '';
  return String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, ' ');
}

function filterUsers() {
  const query = document.getElementById('user-search')?.value || '';
  loadUsers(query);
}

function openAddUser() {
  document.getElementById('user-modal-title').textContent = 'Add User';
  document.getElementById('person-id').value = '';
  document.getElementById('person-name').value = '';
  document.getElementById('person-username').value = '';
  document.getElementById('person-username').disabled = false;
  document.getElementById('person-password').value = '';
  document.getElementById('person-password-confirm').value = '';
  document.getElementById('person-phone').value = '';
  document.getElementById('person-role').value = '';
  document.getElementById('person-shelter').value = '';
  document.getElementById('person-active').checked = true;
  document.getElementById('save-person-btn').textContent = 'Create User';
  document.getElementById('delete-person-btn').style.display = 'none';
  updateShelterFieldVisibility(document.getElementById('person-role').value);
  document.getElementById('modal-user').classList.add('open');
}

async function editUser(id) {
  try {
    const data = await api('/personnel.php?id=' + encodeURIComponent(id));
    const person = data?.personnel;
    if (!person) return toast('Failed to load personnel', 'error');
    document.getElementById('user-modal-title').textContent = 'Edit User';
    document.getElementById('person-id').value = person.personnel_id;
    document.getElementById('person-name').value = person.personnel_name || '';
    document.getElementById('person-username').value = person.username || '';
    document.getElementById('person-username').disabled = true;
    document.getElementById('person-password').value = '';
    document.getElementById('person-password-confirm').value = '';
    document.getElementById('person-phone').value = person.phone || '';
    document.getElementById('person-active').checked = !!person.is_active;
    const r = (person.roles && person.roles[0]) || null;
    if (r) {
      document.getElementById('person-role').value = r.role_name || '';
      if (r.shelter_id) document.getElementById('person-shelter').value = r.shelter_id;
    }
    document.getElementById('save-person-btn').textContent = 'Save Changes';
    document.getElementById('delete-person-btn').style.display = 'inline-block';
    updateShelterFieldVisibility(document.getElementById('person-role').value);
    document.getElementById('modal-user').classList.add('open');
  } catch (err) {
    toast('Failed to load personnel', 'error');
  }
}

function confirmToggle(id, value, name) {
  const action = value ? 'activate' : 'deactivate';
  const display = name || 'this user';
  if (!confirm(`Are you sure you want to ${action} ${display}?`)) return;
  toggleActive(id, value);
}

function confirmDeleteUser(id, name) {
  const display = name || 'this user';
  if (!confirm(`Delete ${display}? This cannot be undone.`)) return;
  deleteUser(id);
}

async function deleteUser(id) {
  try {
    await api('/personnel.php?id=' + encodeURIComponent(id), 'DELETE');
    closeModal('modal-user');
    toast('User deleted');
    await loadUsers();
  } catch (err) {
    toast(err.message, 'error');
  }
}

function resetForm() {
  document.getElementById('person-id').value = '';
  document.getElementById('person-name').value = '';
  document.getElementById('person-username').value = '';
  document.getElementById('person-username').disabled = false;
  document.getElementById('person-password').value = '';
  document.getElementById('person-password-confirm').value = '';
  document.getElementById('person-phone').value = '';
  document.getElementById('person-role').value = '';
  document.getElementById('person-shelter').value = '';
  document.getElementById('person-active').checked = true;
  document.getElementById('save-person-btn').textContent = 'Create Personnel';
}

async function savePersonnel() {
  const name = document.getElementById('person-name').value.trim();
  const username = document.getElementById('person-username').value.trim();
  const password = document.getElementById('person-password').value;
  const passwordConfirm = document.getElementById('person-password-confirm').value;
  const phone = document.getElementById('person-phone').value.trim();
  const role = document.getElementById('person-role').value;
  let shelterId = document.getElementById('person-shelter').value || null;

  const personId = document.getElementById('person-id').value;
  if (personId) {
    if (!name || !role) return toast('Fill in required fields', 'error');
  } else {
    if (!name || !username || !password || !passwordConfirm || !role) {
      return toast('Fill in all required fields', 'error');
    }
    if (password.length < 8) {
      return toast('Password must be at least 8 characters', 'error');
    }
    if (password !== passwordConfirm) {
      return toast('Passwords do not match', 'error');
    }
  }
  if ((role === 'staff' || role === 'volunteer' || role === 'shelter_manager') && !shelterId) {
    return toast('Select a shelter for staff, volunteer, or shelter manager', 'error');
  }

  if (isShelterManager(currentUser) && !isSuperadmin(currentUser)) {
    const assignedShelterId = currentUser.assigned_shelter?.shelter_id;
    if (!assignedShelterId) {
      return toast('Your account is not assigned to a shelter.', 'error');
    }
    shelterId = String(assignedShelterId);
  }

  try {
    const personId = document.getElementById('person-id').value;
    if (personId) {
      const body = {
        personnel_name: name,
        phone: phone || null,
        is_active: document.getElementById('person-active').checked ? 1 : 0,
        roles: [{ role_name: role, shelter_id: shelterId ? parseInt(shelterId) : null }]
      };
      await api('/personnel.php?id=' + encodeURIComponent(personId), 'PUT', body);
      toast('Personnel updated');
      resetForm();
      await loadUsers();
    } else {
      const payload = {
        shelter_id: shelterId ? parseInt(shelterId) : null,
        personnel_name: name,
        role,
        phone: phone || null,
        username,
        password
      };
      await api('/register.php', 'POST', payload);
      toast('Personnel created successfully');
      setTimeout(() => { window.location.href = 'dashboard.html'; }, 600);
    }
  } catch (err) {
    toast(err.message, 'error');
  }
}

async function toggleActive(id, value) {
  try {
    await api('/personnel.php?id=' + encodeURIComponent(id), 'PUT', { is_active: value });
    toast('Updated');
    await loadUsers();
  } catch (err) {
    toast(err.message, 'error');
  }
}
