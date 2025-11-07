<?php
session_start();

define('DB_HOST','127.0.0.1');
define('DB_NAME','supermarket_db');
define('DB_USER','root');
define('DB_PASS',''); // set your local MySQL password

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e){
    die('DB Connection failed: '.$e->getMessage());
}

function jsonOut($arr){ header('Content-Type: application/json'); echo json_encode($arr); exit; }
function requireAuth(){ if(!isset($_SESSION['user'])) jsonOut(['ok'=>false,'auth'=>false,'msg'=>'Not authenticated']); }
function requireAdmin(){ if(!isset($_SESSION['user']) || $_SESSION['user']['role']!=='admin') jsonOut(['ok'=>false,'auth'=>false,'msg'=>'Admin only']); }
?>