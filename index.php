<?php
/**
 * Dashboard — IVOR Paine Memorial Hospital
 */
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Dashboard';

// ── Fetch counts for stat cards ────────────────────
$conn = getConnection();

function getCount(mixed $conn, string $table): int {
    $stmt = executeQuery($conn, "SELECT COUNT(*) AS cnt FROM $table");
    $row  = fetchOne($stmt);
    return (int)($row['cnt'] ?? 0);
}

$totalPatients  = getCount($conn, 'PATIENT');
$totalDoctors   = getCount($conn, 'DOCTOR');
$totalNurses    = getCount($conn, 'NURSE');
$totalWards     = getCount($conn, 'WARD');
$totalBeds      = getCount($conn, 'BED');
$availableBeds  = fetchOne(executeQuery($conn, "SELECT COUNT(*) AS cnt FROM BED WHERE bed_status='Available'"))['cnt'] ?? 0;
$activeAdmissions = fetchOne(executeQuery($conn, "SELECT COUNT(*) AS cnt FROM ADMISSION WHERE date_discharged IS NULL"))['cnt'] ?? 0;
$activeComplaints = fetchOne(executeQuery($conn, "SELECT COUNT(*) AS cnt FROM PATIENT_COMPLAINT WHERE status='Active'"))['cnt'] ?? 0;

// Recent admissions
$recentStmt = executeQuery($conn,
    "SELECT TOP 8 P.patient_name, A.date_admitted, W.ward_name, B.bed_no
     FROM ADMISSION A
     JOIN PATIENT P ON A.patient_no = P.patient_no
     JOIN BED     B ON A.bed_no = B.bed_no
     JOIN WARD    W ON B.ward_id = W.ward_id
     WHERE A.date_discharged IS NULL
     ORDER BY A.date_admitted DESC"
);
$recentAdmissions = fetchAll($recentStmt);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon teal">🧑‍⚕️</div>
        <div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-value"><?= $totalPatients ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">👨‍⚕️</div>
        <div>
            <div class="stat-label">Doctors</div>
            <div class="stat-value"><?= $totalDoctors ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">👩‍⚕️</div>
        <div>
            <div class="stat-label">Nurses</div>
            <div class="stat-value"><?= $totalNurses ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal">🏥</div>
        <div>
            <div class="stat-label">Wards</div>
            <div class="stat-value"><?= $totalWards ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">🛏</div>
        <div>
            <div class="stat-label">Available Beds</div>
            <div class="stat-value"><?= $availableBeds ?> <span class="text-sm text-muted">/ <?= $totalBeds ?></span></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">🩺</div>
        <div>
            <div class="stat-label">Active Admissions</div>
            <div class="stat-value"><?= $activeAdmissions ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">📋</div>
        <div>
            <div class="stat-label">Active Complaints</div>
            <div class="stat-value"><?= $activeComplaints ?></div>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="card mb-6">
    <div class="card-header">
        <span class="card-title">Quick Actions</span>
    </div>
    <div class="card-body flex gap-3" style="flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/forms/patients.php"    class="btn btn-primary">➕ New Patient</a>
        <a href="<?= BASE_URL ?>/forms/admissions.php"  class="btn btn-gold">🛏 New Admission</a>
        <a href="<?= BASE_URL ?>/forms/complaints.php"  class="btn btn-secondary">📋 Log Complaint</a>
        <a href="<?= BASE_URL ?>/forms/treatments.php"  class="btn btn-secondary">💊 Log Treatment</a>
        <a href="<?= BASE_URL ?>/reports/index.php"     class="btn btn-secondary">📈 View Reports</a>
    </div>
</div>

<!-- Recent Admissions Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Active Admissions</span>
        <a href="<?= BASE_URL ?>/forms/admissions.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Ward</th>
                        <th>Bed #</th>
                        <th>Date Admitted</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentAdmissions)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-light);padding:32px">No active admissions.</td></tr>
                    <?php else: ?>
                    <?php foreach ($recentAdmissions as $row): ?>
                    <tr>
                        <td class="fw-600"><?= htmlspecialchars($row['patient_name']) ?></td>
                        <td><?= htmlspecialchars($row['ward_name']) ?></td>
                        <td><?= $row['bed_no'] ?></td>
                        <td><?= $row['date_admitted'] ?></td>
                        <td><span class="badge badge-green">Active</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
