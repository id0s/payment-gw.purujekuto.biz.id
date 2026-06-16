<?php
require_once __DIR__ . '/config.php';
$pdo = getDB();
$dbs = [];
$stmt = $pdo->query("SHOW DATABASES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $dbs[] = $row[0];
}
echo json_encode([
    'status' => 'success',
    'databases' => $dbs
]);
