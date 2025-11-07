<?php
require_once 'config.php';
requireAuth();
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_export.csv"');
$out = fopen('php://output','w');
fputcsv($out, ['Sale ID','Ref','Date','Total','Status','Customer','Cashier']);
$stmt = $pdo->query("SELECT * FROM sales ORDER BY created_at DESC");
while($row = $stmt->fetch()){
    fputcsv($out, [$row['id'],$row['sale_ref'],$row['created_at'],$row['total'],$row['status'],$row['customer_name'],$row['cashier']]);
}
fclose($out);
?>