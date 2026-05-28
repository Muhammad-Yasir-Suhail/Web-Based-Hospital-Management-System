<?php
/**
 * Experience History — CRUD (Consultant Team Record: Previous Experience section)
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    match($action) {
        'save'   => saveExp($conn),
        'delete' => deleteExp($conn),
        default  => sendJson(['success' => false, 'message' => 'Unknown action.']),
    };
}

function saveExp(mixed $conn): void {
    $id           = (int)($_POST['exp_id'] ?? 0);
    $fromDate     = trim($_POST['from_date']     ?? '');
    $toDate       = $_POST['to_date'] ?: null;
    $positionHeld = trim($_POST['position_held'] ?? '');
    $establishment = trim($_POST['establishment'] ?? '');
    $doctorNo     = (int)($_POST['doctor_staff_no'] ?? 0);

    if ($doctorNo <= 0)
        sendJson(['success' => false, 'message' => 'Please select a doctor.']);
    if ($fromDate === '')
        sendJson(['success' => false, 'message' => 'From date is required.']);
    if ($toDate !== null && strtotime($toDate) < strtotime($fromDate))
        sendJson(['success' => false, 'message' => 'To date cannot be before from date.']);
    if ($positionHeld === '')
        sendJson(['success' => false, 'message' => 'Position held is required.']);
    if (!preg_match('/^[a-zA-Z\s\'\-]+$/', $positionHeld))
        sendJson(['success' => false, 'message' => 'Position held must contain letters only.']);
    if ($establishment === '')
        sendJson(['success' => false, 'message' => 'Establishment is required.']);

    $params = [$fromDate, $toDate, $positionHeld, $establishment, $doctorNo];

    if ($id > 0) {
        $params[] = $id;
        executeQuery($conn,
            "UPDATE EXPERIENCE_HISTORY SET from_date=?, to_date=?, position_held=?, establishment=?, doctor_staff_no=?
             WHERE exp_id=?",
            $params
        );
        sendJson(['success' => true, 'message' => 'Experience updated.']);
    } else {
        $stmt = executeQuery($conn, "SELECT ISNULL(MAX(exp_id),0)+1 AS nid FROM EXPERIENCE_HISTORY");
        $nid  = (int)(fetchOne($stmt)['nid']);
        array_unshift($params, $nid);
        executeQuery($conn,
            "INSERT INTO EXPERIENCE_HISTORY (exp_id, from_date, to_date, position_held, establishment, doctor_staff_no)
             VALUES (?,?,?,?,?,?)",
            $params
        );
        sendJson(['success' => true, 'message' => 'Experience added.', 'id' => $nid]);
    }
}

function deleteExp(mixed $conn): void {
    $id = (int)($_POST['exp_id'] ?? 0);
    executeQuery($conn, "DELETE FROM EXPERIENCE_HISTORY WHERE exp_id=?", [$id]);
    sendJson(['success' => true, 'message' => 'Record deleted.']);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

// Filter by doctor if requested
$filterDoctor = (int)($_GET['doctor_staff_no'] ?? 0);

$sql = "SELECT EH.exp_id, EH.from_date, EH.to_date, EH.position_held, EH.establishment,
               EH.doctor_staff_no, S.f_name + ' ' + S.l_name AS doctor_name
        FROM EXPERIENCE_HISTORY EH
        JOIN STAFF S ON EH.doctor_staff_no = S.staff_no";
$params = [];
if ($filterDoctor > 0) {
    $sql .= " WHERE EH.doctor_staff_no=?";
    $params[] = $filterDoctor;
}
$sql .= " ORDER BY EH.from_date DESC";

$experiences = fetchAll(executeQuery($conn, $sql, $params));

$doctors = fetchAll(executeQuery($conn,
    "SELECT D.staff_no, S.f_name + ' ' + S.l_name AS dname FROM DOCTOR D JOIN STAFF S ON D.staff_no=S.staff_no ORDER BY S.l_name"
));

$pageTitle = 'Experience History';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search experience…" oninput="filterTable(this,'exp-table')">
        <select id="doc-filter" onchange="applyDoctorFilter()" style="max-width:240px">
            <option value="0">All Doctors</option>
            <?php foreach ($doctors as $d): ?>
            <option value="<?= $d['staff_no'] ?>" <?= $filterDoctor == $d['staff_no'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['dname']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">📜 Add Experience</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="exp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Doctor</th>
                        <th>Position Held</th>
                        <th>Establishment</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($experiences as $e): ?>
                    <tr>
                        <td><?= $e['exp_id'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($e['doctor_name']) ?></td>
                        <td><?= htmlspecialchars($e['position_held']) ?></td>
                        <td><?= htmlspecialchars($e['establishment']) ?></td>
                        <td><?= $e['from_date'] ?></td>
                        <td><?= $e['to_date'] ?: 'Present' ?></td>
                        <td class="btn-group">
                            <button class="btn btn-sm btn-secondary"
                                onclick='editExp(<?= json_encode($e) ?>)'>✏</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteExpRow(<?= $e['exp_id'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="exp-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="e-modal-title">Add Experience</span>
            <button class="modal-close" onclick="closeModal('exp-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="exp-form">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="exp_id" id="e_id" value="0">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Doctor *</label>
                        <select name="doctor_staff_no" id="e_doctor" required>
                            <option value="">— Select —</option>
                            <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['staff_no'] ?>"><?= htmlspecialchars($d['dname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position Held *</label>
                        <input type="text" name="position_held" id="e_pos" required placeholder="e.g. MO, Registrar">
                    </div>
                    <div class="form-group">
                        <label>Establishment *</label>
                        <input type="text" name="establishment" id="e_est" required placeholder="e.g. PIMS, CMH">
                    </div>
                    <div class="form-group">
                        <label>From Date *</label>
                        <input type="date" name="from_date" id="e_from" required>
                    </div>
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="to_date" id="e_to">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('exp-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="saveExpForm()">💾 Save</button>
        </div>
    </div>
</div>

<script>
function applyDoctorFilter() {
    const id = document.getElementById('doc-filter').value;
    window.location.href = `<?= BASE_URL ?>/forms/experience.php?doctor_staff_no=${id}`;
}

function openAddModal() {
    document.getElementById('e-modal-title').textContent = 'Add Experience';
    document.getElementById('exp-form').reset();
    document.getElementById('e_id').value = '0';
    openModal('exp-modal');
}

function editExp(e) {
    document.getElementById('e-modal-title').textContent = 'Edit Experience';
    document.getElementById('e_id').value     = e.exp_id;
    document.getElementById('e_doctor').value = e.doctor_staff_no;
    document.getElementById('e_pos').value    = e.position_held;
    document.getElementById('e_est').value    = e.establishment;
    document.getElementById('e_from').value   = e.from_date;
    document.getElementById('e_to').value     = e.to_date || '';
    openModal('exp-modal');
}

async function saveExpForm() {
    const doctor    = document.getElementById('e_doctor').value;
    const fromDate  = document.getElementById('e_from').value;
    const toDate    = document.getElementById('e_to').value;
    const position  = document.getElementById('e_position').value.trim();
    const estab     = document.getElementById('e_estab').value.trim();

    if (!doctor)   { showFlash('Please select a doctor.', 'error'); return; }
    if (!fromDate) { showFlash('From date is required.', 'error'); return; }
    if (toDate && new Date(toDate) < new Date(fromDate)) {
        showFlash('To date cannot be before from date.', 'error'); return;
    }
    if (!position) { showFlash('Position held is required.', 'error'); return; }
    if (!/^[a-zA-Z\s'\-]+$/.test(position)) { showFlash('Position held must contain letters only.', 'error'); return; }
    if (!estab)    { showFlash('Establishment is required.', 'error'); return; }
    await submitForm(document.getElementById('exp-form'), '<?= BASE_URL ?>/forms/experience.php', () => {
        closeModal('exp-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function deleteExpRow(id) {
    if (!confirm('Delete this experience record?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('exp_id', id);
    const resp = await fetch('<?= BASE_URL ?>/forms/experience.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
