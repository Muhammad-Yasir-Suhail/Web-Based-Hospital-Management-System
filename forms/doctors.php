<?php
/**
 * Doctors — CRUD (also links to consultant team record form)
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    match($action) {
        'save'   => saveDoctor($conn),
        'delete' => deleteDoctor($conn),
        default  => sendJson(['success' => false, 'message' => 'Unknown action.']),
    };
}

function saveDoctor(mixed $conn): void {
    $staffNo    = (int)($_POST['staff_no']   ?? 0);
    $fName      = trim($_POST['f_name']      ?? '');
    $mName      = trim($_POST['m_name']      ?? '');
    $lName      = trim($_POST['l_name']      ?? '');
    $posCode    = trim($_POST['position_code']    ?? '');
    $consultNo  = (int)($_POST['consultant_staff_no'] ?? 0) ?: null;
    $dateJoined = trim($_POST['date_joined_team'] ?? '');

    if ($fName === '')
        sendJson(['success' => false, 'message' => 'First name is required.']);
    if (!preg_match('/^[a-zA-Z\s\'\-]+$/', $fName))
        sendJson(['success' => false, 'message' => 'First name must contain letters only.']);
    if ($mName !== '' && !preg_match('/^[a-zA-Z\s\'\-]+$/', $mName))
        sendJson(['success' => false, 'message' => 'Middle name must contain letters only.']);
    if ($lName === '')
        sendJson(['success' => false, 'message' => 'Last name is required.']);
    if (!preg_match('/^[a-zA-Z\s\'\-]+$/', $lName))
        sendJson(['success' => false, 'message' => 'Last name must contain letters only.']);
    if ($posCode === '')
        sendJson(['success' => false, 'message' => 'Position is required.']);
    if ($dateJoined === '')
        sendJson(['success' => false, 'message' => 'Date joined is required.']);
    if (strtotime($dateJoined) > time())
        sendJson(['success' => false, 'message' => 'Date joined cannot be in the future.']);

    $isNew = ($staffNo <= 0);

    if ($isNew) {
        // Get next staff_no
        $stmt    = executeQuery($conn, "SELECT ISNULL(MAX(staff_no),0)+1 AS nid FROM STAFF");
        $staffNo = (int)(fetchOne($stmt)['nid']);
    }

    if ($isNew) {
        executeQuery($conn, "INSERT INTO STAFF (staff_no,f_name,m_name,l_name) VALUES (?,?,?,?)",
            [$staffNo, $fName, $mName, $lName]);
        executeQuery($conn,
            "INSERT INTO DOCTOR (staff_no, position_code, consultant_staff_no, date_joined_team) VALUES (?,?,?,?)",
            [$staffNo, $posCode, $consultNo, $dateJoined]
        );
        sendJson(['success' => true, 'message' => 'Doctor added.', 'id' => $staffNo]);
    } else {
        executeQuery($conn, "UPDATE STAFF SET f_name=?, m_name=?, l_name=? WHERE staff_no=?",
            [$fName, $mName, $lName, $staffNo]);
        executeQuery($conn,
            "UPDATE DOCTOR SET position_code=?, consultant_staff_no=?, date_joined_team=? WHERE staff_no=?",
            [$posCode, $consultNo, $dateJoined, $staffNo]
        );
        sendJson(['success' => true, 'message' => 'Doctor updated.']);
    }
}

function deleteDoctor(mixed $conn): void {
    $id = (int)($_POST['staff_no'] ?? 0);
    // Only non-patient-linked, non-consultant doctors can be cleanly deleted
    executeQuery($conn, "DELETE FROM PERFORMANCE_REVIEW  WHERE doctor_staff_no=?", [$id]);
    executeQuery($conn, "DELETE FROM EXPERIENCE_HISTORY  WHERE doctor_staff_no=?", [$id]);
    executeQuery($conn, "DELETE FROM TREATMENT_HISTORY   WHERE doctor_staff_no=?", [$id]);
    executeQuery($conn, "DELETE FROM DOCTOR              WHERE staff_no=?",         [$id]);
    executeQuery($conn, "DELETE FROM STAFF               WHERE staff_no=?",         [$id]);
    sendJson(['success' => true, 'message' => 'Doctor deleted.']);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

$doctors = fetchAll(executeQuery($conn,
    "SELECT D.staff_no, S.f_name, S.m_name, S.l_name,
            S.f_name + ' ' + S.l_name AS full_name,
            P.position_name, D.position_code, D.consultant_staff_no, D.date_joined_team,
            CS.f_name + ' ' + CS.l_name AS consultant_name
     FROM DOCTOR D
     JOIN STAFF    S  ON D.staff_no          = S.staff_no
     JOIN POSITION P  ON D.position_code     = P.position_code
     LEFT JOIN CONSULTANT C ON D.consultant_staff_no = C.staff_no
     LEFT JOIN STAFF     CS ON C.staff_no    = CS.staff_no
     ORDER BY S.l_name"
));

$positions   = fetchAll(executeQuery($conn, "SELECT position_code, position_name FROM POSITION ORDER BY position_name"));
$consultants = fetchAll(executeQuery($conn,
    "SELECT C.staff_no, S.f_name + ' ' + S.l_name AS cname
     FROM CONSULTANT C JOIN STAFF S ON C.staff_no = S.staff_no ORDER BY S.l_name"
));

$pageTitle = 'Doctors';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search doctors…" oninput="filterTable(this,'doctors-table')">
    </div>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/forms/experience.php"  class="btn btn-secondary">📜 Experience</a>
        <a href="<?= BASE_URL ?>/forms/performance.php" class="btn btn-secondary">📊 Performance</a>
        <button class="btn btn-primary" onclick="openAddModal()">➕ Add Doctor</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="doctors-table">
                <thead>
                    <tr>
                        <th>Staff #</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Under Consultant</th>
                        <th>Date Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $d): ?>
                    <tr>
                        <td><?= $d['staff_no'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($d['full_name']) ?></td>
                        <td><span class="badge badge-teal"><?= htmlspecialchars($d['position_name']) ?></span></td>
                        <td><?= htmlspecialchars($d['consultant_name'] ?? '—') ?></td>
                        <td><?= $d['date_joined_team'] ?></td>
                        <td class="btn-group">
                            <button class="btn btn-sm btn-secondary"
                                onclick='editDoctor(<?= json_encode($d) ?>)'>✏ Edit</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteDoctor(<?= $d['staff_no'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="doctor-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="d-modal-title">Add Doctor</span>
            <button class="modal-close" onclick="closeModal('doctor-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="doctor-form">
                <input type="hidden" name="action"   value="save">
                <input type="hidden" name="staff_no" id="d_staff_no" value="0">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="f_name" id="d_fname" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="m_name" id="d_mname">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="l_name" id="d_lname" required>
                    </div>
                    <div class="form-group">
                        <label>Position *</label>
                        <select name="position_code" id="d_pos" required>
                            <option value="">— Select —</option>
                            <?php foreach ($positions as $p): ?>
                            <option value="<?= $p['position_code'] ?>"><?= htmlspecialchars($p['position_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Consultant (Team Leader)</label>
                        <select name="consultant_staff_no" id="d_consultant">
                            <option value="">— None —</option>
                            <?php foreach ($consultants as $c): ?>
                            <option value="<?= $c['staff_no'] ?>"><?= htmlspecialchars($c['cname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date Joined Team *</label>
                        <input type="date" name="date_joined_team" id="d_joined" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('doctor-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="saveDoctorForm()">💾 Save</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('d-modal-title').textContent = 'Add Doctor';
    document.getElementById('doctor-form').reset();
    document.getElementById('d_staff_no').value = '0';
    document.getElementById('d_joined').value   = new Date().toISOString().slice(0,10);
    openModal('doctor-modal');
}

function editDoctor(d) {
    document.getElementById('d-modal-title').textContent = 'Edit Doctor';
    document.getElementById('d_staff_no').value   = d.staff_no;
    document.getElementById('d_fname').value       = d.f_name;
    document.getElementById('d_mname').value       = d.m_name || '';
    document.getElementById('d_lname').value       = d.l_name;
    document.getElementById('d_pos').value         = d.position_code;
    document.getElementById('d_consultant').value  = d.consultant_staff_no || '';
    document.getElementById('d_joined').value      = d.date_joined_team;
    openModal('doctor-modal');
}

async function saveDoctorForm() {
    const fName   = document.getElementById('d_fname').value.trim();
    const mName   = document.getElementById('d_mname').value.trim();
    const lName   = document.getElementById('d_lname').value.trim();
    const pos     = document.getElementById('d_pos').value;
    const joined  = document.getElementById('d_joined').value;
    const nameRx  = /^[a-zA-Z\s'\-]+$/;

    if (!fName)           { showFlash('First name is required.', 'error'); return; }
    if (!nameRx.test(fName)) { showFlash('First name must contain letters only.', 'error'); return; }
    if (mName && !nameRx.test(mName)) { showFlash('Middle name must contain letters only.', 'error'); return; }
    if (!lName)           { showFlash('Last name is required.', 'error'); return; }
    if (!nameRx.test(lName)) { showFlash('Last name must contain letters only.', 'error'); return; }
    if (!pos)             { showFlash('Position is required.', 'error'); return; }
    if (!joined)          { showFlash('Date joined is required.', 'error'); return; }
    if (new Date(joined) > new Date()) { showFlash('Date joined cannot be in the future.', 'error'); return; }

    await submitForm(document.getElementById('doctor-form'), '<?= BASE_URL ?>/forms/doctors.php', () => {
        closeModal('doctor-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function deleteDoctor(id) {
    if (!confirm('Delete this doctor? All linked experience and performance records will be removed.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('staff_no', id);
    const resp = await fetch('<?= BASE_URL ?>/forms/doctors.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
