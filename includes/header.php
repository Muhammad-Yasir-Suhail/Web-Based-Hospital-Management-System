<?php
define('BASE_URL', '/DB_Labs/hospital');
?>

<?php
/**
 * Shared HTML header — included at the top of every page.
 * $pageTitle should be set before including this file.
 */
$pageTitle = $pageTitle ?? 'IVOR Hospital';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IVOR Paine Memorial Hospital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</head>
<body>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">⚕</div>
        <div class="brand-text">
            <span class="brand-name">IVOR Paine</span>
            <span class="brand-sub">Memorial Hospital</span>
        </div>
    </div>

    <div class="nav-section-label">MANAGEMENT</div>
    <ul class="nav-links">
        <li><a href="<?= BASE_URL ?>/index.php"         class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php'    ? 'active' : '' ?>"><span class="nav-icon">🏠</span> Dashboard</a></li>
        <li><a href="<?= BASE_URL ?>/forms/patients.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'patients.php' ? 'active' : '' ?>"><span class="nav-icon">🧑‍⚕️</span> Patients</a></li>
        <li><a href="<?= BASE_URL ?>/forms/admissions.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'admissions.php' ? 'active' : '' ?>"><span class="nav-icon">🛏</span> Admissions</a></li>
        <li><a href="<?= BASE_URL ?>/forms/complaints.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'complaints.php' ? 'active' : '' ?>"><span class="nav-icon">📋</span> Complaints</a></li>
        <li><a href="<?= BASE_URL ?>/forms/treatments.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'treatments.php' ? 'active' : '' ?>"><span class="nav-icon">💊</span> Treatments</a></li>
    </ul>

    <div class="nav-section-label">STAFF</div>
    <ul class="nav-links">
        <li><a href="<?= BASE_URL ?>/forms/doctors.php"  class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'doctors.php'  ? 'active' : '' ?>"><span class="nav-icon">👨‍⚕️</span> Doctors</a></li>
        <li><a href="<?= BASE_URL ?>/forms/nurses.php"   class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'nurses.php'   ? 'active' : '' ?>"><span class="nav-icon">👩‍⚕️</span> Nurses</a></li>
        <li><a href="<?= BASE_URL ?>/forms/performance.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'performance.php' ? 'active' : '' ?>"><span class="nav-icon">📊</span> Performance</a></li>
        <li><a href="<?= BASE_URL ?>/forms/experience.php"  class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'experience.php'  ? 'active' : '' ?>"><span class="nav-icon">📜</span> Experience</a></li>
    </ul>

    <div class="nav-section-label">FACILITY</div>
    <ul class="nav-links">
        <li><a href="<?= BASE_URL ?>/forms/wards.php"    class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'wards.php'    ? 'active' : '' ?>"><span class="nav-icon">🏥</span> Wards</a></li>
        <li><a href="<?= BASE_URL ?>/forms/beds.php"     class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'beds.php'     ? 'active' : '' ?>"><span class="nav-icon">🛏</span> Beds</a></li>
        <li><a href="<?= BASE_URL ?>/forms/care_units.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'care_units.php' ? 'active' : '' ?>"><span class="nav-icon">🔬</span> Care Units</a></li>
    </ul>

    <div class="nav-section-label">REPORTS</div>
    <ul class="nav-links">
        <li><a href="<?= BASE_URL ?>/reports/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : '' ?>"><span class="nav-icon">📈</span> All Reports</a></li>
    </ul>
</nav>

<div class="main-content" id="main-content">
    <header class="topbar">
        <button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle sidebar">☰</button>
        <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        <div class="topbar-right">
            <span class="hospital-badge">⚕ IVOR Paine Memorial Hospital</span>
        </div>
    </header>
    <div class="content-area">
