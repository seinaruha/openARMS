
const API = '';

function showError(msg) {
  document.getElementById('error-text').textContent = msg;
  document.getElementById('error-banner').classList.add('show');
}

function hideError() {
  document.getElementById('error-banner').classList.remove('show');
}

document.getElementById('toggle-password').addEventListener('click', () => {
  const pw = document.getElementById('password');
  const btn = document.getElementById('toggle-password');
  const isHidden = pw.type === 'password';
  pw.type = isHidden ? 'text' : 'password';
  btn.textContent = isHidden ? 'Hide' : 'Show';
});

if (localStorage.getItem('asrms_user') || sessionStorage.getItem('asrms_user')) {
  window.location.href = 'dashboard.html';
}

document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  hideError();

  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  const remember = document.getElementById('remember').checked;
  const btn = document.getElementById('login-btn');

  if (!username || !password) {
    showError('Enter your username and password.');
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Signing in...';

  try {
    const res = await fetch(API + '/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    });
    const data = await res.json();

    if (!res.ok || data.error) {
      showError(data.error || 'Incorrect username or password.');
      btn.disabled = false;
      btn.textContent = 'Sign In';
      return;
    }

    const storage = remember ? localStorage : sessionStorage;
    storage.setItem('asrms_user', JSON.stringify(data.user || { username }));
    if (data.token) storage.setItem('asrms_token', data.token);

    toast('Signed in — redirecting...');
    setTimeout(() => { window.location.href = 'dashboard.html'; }, 600);

  } catch (err) {
    showError('Couldn\'t reach the server. Try again.');
    btn.disabled = false;
    btn.textContent = 'Sign In';
  }
});
