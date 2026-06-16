<?php
require_once 'config.php';

try {
    $db = getDB();
    
    $productsSchema = $db->query("DESCRIBE products")->fetchAll();
    $transaksiSchema = $db->query("DESCRIBE transaksi")->fetchAll();
    $transaksiDetailSchema = $db->query("DESCRIBE transaksi_detail")->fetchAll();
    $products = $db->query("SELECT * FROM products")->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'products_schema' => $productsSchema,
        'transaksi_schema' => $transaksiSchema,
        'transaksi_detail_schema' => $transaksiDetailSchema,
        'products' => $products
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
