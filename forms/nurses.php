<?php
/**
 * Nurses — CRUD Form
 * Covers: NURSE, DAY_SISTER, NIGHT_SISTER, STAFF_NURSE, NON_REG_NURSE
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    match($action) {
        'save'   => saveNurse($conn),
        'delete' => deleteNurse($conn),
        default  => sendJson(['success' => false, 'message' => 'Unknown action.']),
    };
}

function saveNurse(mixed $conn): void {
    $staffNo   = (int)($_POST['staff_no']    ?? 0);
    $fName     = trim($_POST['f_name']       ?? '');
    $mName     = trim($_POST['m_name']       ?? '');
    $lName     = trim($_POST['l_name']       ?? '');
    $wardId    = (int)($_POST['ward_id']     ?? 0);
    $careUnit  = (int)($_POST['care_unit_no']?? 0);
    $nurseType = $_POST['nurse_type']        ?? 'STAFF';

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
    if ($wardId <= 0)
        sendJson(['success' => false, 'message' => 'Please select a ward.']);
    if ($careUnit <= 0)
        sendJson(['success' => false, 'message' => 'Please select a care unit.']);
    if (!in_array($nurseType, ['STAFF', 'NON_REG']))
        sendJson(['success' => false, 'message' => 'Invalid nurse type.']);

    $isNew = ($staffNo <= 0);

    if ($isNew) {
        $stmt    = executeQuery($conn, "SELECT ISNULL(MAX(staff_no),0)+1 AS nid FROM STAFF");
        $staffNo = (int)(fetchOne($stmt)['nid']);
        executeQuery($conn, "INSERT INTO STAFF (staff_no,f_name,m_name,l_name) VALUES (?,?,?,?)",
            [$staffNo, $fName, $mName, $lName]);
        executeQuery($conn, "INSERT INTO NURSE (staff_no, ward_id, care_unit_no) VALUES (?,?,?)",
            [$staffNo, $wardId, $careUnit]);

        insertNurseSubtype($conn, $staffNo, $nurseType, $wardId);
        sendJson(['success' => true, 'message' => 'Nurse added.', 'id' => $staffNo]);
    } else {
        executeQuery($conn, "UPDATE STAFF SET f_name=?, m_name=?, l_name=? WHERE staff_no=?",
            [$fName, $mName, $lName, $staffNo]);
        executeQuery($conn, "UPDATE NURSE SET ward_id=?, care_unit_no=? WHERE staff_no=?",
            [$wardId, $careUnit, $staffNo]);
        sendJson(['success' => true, 'message' => 'Nurse updated.']);
    }
}

function insertNurseSubtype(mixed $conn, int $staffNo, string $type, int $wardId): void {
    match($type) {
        'DAY_SISTER'  => executeQuery($conn, "INSERT INTO DAY_SISTER    (staff_no, ward_id) VALUES (?,?)", [$staffNo, $wardId]),
        'NIGHT_SISTER'=> executeQuery($conn, "INSERT INTO NIGHT_SISTER  (staff_no, ward_id) VALUES (?,?)", [$staffNo, $wardId]),
        'NON_REG'     => executeQuery($conn, "INSERT INTO NON_REG_NURSE (staff_no) VALUES (?)",            [$staffNo]),
        default       => executeQuery($conn, "INSERT INTO STAFF_NURSE   (staff_no) VALUES (?)",            [$staffNo]),
    };
}

function deleteNurse(mixed $conn): void {
    $id = (int)($_POST['staff_no'] ?? 0);
    foreach (['DAY_SISTER','NIGHT_SISTER','STAFF_NURSE','NON_REG_NURSE','NURSE','STAFF'] as $tbl) {
        executeQuery($conn, "DELETE FROM $tbl WHERE staff_no=?", [$id]);
    }
    sendJson(['success' => true, 'message' => 'Nurse deleted.']);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

$nurses = fetchAll(executeQuery($conn,
    "SELECT N.staff_no, S.f_name, S.m_name, S.l_name,
            S.f_name + ' ' + S.l_name AS full_name,
            W.ward_name, CU.unit_label,
            CASE
                WHEN DS.staff_no IS NOT NULL THEN 'Day Sister'
                WHEN NS.staff_no IS NOT NULL THEN 'Night Sister'
                WHEN SN.staff_no IS NOT NULL THEN 'Staff Nurse'
                WHEN NR.staff_no IS NOT NULL THEN 'Non-Reg Nurse'
                ELSE 'Unknown'
            END AS nurse_type
     FROM NURSE N
     JOIN STAFF    S  ON N.staff_no      = S.staff_no
     JOIN WARD     W  ON N.ward_id       = W.ward_id
     JOIN CARE_UNIT CU ON N.care_unit_no = CU.care_unit_no
     LEFT JOIN DAY_SISTER    DS ON N.staff_no = DS.staff_no
     LEFT JOIN NIGHT_SISTER  NS ON N.staff_no = NS.staff_no
     LEFT JOIN STAFF_NURSE   SN ON N.staff_no = SN.staff_no
     LEFT JOIN NON_REG_NURSE NR ON N.staff_no = NR.staff_no
     ORDER BY S.l_name"
));

$wards     = fetchAll(executeQuery($conn, "SELECT ward_id, ward_name FROM WARD ORDER BY ward_name"));
$careUnits = fetchAll(executeQuery($conn, "SELECT care_unit_no, unit_label FROM CARE_UNIT ORDER BY care_unit_no"));

$pageTitle = 'Nurses';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search nurses…" oninput="filterTable(this,'nurses-table')">
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">➕ Add Nurse</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="nurses-table">
                <thead>
                    <tr>
                        <th>Staff #</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Ward</th>
                        <th>Care Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nurses as $n): ?>
                    <?php
                    $typeClass = match($n['nurse_type']) {
                        'Day Sister'    => 'badge-gold',
                        'Night Sister'  => 'badge-navy',
                        'Staff Nurse'   => 'badge-teal',
                        'Non-Reg Nurse' => 'badge-green',
                        default         => 'badge-teal',
                    };
                    ?>
                    <tr>
                        <td><?= $n['staff_no'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($n['full_name']) ?></td>
                        <td><span class="badge <?= $typeClass ?>"><?= $n['nurse_type'] ?></span></td>
                        <td><?= htmlspecialchars($n['ward_name']) ?></td>
                        <td><?= htmlspecialchars($n['unit_label']) ?></td>
                        <td class="btn-group">
                            <button class="btn btn-sm btn-secondary"
                                onclick='editNurse(<?= json_encode($n) ?>)'>✏</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteNurseRow(<?= $n['staff_no'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="nurse-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="n-modal-title">Add Nurse</span>
            <button class="modal-close" onclick="closeModal('nurse-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="nurse-form">
                <input type="hidden" name="action"   value="save">
                <input type="hidden" name="staff_no" id="n_staff_no" value="0">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="f_name" id="n_fname" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="m_name" id="n_mname">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="l_name" id="n_lname" required>
                    </div>
                    <div class="form-group">
                        <label>Ward *</label>
                        <select name="ward_id" id="n_ward" required>
                            <option value="">— Select —</option>
                            <?php foreach ($wards as $w): ?>
                            <option value="<?= $w['ward_id'] ?>"><?= htmlspecialchars($w['ward_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Care Unit *</label>
                        <select name="care_unit_no" id="n_cu" required>
                            <option value="">— Select —</option>
                            <?php foreach ($careUnits as $cu): ?>
                            <option value="<?= $cu['care_unit_no'] ?>"><?= htmlspecialchars($cu['unit_label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="type-group">
                        <label>Nurse Type *</label>
                        <select name="nurse_type" id="n_type" required>
                            <option value="STAFF">Staff Nurse</option>
                            <option value="DAY_SISTER">Day Sister</option>
                            <option value="NIGHT_SISTER">Night Sister</option>
                            <option value="NON_REG">Non-Registered Nurse</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('nurse-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="saveNurseForm()">💾 Save</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('n-modal-title').textContent = 'Add Nurse';
    document.getElementById('nurse-form').reset();
    document.getElementById('n_staff_no').value = '0';
    document.getElementById('type-group').style.display = '';
    openModal('nurse-modal');
}

function editNurse(n) {
    document.getElementById('n-modal-title').textContent = 'Edit Nurse';
    document.getElementById('n_staff_no').value = n.staff_no;
    document.getElementById('n_fname').value    = n.f_name;
    document.getElementById('n_mname').value    = n.m_name || '';
    document.getElementById('n_lname').value    = n.l_name;
    document.getElementById('n_ward').value     = n.ward_id;
    document.getElementById('n_cu').value       = n.care_unit_no;
    document.getElementById('type-group').style.display = 'none'; // can't change type on edit
    openModal('nurse-modal');
}

async function saveNurseForm() {
    const fName = document.getElementById('n_fname').value.trim();
    const mName = document.getElementById('n_mname').value.trim();
    const lName = document.getElementById('n_lname').value.trim();
    const ward  = document.getElementById('n_ward').value;
    const cu    = document.getElementById('n_careunit').value;
    const nameRx = /^[a-zA-Z\s'\-]+$/;

    if (!fName)              { showFlash('First name is required.', 'error'); return; }
    if (!nameRx.test(fName)) { showFlash('First name must contain letters only.', 'error'); return; }
    if (mName && !nameRx.test(mName)) { showFlash('Middle name must contain letters only.', 'error'); return; }
    if (!lName)              { showFlash('Last name is required.', 'error'); return; }
    if (!nameRx.test(lName)) { showFlash('Last name must contain letters only.', 'error'); return; }
    if (!ward) { showFlash('Please select a ward.', 'error'); return; }
    if (!cu)   { showFlash('Please select a care unit.', 'error'); return; }
    await submitForm(document.getElementById('nurse-form'), '<?= BASE_URL ?>/forms/nurses.php', () => {
        closeModal('nurse-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function deleteNurseRow(id) {
    if (!confirm('Delete this nurse?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('staff_no', id);
    const resp = await fetch('<?= BASE_URL ?>/forms/nurses.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
