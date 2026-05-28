<?php
/**
 * Beds — CRUD Form
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    match($action) {
        'save'         => saveBed($conn),
        'delete'       => deleteBed($conn),
        'update_status'=> updateBedStatus($conn),
        default        => sendJson(['success' => false, 'message' => 'Unknown action.']),
    };
}

function saveBed(mixed $conn): void {
    $bedNo    = (int)($_POST['bed_no']    ?? 0);
    $wardId   = (int)($_POST['ward_id']   ?? 0);
    $status   = $_POST['bed_status']      ?? 'Available';
    $isNew    = ($bedNo <= 0);

    if ($isNew) {
        $stmt  = executeQuery($conn, "SELECT ISNULL(MAX(bed_no),0)+1 AS nid FROM BED");
        $bedNo = (int)(fetchOne($stmt)['nid']);
        executeQuery($conn, "INSERT INTO BED (bed_no, bed_status, ward_id) VALUES (?,?,?)",
            [$bedNo, $status, $wardId]);
        sendJson(['success' => true, 'message' => 'Bed added.', 'id' => $bedNo]);
    } else {
        executeQuery($conn, "UPDATE BED SET bed_status=?, ward_id=? WHERE bed_no=?",
            [$status, $wardId, $bedNo]);
        sendJson(['success' => true, 'message' => 'Bed updated.']);
    }
}

function deleteBed(mixed $conn): void {
    $id = (int)($_POST['bed_no'] ?? 0);
    executeQuery($conn, "DELETE FROM BED WHERE bed_no=?", [$id]);
    sendJson(['success' => true, 'message' => 'Bed deleted.']);
}

function updateBedStatus(mixed $conn): void {
    $id     = (int)($_POST['bed_no']     ?? 0);
    $status = $_POST['bed_status']       ?? 'Available';
    executeQuery($conn, "UPDATE BED SET bed_status=? WHERE bed_no=?", [$status, $id]);
    sendJson(['success' => true, 'message' => "Bed status updated to $status."]);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

$beds = fetchAll(executeQuery($conn,
    "SELECT B.bed_no, B.bed_status, B.ward_id, W.ward_name
     FROM BED B JOIN WARD W ON B.ward_id = W.ward_id
     ORDER BY B.bed_no"
));
$wards = fetchAll(executeQuery($conn, "SELECT ward_id, ward_name FROM WARD ORDER BY ward_name"));

$pageTitle = 'Beds';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search beds…" oninput="filterTable(this,'beds-table')">
        <select id="status-filter" onchange="filterByStatus()" style="max-width:180px">
            <option value="">All Statuses</option>
            <option value="Available">Available</option>
            <option value="Occupied">Occupied</option>
            <option value="Maintenance">Maintenance</option>
        </select>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">➕ Add Bed</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="beds-table">
                <thead>
                    <tr>
                        <th>Bed #</th>
                        <th>Ward</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($beds as $b): ?>
                    <?php
                    $statusClass = match($b['bed_status']) {
                        'Available'   => 'badge-green',
                        'Occupied'    => 'badge-red',
                        'Maintenance' => 'badge-gold',
                        default       => 'badge-navy',
                    };
                    ?>
                    <tr data-status="<?= $b['bed_status'] ?>">
                        <td class="fw-600"><?= $b['bed_no'] ?></td>
                        <td><?= htmlspecialchars($b['ward_name']) ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= $b['bed_status'] ?></span></td>
                        <td class="btn-group">
                            <button class="btn btn-sm btn-secondary"
                                onclick='editBed(<?= json_encode($b) ?>)'>✏</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteBedRow(<?= $b['bed_no'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="bed-modal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <span class="modal-title" id="b-modal-title">Add Bed</span>
            <button class="modal-close" onclick="closeModal('bed-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="bed-form">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="bed_no" id="b_no" value="0">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Ward *</label>
                        <select name="ward_id" id="b_ward" required>
                            <option value="">— Select —</option>
                            <?php foreach ($wards as $w): ?>
                            <option value="<?= $w['ward_id'] ?>"><?= htmlspecialchars($w['ward_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="bed_status" id="b_status" required>
                            <option value="Available">Available</option>
                            <option value="Occupied">Occupied</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('bed-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="saveBedForm()">💾 Save</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('b-modal-title').textContent = 'Add Bed';
    document.getElementById('bed-form').reset();
    document.getElementById('b_no').value = '0';
    openModal('bed-modal');
}

function editBed(b) {
    document.getElementById('b-modal-title').textContent = 'Edit Bed';
    document.getElementById('b_no').value     = b.bed_no;
    document.getElementById('b_ward').value   = b.ward_id;
    document.getElementById('b_status').value = b.bed_status;
    openModal('bed-modal');
}

async function saveBedForm() {
    await submitForm(document.getElementById('bed-form'), '<?= BASE_URL ?>/forms/beds.php', () => {
        closeModal('bed-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function deleteBedRow(id) {
    if (!confirm('Delete this bed?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('bed_no', id);
    const resp = await fetch('<?= BASE_URL ?>/forms/beds.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}

function filterByStatus() {
    const val  = document.getElementById('status-filter').value;
    const rows = document.querySelectorAll('#beds-table tbody tr');
    rows.forEach(r => {
        r.style.display = (!val || r.dataset.status === val) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
