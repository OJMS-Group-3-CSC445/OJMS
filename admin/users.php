<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Restrict access to ICT Admins
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'portal_admin'
) {
    http_response_code(403);
    exit('Access denied.');
}

// User management: edit, protected deletion, search and pagination
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$allowedRoles = ['author','portal_admin','editor_in_chief','managing_editor','reviewer'];
if (!in_array($roleFilter, $allowedRoles, true)) $roleFilter = '';
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$editId = max(0, (int)($_GET['edit'] ?? 0));
$message = trim($_GET['msg'] ?? '');

function users_redirect($search, $role, $page, $msg) {
    $q = ['page' => max(1,(int)$page), 'msg' => $msg];
    if ($search !== '') $q['search'] = $search;
    if ($role !== '') $q['role'] = $role;
    header('Location: users.php?' . http_build_query($q)); exit;
}
function users_table_exists(PDO $pdo, $table) {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]); return (bool)$stmt->fetchColumn();
}
function users_column_exists(PDO $pdo, $table, $column) {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $stmt->execute([$table,$column]); return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search = trim($_POST['search'] ?? $search);
    $roleFilter = trim($_POST['role_filter'] ?? $roleFilter);
    $page = max(1, (int)($_POST['page'] ?? $page));
    if (!in_array($roleFilter, $allowedRoles, true)) $roleFilter = '';
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string)csrf_token(), (string)$token)) {
        users_redirect($search, $roleFilter, $page, 'invalid_token');
    }
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId <= 0) users_redirect($search,$roleFilter,$page,'invalid_user');

    if ($action === 'edit') {
        $first = trim($_POST['first_name'] ?? '');
        $middle = trim($_POST['middle_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? '';
        if ($first === '' || $last === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || !in_array($role,$allowedRoles,true)) users_redirect($search,$roleFilter,$page,'invalid_data');
        $check=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
        $check->execute([$email,$targetId]);
        if ($check->fetch()) users_redirect($search,$roleFilter,$page,'email_exists');
        $update=$pdo->prepare('UPDATE users SET first_name=?, middle_name=?, last_name=?, email=?, phone=?, role=? WHERE id=?');
        $update->execute([$first,$middle,$last,$email,$phone,$role,$targetId]);
        users_redirect($search,$roleFilter,$page,'updated');
    }

    if ($action === 'delete') {
        if ($targetId === (int)$_SESSION['user_id']) users_redirect($search,$roleFilter,$page,'self_delete');
        // Block deletion if any declared FK references this user.
        $fk=$pdo->prepare("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'");
        $fk->execute();
        foreach ($fk->fetchAll(PDO::FETCH_ASSOC) as $ref) {
            $table=$ref['TABLE_NAME']; $column=$ref['COLUMN_NAME'];
            $check=$pdo->prepare("SELECT 1 FROM `" . str_replace('`','',$table) . "` WHERE `" . str_replace('`','',$column) . "`=? LIMIT 1");
            $check->execute([$targetId]);
            if ($check->fetchColumn()) users_redirect($search,$roleFilter,$page,'linked_records');
        }
        // Additional safeguards for common OJMS relationship columns even if no FK was declared.
        $commonColumns=['user_id','author_id','reviewer_id','editor_id','assigned_to','created_by','submitted_by','handled_by'];
        $tables=$pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type="BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            if ($table === 'users') continue;
            foreach ($commonColumns as $column) {
                if (users_column_exists($pdo,$table,$column)) {
                    $check=$pdo->prepare("SELECT 1 FROM `" . str_replace('`','',$table) . "` WHERE `" . str_replace('`','',$column) . "`=? LIMIT 1");
                    $check->execute([$targetId]);
                    if ($check->fetchColumn()) users_redirect($search,$roleFilter,$page,'linked_records');
                }
            }
        }
        $del=$pdo->prepare('DELETE FROM users WHERE id=?'); $del->execute([$targetId]);
        users_redirect($search,$roleFilter,$page,'deleted');
    }
    users_redirect($search,$roleFilter,$page,'invalid_action');
}

// Counts and listing query, with pagination and filters
$countSql='SELECT COUNT(*) FROM users WHERE 1=1'; $countParams=[];
if ($search !== '') { $countSql .= ' AND (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ?)'; $term='%'.$search.'%'; $countParams=[$term,$term,$term,$term]; }
if ($roleFilter !== '') { $countSql .= ' AND role=?'; $countParams[]=$roleFilter; }
$countStmt=$pdo->prepare($countSql); $countStmt->execute($countParams); $filteredTotal=(int)$countStmt->fetchColumn();
$totalPages=max(1,(int)ceil($filteredTotal/$perPage)); $page=min($page,$totalPages); $offset=($page-1)*$perPage;
$sql='SELECT id,first_name,middle_name,last_name,email,phone,role,created_at FROM users WHERE 1=1'; $params=[];
if ($search !== '') { $sql .= ' AND (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ?)'; $term='%'.$search.'%'; $params=[$term,$term,$term,$term]; }
if ($roleFilter !== '') { $sql .= ' AND role=?'; $params[]=$roleFilter; }
$sql .= ' ORDER BY created_at DESC,id DESC LIMIT '.$perPage.' OFFSET '.$offset;
$stmt=$pdo->prepare($sql); $stmt->execute($params); $users=$stmt->fetchAll();

// Role totals
$stmt=$pdo->query('SELECT role,COUNT(*) AS total FROM users GROUP BY role'); $roleCounts=[];
foreach ($stmt->fetchAll() as $row) $roleCounts[$row['role']]=(int)$row['total'];
$totalUsers=array_sum($roleCounts);

$editUser=null;
if ($editId) { $stmt=$pdo->prepare('SELECT id,first_name,middle_name,last_name,email,phone,role FROM users WHERE id=?'); $stmt->execute([$editId]); $editUser=$stmt->fetch(); }

// Helper functions
function admin_user_role_label($role) {
    $labels = [
        'author' => 'Author',
        'portal_admin' => 'ICT Admin',
        'editor_in_chief' => 'Editor-in-Chief',
        'managing_editor' => 'Managing Editor',
        'reviewer' => 'Reviewer'
    ];

    return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

function admin_user_role_class($role) {
    $classes = [
        'author' => 'role-author',
        'portal_admin' => 'role-admin',
        'editor_in_chief' => 'role-editor',
        'managing_editor' => 'role-editor',
        'reviewer' => 'role-reviewer'
    ];

    return $classes[$role] ?? 'role-default';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Management | OJMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, sans-serif;
            background: #f8f7f5;
            color: #1e293b;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .admin-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .admin-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .admin-logo {
            width: 36px;
            height: 36px;
            background: #991b1b;
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .admin-nav {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .admin-nav a,
        .admin-nav button {
            font-size: 14px;
            color: #475569;
        }

        .admin-nav a.active {
            color: #991b1b;
            font-weight: 600;
        }

        .admin-nav a:hover,
        .admin-nav button:hover {
            color: #991b1b;
        }

        .admin-nav form {
            margin: 0;
        }

        .admin-nav button {
            background: none;
            border: none;
            cursor: pointer;
            font: inherit;
        }

        .main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .page-heading {
            margin-bottom: 30px;
        }

        .page-heading h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .page-heading p {
            margin: 0;
            color: #64748b;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 22px;
        }

        .stat-label {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-card.total {
            border-top: 3px solid #334155;
        }

        .stat-card.author {
            border-top: 3px solid #2563eb;
        }

        .stat-card.reviewer {
            border-top: 3px solid #7c3aed;
        }

        .stat-card.editor {
            border-top: 3px solid #15803d;
        }

        .stat-card.admin {
            border-top: 3px solid #b45309;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .card-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .card-header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .filters {
            padding: 22px 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
        }

        .filters input,
        .filters select {
            padding: 11px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
            color: #334155;
            background: white;
        }

        .filters input {
            flex: 1;
            min-width: 220px;
        }

        .filters select {
            min-width: 180px;
        }

        .filter-button {
            background: #991b1b;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 11px 18px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-button:hover {
            background: #7f1d1d;
        }

        .reset-button {
            padding: 11px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #475569;
            font-size: 14px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
        }

        td {
            color: #334155;
        }

        .user-name {
            font-weight: 500;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .role-author {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .role-reviewer {
            background: #f5f3ff;
            color: #6d28d9;
        }

        .role-editor {
            background: #f0fdf4;
            color: #166534;
        }

        .role-admin {
            background: #fff7ed;
            color: #9a3412;
        }

        .role-default {
            background: #f1f5f9;
            color: #475569;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #64748b;
        }

        .results-info {
            padding: 16px 24px;
            font-size: 13px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        .admin-footer {
            background: #1e293b;
            color: white;
            padding: 35px 30px;
            margin-top: 50px;
        }

        .footer-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            gap: 30px;
            flex-wrap: wrap;
        }

        .footer-inner h2 {
            margin: 0 0 5px;
        }

        .footer-inner p {
            margin: 0;
            color: #cbd5e1;
            font-size: 14px;
        }

        .footer-copy {
            max-width: 1400px;
            margin: 30px auto 0;
            font-size: 12px;
            color: #cbd5e1;
        }

        @media(max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media(max-width: 700px) {
            .admin-header {
                padding: 18px;
            }

            .admin-nav {
                width: 100%;
                gap: 18px;
            }

            .main {
                padding: 28px 18px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-value {
                font-size: 24px;
            }

            .card-header {
                padding: 20px;
            }

            .filters {
                padding: 18px;
            }

            .filters input,
            .filters select {
                width: 100%;
                min-width: 0;
            }
        }

        @media(max-width: 450px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    

/* ICT ADMIN NAVBAR AND FOOTER */
.admin-header {
    position: fixed; top: 0; left: 0; width: 100%; z-index: 1000;
    background: #fff; border-bottom: 1px solid #e2e8f0;
    padding: 16px clamp(24px, 5vw, 80px); display: flex;
    justify-content: space-between; align-items: center; gap: 20px;
}
.admin-brand { display:flex; align-items:center; gap:12px; flex-shrink:0; font-weight:700; text-decoration:none; }
.admin-logo { width:42px; height:42px; border-radius:10px; background:#991b1b; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:700; }
.admin-brand-text { color:#1e293b; font-size:16px; }
.admin-nav { display:flex; align-items:center; gap:24px; }
.admin-nav > a:not(.admin-profile-pill) { color:#64748b; font-size:14px; font-weight:500; transition:color .2s; }
.admin-nav > a:not(.admin-profile-pill):hover, .admin-nav > a:not(.admin-profile-pill).active { color:#991b1b; }
.admin-profile-pill { display:inline-flex; align-items:center; gap:9px; max-width:220px; padding:9px 14px; border:1px solid #e2e8f0; border-radius:999px; background:#f8fafc; color:#334155; font-size:13px; font-weight:600; }
.admin-profile-pill span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.admin-profile-pill svg { flex-shrink:0; }
.admin-logout-form { margin:0; }
.admin-logout-btn { display:inline-flex; align-items:center; justify-content:center; padding:10px 18px; background:#fef2f2; color:#991b1b; border:1px solid #fecaca; border-radius:999px; font:600 14px Inter,sans-serif; cursor:pointer; white-space:nowrap; transition:background .2s,border-color .2s; }
.admin-logout-btn:hover { background:#fee2e2; border-color:#fca5a5; }
.admin-menu-toggle { display:none; flex-direction:column; justify-content:center; gap:5px; width:44px; height:44px; padding:11px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; cursor:pointer; }
.admin-menu-toggle span { display:block; width:20px; height:2px; background:#1e293b; border-radius:2px; }
body { padding-top:78px; }
.main { width:100%; max-width:none; margin:0; padding-left:clamp(24px,5vw,80px); padding-right:clamp(24px,5vw,80px); }
.admin-footer.admin-footer-simple { width:100%; margin-top:50px; padding:24px clamp(24px,5vw,80px); background:#1e293b; color:#cbd5e1; text-align:center; }
.admin-footer.admin-footer-simple p { margin:0; font-size:13px; color:#cbd5e1; }
@media(max-width:900px) {
 .admin-header { padding:14px 24px; }
 .admin-menu-toggle { display:flex; flex-shrink:0; }
 .admin-nav { display:none; position:absolute; top:100%; left:0; width:100%; background:#fff; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; padding:24px; flex-direction:column; align-items:flex-start; gap:20px; box-shadow:0 10px 20px rgba(15,23,42,.08); }
 .admin-nav.is-open { display:flex; }
 .admin-nav > a { width:100%; }
 .admin-profile-pill { max-width:100%; width:fit-content; }
 .admin-logout-form,.admin-logout-btn { width:100%; }
 .main { padding-left:24px; padding-right:24px; }
}
@media(max-width:480px) { .admin-header {padding:12px 18px;} .admin-brand-text {font-size:14px;} .admin-logo {width:38px;height:38px;} .main {padding-left:18px;padding-right:18px;} }

    
.actions-cell { white-space:nowrap; display:flex; align-items:center; gap:10px; }
.action-edit,.action-delete { border:0; border-radius:6px; padding:8px 12px; font:600 13px Inter,sans-serif; cursor:pointer; display:inline-block; }
.action-edit { background:#eff6ff; color:#1d4ed8; }
.action-delete { background:#fef2f2; color:#b91c1c; }
.delete-form { display:inline; margin:0; }
.notice { padding:14px 24px; font-size:14px; border-bottom:1px solid #e2e8f0; }
.notice-success { background:#f0fdf4; color:#166534; }
.notice-error { background:#fff7ed; color:#9a3412; }
.pagination { display:flex; gap:8px; align-items:center; justify-content:center; padding:20px; flex-wrap:wrap; border-top:1px solid #e2e8f0; }
.pagination a,.pagination span { padding:8px 12px; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; color:#475569; }
.pagination a.current { background:#991b1b; border-color:#991b1b; color:white; }
.pagination .disabled { opacity:.45; }
.edit-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:2000; display:flex; align-items:center; justify-content:center; padding:20px; overflow:auto; }
.edit-panel { background:white; width:100%; max-width:560px; border-radius:12px; padding:28px; position:relative; box-shadow:0 20px 50px rgba(15,23,42,.2); }
.edit-panel h2 { margin:0 0 8px; font-size:22px; }.edit-panel>p { margin:0 0 20px; color:#64748b; font-size:14px; }
.edit-close { position:absolute; top:16px; right:20px; font-size:28px; color:#64748b; }
.edit-form { display:grid; gap:14px; }.edit-form label { display:grid; gap:6px; font-size:13px; font-weight:600; color:#334155; }
.edit-form input,.edit-form select { width:100%; padding:11px 12px; border:1px solid #cbd5e1; border-radius:6px; font:14px Inter,sans-serif; }
.edit-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:8px; }.edit-actions button,.cancel-edit { padding:11px 16px; border-radius:6px; font:600 14px Inter,sans-serif; }
.edit-actions button { border:0; background:#991b1b; color:white; cursor:pointer; }.cancel-edit { border:1px solid #cbd5e1; color:#475569; }

</style>
</head>

<body>

<header class="admin-header">
    <a href="dashboard.php" class="admin-brand">
        <span class="admin-logo">OJ</span>
        <span class="admin-brand-text">Admin Dashboard</span>
    </a>

    <button type="button" class="admin-menu-toggle" id="admin-menu-toggle"
        aria-label="Toggle navigation" aria-expanded="false" aria-controls="admin-nav">
        <span></span><span></span><span></span>
    </button>

    <nav class="admin-nav" id="admin-nav" aria-label="Admin navigation">
        <a href="dashboard.php">Dashboard</a>
        <a href="reviewers.php">Manage Accounts</a>
        <a class="active" href="users.php">Users</a>

        <a href="settings.php" class="admin-profile-pill" aria-label="Admin profile">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/><path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/>
            </svg>
            <span><?= e(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?: ($_SESSION['email'] ?? 'Administrator')) ?></span>
        </a>

        <form action="../auth/logout.php" method="post" class="admin-logout-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="admin-logout-btn">Log out</button>
        </form>
    </nav>
</header>

<main class="main">

    <section class="page-heading">
        <h1>User management</h1>
        <p>View and monitor all accounts registered on the OJMS platform.</p>
    </section>

    <!-- User statistics -->
    <section class="stats-grid">

        <div class="stat-card total">
            <div class="stat-label">Total users</div>
            <div class="stat-value"><?= $totalUsers ?></div>
        </div>

        <div class="stat-card author">
            <div class="stat-label">Authors</div>
            <div class="stat-value"><?= $roleCounts['author'] ?? 0 ?></div>
        </div>

        <div class="stat-card reviewer">
            <div class="stat-label">Reviewers</div>
            <div class="stat-value"><?= $roleCounts['reviewer'] ?? 0 ?></div>
        </div>

        <div class="stat-card editor">
            <div class="stat-label">Editors</div>
            <div class="stat-value">
                <?= ($roleCounts['editor_in_chief'] ?? 0) + ($roleCounts['managing_editor'] ?? 0) ?>
            </div>
        </div>

        <div class="stat-card admin">
            <div class="stat-label">ICT Admins</div>
            <div class="stat-value"><?= $roleCounts['portal_admin'] ?? 0 ?></div>
        </div>

    </section>

    <!-- User listing -->
    <section class="card">

        <div class="card-header">
            <h2>All users</h2>
            <p>Browse and search user accounts.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="notice <?= in_array($message,['updated','deleted'],true) ? 'notice-success' : 'notice-error' ?>">
                <?php $messages=['updated'=>'Account updated successfully.','deleted'=>'Account deleted.','linked_records'=>'This account is linked to existing OJMS records and cannot be deleted.','self_delete'=>'You cannot delete your own account.','email_exists'=>'That email address is already in use.','invalid_data'=>'Please provide valid account details.','invalid_token'=>'Security token validation failed. Please try again.','invalid_user'=>'Invalid account selected.','invalid_action'=>'Invalid action.']; ?>
                <?= e($messages[$message] ?? 'Action completed.') ?>
            </div>
        <?php endif; ?>

        <form method="get" class="filters">

            <input
                type="search"
                name="search"
                placeholder="Search name or email..."
                value="<?= e($search) ?>">

            <select name="role">

                <option value="">All roles</option>

                <option value="author" <?= $roleFilter === 'author' ? 'selected' : '' ?>>
                    Authors
                </option>

                <option value="reviewer" <?= $roleFilter === 'reviewer' ? 'selected' : '' ?>>
                    Reviewers
                </option>

                <option value="editor_in_chief" <?= $roleFilter === 'editor_in_chief' ? 'selected' : '' ?>>
                    Editor-in-Chief
                </option>

                <option value="managing_editor" <?= $roleFilter === 'managing_editor' ? 'selected' : '' ?>>
                    Managing Editor
                </option>

                <option value="portal_admin" <?= $roleFilter === 'portal_admin' ? 'selected' : '' ?>>
                    ICT Admins
                </option>

            </select>

            <button type="submit" class="filter-button">
                Apply filters
            </button>

            <a href="users.php" class="reset-button">
                Reset
            </a>

        </form>

        <div class="results-info">
            Showing <?= $filteredTotal ? ($offset + 1) : 0 ?>–<?= min($offset + count($users), $filteredTotal) ?> of <?= $filteredTotal ?> account(s)
            <?php if ($search !== '' || $roleFilter !== ''): ?>
                matching your filters.
            <?php else: ?>
                in the current listing.
            <?php endif; ?>
            <span>Page <?= $page ?> of <?= $totalPages ?>.</span>
        </div>

        <?php if (empty($users)): ?>

            <div class="empty-state">
                <p>No users found matching your search.</p>
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Date registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($users as $user): ?>

                            <?php
                            $fullName = trim(
                                ($user['first_name'] ?? '') . ' ' .
                                ($user['middle_name'] ?? '') . ' ' .
                                ($user['last_name'] ?? '')
                            );
                            ?>

                            <tr>

                                <td class="user-name">
                                    <?= e($fullName ?: 'Unnamed user') ?>
                                </td>

                                <td>
                                    <?= e($user['email']) ?>
                                </td>

                                <td>
                                    <?= e($user['phone'] ?: '—') ?>
                                </td>

                                <td>
                                    <span class="role-badge <?= e(admin_user_role_class($user['role'])) ?>">
                                        <?= e(admin_user_role_label($user['role'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= $user['created_at']
                                        ? e(date('d M Y', strtotime($user['created_at'])))
                                        : '—' ?>
                                </td>
                                <td class="actions-cell">
                                    <a class="action-edit" href="users.php?<?= e(http_build_query(['search'=>$search,'role'=>$roleFilter,'page'=>$page,'edit'=>$user['id']])) ?>">Edit</a>
                                    <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="post" class="delete-form" onsubmit="return confirm('Delete this account? Deletion will only proceed if no linked records need to be preserved.');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                        <input type="hidden" name="search" value="<?= e($search) ?>">
                                        <input type="hidden" name="role_filter" value="<?= e($roleFilter) ?>">
                                        <input type="hidden" name="page" value="<?= (int)$page ?>">
                                        <button type="submit" class="action-delete">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php $base=['search'=>$search,'role'=>$roleFilter]; ?>
                <?php if ($page > 1): ?><a href="users.php?<?= e(http_build_query($base+['page'=>$page-1])) ?>">&larr; Previous</a><?php else: ?><span class="disabled">&larr; Previous</span><?php endif; ?>
                <?php for ($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
                    <a class="<?= $p===$page ? 'current' : '' ?>" href="users.php?<?= e(http_build_query($base+['page'=>$p])) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="users.php?<?= e(http_build_query($base+['page'=>$page+1])) ?>">Next &rarr;</a><?php else: ?><span class="disabled">Next &rarr;</span><?php endif; ?>
            </div>
        <?php endif; ?>

    </section>

</main>

<?php if ($editUser): ?>
<div class="edit-overlay" id="edit-overlay">
  <div class="edit-panel">
    <a class="edit-close" href="users.php?<?= e(http_build_query(['search'=>$search,'role'=>$roleFilter,'page'=>$page])) ?>" aria-label="Close">&times;</a>
    <h2>Edit user account</h2><p>Update the account details and role.</p>
    <form method="post" class="edit-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="edit"><input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
      <input type="hidden" name="search" value="<?= e($search) ?>"><input type="hidden" name="role_filter" value="<?= e($roleFilter) ?>"><input type="hidden" name="page" value="<?= (int)$page ?>">
      <label>First name<input name="first_name" required value="<?= e($editUser['first_name']) ?>"></label>
      <label>Middle name<input name="middle_name" value="<?= e($editUser['middle_name'] ?? '') ?>"></label>
      <label>Last name<input name="last_name" required value="<?= e($editUser['last_name']) ?>"></label>
      <label>Email<input type="email" name="email" required value="<?= e($editUser['email']) ?>"></label>
      <label>Phone<input name="phone" value="<?= e($editUser['phone'] ?? '') ?>"></label>
      <label>Role<select name="role" required><?php foreach ($allowedRoles as $r): ?><option value="<?= e($r) ?>" <?= $editUser['role']===$r?'selected':'' ?>><?= e(admin_user_role_label($r)) ?></option><?php endforeach; ?></select></label>
      <div class="edit-actions"><a class="cancel-edit" href="users.php?<?= e(http_build_query(['search'=>$search,'role'=>$roleFilter,'page'=>$page])) ?>">Cancel</a><button type="submit">Save changes</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<footer class="admin-footer admin-footer-simple">
    <p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>


<script>
const adminToggle = document.getElementById('admin-menu-toggle');
const adminNav = document.getElementById('admin-nav');
if (adminToggle && adminNav) {
    adminToggle.addEventListener('click', function() {
        const isOpen = adminNav.classList.toggle('is-open');
        adminToggle.setAttribute('aria-expanded', isOpen);
    });
    adminNav.querySelectorAll('a').forEach(link => link.addEventListener('click', function() {
        adminNav.classList.remove('is-open');
        adminToggle.setAttribute('aria-expanded', 'false');
    }));
    document.addEventListener('click', function(event) {
        if (!adminNav.contains(event.target) && !adminToggle.contains(event.target)) {
            adminNav.classList.remove('is-open');
            adminToggle.setAttribute('aria-expanded', 'false');
        }
    });
}
</script>

</body>
</html>