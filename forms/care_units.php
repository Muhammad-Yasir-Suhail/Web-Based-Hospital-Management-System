<?php
/**
 * Care Units — CRUD Form
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    match($action) {
        'save'   => saveCareUnit($conn),
        'delete' => deleteCareUnit($conn),
        default  => sendJson(['success' => false, 'message' => 'Unknown action.']),
    };
}

function saveCareUnit(mixed $conn): void {
    $id          = (int)($_POST['care_unit_no']       ?? 0);
    $label       = trim($_POST['unit_label']          ?? '');
    $wardId      = (int)($_POST['ward_id']            ?? 0);
    $staffNurse  = (int)($_POST['staff_nurse_staff_no']?? 0) ?: null;
    $isNew       = ($id <= 0);

    if ($isNew) {
        $stmt = executeQuery($conn, "SELECT ISNULL(MAX(care_unit_no),0)+1 AS nid FROM CARE_UNIT");
        $id   = (int)(fetchOne($stmt)['nid']);
        executeQuery($conn,
            "INSERT INTO CARE_UNIT (care_unit_no, unit_label, ward_id, staff_nurse_staff_no) VALUES (?,?,?,?)",
            [$id, $label, $wardId, $staffNurse]
        );
        sendJson(['success' => true, 'message' => 'Care unit created.', 'id' => $id]);
    } else {
        executeQuery($conn,
            "UPDATE CARE_UNIT SET unit_label=?, ward_id=?, staff_nurse_staff_no=? WHERE care_unit_no=?",
            [$label, $wardId, $staffNurse, $id]
        );
        sendJson(['success' => true, 'message' => 'Care unit updated.']);
    }
}

function deleteCareUnit(mixed $conn): void {
    $id = (int)($_POST['care_unit_no'] ?? 0);
    // Unlink patients from care unit before deleting
    executeQuery($conn, "UPDATE PATIENT SET care_unit_no=NULL WHERE care_unit_no=?", [$id]);
    executeQuery($conn, "DELETE FROM CARE_UNIT WHERE care_unit_no=?", [$id]);
    sendJson(['success' => true, 'message' => 'Care unit deleted.']);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

$careUnits = fetchAll(executeQuery($conn,
    "SELECT CU.care_unit_no, CU.unit_label, CU.ward_id, CU.staff_nurse_staff_no,
            W.ward_name,
            S.f_name + ' ' + S.l_name AS nurse_name,
            (SELECT COUNT(*) FROM PATIENT P WHERE P.care_unit_no = CU.care_unit_no) AS patient_count,
            (SELECT COUNT(*) FROM NURSE  N WHERE N.care_unit_no  = CU.care_unit_no) AS nurse_count
     FROM CARE_UNIT CU
     JOIN WARD W ON CU.ward_id = W.ward_id
     LEFT JOIN STAFF_NURSE SN ON CU.staff_nurse_staff_no = SN.staff_no
     LEFT JOIN STAFF        S ON SN.staff_no              = S.staff_no
     ORDER BY CU.care_unit_no"
));

$wards = fetchAll(executeQuery($conn, "SELECT ward_id, ward_name FROM WARD ORDER BY ward_name"));

$staffNurses = fetchAll(executeQuery($conn,
    "SELECT SN.staff_no, S.f_name + ' ' + S.l_name AS sname
     FROM STAFF_NURSE SN JOIN STAFF S ON SN.staff_no = S.staff_no ORDER BY S.l_name"
));

$pageTitle = 'Care Units';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search care units…" oninput="filterTable(this,'cu-table')">
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">🔬 Add Care Unit</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="cu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Unit Label</th>
                        <th>Ward</th>
                        <th>Staff Nurse In-Charge</th>
                        <th>Patients</th>
                        <th>Nurses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($careUnits as $cu): ?>
                    <tr>
                        <td><?= $cu['care_unit_no'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($cu['unit_label']) ?></td>
                        <td><?= htmlspecialchars($cu['ward_name']) ?></td>
                        <td><?= htmlspecialchars($cu['nurse_name'] ?? '—') ?></td>
                        <td><span class="badge badge-teal"><?= $cu['patient_count'] ?></span></td>
                        <td><span class="badge badge-gold"><?= $cu['nurse_count'] ?></span></td>
                        <td class="btn-group">
                            <button class="btn btn-sm btn-secondary"
                                onclick='editCU(<?= json_encode($cu) ?>)'>✏</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteCU(<?= $cu['care_unit_no'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="cu-modal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <span class="modal-title" id="cu-modal-title">Add Care Unit</span>
            <button class="modal-close" onclick="closeModal('cu-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="cu-form">
                <input type="hidden" name="action"       value="save">
                <input type="hidden" name="care_unit_no" id="cu_id" value="0">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Unit Label *</label>
                        <input type="text" name="unit_label" id="cu_label" required placeholder="e.g. CU-Ortho">
                    </div>
                    <div class="form-group">
                        <label>Ward *</label>
                        <select name="ward_id" id="cu_ward" required>
                            <option value="">— Select —</option>
                            <?php foreach ($wards as $w): ?>
                            <option value="<?= $w['ward_id'] ?>"><?= htmlspecialchars($w['ward_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Staff Nurse In-Charge</label>
                        <select name="staff_nurse_staff_no" id="cu_nurse">
                            <option value="">— None —</option>
                            <?php foreach ($staffNurses as $sn): ?>
                            <option value="<?= $sn['staff_no'] ?>"><?= htmlspecialchars($sn['sname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('cu-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="saveCUForm()">💾 Save</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('cu-modal-title').textContent = 'Add Care Unit';
    document.getElementById('cu-form').reset();
    document.getElementById('cu_id').value = '0';
    openModal('cu-modal');
}

function editCU(cu) {
    document.getElementById('cu-modal-title').textContent  = 'Edit Care Unit';
    document.getElementById('cu_id').value    = cu.care_unit_no;
    document.getElementById('cu_label').value = cu.unit_label;
    document.getElementById('cu_ward').value  = cu.ward_id;
    document.getElementById('cu_nurse').value = cu.staff_nurse_staff_no || '';
    openModal('cu-modal');
}

async function saveCUForm() {
    await submitForm(document.getElementById('cu-form'),'<?= BASE_URL ?>/forms/care_units.php', () => {
        closeModal('cu-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function deleteCU(id) {
    if (!confirm('Delete this care unit? Patients assigned to it will be unlinked.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('care_unit_no', id);
    const resp = await fetch('<?= BASE_URL ?>/forms/care_units.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
