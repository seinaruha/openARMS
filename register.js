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
  if (role === 'superadmin' || role === 'auditor') {
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
    if (role === 'superadmin' || role === 'auditor') {
      label.textContent = 'Shelter (optional)';
    } else {
      label.textContent = 'Shelter *';
    }
    updateShelterFieldVisibility(role);
  });
});

async function savePersonnel() {
  const name = document.getElementById('person-name').value.trim();
  const username = document.getElementById('person-username').value.trim();
  const password = document.getElementById('person-password').value;
  const passwordConfirm = document.getElementById('person-password-confirm').value;
  const phone = document.getElementById('person-phone').value.trim();
  const role = document.getElementById('person-role').value;
  let shelterId = document.getElementById('person-shelter').value || null;

  if (!name || !username || !password || !passwordConfirm || !role) {
    return toast('Fill in all required fields', 'error');
  }
  if (password.length < 8) {
    return toast('Password must be at least 8 characters', 'error');
  }
  if (password !== passwordConfirm) {
    return toast('Passwords do not match', 'error');
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
  } catch (err) {
    toast(err.message, 'error');
  }
}
