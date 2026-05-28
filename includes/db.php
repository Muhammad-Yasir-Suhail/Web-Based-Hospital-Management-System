<?php
/**
 * Database Connection - IVOR PAINE MEMORIAL HOSPITAL
 * Uses SQL Server via SQLSRV extension
 */
ob_start();
define('DB_SERVER', 'DESKTOP-62DQD2A\SQLEXPRESS');
define('DB_NAME', 'IVOR_Hospital');

/**
 * Returns an active SQLSRV connection or dies with an error message.
 */
function getConnection(): mixed {
    $connectionInfo = [
        'Database' => DB_NAME,
        'CharacterSet' => 'UTF-8',
        'TrustServerCertificate' => true
    ];

    $conn = sqlsrv_connect(DB_SERVER, $connectionInfo);

    if ($conn === false) {
        $errors = sqlsrv_errors();
       ob_clean();
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $errors[0]['message'],
        ]));
    }

    return $conn;
}
/**
 * Executes a parameterized query and returns the statement resource.
 * Calls die() with JSON error on failure.
 */
function executeQuery(mixed $conn, string $sql, array $params = []): mixed {
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        ob_clean();
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'message' => 'Query failed: ' . $errors[0]['message'],
        ]));
    }

    return $stmt;
}

/**
 * Fetches all rows from a statement as an associative array.
 */
function fetchAll(mixed $stmt): array {
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Convert DateTime objects to formatted strings
        foreach ($row as $key => $value) {
            if ($value instanceof DateTime) {
                $row[$key] = $value->format('Y-m-d');
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Fetches a single row from a statement as an associative array.
 */
function fetchOne(mixed $stmt): ?array {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($row === false || $row === null) return null;

    foreach ($row as $key => $value) {
        if ($value instanceof DateTime) {
            $row[$key] = $value->format('Y-m-d');
        }
    }
    return $row;
}

/**
 * Sends a JSON response and exits.
 */

function sendJson(array $data): void {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
