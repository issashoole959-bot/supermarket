<?php
require_once 'config.php';

$action = $_REQUEST['action'] ?? '';

if($action === 'login'){
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if(!$u || !$p) jsonOut(['ok'=>false,'msg'=>'Username and password required']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch();
    if($user && password_verify($p, $user['password_hash'])){
        $_SESSION['user'] = ['id'=>$user['id'],'username'=>$user['username'],'role'=>$user['role'],'fullname'=>$user['fullname']];
        jsonOut(['ok'=>true,'user'=>$_SESSION['user']]);
    } else jsonOut(['ok'=>false,'msg'=>'Invalid credentials']);
}

if($action === 'logout'){
    session_destroy();
    jsonOut(['ok'=>true]);
}

// Items
if($action === 'items_list'){
    requireAuth();
    $q = $pdo->query("SELECT * FROM items ORDER BY name ASC")->fetchAll();
    jsonOut(['ok'=>true,'items'=>$q]);
}

if($action === 'items_add'){
    requireAdmin();
    $name = trim($_POST['name'] ?? '');
    $qty = intval($_POST['qty'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $low = intval($_POST['low'] ?? 5);
    if(!$name) jsonOut(['ok'=>false,'msg'=>'Name required']);
    $stmt = $pdo->prepare("INSERT INTO items (name,qty,price,low_threshold,created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$name,$qty,$price,$low]);
    jsonOut(['ok'=>true,'id'=>$pdo->lastInsertId()]);
}

if($action === 'items_update'){
    requireAdmin();
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $qty = intval($_POST['qty'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $low = intval($_POST['low'] ?? 5);
    if(!$id || !$name) jsonOut(['ok'=>false,'msg'=>'Invalid data']);
    $stmt = $pdo->prepare("UPDATE items SET name=?, qty=?, price=?, low_threshold=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$name,$qty,$price,$low,$id]);
    jsonOut(['ok'=>true]);
}

if($action === 'items_delete'){
    requireAdmin();
    $id = intval($_POST['id'] ?? 0);
    if(!$id) jsonOut(['ok'=>false,'msg'=>'Invalid id']);
    $pdo->prepare("DELETE FROM items WHERE id=?")->execute([$id]);
    jsonOut(['ok'=>true]);
}

// Sales
if($action === 'sales_create'){
    requireAuth();
    $data = json_decode(file_get_contents('php://input'), true);
    $items = $data['items'] ?? [];
    $status = $data['status'] ?? 'Paid';
    $customer = trim($data['customer'] ?? null);
    if(!$items || !is_array($items)) jsonOut(['ok'=>false,'msg'=>'No items in sale']);
    // stock check
    foreach($items as $it){
        if(!empty($it['item_id'])){
            $stmt = $pdo->prepare("SELECT qty FROM items WHERE id = ?");
            $stmt->execute([$it['item_id']]);
            $row = $stmt->fetch();
            if($row && $row['qty'] < intval($it['qty'])) jsonOut(['ok'=>false,'msg'=>"Not enough stock for {$it['name']}"]);
        }
    }
    $total = 0.0;
    foreach($items as $it) $total += floatval($it['qty']) * floatval($it['price']);
    $ref = 'S'.time().rand(100,999);
    $stmt = $pdo->prepare("INSERT INTO sales (sale_ref,total,status,customer_name,cashier,created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$ref, $total, $status, $customer ?: null, $_SESSION['user']['username']]);
    $sale_id = $pdo->lastInsertId();
    $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id,item_id,item_name,qty,unit_price,subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtUpdate = $pdo->prepare("UPDATE items SET qty = qty - ? WHERE id = ?");
    foreach($items as $it){
        $qty = intval($it['qty']);
        $price = floatval($it['price']);
        $subtotal = $qty * $price;
        $stmtItem->execute([$sale_id, $it['item_id'] ?: null, $it['name'], $qty, $price, $subtotal]);
        if(!empty($it['item_id'])) $stmtUpdate->execute([$qty, $it['item_id']]);
    }
    jsonOut(['ok'=>true,'sale_id'=>$sale_id,'ref'=>$ref]);
}

// Reports
if($action === 'reports_sales'){
    requireAuth();
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $sql = "SELECT * FROM sales WHERE 1=1";
    $params = [];
    if($from){ $sql .= " AND DATE(created_at) >= ?"; $params[] = $from; }
    if($to){ $sql .= " AND DATE(created_at) <= ?"; $params[] = $to; }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    jsonOut(['ok'=>true,'sales'=>$rows]);
}

// Change password - only admin can change admin password
if($action === 'change_password'){
    requireAdmin();
    $old = $_POST['old'] ?? '';
    $new = $_POST['new'] ?? '';
    if(!$old || !$new) jsonOut(['ok'=>false,'msg'=>'Old and new passwords required']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch();
    if(!$user || !password_verify($old, $user['password_hash'])) jsonOut(['ok'=>false,'msg'=>'Old password incorrect']);
    $newhash = password_hash($new, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newhash, $user['id']]);
    jsonOut(['ok'=>true]);
}

jsonOut(['ok'=>false,'msg'=>'Invalid action']);
?>