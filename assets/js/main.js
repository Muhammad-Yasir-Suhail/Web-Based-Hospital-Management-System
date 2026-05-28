/* ============================================================
   IVOR PAINE MEMORIAL HOSPITAL — Main JavaScript
   ============================================================ */

// ── Sidebar Toggle ─────────────────────────────────
function toggleSidebar() {
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        document.body.classList.toggle('sidebar-open');
    } else {
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));
    }
}

// Restore sidebar state on load
(function restoreSidebar() {
    if (window.innerWidth > 768 && localStorage.getItem('sidebarCollapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
})();

// ── Modal Helpers ──────────────────────────────────
function openModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) overlay.classList.add('open');
}

function closeModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) overlay.classList.remove('open');
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// ── Tab System ─────────────────────────────────────
function switchTab(tabGroupId, tabId) {
    const group = document.getElementById(tabGroupId) || document;

    group.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    group.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.toggle('active', pane.id === tabId);
    });
}

// Auto-init tabs
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const paneId = this.dataset.tab;
            const tabs   = this.closest('.card') || document;

            tabs.querySelectorAll('.tab-btn').forEach(b  => b.classList.remove('active'));
            tabs.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            this.classList.add('active');
            const pane = document.getElementById(paneId);
            if (pane) pane.classList.add('active');
        });
    });
});

// ── Alert Auto-dismiss ─────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.alert[data-dismiss]').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });
});

// ── Flash Message (inline) ─────────────────────────
function showFlash(message, type = 'success') {
    const el = document.createElement('div');
    el.className = `alert alert-${type}`;
    el.setAttribute('data-dismiss', '1');
    el.textContent = (type === 'success' ? '✓ ' : '✕ ') + message;

    const area = document.querySelector('.content-area');
    if (area) area.prepend(el);

    setTimeout(() => {
        el.style.transition = 'opacity .5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    }, 4000);
}

// ── Generic CRUD helpers ───────────────────────────

/**
 * Submits a form via AJAX (fetch).
 * formEl    — <form> element
 * endpoint  — PHP endpoint URL
 * onSuccess — callback(data) called when success:true
 */
async function submitForm(formEl, endpoint, onSuccess) {
    const data = new FormData(formEl);

    try {
        const resp = await fetch(endpoint, { method: 'POST', body: data });
        const json = await resp.json();

        if (json.success) {
            showFlash(json.message || 'Saved successfully.');
            if (typeof onSuccess === 'function') onSuccess(json);
        } else {
            showFlash(json.message || 'An error occurred.', 'error');
        }
    } catch (err) {
        showFlash('Network error: ' + err.message, 'error');
    }
}

/**
 * Populates a <select> element with {value, label} pairs.
 */
function populateSelect(selectEl, options, placeholder = '— Select —') {
    selectEl.innerHTML = `<option value="">${placeholder}</option>`;
    options.forEach(opt => {
        const o = document.createElement('option');
        o.value = opt.value;
        o.textContent = opt.label;
        selectEl.appendChild(o);
    });
}

/**
 * Renders a table body from an array of row arrays.
 * columns — array of keys to extract from each row object
 */
function renderTableBody(tbodyEl, rows, columns, renderActions) {
    if (!rows.length) {
        tbodyEl.innerHTML = `<tr><td colspan="${columns.length + (renderActions ? 1 : 0)}" style="text-align:center;color:var(--text-light);padding:32px">No records found.</td></tr>`;
        return;
    }

    tbodyEl.innerHTML = rows.map(row => {
        const cells = columns.map(col => `<td>${row[col] ?? '—'}</td>`).join('');
        const actions = renderActions ? `<td>${renderActions(row)}</td>` : '';
        return `<tr>${cells}${actions}</tr>`;
    }).join('');
}

// ── Table search filter ────────────────────────────
function filterTable(inputEl, tableId) {
    const term  = inputEl.value.toLowerCase();
    const rows  = document.querySelectorAll(`#${tableId} tbody tr`);

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}
