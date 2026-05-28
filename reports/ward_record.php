<?php
/**
 * WARD RECORD — Manual Record Form
 * Input: Ward Name (List Item)  →  displays ward record with patient information
 */
require_once __DIR__ . '/../includes/db.php';

$wardId   = (int)($_GET['ward_id'] ?? 0);
$ward     = null;
$patients = [];
$nurses   = ['day_sister' => null, 'night_sister' => null, 'staff' => [], 'non_reg' => []];

$conn     = getConnection();
$allWards = fetchAll(executeQuery($conn, "SELECT ward_id, ward_name FROM WARD ORDER BY ward_name"));

if ($wardId > 0) {
    $ward = fetchOne(executeQuery($conn,
        "SELECT W.ward_id, W.ward_name, SP.specialty_name
         FROM WARD W JOIN SPECIALTY SP ON W.specialty_id = SP.specialty_id
         WHERE W.ward_id = ?",
        [$wardId]
    ));

    $nurses['day_sister'] = fetchOne(executeQuery($conn,
        "SELECT S.f_name + ' ' + S.l_name AS full_name
         FROM DAY_SISTER DS JOIN STAFF S ON DS.staff_no = S.staff_no
         WHERE DS.ward_id = ?",
        [$wardId]
    ));

    $nurses['night_sister'] = fetchOne(executeQuery($conn,
        "SELECT S.f_name + ' ' + S.l_name AS full_name
         FROM NIGHT_SISTER NS JOIN STAFF S ON NS.staff_no = S.staff_no
         WHERE NS.ward_id = ?",
        [$wardId]
    ));

    $nurses['staff'] = fetchAll(executeQuery($conn,
        "SELECT S.f_name + ' ' + S.l_name AS full_name
         FROM NURSE N
         JOIN STAFF_NURSE SN ON N.staff_no = SN.staff_no
         JOIN STAFF       S  ON N.staff_no = S.staff_no
         WHERE N.ward_id = ?",
        [$wardId]
    ));

    $nurses['non_reg'] = fetchAll(executeQuery($conn,
        "SELECT S.f_name + ' ' + S.l_name AS full_name
         FROM NURSE N
         JOIN NON_REG_NURSE NR ON N.staff_no = NR.staff_no
         JOIN STAFF          S ON N.staff_no  = S.staff_no
         WHERE N.ward_id = ?",
        [$wardId]
    ));

    $patients = fetchAll(executeQuery($conn,
        "SELECT P.patient_no, P.patient_name,
                CU.unit_label AS care_unit,
                A.bed_no, A.date_admitted,
                SC.f_name + ' ' + SC.l_name AS consultant_name
         FROM PATIENT P
         JOIN CARE_UNIT   CU  ON P.care_unit_no      = CU.care_unit_no
         JOIN ADMISSION   A   ON A.patient_no         = P.patient_no AND A.date_discharged IS NULL
         JOIN DOCTOR      D   ON P.doctor_staff_no    = D.staff_no
         LEFT JOIN CONSULTANT CON ON D.consultant_staff_no = CON.staff_no
         LEFT JOIN STAFF      SC  ON CON.staff_no          = SC.staff_no
         WHERE CU.ward_id = ?
         ORDER BY A.date_admitted",
        [$wardId]
    ));
}

function nameList(array $rows, string $key = 'full_name'): string {
    return implode(', ', array_column($rows, $key)) ?: '—';
}

$pageTitle = 'Ward Record';
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
        max-width: 360px;
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
        min-width: 130px;
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
        <div class="form-sub">Ward Record</div>
    </div>

    <!-- Ward selector -->
    <div class="rec-selector no-print">
        <label for="ward-select">Input Ward Name:</label>
        <select id="ward-select" onchange="loadWard(this.value)">
            <option value="">— Select Ward —</option>
            <?php foreach ($allWards as $w): ?>
            <option value="<?= $w['ward_id'] ?>"
                <?= $w['ward_id'] == $wardId ? 'selected' : '' ?>>
                <?= htmlspecialchars($w['ward_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($ward): ?>
        <button class="btn btn-gold btn-sm" onclick="window.print()">🖨 Print</button>
        <?php endif; ?>
    </div>

    <div class="rec-card">

        <?php if (!$ward): ?>
        <div class="rec-empty">
            <div class="icon">🏥</div>
            <p>Select a ward name above to view the ward record.</p>
        </div>

        <?php else: ?>

        <!-- Ward header fields -->
        <div class="rec-header">
            <div class="rec-header-col">
                <div class="field-row">
                    <span class="field-label">Ward Name:</span>
                    <span class="field-value"><?= htmlspecialchars($ward['ward_name']) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Specialty:</span>
                    <span class="field-value"><?= htmlspecialchars($ward['specialty_name']) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Day Sister:</span>
                    <span class="field-value"><?= htmlspecialchars($nurses['day_sister']['full_name'] ?? '—') ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Night Sister:</span>
                    <span class="field-value"><?= htmlspecialchars($nurses['night_sister']['full_name'] ?? '—') ?></span>
                </div>
            </div>
            <div class="rec-header-col">
                <div class="field-row">
                    <span class="field-label">Staff Nurses:</span>
                    <span class="field-value"><?= htmlspecialchars(nameList($nurses['staff'])) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Non-Reg Nurses:</span>
                    <span class="field-value"><?= htmlspecialchars(nameList($nurses['non_reg'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="rec-section">Patient Information</div>

        <div style="overflow-x:auto">
            <?php if (empty($patients)): ?>
            <div class="rec-empty" style="padding:32px">
                <p>No currently admitted patients in this ward.</p>
            </div>
            <?php else: ?>
            <table class="rec-table">
                <thead>
                    <tr>
                        <th>Patient No</th>
                        <th>Patient Name</th>
                        <th>Care Unit</th>
                        <th>Bed No</th>
                        <th>Consultant</th>
                        <th>Date Admitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= $p['patient_no'] ?></td>
                        <td class="fw-600"><?= htmlspecialchars($p['patient_name']) ?></td>
                        <td><?= htmlspecialchars($p['care_unit']) ?></td>
                        <td><?= $p['bed_no'] ?></td>
                        <td><?= htmlspecialchars($p['consultant_name'] ?? '—') ?></td>
                        <td><?= $p['date_admitted'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="rec-actions no-print">
            <a href="<?= BASE_URL ?>/forms/wards.php"      class="btn btn-secondary btn-sm">✏ Edit Ward</a>
            <a href="<?= BASE_URL ?>/forms/admissions.php" class="btn btn-secondary btn-sm">🛏 Manage Admissions</a>
            <button class="btn btn-gold btn-sm"            onclick="window.print()">🖨 Print Record</button>
        </div>

        <?php endif; ?>
    </div><!-- .rec-card -->
</div><!-- .rec-wrapper -->

<script>
function loadWard(wardId) {
    if (!wardId) return;
    window.location.href = `<?= BASE_URL ?>/reports/ward_record.php?ward_id=${wardId}`;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>