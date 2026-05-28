<?php
/**
 * Wards — CRUD Form
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    match($action) {
        'save'   => saveWard($conn),
        'delete' => deleteWard($conn),
        default  => sendJson(['success' => false, 'message' => 'Unknown action.']),
    };
}

function saveWard(mixed $conn): void {
    $id          = (int)($_POST['ward_id']      ?? 0);
    $wardName    = trim($_POST['ward_name']     ?? '');
    $specialtyId = (int)($_POST['specialty_id'] ?? 0);

    if ($id > 0) {
        executeQuery($conn, "UPDATE WARD SET ward_name=?, specialty_id=? WHERE ward_id=?",
            [$wardName, $specialtyId, $id]);
        sendJson(['success' => true, 'message' => 'Ward updated.']);
    } else {
        $stmt = executeQuery($conn, "SELECT ISNULL(MAX(ward_id),0)+1 AS nid FROM WARD");
        $nid  = (int)(fetchOne($stmt)['nid']);
        executeQuery($conn, "INSERT INTO WARD (ward_id, ward_name, specialty_id) VALUES (?,?,?)",
            [$nid, $wardName, $specialtyId]);
        sendJson(['success' => true, 'message' => 'Ward created.', 'id' => $nid]);
    }
}

function deleteWard(mixed $conn): void {
    $id = (int)($_POST['ward_id'] ?? 0);
    executeQuery($conn, "DELETE FROM WARD WHERE ward_id=?", [$id]);
    sendJson(['success' => true, 'message' => 'Ward deleted.']);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

$wards = fetchAll(executeQuery($conn,
    "SELECT W.ward_id, W.ward_name, W.specialty_id, SP.specialty_name,
            (SELECT COUNT(*) FROM BED B WHERE B.ward_id = W.ward_id) AS bed_count,
            (SELECT COUNT(*) FROM NURSE N WHERE N.ward_id = W.ward_id) AS nurse_count,
            DS.f_name + ' ' + DS.l_name AS day_sister_name,
            NS.f_name + ' ' + NS.l_name AS night_sister_name
     FROM WARD W
     JOIN SPECIALTY SP ON W.specialty_id = SP.specialty_id
     LEFT JOIN DAY_SISTER   D_SIS ON D_SIS.ward_id = W.ward_id
     LEFT JOIN STAFF        DS    ON D_SIS.staff_no = DS.staff_no
     LEFT JOIN NIGHT_SISTER N_SIS ON N_SIS.ward_id = W.ward_id
     LEFT JOIN STAFF        NS    ON N_SIS.staff_no = NS.staff_no
     ORDER BY W.ward_id"
));

$specialties = fetchAll(executeQuery($conn, "SELECT specialty_id, specialty_name FROM SPECIALTY ORDER BY specialty_name"));

$pageTitle = 'Wards';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search wards…" oninput="filterTable(this,'wards-table')">
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">🏥 Add Ward</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="wards-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ward Name</th>
                        <th>Specialty</th>
                        <th>Beds</th>
                        <th>Nurses</th>
                        <th>Day Sister</th>
                        <th>Night Sister</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wards as $w): ?>
                    <tr>
                        <td><?= $w['ward_id'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($w['ward_name']) ?></td>
                        <td><span class="badge badge-teal"><?= htmlspecialchars($w['specialty_name']) ?></span></td>
                        <td><?= $w['bed_count'] ?></td>
                        <td><?= $w['nurse_count'] ?></td>
                        <td><?= htmlspecialchars($w['day_sister_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($w['night_sister_name'] ?? '—') ?></td>
                        <td class="btn-group">
                            <button class="btn btn-sm btn-secondary"
                                onclick='editWard(<?= json_encode($w) ?>)'>✏</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteWardRow(<?= $w['ward_id'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="ward-modal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <span class="modal-title" id="w-modal-title">Add Ward</span>
            <button class="modal-close" onclick="closeModal('ward-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="ward-form">
                <input type="hidden" name="action"  value="save">
                <input type="hidden" name="ward_id" id="w_id" value="0">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Ward Name *</label>
                        <input type="text" name="ward_name" id="w_name" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Specialty *</label>
                        <select name="specialty_id" id="w_spec" required>
                            <option value="">— Select —</option>
                            <?php foreach ($specialties as $sp): ?>
                            <option value="<?= $sp['specialty_id'] ?>"><?= htmlspecialchars($sp['specialty_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('ward-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="saveWardForm()">💾 Save</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('w-modal-title').textContent = 'Add Ward';
    document.getElementById('ward-form').reset();
    document.getElementById('w_id').value = '0';
    openModal('ward-modal');
}

function editWard(w) {
    document.getElementById('w-modal-title').textContent = 'Edit Ward';
    document.getElementById('w_id').value   = w.ward_id;
    document.getElementById('w_name').value = w.ward_name;
    document.getElementById('w_spec').value = w.specialty_id;
    openModal('ward-modal');
}

async function saveWardForm() {
    await submitForm(document.getElementById('ward-form'), '<?= BASE_URL ?>/forms/wards.php', () => {
        closeModal('ward-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function deleteWardRow(id) {
    if (!confirm('Delete this ward? Make sure no nurses or beds are linked.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('ward_id', id);
    const resp = await fetch('<?= BASE_URL ?>/forms/wards.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
