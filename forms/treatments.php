<?php
/**
 * Treatment History — CRUD Form
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    if ($action === 'save')   saveTreatment($conn);
    elseif ($action === 'delete') deleteTreatment($conn);
    else sendJson(['success' => false, 'message' => 'Unknown action.']);
}

function saveTreatment(mixed $conn): void {
    $id          = (int)($_POST['treatment_history_id'] ?? 0);
    $complaintId = (int)($_POST['patient_complaint_id'] ?? 0);
    $trtCode     = trim($_POST['treatment_code']        ?? '');
    $doctorNo    = (int)($_POST['doctor_staff_no']      ?? 0);
    $startDate   = trim($_POST['treatment_start_date']  ?? '');
    $endDate     = $_POST['treatment_end_date'] ?: null;
    $notes       = trim($_POST['notes'] ?? '');

    if ($complaintId <= 0)
        sendJson(['success' => false, 'message' => 'Please select a complaint.']);
    if ($trtCode === '')
        sendJson(['success' => false, 'message' => 'Please select a treatment.']);
    if ($doctorNo <= 0)
        sendJson(['success' => false, 'message' => 'Please select a doctor.']);
    if ($startDate === '')
        sendJson(['success' => false, 'message' => 'Treatment start date is required.']);
    if ($endDate !== null && strtotime($endDate) < strtotime($startDate))
        sendJson(['success' => false, 'message' => 'End date cannot be before start date.']);

    $params = [$complaintId, $trtCode, $doctorNo, $startDate, $endDate, $notes];

    if ($id > 0) {
        $params[] = $id;
        executeQuery($conn,
            "UPDATE TREATMENT_HISTORY SET patient_complaint_id=?, treatment_code=?, doctor_staff_no=?,
             treatment_start_date=?, treatment_end_date=?, notes=? WHERE treatment_history_id=?",
            $params
        );
        sendJson(['success' => true, 'message' => 'Treatment updated.']);
    } else {
        $stmt = executeQuery($conn, "SELECT ISNULL(MAX(treatment_history_id),0)+1 AS nid FROM TREATMENT_HISTORY");
        $nid  = (int)(fetchOne($stmt)['nid']);
        array_unshift($params, $nid);
        executeQuery($conn,
            "INSERT INTO TREATMENT_HISTORY
             (treatment_history_id, patient_complaint_id, treatment_code, doctor_staff_no,
              treatment_start_date, treatment_end_date, notes)
             VALUES (?,?,?,?,?,?,?)",
            $params
        );
        sendJson(['success' => true, 'message' => 'Treatment logged.', 'id' => $nid]);
    }
}

function deleteTreatment(mixed $conn): void {
    $id = (int)($_POST['treatment_history_id'] ?? 0);
    executeQuery($conn, "DELETE FROM TREATMENT_HISTORY WHERE treatment_history_id=?", [$id]);
    sendJson(['success' => true, 'message' => 'Treatment deleted.']);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

$treatments = fetchAll(executeQuery($conn,
    "SELECT TH.treatment_history_id, TH.patient_complaint_id, TH.treatment_code,
            TH.doctor_staff_no, TH.treatment_start_date, TH.treatment_end_date, TH.notes,
            P.patient_name, C.complaint_name, T.treatment_name,
            S.f_name + ' ' + S.l_name AS doctor_name
     FROM TREATMENT_HISTORY TH
     JOIN PATIENT_COMPLAINT PC ON TH.patient_complaint_id = PC.patient_complaint_id
     JOIN PATIENT   P  ON PC.patient_no     = P.patient_no
     JOIN COMPLAINT C  ON PC.complaint_code = C.complaint_code
     JOIN TREATMENT T  ON TH.treatment_code = T.treatment_code
     JOIN DOCTOR    D  ON TH.doctor_staff_no = D.staff_no
     JOIN STAFF     S  ON D.staff_no         = S.staff_no
     ORDER BY TH.treatment_start_date DESC"
));

$patientComplaints = fetchAll(executeQuery($conn,
    "SELECT PC.patient_complaint_id, P.patient_name, C.complaint_name
     FROM PATIENT_COMPLAINT PC
     JOIN PATIENT   P ON PC.patient_no     = P.patient_no
     JOIN COMPLAINT C ON PC.complaint_code = C.complaint_code
     WHERE PC.status != 'Resolved'
     ORDER BY P.patient_name"
));

$treatmentCodes = fetchAll(executeQuery($conn, "SELECT treatment_code, treatment_name FROM TREATMENT ORDER BY treatment_name"));
$doctors        = fetchAll(executeQuery($conn,
    "SELECT D.staff_no, S.f_name + ' ' + S.l_name AS doctor_name
     FROM DOCTOR D JOIN STAFF S ON D.staff_no = S.staff_no ORDER BY S.l_name"
));

$pageTitle = 'Treatment History';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search treatments…" oninput="filterTable(this,'treatments-table')">
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">💊 Log Treatment</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="treatments-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Complaint</th>
                        <th>Treatment</th>
                        <th>Doctor</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($treatments as $t): ?>
                    <tr>
                        <td><?= $t['treatment_history_id'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($t['patient_name']) ?></td>
                        <td><?= htmlspecialchars($t['complaint_name']) ?></td>
                        <td><?= htmlspecialchars($t['treatment_name']) ?></td>
                        <td><?= htmlspecialchars($t['doctor_name']) ?></td>
                        <td><?= $t['treatment_start_date'] ?></td>
                        <td><?= $t['treatment_end_date'] ?: '—' ?></td>
                        <td class="btn-group">
                            <button class="btn btn-sm btn-secondary"
                                onclick='editTreatment(<?= json_encode($t) ?>)'>✏</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteTreatmentRow(<?= $t['treatment_history_id'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="treatment-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="t-modal-title">Log Treatment</span>
            <button class="modal-close" onclick="closeModal('treatment-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="treatment-form">
                <input type="hidden" name="action"               value="save">
                <input type="hidden" name="treatment_history_id" id="th_id" value="0">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Patient Complaint *</label>
                        <select name="patient_complaint_id" id="th_complaint" required>
                            <option value="">— Select —</option>
                            <?php foreach ($patientComplaints as $pc): ?>
                            <option value="<?= $pc['patient_complaint_id'] ?>">
                                <?= htmlspecialchars($pc['patient_name'] . ' — ' . $pc['complaint_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Treatment *</label>
                        <select name="treatment_code" id="th_code" required>
                            <option value="">— Select —</option>
                            <?php foreach ($treatmentCodes as $tc): ?>
                            <option value="<?= $tc['treatment_code'] ?>"><?= htmlspecialchars($tc['treatment_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Doctor *</label>
                        <select name="doctor_staff_no" id="th_doctor" required>
                            <option value="">— Select —</option>
                            <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['staff_no'] ?>"><?= htmlspecialchars($d['doctor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="treatment_start_date" id="th_start" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="treatment_end_date" id="th_end">
                    </div>
                    <div class="form-group full-width">
                        <label>Notes</label>
                        <textarea name="notes" id="th_notes" rows="3" placeholder="Clinical notes…"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('treatment-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="saveTreatmentForm()">💾 Save</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('t-modal-title').textContent = 'Log Treatment';
    document.getElementById('treatment-form').reset();
    document.getElementById('th_id').value    = '0';
    document.getElementById('th_start').value = new Date().toISOString().slice(0,10);
    openModal('treatment-modal');
}

function editTreatment(t) {
    document.getElementById('t-modal-title').textContent  = 'Edit Treatment';
    document.getElementById('th_id').value       = t.treatment_history_id;
    document.getElementById('th_complaint').value = t.patient_complaint_id;
    document.getElementById('th_code').value     = t.treatment_code;
    document.getElementById('th_doctor').value   = t.doctor_staff_no;
    document.getElementById('th_start').value    = t.treatment_start_date;
    document.getElementById('th_end').value      = t.treatment_end_date || '';
    document.getElementById('th_notes').value    = t.notes || '';
    openModal('treatment-modal');
}

async function saveTreatmentForm() {
    const complaint = document.getElementById('t_complaint').value;
    const treatment = document.getElementById('t_code').value;
    const doctor    = document.getElementById('t_doctor').value;
    const startDate = document.getElementById('t_start').value;
    const endDate   = document.getElementById('t_end').value;

    if (!complaint) { showFlash('Please select a complaint.', 'error'); return; }
    if (!treatment) { showFlash('Please select a treatment.', 'error'); return; }
    if (!doctor)    { showFlash('Please select a doctor.', 'error'); return; }
    if (!startDate) { showFlash('Start date is required.', 'error'); return; }
    if (endDate && new Date(endDate) < new Date(startDate)) {
        showFlash('End date cannot be before start date.', 'error'); return;
    }
    await submitForm(document.getElementById('treatment-form'), '<?= BASE_URL ?>/forms/treatments.php', () => {
        closeModal('treatment-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function deleteTreatmentRow(id) {
    if (!confirm('Delete this treatment record?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('treatment_history_id', id);
    const resp = await fetch('<?= BASE_URL ?>/forms/treatments.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
