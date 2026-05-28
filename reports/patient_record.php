<?php
/**
 * PATIENT RECORD — Manual Record Form
 * Input: Patient No  →  displays full patient record with medical history
 */
require_once __DIR__ . '/../includes/db.php';

$patientNo   = (int)($_GET['patient_no'] ?? 0);
$patient     = null;
$medHistory  = [];
$allPatients = [];

$conn = getConnection();

$allPatients = fetchAll(executeQuery($conn,
    "SELECT patient_no, patient_name FROM PATIENT ORDER BY patient_name"
));

if ($patientNo > 0) {
    $patient = fetchOne(executeQuery($conn,
        "SELECT P.*,
                SD.f_name + ' ' + SD.l_name AS doctor_name,
                D.staff_no AS doctor_no,
                SC.f_name + ' ' + SC.l_name AS consultant_name
         FROM PATIENT P
         LEFT JOIN DOCTOR     D   ON P.doctor_staff_no     = D.staff_no
         LEFT JOIN STAFF      SD  ON D.staff_no             = SD.staff_no
         LEFT JOIN CONSULTANT CON ON D.consultant_staff_no  = CON.staff_no
         LEFT JOIN STAFF      SC  ON CON.staff_no           = SC.staff_no
         WHERE P.patient_no = ?",
        [$patientNo]
    ));

    $medHistory = fetchAll(executeQuery($conn,
        "SELECT C.complaint_code, C.complaint_name,
                T.treatment_code, T.treatment_name,
                ST.f_name + ' ' + ST.l_name AS treating_doctor,
                TH.treatment_start_date, TH.treatment_end_date,
                PC.status AS complaint_status
         FROM PATIENT_COMPLAINT PC
         JOIN COMPLAINT         C   ON PC.complaint_code       = C.complaint_code
         JOIN TREATMENT_HISTORY TH  ON TH.patient_complaint_id = PC.patient_complaint_id
         JOIN TREATMENT         T   ON TH.treatment_code       = T.treatment_code
         JOIN DOCTOR            DT  ON TH.doctor_staff_no      = DT.staff_no
         JOIN STAFF             ST  ON DT.staff_no             = ST.staff_no
         WHERE PC.patient_no = ?
         ORDER BY TH.treatment_start_date DESC",
        [$patientNo]
    ));
}

$pageTitle = 'Patient Record';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* ── Record layout ── */
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

    /* Selector bar */
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
        max-width: 380px;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid rgba(255,255,255,.18);
        background: rgba(255,255,255,.08);
        color: #fff;
        font-family: var(--font-body);
        font-size: .9rem;
    }
    .rec-selector select option { background: #1b2e44; color: #fff; }

    /* Card body */
    .rec-card {
        background: var(--warm-white);
        border: 1px solid var(--border);
        border-top: none;
        border-radius: 0 0 var(--radius) var(--radius);
        box-shadow: var(--shadow-md);
    }

    /* Header fields grid */
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
        min-width: 115px;
    }
    .field-value {
        font-size: .93rem;
        color: var(--navy);
        font-weight: 500;
        border-bottom: 1px solid var(--border);
        flex: 1;
        padding-bottom: 2px;
    }

    /* Section divider */
    .rec-section {
        background: var(--navy);
        color: #fff;
        padding: 9px 28px;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    /* Table */
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

    /* Empty / action */
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
        .rec-selector, .rec-actions, .sidebar, .topbar { display: none !important; }
        .main-content { margin-left: 0 !important; }
    }
    @media (max-width: 640px) {
        .rec-header { grid-template-columns: 1fr; }
        .rec-header-col { border-right: none; border-bottom: 1px solid var(--border); }
    }
</style>

<div class="rec-wrapper">

    <div class="mb-4 no-print" style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>/index.php"          class="btn btn-secondary btn-sm">← Dashboard</a>
        <a href="<?= BASE_URL ?>/reports/index.php"  class="btn btn-secondary btn-sm">📈 All Reports</a>
    </div>

    <!-- Letterhead -->
    <div class="rec-letterhead">
        <div class="cross">⚕</div>
        <h2>IVOR PAINE MEMORIAL HOSPITAL</h2>
        <div class="form-sub">Patient Record</div>
    </div>

    <!-- Patient selector -->
    <div class="rec-selector no-print">
        <label for="patient-select">Input Patient No:</label>
        <select id="patient-select" onchange="loadPatient(this.value)">
            <option value="">— Select Patient —</option>
            <?php foreach ($allPatients as $p): ?>
            <option value="<?= $p['patient_no'] ?>"
                <?= $p['patient_no'] == $patientNo ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['patient_no'] . ' — ' . $p['patient_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($patient): ?>
        <button class="btn btn-gold btn-sm" onclick="window.print()">🖨 Print</button>
        <?php endif; ?>
    </div>

    <div class="rec-card">

        <?php if (!$patient): ?>
        <div class="rec-empty">
            <div class="icon">🧑‍⚕️</div>
            <p>Select a patient number above to view their record.</p>
        </div>

        <?php else: ?>

        <!-- Header fields -->
        <div class="rec-header">
            <div class="rec-header-col">
                <div class="field-row">
                    <span class="field-label">Patient No:</span>
                    <span class="field-value"><?= $patient['patient_no'] ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Patient Name:</span>
                    <span class="field-value"><?= htmlspecialchars($patient['patient_name']) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Date of Birth:</span>
                    <span class="field-value"><?= $patient['date_of_birth'] ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Gender:</span>
                    <span class="field-value">
                        <?= match($patient['gender']) { 'M' => 'Male', 'F' => 'Female', default => 'Other' } ?>
                    </span>
                </div>
                <div class="field-row">
                    <span class="field-label">Phone:</span>
                    <span class="field-value"><?= htmlspecialchars($patient['phone'] ?: '—') ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Address:</span>
                    <span class="field-value"><?= htmlspecialchars($patient['address'] ?: '—') ?></span>
                </div>
            </div>
            <div class="rec-header-col">
                <div class="field-row">
                    <span class="field-label">Doctor No:</span>
                    <span class="field-value"><?= $patient['doctor_no'] ?? '—' ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Doctor Name:</span>
                    <span class="field-value"><?= htmlspecialchars($patient['doctor_name'] ?? '—') ?></span>
                </div>
                <div class="field-row">
                    <span class="field-label">Consultant:</span>
                    <span class="field-value"><?= htmlspecialchars($patient['consultant_name'] ?? '—') ?></span>
                </div>
            </div>
        </div>

        <!-- Medical History -->
        <div class="rec-section">Medical History</div>

        <div style="overflow-x:auto">
            <?php if (empty($medHistory)): ?>
            <div class="rec-empty" style="padding:32px">
                <p>No medical history recorded for this patient.</p>
            </div>
            <?php else: ?>
            <table class="rec-table">
                <thead>
                    <tr>
                        <th>Complaint Code</th>
                        <th>Complaint</th>
                        <th>Treatment Code</th>
                        <th>Treatment</th>
                        <th>Doctor</th>
                        <th>Date Started</th>
                        <th>Date Ended</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medHistory as $row): ?>
                    <tr>
                        <td><span class="badge badge-navy"><?= htmlspecialchars($row['complaint_code']) ?></span></td>
                        <td class="fw-600"><?= htmlspecialchars($row['complaint_name']) ?></td>
                        <td><span class="badge badge-teal"><?= htmlspecialchars($row['treatment_code']) ?></span></td>
                        <td><?= htmlspecialchars($row['treatment_name']) ?></td>
                        <td><?= htmlspecialchars($row['treating_doctor']) ?></td>
                        <td><?= $row['treatment_start_date'] ?></td>
                        <td><?= $row['treatment_end_date'] ?: '—' ?></td>
                        <td>
                            <?php
                            $sc = match($row['complaint_status']) {
                                'Active'   => 'badge-green',
                                'Critical' => 'badge-red',
                                'Resolved' => 'badge-navy',
                                default    => 'badge-teal',
                            };
                            ?>
                            <span class="badge <?= $sc ?>"><?= $row['complaint_status'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="rec-actions no-print">
            <a href="<?= BASE_URL ?>/forms/patients.php"   class="btn btn-secondary btn-sm">✏ Edit Patient</a>
            <a href="<?= BASE_URL ?>/forms/complaints.php" class="btn btn-secondary btn-sm">📋 Add Complaint</a>
            <a href="<?= BASE_URL ?>/forms/treatments.php" class="btn btn-secondary btn-sm">💊 Add Treatment</a>
            <button class="btn btn-gold btn-sm"            onclick="window.print()">🖨 Print Record</button>
        </div>

        <?php endif; ?>
    </div><!-- .rec-card -->
</div><!-- .rec-wrapper -->

<script>
function loadPatient(patientNo) {
    if (!patientNo) return;
    window.location.href = `<?= BASE_URL ?>/reports/patient_record.php?patient_no=${patientNo}`;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>