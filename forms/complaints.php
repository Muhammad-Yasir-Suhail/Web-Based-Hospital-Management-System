<?php
/**
 * Patient Complaints — CRUD Form
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        saveComplaint($conn);
    } elseif ($action === 'delete') {
        deleteComplaint($conn);
    } elseif ($action === 'update_status') {
        updateStatus($conn);
    } else {
        sendJson(['success' => false, 'message' => 'Unknown action.']);
    }
}

function saveComplaint(mixed $conn): void {
    $id         = (int)($_POST['patient_complaint_id'] ?? 0);
    $patientNo  = (int)($_POST['patient_no']        ?? 0);
    $compCode   = trim($_POST['complaint_code']     ?? '');
    $startDate  = trim($_POST['complaint_start_date'] ?? '');
    $endDate    = $_POST['complaint_end_date'] ?: null;
    $status     = trim($_POST['status']        ?? 'Active');

    if ($patientNo <= 0)
        sendJson(['success' => false, 'message' => 'Please select a patient.']);
    if ($compCode === '')
        sendJson(['success' => false, 'message' => 'Please select a complaint.']);
    if ($startDate === '')
        sendJson(['success' => false, 'message' => 'Start date is required.']);
    if ($endDate !== null && strtotime($endDate) < strtotime($startDate))
        sendJson(['success' => false, 'message' => 'End date cannot be before start date.']);
    if (!in_array($status, ['Active', 'Resolved', 'Critical']))
        sendJson(['success' => false, 'message' => 'Invalid status value.']);

    $params = [$patientNo, $compCode, $startDate, $endDate, $status];

    if ($id > 0) {
        $params[] = $id;
        executeQuery($conn,
            "UPDATE PATIENT_COMPLAINT SET patient_no=?, complaint_code=?, complaint_start_date=?,
             complaint_end_date=?, status=? WHERE patient_complaint_id=?",
            $params
        );
        sendJson(['success' => true, 'message' => 'Complaint updated.']);
    } else {
        $stmt = executeQuery($conn, "SELECT ISNULL(MAX(patient_complaint_id),0)+1 AS nid FROM PATIENT_COMPLAINT");
        $nid  = (int)(fetchOne($stmt)['nid']);
        array_unshift($params, $nid);
        executeQuery($conn,
            "INSERT INTO PATIENT_COMPLAINT (patient_complaint_id, patient_no, complaint_code,
             complaint_start_date, complaint_end_date, status) VALUES (?,?,?,?,?,?)",
            $params
        );
        sendJson(['success' => true, 'message' => 'Complaint logged.', 'id' => $nid]);
    }
}

function deleteComplaint(mixed $conn): void {
    $id = (int)($_POST['patient_complaint_id'] ?? 0);
    executeQuery($conn, "DELETE FROM TREATMENT_HISTORY WHERE patient_complaint_id=?", [$id]);
    executeQuery($conn, "DELETE FROM PATIENT_COMPLAINT  WHERE patient_complaint_id=?", [$id]);
    sendJson(['success' => true, 'message' => 'Complaint deleted.']);
}

function updateStatus(mixed $conn): void {
    $id     = (int)($_POST['patient_complaint_id'] ?? 0);
    $status = $_POST['status'] ?? 'Active';
    $end    = ($status === 'Resolved') ? date('Y-m-d') : null;
    executeQuery($conn,
        "UPDATE PATIENT_COMPLAINT SET status=?, complaint_end_date=? WHERE patient_complaint_id=?",
        [$status, $end, $id]
    );
    sendJson(['success' => true, 'message' => "Status updated to $status."]);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

$complaints = fetchAll(executeQuery($conn,
    "SELECT PC.patient_complaint_id, PC.patient_no, PC.complaint_code,
            PC.complaint_start_date, PC.complaint_end_date, PC.status,
            P.patient_name, C.complaint_name
     FROM PATIENT_COMPLAINT PC
     JOIN PATIENT   P ON PC.patient_no      = P.patient_no
     JOIN COMPLAINT C ON PC.complaint_code  = C.complaint_code
     ORDER BY PC.complaint_start_date DESC"
));

$patients        = fetchAll(executeQuery($conn, "SELECT patient_no, patient_name FROM PATIENT ORDER BY patient_name"));
$complaintCodes  = fetchAll(executeQuery($conn, "SELECT complaint_code, complaint_name FROM COMPLAINT ORDER BY complaint_name"));

$pageTitle = 'Patient Complaints';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search complaints…" oninput="filterTable(this,'complaints-table')">
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">📋 Log Complaint</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="complaints-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Complaint</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $c): ?>
                    <?php
                    $badgeClass = match($c['status']) {
                        'Active'   => 'badge-green',
                        'Critical' => 'badge-red',
                        'Resolved' => 'badge-navy',
                        default    => 'badge-teal',
                    };
                    ?>
                    <tr>
                        <td><?= $c['patient_complaint_id'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($c['patient_name']) ?></td>
                        <td><?= htmlspecialchars($c['complaint_name']) ?></td>
                        <td><?= $c['complaint_start_date'] ?></td>
                        <td><?= $c['complaint_end_date'] ?: '—' ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $c['status'] ?></span></td>
                        <td class="btn-group">
                            <button class="btn btn-sm btn-secondary"
                                onclick='editComplaint(<?= json_encode($c) ?>)'>✏</button>
                            <?php if ($c['status'] !== 'Resolved'): ?>
                            <button class="btn btn-sm btn-gold"
                                onclick="resolveComplaint(<?= $c['patient_complaint_id'] ?>)">✓ Resolve</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteComplaint(<?= $c['patient_complaint_id'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="complaint-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="c-modal-title">Log Complaint</span>
            <button class="modal-close" onclick="closeModal('complaint-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="complaint-form">
                <input type="hidden" name="action"               value="save">
                <input type="hidden" name="patient_complaint_id" id="c_id" value="0">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Patient *</label>
                        <select name="patient_no" id="c_patient" required>
                            <option value="">— Select —</option>
                            <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['patient_no'] ?>"><?= htmlspecialchars($p['patient_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Complaint *</label>
                        <select name="complaint_code" id="c_code" required>
                            <option value="">— Select —</option>
                            <?php foreach ($complaintCodes as $cc): ?>
                            <option value="<?= $cc['complaint_code'] ?>"><?= htmlspecialchars($cc['complaint_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="c_status">
                            <option value="Active">Active</option>
                            <option value="Critical">Critical</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="complaint_start_date" id="c_start" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="complaint_end_date" id="c_end">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('complaint-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="saveComplaintForm()">💾 Save</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('c-modal-title').textContent = 'Log Complaint';
    document.getElementById('complaint-form').reset();
    document.getElementById('c_id').value = '0';
    document.getElementById('c_start').value = new Date().toISOString().slice(0,10);
    openModal('complaint-modal');
}

function editComplaint(c) {
    document.getElementById('c-modal-title').textContent = 'Edit Complaint';
    document.getElementById('c_id').value      = c.patient_complaint_id;
    document.getElementById('c_patient').value = c.patient_no;
    document.getElementById('c_code').value    = c.complaint_code;
    document.getElementById('c_status').value  = c.status;
    document.getElementById('c_start').value   = c.complaint_start_date;
    document.getElementById('c_end').value     = c.complaint_end_date || '';
    openModal('complaint-modal');
}

async function saveComplaintForm() {
    const patient   = document.getElementById('c_patient').value;
    const complaint = document.getElementById('c_code').value;
    const startDate = document.getElementById('c_start').value;
    const endDate   = document.getElementById('c_end').value;

    if (!patient)   { showFlash('Please select a patient.', 'error'); return; }
    if (!complaint) { showFlash('Please select a complaint.', 'error'); return; }
    if (!startDate) { showFlash('Start date is required.', 'error'); return; }
    if (endDate && new Date(endDate) < new Date(startDate)) {
        showFlash('End date cannot be before start date.', 'error'); return;
    }
    await submitForm(document.getElementById('complaint-form'), '<?= BASE_URL ?>/forms/complaints.php', () => {
        closeModal('complaint-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function resolveComplaint(id) {
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('patient_complaint_id', id);
    fd.append('status', 'Resolved');
    const resp = await fetch('<?= BASE_URL ?>/forms/complaints.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}

async function deleteComplaint(id) {
    if (!confirm('Delete this complaint and its treatments?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('patient_complaint_id', id);
    const resp = await fetch('<?= BASE_URL ?>/forms/complaints.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
