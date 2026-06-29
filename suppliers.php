<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Suppliers</title>
<link rel="stylesheet" href="suppliers.css">
</head>
<body>

<div class="app">
<div id="sidebar-container"></div>
<main class="main">
<div class="topbar">
<div class="topbar-title">Suppliers</div>
<div class="topbar-right">
<span style="font-size:13px;color:var(--gray-600);">Admin</span>
</div>
</div>

<div class="content">
<div class="toolbar">
<div class="toolbar-left"></div>
<button class="btn btn-primary" onclick="openAddSupplier()">+ Add Supplier</button>
</div>
<div class="card">
<div class="table-wrap">
<table>
<thead>
<tr><th>Supplier</th><th>Contact</th><th>Email</th><th>Address</th><th>Registered</th></tr>
</thead>
<tbody id="sup-tbody"></tbody>
</table>
</div>
</div>
</div>
</main>
</div>

<!-- Add Supplier Modal -->
<div class="modal-overlay" id="modal-supplier">
<div class="modal">
<div class="modal-header">
<h2 class="modal-title">Add Supplier</h2>
<button class="modal-close" onclick="closeModal('modal-supplier')">×</button>
</div>
<div class="form-grid">
<div class="form-group full"><label>Supplier Name *</label><input type="text" id="sup-name" placeholder="Company or person name"></div>
<div class="form-group"><label>Contact</label><input type="text" id="sup-contact" placeholder="Phone number"></div>
<div class="form-group"><label>Email</label><input type="email" id="sup-email" placeholder="email@example.com"></div>
<div class="form-group full"><label>Address</label><input type="text" id="sup-address" placeholder="Street, City"></div>
</div>
<div class="modal-footer">
<button class="btn btn-secondary" onclick="closeModal('modal-supplier')">Cancel</button>
<button class="btn btn-primary" onclick="saveSupplier()">Save</button>
</div>
</div>
</div>

<div id="toast"></div>

<script>
const API = 'http://localhost/api/index.php?resource=suppliers';
let allSuppliers = [];

fetch('sidebar.html')
.then(r => r.text())
.then(html => {
    document.getElementById('sidebar-container').innerHTML = html;
    document.querySelectorAll('.nav-item')[4].classList.add('active');
});

function toast(msg, type='success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show ' + type;
    setTimeout(() => t.className = '', 2500);
}

function fmt(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'});
}

async function api(method='GET', body=null) {
    const opts = { method, headers: {'Content-Type':'application/json'} };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(API, opts);
    return r.json();
}

async function loadSuppliers() {
    allSuppliers = await api('GET');
    document.getElementById('sup-tbody').innerHTML = allSuppliers.length ? allSuppliers.map(s => `
    <tr>
    <td><strong>${s.name}</strong></td>
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
        name: document.getElementById('sup-name').value.trim(),
        contact: document.getElementById('sup-contact').value,
        email: document.getElementById('sup-email').value,
        address: document.getElementById('sup-address').value
    };
    if (!data.name) return toast('Supplier name is required', 'error');
    await api('POST', data);
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
</script>
</body>
</html>
