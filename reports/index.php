<?php
/**
 * Reports — All 12 Queries
 * Each query can be run independently; some accept parameters.
 */
require_once __DIR__ . '/../includes/db.php';

// ── Active report from URL ──────────────────────────
$reportId = (int)($_GET['report'] ?? 1);

// ── Parameter inputs ────────────────────────────────
$doctorNo      = (int)($_GET['doctor_no']       ?? 0);
$patientNo     = (int)($_GET['patient_no']      ?? 0);
$complaintCode = trim($_GET['complaint_code']   ?? '');
$dateFrom      = trim($_GET['date_from']        ?? '');
$dateTo        = trim($_GET['date_to']          ?? '');

$conn = getConnection();

// ─────────────────────────────────────────────────────
// SQL QUERY DEFINITIONS
// Each entry: title, description, params_needed, sql, params[]
// ─────────────────────────────────────────────────────
$reports = [

    // 1. Consultants and their team
    1 => [
        'title' => 'Consultants & Their Doctor Teams',
        'desc'  => 'A list of consultants and the doctors in their team.',
        'cols'  => ['Consultant', 'Specialty', 'Doctor Name', 'Position', 'Date Joined'],
        'sql'   => "SELECT
                        SC.f_name + ' ' + SC.l_name  AS consultant_name,
                        SP.specialty_name,
                        SD.f_name + ' ' + SD.l_name  AS doctor_name,
                        P.position_name,
                        D.date_joined_team
                    FROM CONSULTANT CON
                    JOIN STAFF      SC  ON CON.staff_no       = SC.staff_no
                    JOIN SPECIALTY  SP  ON CON.specialty_id   = SP.specialty_id
                    JOIN DOCTOR     D   ON D.consultant_staff_no = CON.staff_no
                    JOIN STAFF      SD  ON D.staff_no          = SD.staff_no
                    JOIN POSITION   P   ON D.position_code     = P.position_code
                    ORDER BY SC.l_name, SD.l_name",
        'params'  => [],
        'filters' => [],
    ],

    // 2. Wards with sisters, care units, staff nurses
    2 => [
        'title' => 'Ward Details — Sisters, Care Units & Staff Nurses',
        'desc'  => 'A list of wards with respective sisters, care units and staff nurses in charge of care units.',
        'cols'  => ['Ward', 'Specialty', 'Day Sister', 'Night Sister', 'Care Unit', 'Staff Nurse In-Charge'],
        'sql'   => "SELECT
                        W.ward_name,
                        SP.specialty_name,
                        SDS.f_name + ' ' + SDS.l_name  AS day_sister,
                        SNS.f_name + ' ' + SNS.l_name  AS night_sister,
                        CU.unit_label                   AS care_unit,
                        SSN.f_name + ' ' + SSN.l_name  AS staff_nurse
                    FROM WARD W
                    JOIN SPECIALTY  SP   ON W.specialty_id   = SP.specialty_id
                    LEFT JOIN DAY_SISTER   DS  ON DS.ward_id  = W.ward_id
                    LEFT JOIN STAFF        SDS ON DS.staff_no = SDS.staff_no
                    LEFT JOIN NIGHT_SISTER NS  ON NS.ward_id  = W.ward_id
                    LEFT JOIN STAFF        SNS ON NS.staff_no = SNS.staff_no
                    LEFT JOIN CARE_UNIT    CU  ON CU.ward_id  = W.ward_id
                    LEFT JOIN STAFF_NURSE  SN  ON SN.staff_no = CU.staff_nurse_staff_no
                    LEFT JOIN STAFF        SSN ON SN.staff_no = SSN.staff_no
                    ORDER BY W.ward_name, CU.unit_label",
        'params'  => [],
        'filters' => [],
    ],

    // 3. Patients with complaints, treatments, dates
    3 => [
        'title' => 'Patients — Complaints, Treatments & Dates',
        'desc'  => 'A list of patients and their complaints, treatments and dates of treatment.',
        'cols'  => ['Patient', 'Complaint', 'Treatment', 'Treating Doctor', 'Start Date', 'End Date', 'Status'],
        'sql'   => "SELECT
                        P.patient_name,
                        C.complaint_name,
                        T.treatment_name,
                        ST.f_name + ' ' + ST.l_name  AS treating_doctor,
                        TH.treatment_start_date,
                        TH.treatment_end_date,
                        PC.status
                    FROM PATIENT P
                    JOIN PATIENT_COMPLAINT  PC  ON PC.patient_no         = P.patient_no
                    JOIN COMPLAINT          C   ON C.complaint_code       = PC.complaint_code
                    JOIN TREATMENT_HISTORY  TH  ON TH.patient_complaint_id= PC.patient_complaint_id
                    JOIN TREATMENT          T   ON T.treatment_code       = TH.treatment_code
                    JOIN DOCTOR             DT  ON TH.doctor_staff_no     = DT.staff_no
                    JOIN STAFF              ST  ON DT.staff_no            = ST.staff_no
                    ORDER BY P.patient_name, TH.treatment_start_date",
        'params'  => [],
        'filters' => [],
    ],

    // 4. Junior housemen, their patients, and care unit staff nurse
    4 => [
        'title' => 'Junior Housemen — Patients & Staff Nurses',
        'desc'  => 'A list of junior housemen and their patients and the staff nurse for the care-unit of that patient.',
        'cols'  => ['Junior Houseman', 'Patient', 'Care Unit', 'Staff Nurse In-Charge'],
        'sql'   => "SELECT
                        SD.f_name + ' ' + SD.l_name  AS junior_houseman,
                        P.patient_name,
                        CU.unit_label                 AS care_unit,
                        SSN.f_name + ' ' + SSN.l_name AS staff_nurse
                    FROM DOCTOR     D
                    JOIN POSITION   POS ON D.position_code       = POS.position_code
                    JOIN STAFF      SD  ON D.staff_no             = SD.staff_no
                    JOIN PATIENT    P   ON P.doctor_staff_no      = D.staff_no
                    JOIN CARE_UNIT  CU  ON P.care_unit_no         = CU.care_unit_no
                    LEFT JOIN STAFF_NURSE SN  ON SN.staff_no = CU.staff_nurse_staff_no
                    LEFT JOIN STAFF       SSN ON SN.staff_no = SSN.staff_no
                    WHERE POS.position_code = 'POS_JH'
                    ORDER BY SD.l_name, P.patient_name",
        'params'  => [],
        'filters' => [],
    ],

    // 5. Consultants with unique specialty
    5 => [
        'title' => 'Consultants with a Unique Specialty',
        'desc'  => 'A list of consultants whose specialty is held by only one consultant.',
        'cols'  => ['Consultant', 'Specialty', 'Description'],
        'sql'   => "SELECT
                        S.f_name + ' ' + S.l_name  AS consultant_name,
                        SP.specialty_name,
                        SP.description
                    FROM CONSULTANT CON
                    JOIN STAFF      S  ON CON.staff_no     = S.staff_no
                    JOIN SPECIALTY  SP ON CON.specialty_id = SP.specialty_id
                    WHERE CON.specialty_id IN (
                        SELECT specialty_id FROM CONSULTANT
                        GROUP BY specialty_id HAVING COUNT(*) = 1
                    )
                    ORDER BY SP.specialty_name",
        'params'  => [],
        'filters' => [],
    ],

    // 6. Complaints + treatments + experience of treating doctor
    6 => [
        'title' => 'Complaints, Treatments & Doctor\'s Experience',
        'desc'  => 'A list of complaints, treatments given, and the experience history of the treating doctor.',
        'cols'  => ['Complaint', 'Treatment', 'Treating Doctor', 'Experience: From', 'Experience: To', 'Position Held', 'Establishment'],
        'sql'   => "SELECT
                        C.complaint_name,
                        T.treatment_name,
                        ST.f_name + ' ' + ST.l_name  AS treating_doctor,
                        EH.from_date,
                        EH.to_date,
                        EH.position_held,
                        EH.establishment
                    FROM PATIENT_COMPLAINT  PC
                    JOIN COMPLAINT          C   ON C.complaint_code        = PC.complaint_code
                    JOIN TREATMENT_HISTORY  TH  ON TH.patient_complaint_id = PC.patient_complaint_id
                    JOIN TREATMENT          T   ON T.treatment_code        = TH.treatment_code
                    JOIN DOCTOR             DT  ON TH.doctor_staff_no      = DT.staff_no
                    JOIN STAFF              ST  ON DT.staff_no             = ST.staff_no
                    LEFT JOIN EXPERIENCE_HISTORY EH ON EH.doctor_staff_no  = DT.staff_no
                    ORDER BY C.complaint_name, T.treatment_name",
        'params'  => [],
        'filters' => [],
    ],

    // 7. Patients with more than one complaint and their treatments
    7 => [
        'title' => 'Patients with Multiple Complaints & Their Treatments',
        'desc'  => 'A list of patients with more than one complaint and their treatments.',
        'cols'  => ['Patient', 'Total Complaints', 'Complaint', 'Treatment', 'Start Date'],
        'sql'   => "SELECT
                        P.patient_name,
                        (SELECT COUNT(*) FROM PATIENT_COMPLAINT PC2 WHERE PC2.patient_no = P.patient_no) AS total_complaints,
                        C.complaint_name,
                        T.treatment_name,
                        TH.treatment_start_date
                    FROM PATIENT P
                    JOIN PATIENT_COMPLAINT  PC  ON PC.patient_no          = P.patient_no
                    JOIN COMPLAINT          C   ON C.complaint_code        = PC.complaint_code
                    JOIN TREATMENT_HISTORY  TH  ON TH.patient_complaint_id = PC.patient_complaint_id
                    JOIN TREATMENT          T   ON T.treatment_code        = TH.treatment_code
                    WHERE P.patient_no IN (
                        SELECT patient_no FROM PATIENT_COMPLAINT
                        GROUP BY patient_no HAVING COUNT(*) > 1
                    )
                    ORDER BY P.patient_name, C.complaint_name",
        'params'  => [],
        'filters' => [],
    ],

    // 8. Patients grouped by treatment within complaint
    8 => [
        'title' => 'Patients Grouped by Treatment within Complaint',
        'desc'  => 'A list of patients grouped by treatment within complaint.',
        'cols'  => ['Complaint', 'Treatment', 'Patient', 'Doctor', 'Start Date', 'End Date'],
        'sql'   => "SELECT
                        C.complaint_name,
                        T.treatment_name,
                        P.patient_name,
                        ST.f_name + ' ' + ST.l_name  AS doctor_name,
                        TH.treatment_start_date,
                        TH.treatment_end_date
                    FROM PATIENT_COMPLAINT  PC
                    JOIN COMPLAINT          C   ON C.complaint_code        = PC.complaint_code
                    JOIN TREATMENT_HISTORY  TH  ON TH.patient_complaint_id = PC.patient_complaint_id
                    JOIN TREATMENT          T   ON T.treatment_code        = TH.treatment_code
                    JOIN PATIENT            P   ON PC.patient_no           = P.patient_no
                    JOIN DOCTOR             DT  ON TH.doctor_staff_no      = DT.staff_no
                    JOIN STAFF              ST  ON DT.staff_no             = ST.staff_no
                    ORDER BY C.complaint_name, T.treatment_name, P.patient_name",
        'params'  => [],
        'filters' => [],
    ],

    // 9. Performance history for a particular doctor
    9 => [
        'title' => 'Performance History for a Doctor',
        'desc'  => 'A performance history for a particular doctor. Select a doctor to filter.',
        'cols'  => ['Doctor', 'Review Date', 'Grade', 'Remarks', 'Reviewed By'],
        'sql'   => "SELECT
                        SD.f_name + ' ' + SD.l_name  AS doctor_name,
                        PR.review_date,
                        PR.grade,
                        PR.remarks,
                        SC.f_name + ' ' + SC.l_name  AS consultant_name
                    FROM PERFORMANCE_REVIEW PR
                    JOIN STAFF SD ON PR.doctor_staff_no     = SD.staff_no
                    JOIN STAFF SC ON PR.consultant_staff_no = SC.staff_no
                    {WHERE_CLAUSE}
                    ORDER BY SD.l_name, PR.review_date",
        'params'  => [],
        'filters' => ['doctor'],
    ],

    // 10. Full medical details for a particular patient
    10 => [
        'title' => 'Full Medical Details for a Patient',
        'desc'  => 'Full medical details for a particular patient. Select a patient to filter.',
        'cols'  => ['Patient', 'DOB', 'Gender', 'Ward', 'Care Unit', 'Bed', 'Date Admitted', 'Doctor', 'Consultant', 'Complaint', 'Treatment', 'Treat. Start', 'Treat. End'],
        'sql'   => "SELECT
                        P.patient_name,
                        P.date_of_birth,
                        P.gender,
                        W.ward_name,
                        CU.unit_label  AS care_unit,
                        A.bed_no,
                        A.date_admitted,
                        SD.f_name + ' ' + SD.l_name  AS doctor_name,
                        SC.f_name + ' ' + SC.l_name  AS consultant_name,
                        C.complaint_name,
                        T.treatment_name,
                        TH.treatment_start_date,
                        TH.treatment_end_date
                    FROM PATIENT P
                    JOIN CARE_UNIT          CU  ON P.care_unit_no          = CU.care_unit_no
                    JOIN WARD               W   ON CU.ward_id              = W.ward_id
                    LEFT JOIN ADMISSION     A   ON A.patient_no            = P.patient_no AND A.date_discharged IS NULL
                    JOIN DOCTOR             D   ON P.doctor_staff_no       = D.staff_no
                    JOIN STAFF              SD  ON D.staff_no              = SD.staff_no
                    LEFT JOIN CONSULTANT    CON ON D.consultant_staff_no   = CON.staff_no
                    LEFT JOIN STAFF         SC  ON CON.staff_no            = SC.staff_no
                    LEFT JOIN PATIENT_COMPLAINT  PC  ON PC.patient_no      = P.patient_no
                    LEFT JOIN COMPLAINT          C   ON C.complaint_code   = PC.complaint_code
                    LEFT JOIN TREATMENT_HISTORY  TH  ON TH.patient_complaint_id = PC.patient_complaint_id
                    LEFT JOIN TREATMENT          T   ON T.treatment_code   = TH.treatment_code
                    {WHERE_CLAUSE}
                    ORDER BY C.complaint_name, TH.treatment_start_date",
        'params'  => [],
        'filters' => ['patient'],
    ],

    // 11. Treatments for a complaint between two dates
    11 => [
        'title' => 'Treatments for a Complaint Between Two Dates',
        'desc'  => 'A list of treatments given for a particular complaint between two given dates, ordered by treatment.',
        'cols'  => ['Complaint', 'Treatment', 'Patient', 'Doctor', 'Start Date', 'End Date'],
        'sql'   => "SELECT
                        C.complaint_name,
                        T.treatment_name,
                        P.patient_name,
                        ST.f_name + ' ' + ST.l_name  AS doctor_name,
                        TH.treatment_start_date,
                        TH.treatment_end_date
                    FROM PATIENT_COMPLAINT  PC
                    JOIN COMPLAINT          C   ON C.complaint_code        = PC.complaint_code
                    JOIN TREATMENT_HISTORY  TH  ON TH.patient_complaint_id = PC.patient_complaint_id
                    JOIN TREATMENT          T   ON T.treatment_code        = TH.treatment_code
                    JOIN PATIENT            P   ON PC.patient_no           = P.patient_no
                    JOIN DOCTOR             DT  ON TH.doctor_staff_no      = DT.staff_no
                    JOIN STAFF              ST  ON DT.staff_no             = ST.staff_no
                    {WHERE_CLAUSE}
                    ORDER BY T.treatment_name, TH.treatment_start_date",
        'params'  => [],
        'filters' => ['complaint', 'date_range'],
    ],

    // 12. Positions and count of staff
    12 => [
        'title' => 'Staff Positions & Count',
        'desc'  => 'A list of the different positions held by staff in the hospital and a count of the number of staff in each position.',
        'cols'  => ['Position', 'Staff Count'],
        'sql'   => "SELECT
                        P.position_name,
                        COUNT(D.staff_no) AS staff_count
                    FROM POSITION P
                    LEFT JOIN DOCTOR D ON D.position_code = P.position_code
                    GROUP BY P.position_name
                    ORDER BY staff_count DESC, P.position_name",
        'params'  => [],
        'filters' => [],
    ],
];

// ── Build & execute selected query ─────────────────
$currentReport = $reports[$reportId];
$results       = [];
$queryError    = '';

// Load lookup data for filter dropdowns
$allDoctors   = fetchAll(executeQuery($conn,
    "SELECT D.staff_no, S.f_name + ' ' + S.l_name AS dname FROM DOCTOR D JOIN STAFF S ON D.staff_no=S.staff_no ORDER BY S.l_name"
));
$allPatients  = fetchAll(executeQuery($conn,
    "SELECT patient_no, patient_name FROM PATIENT ORDER BY patient_name"
));
$allComplaints = fetchAll(executeQuery($conn,
    "SELECT complaint_code, complaint_name FROM COMPLAINT ORDER BY complaint_name"
));

// Build dynamic SQL
function buildQuery(array $report, int $reportId, int $doctorNo, int $patientNo, string $complaintCode, string $dateFrom, string $dateTo): array {
    $sql    = $report['sql'];
    $params = [];
    $where  = [];

    if (in_array('doctor', $report['filters']) && $doctorNo > 0) {
        $where[]  = "PR.doctor_staff_no = ?";
        $params[] = $doctorNo;
    }
    if (in_array('patient', $report['filters']) && $patientNo > 0) {
        $where[]  = "P.patient_no = ?";
        $params[] = $patientNo;
    }
    if (in_array('complaint', $report['filters']) && $complaintCode !== '') {
        $where[]  = "PC.complaint_code = ?";
        $params[] = $complaintCode;
    }
    if (in_array('date_range', $report['filters'])) {
        if ($dateFrom) { $where[] = "TH.treatment_start_date >= ?"; $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = "TH.treatment_start_date <= ?"; $params[] = $dateTo;   }
    }

    $whereStr = $where ? "WHERE " . implode(" AND ", $where) : "";
    $sql      = str_replace('{WHERE_CLAUSE}', $whereStr, $sql);

    return [$sql, $params];
}

try {
    [$sql, $params] = buildQuery($currentReport, $reportId, $doctorNo, $patientNo, $complaintCode, $dateFrom, $dateTo);
    $stmt    = executeQuery($conn, $sql, $params);
    $results = fetchAll($stmt);
} catch (Throwable $e) {
    $queryError = $e->getMessage();
}

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex gap-3 mb-6" style="flex-wrap:wrap;align-items:flex-start">

    <!-- Report selector sidebar -->
    <div style="width:260px;flex-shrink:0">
        <div class="card">
            <div class="card-header"><span class="card-title">Reports</span></div>
            <div style="padding:8px">
                <?php foreach ($reports as $id => $r): ?>
                <a href="?report=<?= $id ?>"
                   style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;
                          color:<?= $id === $reportId ? '#fff' : 'var(--text-dark)' ?>;
                          background:<?= $id === $reportId ? 'var(--teal)' : 'transparent' ?>;
                          font-size:.875rem;margin-bottom:2px;transition:background .15s"
                   onmouseover="if(this.style.background==='transparent')this.style.background='var(--teal-pale)'"
                   onmouseout="if('<?= $id === $reportId ? 'active' : '' ?>'!=='active')this.style.background='transparent'">
                    <span style="font-size:1rem;width:22px;text-align:center;flex-shrink:0"><?= $id ?></span>
                    <span><?= htmlspecialchars($r['title']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Manual Records -->
        <div class="card mt-4">
            <div class="card-header"><span class="card-title">Manual Records</span></div>
            <div style="padding:8px">
                <a href="<?= BASE_URL ?>/reports/patient_record.php"
                   style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:var(--text-dark);font-size:.875rem;margin-bottom:2px">
                    🧑‍⚕️ Patient Record
                </a>
                <a href="<?= BASE_URL ?>/reports/ward_record.php"
                   style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:var(--text-dark);font-size:.875rem;margin-bottom:2px">
                    🏥 Ward Record
                </a>
                <a href="<?= BASE_URL ?>/reports/consultant_team_record.php"
                   style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:var(--text-dark);font-size:.875rem">
                    👨‍⚕️ Consultant Team Record
                </a>
            </div>
        </div>
    </div>

    <!-- Report content -->
    <div style="flex:1;min-width:0">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title"><?= htmlspecialchars($currentReport['title']) ?></div>
                    <div class="text-sm text-muted mt-4" style="margin-top:4px"><?= htmlspecialchars($currentReport['desc']) ?></div>
                </div>
                <button class="btn btn-sm btn-secondary no-print" onclick="window.print()">🖨 Print</button>
            </div>

            <!-- Filter panel (only shown for reports that need params) -->
            <?php if (!empty($currentReport['filters'])): ?>
            <div class="card-body" style="background:var(--cream);border-bottom:1px solid var(--border);padding:16px 24px">
                <form method="GET" style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end">
                    <input type="hidden" name="report" value="<?= $reportId ?>">

                    <?php if (in_array('doctor', $currentReport['filters'])): ?>
                    <div class="form-group" style="min-width:220px;margin:0">
                        <label>Doctor</label>
                        <select name="doctor_no">
                            <option value="0">All Doctors</option>
                            <?php foreach ($allDoctors as $d): ?>
                            <option value="<?= $d['staff_no'] ?>" <?= $d['staff_no'] == $doctorNo ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['dname']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array('patient', $currentReport['filters'])): ?>
                    <div class="form-group" style="min-width:220px;margin:0">
                        <label>Patient</label>
                        <select name="patient_no">
                            <option value="0">All Patients</option>
                            <?php foreach ($allPatients as $p): ?>
                            <option value="<?= $p['patient_no'] ?>" <?= $p['patient_no'] == $patientNo ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['patient_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array('complaint', $currentReport['filters'])): ?>
                    <div class="form-group" style="min-width:200px;margin:0">
                        <label>Complaint</label>
                        <select name="complaint_code">
                            <option value="">All Complaints</option>
                            <?php foreach ($allComplaints as $c): ?>
                            <option value="<?= $c['complaint_code'] ?>" <?= $c['complaint_code'] === $complaintCode ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['complaint_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array('date_range', $currentReport['filters'])): ?>
                    <div class="form-group" style="min-width:160px;margin:0">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="form-group" style="min-width:160px;margin:0">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary">🔍 Run Report</button>
                    <a href="?report=<?= $reportId ?>" class="btn btn-secondary">↺ Reset</a>
                </form>
            </div>
            <?php endif; ?>

            <!-- Result table -->
            <?php if ($queryError): ?>
            <div class="card-body">
                <div class="alert alert-error">Query error: <?= htmlspecialchars($queryError) ?></div>
            </div>

            <?php elseif (empty($results)): ?>
            <div class="card-body" style="text-align:center;padding:48px;color:var(--text-light)">
                <div style="font-size:2rem;margin-bottom:10px">📋</div>
                <p>No results found<?= (!empty($currentReport['filters'])) ? ' for the current filter.' : '.' ?></p>
            </div>

            <?php else: ?>
            <div style="padding:12px 24px 8px;color:var(--text-light);font-size:.8rem">
                <?= count($results) ?> record<?= count($results) !== 1 ? 's' : '' ?> found
            </div>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($currentReport['cols'] as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                        <tr>
                            <?php foreach (array_values($row) as $i => $cell): ?>
                            <td <?= $i === 0 ? 'class="fw-600"' : '' ?>>
                                <?php
                                // Grade badge for report 9
                                if ($reportId === 9 && $currentReport['cols'][$i] === 'Grade' && $cell) {
                                    $gc = match($cell) { 'A'=>'badge-green','B'=>'badge-teal','C'=>'badge-gold',default=>'badge-navy' };
                                    echo "<span class=\"badge $gc\">$cell</span>";
                                }
                                // Status badge for report 3
                                elseif ($reportId === 3 && $currentReport['cols'][$i] === 'Status' && $cell) {
                                    $sc = match($cell) { 'Active'=>'badge-green','Critical'=>'badge-red','Resolved'=>'badge-navy',default=>'badge-teal' };
                                    echo "<span class=\"badge $sc\">$cell</span>";
                                }
                                // Count badge for report 7 & 12
                                elseif (in_array($reportId, [7,12]) && str_contains(strtolower($currentReport['cols'][$i]), 'count')) {
                                    echo "<span class=\"badge badge-teal\">$cell</span>";
                                }
                                else {
                                    echo htmlspecialchars($cell ?? '—');
                                }
                                ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div><!-- .card -->
    </div><!-- report content -->
</div>

<style>
@media print {
    .sidebar, .topbar, .no-print { display: none !important; }
    .main-content { margin-left: 0; }
    .content-area { padding: 0; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
