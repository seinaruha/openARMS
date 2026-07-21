const API = '';
initSidebar();

async function loadAudit() {
  try {
    const res = await api('/audit.php');
    const rows = res?.audit || [];
    document.getElementById('audit-tbody').innerHTML = rows.length ? rows.map(r => `
      <tr>
        <td class="cell-small">${new Date(r.changed_at).toLocaleString()}</td>
        <td><strong class="strong-regular">${r.table_name}</strong></td>
        <td>${r.record_id}</td>
        <td>${r.action}</td>
        <td class="cell-regular">${r.personnel_name || r.changed_by || 'System'}</td>
        <td class="pre-wrap">${formatData(r)}</td>
      </tr>
    `).join('') : '<tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📜</div><p>No audit entries</p></div></td></tr>';
  } catch (e) {
    toast(e.message || 'Failed to load audit log', 'error');
  }
}

function formatData(r) {
  try {
    const parts = [];
    if (r.old_data) parts.push('OLD: ' + JSON.stringify(JSON.parse(r.old_data), null, 2));
    if (r.new_data) parts.push('NEW: ' + JSON.stringify(JSON.parse(r.new_data), null, 2));
    return parts.join('\n---\n');
  } catch (e) {
    return r.new_data || r.old_data || '';
  }
}

loadAudit();
