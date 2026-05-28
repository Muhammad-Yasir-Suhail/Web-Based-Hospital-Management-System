<?php
/**
 * Patients — CRUD Form
 * Handles: list, add, edit, delete via AJAX + full HTML form
 */
require_once __DIR__ . '/../includes/db.php';

// ── AJAX handlers ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn   = getConnection();

    if ($action === 'save') {
        savePatient($conn);
    } elseif ($action === 'delete') {
        deletePatient($conn);
    } else {
        sendJson(['success' => false, 'message' => 'Unknown action.']);
    }
}

// ── Save (insert or update) ────────────────────────
function savePatient(mixed $conn): void {
    $id      = (int)($_POST['patient_no'] ?? 0);
    $name    = trim($_POST['patient_name']  ?? '');
    $dob     = trim($_POST['date_of_birth'] ?? '');
    $age     = (int)($_POST['age'] ?? 0);
    $gender  = $_POST['gender'] ?? '';
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    $careUnit = (int)($_POST['care_unit_no']    ?? 0);
    $doctor   = (int)($_POST['doctor_staff_no'] ?? 0);

    // Required field checks
    if ($name === '')
        sendJson(['success' => false, 'message' => 'Patient name is required.']);
    if (!preg_match('/^[a-zA-Z\s\'\-]+$/', $name))
        sendJson(['success' => false, 'message' => 'Patient name must contain letters only.']);
    if ($dob === '')
        sendJson(['success' => false, 'message' => 'Date of birth is required.']);
    if (strtotime($dob) > time())
        sendJson(['success' => false, 'message' => 'Date of birth cannot be in the future.']);
    if ($age < 0 || $age > 150)
        sendJson(['success' => false, 'message' => 'Age must be between 0 and 150.']);
    if (!in_array($gender, ['M', 'F', 'O']))
        sendJson(['success' => false, 'message' => 'Invalid gender value.']);
    if ($phone !== '' && !preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', $phone))
        sendJson(['success' => false, 'message' => 'Phone must contain digits only (7–20 characters).']);
    if ($address !== '' && preg_match('/^[0-9]+$/', $address))
        sendJson(['success' => false, 'message' => 'Address cannot be numbers only.']);
    if ($careUnit <= 0)
        sendJson(['success' => false, 'message' => 'Please select a care unit.']);
    if ($doctor <= 0)
        sendJson(['success' => false, 'message' => 'Please select an attending doctor.']);

    $params = [$name, $dob, $age, $gender, $phone, $address, $careUnit, $doctor];

    if ($id > 0) {
        // UPDATE
        $params[] = $id;
        $sql = "UPDATE PATIENT SET
                    patient_name=?, date_of_birth=?, age=?, gender=?,
                    phone=?, address=?, care_unit_no=?, doctor_staff_no=?
                WHERE patient_no=?";
        executeQuery($conn, $sql, $params);
        sendJson(['success' => true, 'message' => 'Patient updated successfully.']);
    } else {
        // INSERT — get next id
        $stmt = executeQuery($conn, "SELECT ISNULL(MAX(patient_no),0)+1 AS nid FROM PATIENT");
        $nid  = (int)(fetchOne($stmt)['nid']);

        array_unshift($params, $nid);
        $sql = "INSERT INTO PATIENT
                    (patient_no, patient_name, date_of_birth, age, gender, phone, address, care_unit_no, doctor_staff_no)
                VALUES (?,?,?,?,?,?,?,?,?)";
        executeQuery($conn, $sql, $params);
        sendJson(['success' => true, 'message' => 'Patient added successfully.', 'id' => $nid]);
    }
}

// ── Delete ─────────────────────────────────────────
function deletePatient(mixed $conn): void {
    $id = (int)($_POST['patient_no'] ?? 0);
    if ($id <= 0) sendJson(['success' => false, 'message' => 'Invalid patient ID.']);

    executeQuery($conn, "DELETE FROM ADMISSION          WHERE patient_no=?", [$id]);
    executeQuery($conn, "DELETE FROM PATIENT_COMPLAINT  WHERE patient_no=?", [$id]);
    executeQuery($conn, "DELETE FROM PATIENT            WHERE patient_no=?", [$id]);

    sendJson(['success' => true, 'message' => 'Patient deleted.']);
}

// ── Load data for page ─────────────────────────────
$conn     = getConnection();
$patients = fetchAll(executeQuery($conn,
    "SELECT P.patient_no, P.patient_name, P.date_of_birth, P.age, P.gender, P.phone, P.address,
            CU.unit_label, S.f_name + ' ' + S.l_name AS doctor_name
     FROM PATIENT P
     LEFT JOIN CARE_UNIT CU ON P.care_unit_no = CU.care_unit_no
     LEFT JOIN DOCTOR    D  ON P.doctor_staff_no = D.staff_no
     LEFT JOIN STAFF     S  ON D.staff_no = S.staff_no
     ORDER BY P.patient_no"
));

$careUnits = fetchAll(executeQuery($conn, "SELECT care_unit_no, unit_label FROM CARE_UNIT ORDER BY care_unit_no"));
$doctors   = fetchAll(executeQuery($conn,
    "SELECT D.staff_no, S.f_name + ' ' + S.l_name AS doctor_name
     FROM DOCTOR D JOIN STAFF S ON D.staff_no = S.staff_no ORDER BY S.l_name"
));

$pageTitle = 'Patients';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Flash area -->
<div id="flash-area"></div>

<!-- Toolbar -->
<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" id="patient-search" placeholder="Search patients…" oninput="filterTable(this,'patient-table')">
    </div>
    <button class="btn btn-primary" onclick="openModal('patient-modal')">➕ Add Patient</button>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="patient-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>DOB</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Care Unit</th>
                        <th>Doctor</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= $p['patient_no'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($p['patient_name']) ?></td>
                        <td><?= $p['date_of_birth'] ?></td>
                        <td><?= $p['age'] ?></td>
                        <td><span class="badge <?= $p['gender']==='M' ? 'badge-teal' : 'badge-gold' ?>"><?= $p['gender'] ?></span></td>
                        <td><?= htmlspecialchars($p['phone']) ?></td>
                        <td><?= htmlspecialchars($p['unit_label'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($p['doctor_name'] ?? '—') ?></td>
                        <td>
                            <button class="btn btn-sm btn-secondary"
                                onclick='editPatient(<?= json_encode($p) ?>)'>✏ Edit</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="confirmDelete(<?= $p['patient_no'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="patient-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-title">Add New Patient</span>
            <button class="modal-close" onclick="closeModal('patient-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="patient-form">
                <input type="hidden" name="action"     value="save">
                <input type="hidden" name="patient_no" id="patient_no" value="0">

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Full Name *</label>
                        <input type="text" name="patient_name" id="patient_name" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth *</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" id="age" min="0" max="150">
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" id="gender" required>
                            <option value="M">Male</option>
                            <option value="F">Female</option>
                            <option value="O">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" id="phone">
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <input type="text" name="address" id="address">
                    </div>
                    <div class="form-group">
                        <label>Care Unit *</label>
                        <select name="care_unit_no" id="care_unit_no" required>
                            <option value="">— Select —</option>
                            <?php foreach ($careUnits as $cu): ?>
                            <option value="<?= $cu['care_unit_no'] ?>"><?= htmlspecialchars($cu['unit_label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Attending Doctor *</label>
                        <select name="doctor_staff_no" id="doctor_staff_no" required>
                            <option value="">— Select —</option>
                            <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['staff_no'] ?>"><?= htmlspecialchars($d['doctor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('patient-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="savePatientForm()">💾 Save Patient</button>
        </div>
    </div>
</div>

<!-- Delete confirm modal -->
<div class="modal-overlay" id="delete-modal">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <span class="modal-title">Confirm Delete</span>
            <button class="modal-close" onclick="closeModal('delete-modal')">×</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this patient? This will also remove their admissions and complaints.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('delete-modal')">Cancel</button>
            <button class="btn btn-danger"    id="confirm-delete-btn">Delete</button>
        </div>
    </div>
</div>

<script>
// Populate form for edit
function editPatient(p) {
    document.getElementById('modal-title').textContent   = 'Edit Patient';
    document.getElementById('patient_no').value          = p.patient_no;
    document.getElementById('patient_name').value        = p.patient_name;
    document.getElementById('date_of_birth').value       = p.date_of_birth;
    document.getElementById('age').value                 = p.age;
    document.getElementById('gender').value              = p.gender;
    document.getElementById('phone').value               = p.phone;
    document.getElementById('address').value             = p.address;
    document.getElementById('care_unit_no').value        = p.care_unit_no;
    document.getElementById('doctor_staff_no').value     = p.doctor_staff_no;
    openModal('patient-modal');
}

// Reset form for new patient
document.querySelector('[onclick="openModal(\'patient-modal\')"]')?.addEventListener('click', () => {
    document.getElementById('modal-title').textContent = 'Add New Patient';
    document.getElementById('patient-form').reset();
    document.getElementById('patient_no').value = '0';
});

// Save via AJAX
async function savePatientForm() {
    const name    = document.getElementById('patient_name').value.trim();
    const dob     = document.getElementById('date_of_birth').value;
    const phone   = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();
    const careUnit = document.getElementById('care_unit_no').value;
    const doctor   = document.getElementById('doctor_staff_no').value;

    if (!name) { showFlash('Patient name is required.', 'error'); return; }
    if (!/^[a-zA-Z\s'\-]+$/.test(name)) { showFlash('Patient name must contain letters only.', 'error'); return; }
    if (!dob) { showFlash('Date of birth is required.', 'error'); return; }
    if (new Date(dob) > new Date()) { showFlash('Date of birth cannot be in the future.', 'error'); return; }
    if (phone && !/^[0-9\+\-\s\(\)]{7,20}$/.test(phone)) { showFlash('Phone must be digits only (7–20 characters).', 'error'); return; }
    if (address && /^[0-9]+$/.test(address)) { showFlash('Address cannot be numbers only.', 'error'); return; }
    if (!careUnit) { showFlash('Please select a care unit.', 'error'); return; }
    if (!doctor)   { showFlash('Please select an attending doctor.', 'error'); return; }

    const form = document.getElementById('patient-form');
    await submitForm(form, '<?= BASE_URL ?>/forms/patients.php', () => {
        closeModal('patient-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

// Delete confirmation
function confirmDelete(id) {
    document.getElementById('confirm-delete-btn').onclick = async () => {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('patient_no', id);
        const resp = await fetch('<?= BASE_URL ?>/forms/patients.php', { method:'POST', body: fd });
        const json = await resp.json();
        closeModal('delete-modal');
        showFlash(json.message, json.success ? 'success' : 'error');
        if (json.success) setTimeout(() => location.reload(), 1200);
    };
    openModal('delete-modal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
