<?php
/**
 * CONSULTANT TEAM RECORD — Manual Record Form
 * Input: Staff No  →  displays consultant team record with experience + performance
 */
require_once __DIR__ . '/../includes/db.php';

$staffNo    = (int)($_GET['staff_no'] ?? 0);
$doctor     = null;
$experience = [];
$progress   = [];

$conn       = getConnection();
$allDoctors = fetchAll(executeQuery($conn,
    "SELECT D.staff_no, S.f_name + ' ' + S.l_name AS full_name, P.position_name
     FROM DOCTOR D
     JOIN STAFF    S ON D.staff_no      = S.staff_no
     JOIN POSITION P ON D.position_code = P.position_code
     ORDER BY S.l_name"
));

if ($staffNo > 0) {
    $doctor = fetchOne(executeQuery($conn,
        "SELECT D.staff_no, S.f_name, S.m_name, S.l_name,
                S.f_name + ' ' + S.l_name AS full_name,
                P.position_name, D.date_joined_team,
                SC.f_name + ' ' + SC.l_name AS consultant_name
         FROM DOCTOR D
         JOIN STAFF    S  ON D.staff_no             = S.staff_no
         JOIN POSITION P  ON D.position_code        = P.position_code
         LEFT JOIN CONSULTANT CON ON D.consultant_staff_no = CON.staff_no
         LEFT JOIN STAFF      SC  ON CON.staff_no          = SC.staff_no
         WHERE D.staff_no = ?",
        [$staffNo]
    ));

    if ($doctor) {
        $experience = fetchAll(executeQuery($conn,
            "SELECT exp_id, from_date, to_date, position_held, establishment
             FROM EXPERIENCE_HISTORY
             WHERE doctor_staff_no = ?
             ORDER BY from_date",
            [$staffNo]
        ));

        $progress = fetchAll(executeQuery($conn,
            "SELECT PR.review_id, PR.review_date, PR.grade, PR.remarks,
                    SC.f_name + ' ' + SC.l_name AS consultant_name
             FROM PERFORMANCE_REVIEW PR
             JOIN STAFF SC ON PR.consultant_staff_no = SC.staff_no
             WHERE PR.doctor_staff_no = ?
             ORDER BY PR.review_date",
            [$staffNo]
        ));
    }
}

$pageTitle = 'Consultant Team Record';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .rec-wrapper { max-width: 960px; margin: 0 auto; }

    .rec-letterhead {
        background: var(--navy);
        color: #fff;
        text-align: center;
        padding: 24px 32px 18px;
        border-radius: var(--radius) var(--radius) 0 0;
    }
    .rec-letterhead .cross { font-size: 2rem; color: var(--teal-light); line-height: 1; }
    .rec-letterhead h2 {
        font-family: var(--font-head);
        font-size: 1.4rem;
        margin: 6px 0 2px;
        letter-spacing: .05em;
    }
    .rec-letterhead .form-sub {
        font-size: .72rem;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: #8fa9c1;
        font-weight: 500;
    }

    .rec-selector {
        background: var(--navy-mid);
        padding: 14px 28px;
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .rec-selector label {
        color: #8fa9c1;
        font-size: .85rem;
        font-weight: 500;
        white-space: nowrap;
    }
    .rec-selector select {
        flex: 1;
        min-width: 220px;
        max-width: 420px;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid rgba(255,255,255,.18);
        background: rgba(255,255,255,.08);
        color: #fff;
        font-family: var(--font-body);
        font-size: .9rem;
    }
    .rec-selector select option { background: #1b2e44; color: #fff; }

    .rec-card {
        background: var(--warm-white);
        border: 1px solid var(--border);
        border-top: none;
        border-radius: 0 0 var(--radius) var(--radius);
        box-shadow: var(--shadow-md);
    }

    .rec-header {
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-bottom: 2px solid var(--navy);
    }
    .rec-header-col {
        padding: 20px 28px;
        border-right: 1px solid var(--border);
    }
    .rec-header-col:last-child { border-right: none; }

    .field-row {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin-bottom: 12px;
    }
    .field-row:last-child { margin-bottom: 0; }
    .field-label {
        font-size: .73rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--text-mid);
        white-space: nowrap;
        min-width: 120px;
    }
    .field-value {
        font-size: .93rem;
        color: var(--navy);
        font-weight: 500;
        border-bottom: 1px solid var(--border);
        flex: 1;
        padding-bottom: 2px;
    }

    .rec-section {
        background: var(--navy);
        color: #fff;
        padding: 9px 28px;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    .rec-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .rec-table thead tr { background: var(--navy-mid); color: #c9d4e0; }
    .rec-table thead th {
        padding: 11px 16px;
        text-align: left;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .09em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .rec-table tbody tr { border-bottom: 1px solid var(--border); }
    .rec-table tbody tr:hover { background: var(--teal-pale); }
    .rec-table tbody td { padding: 10px 16px; vertical-align: middle; }
    .rec-table tbody tr:last-child { border-bottom: none; }
    .rec-table .empty-row td {
        color: var(--text-light);
        font-style: italic;
        text-align: center;
        padding: 28px;
    }

    /* Grade badge colours */
    .grade-A { background: var(--green-pale); color: var(--green); }
    .grade-B { background: var(--teal-pale);  color: var(--teal);  }
    .grade-C { background: var(--gold-pale);  color: var(--gold);  }
    .grade-D { background: var(--red-pale);   color: var(--red);   }

    .rec-empty { text-align: center; padding: 48px 20px; color: var(--text-light); }
    .rec-empty .icon { font-size: 2.4rem; margin-bottom: 10px; }

    .rec-actions {
        padding: 14px 28px;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        background: var(--cream);
        border-radius: 0 0 var(--radius) var(--radius);
    }

    @media print {
        .rec-selector, .rec-actions, .sidebar, .topbar, .no-print { display: none !important; }
        .main-content { margin-left: 0 !important; }
    }
    @media (max-width: 640px) {
        .rec-header { grid-template-columns: 1fr; }
        .rec-header-col { border-right: none; border-bottom: 1px solid var(--border); }
    }
</style>

<div class="rec-wrapper">

    <div class="mb-4 no-print" style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>/index.php"         class="btn btn-secondary btn-sm">← Dashboard</a>
        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-secondary btn-sm">📈 All Reports</a>
    </div>

    <!-- Letterhead -->
    <div class="rec-letterhead">
        <div class="cross">⚕</div>
        <h2>IVOR PAINE MEMORIAL HOSPITAL</h2>
        <div class="form-sub">Consultant Team Record</div>
    </div>

    <!-- Staff selector -->
    <div class="rec-selector no-print">
        <label for="staff-select">Input Staff No:</label>
        <select id="staff-select" onchange="loadStaff(this.value)">
            <option value="">— Select Doctor —</option>
            <?php foreach ($allDoctors as $d): ?>
            <option value="<?= $d['staff_no'] ?>"
                <?= $d['staff_no'] == $staffNo ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['staff_no'] . ' — ' . $d['full_name'] . ' (' . $d['position_name'] . ')') ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($doctor): ?>
        <button class="btn btn-gold btn-sm" onclick="window.print()">🖨 Print</button>
        <?php endif; ?>
    </div>

    <div class="rec-card">

        <?php if (!$doctor): ?>
        <div class="rec-empty">
            <div class="icon">👨‍⚕️</div>
            <p>Select a staff number above to view the consultant team record.</p>
        </div>

        <?php else: ?>

        <!-- Staff header fields -->
        <div class="rec-header">
            <div class="rec-header-col">
                <div class="field-row">
                    <span class="field-label">Staff No:</span>
                    <span class="field-value"><?= $doctor['staff_no'] ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Name:</span>
                    <span class="field-value"><?= htmlspecialchars($doctor['full_name']) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Position:</span>
                    <span class="field-value"><?= htmlspecialchars($doctor['position_name']) ?></span>
                </div>
            </div>
            <div class="rec-header-col">
                <div class="field-row">
                    <span class="field-label">Date Joined:</span>
                    <span class="field-value"><?= $doctor['date_joined_team'] ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Consultant:</span>
                    <span class="field-value"><?= htmlspecialchars($doctor['consultant_name'] ?? '—') ?></span>
                </div>
            </div>
        </div>

        <!-- Previous Experience -->
        <div class="rec-section">Previous Experience</div>
        <div style="overflow-x:auto">
            <table class="rec-table">
                <thead>
                    <tr>
                        <th>From Date</th>
                        <th>To Date</th>
                        <th>Position Held</th>
                        <th>Establishment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($experience)): ?>
                    <tr class="empty-row"><td colspan="4">No experience history recorded.</td></tr>
                    <?php else: ?>
                    <?php foreach ($experience as $e): ?>
                    <tr>
                        <td><?= $e['from_date'] ?></td>
                        <td><?= $e['to_date'] ?: 'Present' ?></td>
                        <td class="fw-600"><?= htmlspecialchars($e['position_held']) ?></td>
                        <td><?= htmlspecialchars($e['establishment']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Progress / Performance -->
        <div class="rec-section">Progress</div>
        <div style="overflow-x:auto">
            <table class="rec-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Performance Grade</th>
                        <th>Remarks</th>
                        <th>Reviewed By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($progress)): ?>
                    <tr class="empty-row"><td colspan="4">No performance reviews recorded.</td></tr>
                    <?php else: ?>
                    <?php foreach ($progress as $pr): ?>
                    <tr>
                        <td><?= $pr['review_date'] ?></td>
                        <td>
                            <span class="badge grade-<?= htmlspecialchars($pr['grade']) ?>">
                                <?= htmlspecialchars($pr['grade']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($pr['remarks']) ?></td>
                        <td><?= htmlspecialchars($pr['consultant_name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="rec-actions no-print">
            <a href="<?= BASE_URL ?>/forms/doctors.php"    class="btn btn-secondary btn-sm">✏ Edit Doctor</a>
            <a href="<?= BASE_URL ?>/forms/experience.php?doctor_staff_no=<?= $staffNo ?>" class="btn btn-secondary btn-sm">📜 Add Experience</a>
            <a href="<?= BASE_URL ?>/forms/performance.php" class="btn btn-secondary btn-sm">📊 Add Review</a>
            <button class="btn btn-gold btn-sm"             onclick="window.print()">🖨 Print Record</button>
        </div>

        <?php endif; ?>
    </div><!-- .rec-card -->
</div><!-- .rec-wrapper -->

<script>
function loadStaff(staffNo) {
    if (!staffNo) return;
    window.location.href = `<?= BASE_URL ?>/reports/consultant_team_record.php?staff_no=${staffNo}`;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>