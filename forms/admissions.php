<?php
/**
 * Admissions — CRUD Form
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    if ($action === 'admit') {
        admitPatient($conn);
    } elseif ($action === 'discharge') {
        dischargePatient($conn);
    } else {
        sendJson(['success' => false, 'message' => 'Unknown action.']);
    }
}

function admitPatient(mixed $conn): void {
    $patientNo    = (int)($_POST['patient_no']    ?? 0);
    $bedNo        = (int)($_POST['bed_no']        ?? 0);
    $dateAdmitted = $_POST['date_admitted']       ?? '';

    if (!$patientNo || !$bedNo || !$dateAdmitted) {
        sendJson(['success' => false, 'message' => 'All fields are required.']);
    }

    // Check bed is available
    $bedRow = fetchOne(executeQuery($conn, "SELECT bed_status FROM BED WHERE bed_no=?", [$bedNo]));
    if (!$bedRow || $bedRow['bed_status'] !== 'Available') {
        sendJson(['success' => false, 'message' => 'Selected bed is not available.']);
    }

    executeQuery($conn,
        "INSERT INTO ADMISSION (patient_no, bed_no, date_admitted) VALUES (?,?,?)",
        [$patientNo, $bedNo, $dateAdmitted]
    );
    executeQuery($conn, "UPDATE BED SET bed_status='Occupied' WHERE bed_no=?", [$bedNo]);

    sendJson(['success' => true, 'message' => 'Patient admitted successfully.']);
}

function dischargePatient(mixed $conn): void {
    $patientNo    = (int)($_POST['patient_no']    ?? 0);
    $bedNo        = (int)($_POST['bed_no']        ?? 0);
    $dateAdmitted = $_POST['date_admitted']       ?? '';
    $dateDischarged = date('Y-m-d');

    executeQuery($conn,
        "UPDATE ADMISSION SET date_discharged=? WHERE patient_no=? AND bed_no=? AND date_admitted=?",
        [$dateDischarged, $patientNo, $bedNo, $dateAdmitted]
    );
    executeQuery($conn, "UPDATE BED SET bed_status='Available' WHERE bed_no=?", [$bedNo]);

    sendJson(['success' => true, 'message' => 'Patient discharged successfully.']);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

$admissions = fetchAll(executeQuery($conn,
    "SELECT A.patient_no, A.bed_no, A.date_admitted, A.date_discharged,
            P.patient_name, W.ward_name, B.bed_status
     FROM ADMISSION A
     JOIN PATIENT P ON A.patient_no = P.patient_no
     JOIN BED     B ON A.bed_no     = B.bed_no
     JOIN WARD    W ON B.ward_id    = W.ward_id
     ORDER BY A.date_admitted DESC"
));

$patients = fetchAll(executeQuery($conn, "SELECT patient_no, patient_name FROM PATIENT ORDER BY patient_name"));
$availBeds = fetchAll(executeQuery($conn,
    "SELECT B.bed_no, W.ward_name
     FROM BED B JOIN WARD W ON B.ward_id = W.ward_id
     WHERE B.bed_status='Available'
     ORDER BY B.bed_no"
));

$pageTitle = 'Admissions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search admissions…" oninput="filterTable(this,'admissions-table')">
    </div>
    <button class="btn btn-primary" onclick="openModal('admit-modal')">🛏 Admit Patient</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="admissions-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Ward</th>
                        <th>Bed #</th>
                        <th>Date Admitted</th>
                        <th>Date Discharged</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admissions as $a): ?>
                    <tr>
                        <td class="fw-600"><?= htmlspecialchars($a['patient_name']) ?></td>
                        <td><?= htmlspecialchars($a['ward_name']) ?></td>
                        <td><?= $a['bed_no'] ?></td>
                        <td><?= $a['date_admitted'] ?></td>
                        <td><?= $a['date_discharged'] ?: '—' ?></td>
                        <td>
                            <?php if (!$a['date_discharged']): ?>
                                <span class="badge badge-green">Active</span>
                            <?php else: ?>
                                <span class="badge badge-navy">Discharged</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$a['date_discharged']): ?>
                            <button class="btn btn-sm btn-gold"
                                onclick="confirmDischarge(<?= $a['patient_no'] ?>, <?= $a['bed_no'] ?>, '<?= $a['date_admitted'] ?>')">
                                ✓ Discharge
                            </button>
                            <?php else: ?>
                            <span class="text-muted text-sm">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Admit Modal -->
<div class="modal-overlay" id="admit-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Admit Patient</span>
            <button class="modal-close" onclick="closeModal('admit-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="admit-form">
                <input type="hidden" name="action" value="admit">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Patient *</label>
                        <select name="patient_no" required>
                            <option value="">— Select Patient —</option>
                            <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['patient_no'] ?>"><?= htmlspecialchars($p['patient_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Available Bed *</label>
                        <select name="bed_no" required>
                            <option value="">— Select Bed —</option>
                            <?php foreach ($availBeds as $b): ?>
                            <option value="<?= $b['bed_no'] ?>">Bed <?= $b['bed_no'] ?> — <?= htmlspecialchars($b['ward_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date Admitted *</label>
                        <input type="date" name="date_admitted" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('admit-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="admitPatient()">🛏 Admit</button>
        </div>
    </div>
</div>

<script>
async function admitPatient() {
    const form = document.getElementById('admit-form');
    await submitForm(form, '<?= BASE_URL ?>/forms/admissions.php', () => {
        closeModal('admit-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function confirmDischarge(patientNo, bedNo, dateAdmitted) {
    if (!confirm('Discharge this patient today?')) return;
    const fd = new FormData();
    fd.append('action',        'discharge');
    fd.append('patient_no',    patientNo);
    fd.append('bed_no',        bedNo);
    fd.append('date_admitted', dateAdmitted);
    const resp = await fetch('<?= BASE_URL ?>/forms/admissions.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
