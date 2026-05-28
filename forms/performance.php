<?php
/**
 * Performance Reviews — CRUD (Consultant Team Record: Progress section)
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn   = getConnection();
    $action = $_POST['action'] ?? '';

    match($action) {
        'save'   => saveReview($conn),
        'delete' => deleteReview($conn),
        default  => sendJson(['success' => false, 'message' => 'Unknown action.']),
    };
}

function saveReview(mixed $conn): void {
    $id           = (int)($_POST['review_id'] ?? 0);
    $reviewDate   = trim($_POST['review_date'] ?? '');
    $grade        = trim($_POST['grade']       ?? '');
    $remarks      = trim($_POST['remarks']     ?? '');
    $doctorNo     = (int)($_POST['doctor_staff_no']     ?? 0);
    $consultantNo = (int)($_POST['consultant_staff_no'] ?? 0);

    if ($reviewDate === '')
        sendJson(['success' => false, 'message' => 'Review date is required.']);
    if (strtotime($reviewDate) > time())
        sendJson(['success' => false, 'message' => 'Review date cannot be in the future.']);
    if ($grade === '')
        sendJson(['success' => false, 'message' => 'Grade is required.']);
    if (!preg_match('/^[A-F][+-]?$/', $grade))
        sendJson(['success' => false, 'message' => 'Grade must be a valid letter grade (A, B, C, D, F).']);
    if ($remarks === '')
        sendJson(['success' => false, 'message' => 'Remarks are required.']);
    if ($doctorNo <= 0)
        sendJson(['success' => false, 'message' => 'Please select a doctor.']);
    if ($consultantNo <= 0)
        sendJson(['success' => false, 'message' => 'Please select a consultant.']);

    $params = [$reviewDate, $grade, $remarks, $doctorNo, $consultantNo];

    if ($id > 0) {
        $params[] = $id;
        executeQuery($conn,
            "UPDATE PERFORMANCE_REVIEW SET review_date=?, grade=?, remarks=?,
             doctor_staff_no=?, consultant_staff_no=? WHERE review_id=?",
            $params
        );
        sendJson(['success' => true, 'message' => 'Review updated.']);
    } else {
        $stmt = executeQuery($conn, "SELECT ISNULL(MAX(review_id),0)+1 AS nid FROM PERFORMANCE_REVIEW");
        $nid  = (int)(fetchOne($stmt)['nid']);
        array_unshift($params, $nid);
        executeQuery($conn,
            "INSERT INTO PERFORMANCE_REVIEW (review_id, review_date, grade, remarks, doctor_staff_no, consultant_staff_no)
             VALUES (?,?,?,?,?,?)",
            $params
        );
        sendJson(['success' => true, 'message' => 'Review added.', 'id' => $nid]);
    }
}

function deleteReview(mixed $conn): void {
    $id = (int)($_POST['review_id'] ?? 0);
    executeQuery($conn, "DELETE FROM PERFORMANCE_REVIEW WHERE review_id=?", [$id]);
    sendJson(['success' => true, 'message' => 'Review deleted.']);
}

// ── Load page data ─────────────────────────────────
$conn = getConnection();

$reviews = fetchAll(executeQuery($conn,
    "SELECT PR.review_id, PR.review_date, PR.grade, PR.remarks,
            PR.doctor_staff_no, PR.consultant_staff_no,
            SD.f_name + ' ' + SD.l_name AS doctor_name,
            SC.f_name + ' ' + SC.l_name AS consultant_name
     FROM PERFORMANCE_REVIEW PR
     JOIN STAFF SD ON PR.doctor_staff_no     = SD.staff_no
     JOIN STAFF SC ON PR.consultant_staff_no = SC.staff_no
     ORDER BY PR.review_date DESC"
));

$doctors = fetchAll(executeQuery($conn,
    "SELECT D.staff_no, S.f_name + ' ' + S.l_name AS dname FROM DOCTOR D JOIN STAFF S ON D.staff_no=S.staff_no ORDER BY S.l_name"
));
$consultants = fetchAll(executeQuery($conn,
    "SELECT C.staff_no, S.f_name + ' ' + S.l_name AS cname FROM CONSULTANT C JOIN STAFF S ON C.staff_no=S.staff_no ORDER BY S.l_name"
));

$pageTitle = 'Performance Reviews';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center mb-4" style="flex-wrap:wrap;gap:12px">
    <div class="search-bar">
        <input type="text" placeholder="Search reviews…" oninput="filterTable(this,'reviews-table')">
        <select id="doctor-filter" onchange="filterByDoctor()" style="max-width:220px">
            <option value="">All Doctors</option>
            <?php foreach ($doctors as $d): ?>
            <option value="<?= htmlspecialchars($d['dname']) ?>"><?= htmlspecialchars($d['dname']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">📊 Add Review</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table id="reviews-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Doctor</th>
                        <th>Consultant</th>
                        <th>Review Date</th>
                        <th>Grade</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                    <?php
                    $gradeClass = match($r['grade']) {
                        'A'     => 'badge-green',
                        'B'     => 'badge-teal',
                        'C'     => 'badge-gold',
                        default => 'badge-navy',
                    };
                    ?>
                    <tr>
                        <td><?= $r['review_id'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($r['doctor_name']) ?></td>
                        <td><?= htmlspecialchars($r['consultant_name']) ?></td>
                        <td><?= $r['review_date'] ?></td>
                        <td><span class="badge <?= $gradeClass ?>"><?= $r['grade'] ?></span></td>
                        <td class="text-sm"><?= htmlspecialchars($r['remarks']) ?></td>
                        <td class="btn-group">
                            <button class="btn btn-sm btn-secondary"
                                onclick='editReview(<?= json_encode($r) ?>)'>✏</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteReviewRow(<?= $r['review_id'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="review-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="r-modal-title">Add Performance Review</span>
            <button class="modal-close" onclick="closeModal('review-modal')">×</button>
        </div>
        <div class="modal-body">
            <form id="review-form">
                <input type="hidden" name="action"    value="save">
                <input type="hidden" name="review_id" id="r_id" value="0">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Doctor *</label>
                        <select name="doctor_staff_no" id="r_doctor" required>
                            <option value="">— Select —</option>
                            <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['staff_no'] ?>"><?= htmlspecialchars($d['dname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Consultant *</label>
                        <select name="consultant_staff_no" id="r_consultant" required>
                            <option value="">— Select —</option>
                            <?php foreach ($consultants as $c): ?>
                            <option value="<?= $c['staff_no'] ?>"><?= htmlspecialchars($c['cname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Review Date *</label>
                        <input type="date" name="review_date" id="r_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Grade *</label>
                        <select name="grade" id="r_grade" required>
                            <option value="A">A — Excellent</option>
                            <option value="B">B — Good</option>
                            <option value="C">C — Average</option>
                            <option value="D">D — Below Average</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Remarks</label>
                        <textarea name="remarks" id="r_remarks" rows="3" placeholder="Performance notes…"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('review-modal')">Cancel</button>
            <button class="btn btn-primary"   onclick="saveReviewForm()">💾 Save</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('r-modal-title').textContent = 'Add Performance Review';
    document.getElementById('review-form').reset();
    document.getElementById('r_id').value   = '0';
    document.getElementById('r_date').value = new Date().toISOString().slice(0,10);
    openModal('review-modal');
}

function editReview(r) {
    document.getElementById('r-modal-title').textContent = 'Edit Review';
    document.getElementById('r_id').value          = r.review_id;
    document.getElementById('r_doctor').value      = r.doctor_staff_no;
    document.getElementById('r_consultant').value  = r.consultant_staff_no;
    document.getElementById('r_date').value        = r.review_date;
    document.getElementById('r_grade').value       = r.grade;
    document.getElementById('r_remarks').value     = r.remarks;
    openModal('review-modal');
}

async function saveReviewForm() {
    const reviewDate  = document.getElementById('r_date').value;
    const grade       = document.getElementById('r_grade').value.trim();
    const remarks     = document.getElementById('r_remarks').value.trim();
    const doctor      = document.getElementById('r_doctor').value;
    const consultant  = document.getElementById('r_consultant').value;

    if (!reviewDate) { showFlash('Review date is required.', 'error'); return; }
    if (new Date(reviewDate) > new Date()) { showFlash('Review date cannot be in the future.', 'error'); return; }
    if (!grade)      { showFlash('Grade is required.', 'error'); return; }
    if (!/^[A-F][+-]?$/.test(grade)) { showFlash('Grade must be a valid letter grade (A, B, C, D, F).', 'error'); return; }
    if (!remarks)    { showFlash('Remarks are required.', 'error'); return; }
    if (!doctor)     { showFlash('Please select a doctor.', 'error'); return; }
    if (!consultant) { showFlash('Please select a consultant.', 'error'); return; }
    await submitForm(document.getElementById('review-form'), '<?= BASE_URL ?>/forms/performance.php', () => {
        closeModal('review-modal');
        setTimeout(() => location.reload(), 1200);
    });
}

async function deleteReviewRow(id) {
    if (!confirm('Delete this review?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('review_id', id);
    const resp = await fetch('<?= BASE_URL ?>/forms/performance.php', { method:'POST', body: fd });
    const json = await resp.json();
    showFlash(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
}

function filterByDoctor() {
    const val  = document.getElementById('doctor-filter').value.toLowerCase();
    const rows = document.querySelectorAll('#reviews-table tbody tr');
    rows.forEach(r => {
        r.style.display = (!val || r.cells[1].textContent.toLowerCase().includes(val)) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
