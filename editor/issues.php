<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'editor_in_chief') {
    http_response_code(403); exit('Access denied.');
}

$successMessage = ''; $errorMessage = '';
$token = csrf_token();
$editIssue = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save_issue') {
                $id = (int)($_POST['issue_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $volume = trim($_POST['volume'] ?? '');
                $issueNumber = trim($_POST['issue_number'] ?? '');
                $publicationDate = $_POST['publication_date'] ?? '';
                $description = trim($_POST['description'] ?? '');
                if ($title === '' || $volume === '' || $issueNumber === '' || $publicationDate === '') throw new Exception('Please complete all required issue fields.');
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE journal_issues SET title=?, volume=?, issue_number=?, publication_date=?, description=?, updated_at=NOW() WHERE id=?');
                    $stmt->execute([$title,$volume,$issueNumber,$publicationDate,$description,$id]);
                    $successMessage = 'Issue updated successfully.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO journal_issues (title, volume, issue_number, publication_date, description, is_current, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())');
                    $stmt->execute([$title,$volume,$issueNumber,$publicationDate,$description]);
                    $successMessage = 'Issue created successfully.';
                }
            } elseif ($action === 'set_current') {
                $issueId = (int)($_POST['issue_id'] ?? 0);
                if ($issueId <= 0) throw new Exception('Invalid issue selected.');
                $pdo->beginTransaction();
                $check = $pdo->prepare('SELECT id FROM journal_issues WHERE id=? FOR UPDATE'); $check->execute([$issueId]);
                if (!$check->fetchColumn()) throw new Exception('Issue not found.');
                $pdo->exec('UPDATE journal_issues SET is_current=0');
                $stmt = $pdo->prepare('UPDATE journal_issues SET is_current=1, updated_at=NOW() WHERE id=?'); $stmt->execute([$issueId]);
                $pdo->commit(); $successMessage = 'Current issue updated.';
            } elseif ($action === 'assign_publish') {
                $articleId = (int)($_POST['article_id'] ?? 0); $issueId = (int)($_POST['issue_id'] ?? 0);
                if ($articleId <= 0 || $issueId <= 0) throw new Exception('Select a manuscript and an issue.');
                $pdo->beginTransaction();
                $articleLock = $pdo->prepare("SELECT status FROM articles WHERE id=? FOR UPDATE"); $articleLock->execute([$articleId]); $status = $articleLock->fetchColumn();
                $issueCheck = $pdo->prepare('SELECT id FROM journal_issues WHERE id=? FOR UPDATE'); $issueCheck->execute([$issueId]);
                if (!$status || !$issueCheck->fetchColumn()) throw new Exception('Manuscript or issue not found.');
                if (!in_array($status, ['accepted','published'], true)) throw new Exception('Only accepted or already published manuscripts can be assigned to an issue.');
                $stmt = $pdo->prepare("UPDATE articles SET issue_id=?, status='published', updated_at=NOW() WHERE id=?"); $stmt->execute([$issueId,$articleId]);
                $pdo->commit(); $successMessage = 'Manuscript assigned to the issue and published.';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof PDOException) { error_log('Issue management error: '.$e->getMessage()); $errorMessage = 'Unable to complete the action. Check the database fields and try again.'; }
            else $errorMessage = $e->getMessage();
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM journal_issues WHERE id=?'); $stmt->execute([(int)$_GET['edit']]); $editIssue = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$issues = $pdo->query("SELECT ji.*, (SELECT COUNT(*) FROM articles a WHERE a.issue_id=ji.id AND a.status='published') AS article_count FROM journal_issues ji ORDER BY publication_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$acceptedArticles = $pdo->query("SELECT id, title, authors FROM articles WHERE status='accepted' OR (status='published' AND issue_id IS NULL) ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
$assignedArticles = $pdo->query("SELECT a.id, a.title, a.authors, a.status, ji.title AS issue_title, ji.volume, ji.issue_number FROM articles a LEFT JOIN journal_issues ji ON a.issue_id=ji.id WHERE a.issue_id IS NOT NULL ORDER BY ji.publication_date DESC, a.title ASC")->fetchAll(PDO::FETCH_ASSOC);
$eicName = trim(($_SESSION['first_name'] ?? '').' '.($_SESSION['last_name'] ?? '')) ?: ($_SESSION['email'] ?? 'Editor-in-Chief');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Issue Management | OJMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box}body{margin:0;font-family:Inter,sans-serif;background:#f8f7f5;color:#1e293b}a{text-decoration:none;color:inherit}.navbar{background:#fff;padding:18px 5%;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e5e7eb;flex-wrap:wrap;gap:20px}.brand{display:flex;align-items:center;gap:12px;font-weight:700}.logo{width:42px;height:42px;background:#991b1b;color:white;display:flex;align-items:center;justify-content:center;border-radius:10px}.nav-links{display:flex;gap:22px;align-items:center;flex-wrap:wrap}.nav-links a{font-size:14px;color:#64748b}.nav-links a:hover,.nav-links a.active{color:#991b1b}.logout-form{margin:0}.logout-btn{border:0;background:#fef2f2;color:#991b1b;padding:10px 15px;border-radius:8px;cursor:pointer;font:600 14px Inter}.container{width:90%;max-width:1400px;margin:40px auto;min-height:60vh}.page-header{margin-bottom:30px}.page-header h1{font-size:30px;margin:0 0 12px}.page-header p{color:#64748b;margin:0;line-height:1.7}.card{background:white;border:1px solid #e2e8f0;border-radius:12px;padding:26px;margin-bottom:25px}.card h2{font-size:20px;margin:0 0 20px}.notice{padding:15px;border-radius:8px;margin-bottom:20px;font-size:14px}.success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.field{display:flex;flex-direction:column;gap:8px}.field.full{grid-column:1/-1}.field label{font-size:14px;font-weight:600;color:#475569}.field input,.field textarea,.field select{width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:8px;font:14px Inter;background:#fff}.field textarea{min-height:110px;resize:vertical}.btn{border:0;border-radius:8px;padding:11px 16px;background:#991b1b;color:white;font:600 14px Inter;cursor:pointer;display:inline-block}.btn:hover{background:#7f1d1d}.btn.secondary{background:#fef2f2;color:#991b1b}.btn.gray{background:#f1f5f9;color:#475569}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:800px}th,td{text-align:left;padding:15px;border-bottom:1px solid #e5e7eb;font-size:14px;vertical-align:top}th{background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.04em}td{color:#475569}.badge{display:inline-block;padding:6px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#f1f5f9;color:#475569}.badge.current{background:#dcfce7;color:#166534}.actions{display:flex;gap:8px;flex-wrap:wrap}.inline-form{display:inline-flex;gap:8px;align-items:center}.inline-form select{padding:9px;border:1px solid #d1d5db;border-radius:7px;font:13px Inter}.muted{color:#64748b;font-size:13px}.footer{background:#1e293b;color:white;padding:30px 5%;margin-top:60px;text-align:center;font-size:13px;color:#cbd5e1}@media(max-width:768px){.navbar{align-items:flex-start;flex-direction:column}.nav-links{gap:15px}.container{width:92%;margin:25px auto}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.card{padding:20px}}
    /* RESPONSIVE EDITOR NAVBAR */

.editor-menu-toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 48px;
    height: 48px;
    padding: 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
}

.editor-menu-toggle span {
    display: block;
    width: 22px;
    height: 2px;
    background: #1e293b;
    border-radius: 2px;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 22px;
}

.nav-links a {
    text-decoration: none;
}

@media (max-width: 768px) {
    .navbar {
        position: relative;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: nowrap !important;
        padding: 14px 20px !important;
        gap: 12px !important;
    }

    .brand {
        flex: 1;
        min-width: 0;
    }

    .brand > span:last-child {
        font-size: 14px;
    }

    .editor-menu-toggle {
        display: flex;
        flex-shrink: 0;
    }

    .nav-links {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 18px;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
        z-index: 1000;
    }

    .nav-links.is-open {
        display: flex;
    }

    .nav-links > a {
        width: 100%;
    }

    .logout-form {
        width: 100%;
    }

    .logout-btn {
        width: 100%;
    }
}

/* EDITOR-IN-CHIEF NAVBAR */

.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: #fff;
    padding: 16px clamp(24px, 5vw, 80px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e5e7eb;
    gap: 20px;
}

.editor-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    flex-shrink: 0;
}

.editor-logo {
    width: 42px;
    height: 42px;
    background: #991b1b;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
}

.editor-brand-text {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
}

.editor-nav {
    display: flex;
    align-items: center;
    gap: 24px;
}

.editor-nav > a:not(.editor-profile-pill) {
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
    transition: color 0.2s;
}

.editor-nav > a:not(.editor-profile-pill):hover,
.editor-nav > a:not(.editor-profile-pill).active {
    color: #991b1b;
}

.editor-profile-pill {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 9px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    max-width: 220px;
}

.editor-profile-pill span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.editor-profile-pill svg {
    flex-shrink: 0;
}

.editor-logout-form {
    margin: 0;
}

.editor-logout-btn {
    border: none;
    background: #fef2f2;
    color: #991b1b;
    padding: 10px 16px;
    border-radius: 999px;
    cursor: pointer;
    font: 600 14px Inter, sans-serif;
    transition: background 0.2s;
}

.editor-logout-btn:hover {
    background: #fee2e2;
}

.editor-menu-toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 44px;
    height: 44px;
    padding: 11px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
}

.editor-menu-toggle span {
    display: block;
    width: 20px;
    height: 2px;
    background: #1e293b;
    border-radius: 2px;
}

/* ACCOUNT FOR FIXED NAVBAR */

body {
    padding-top: 78px;
}

/* RESPONSIVE NAVBAR */

@media (max-width: 900px) {
    .navbar {
        padding: 14px 24px;
    }

    .editor-menu-toggle {
        display: flex;
        flex-shrink: 0;
    }

    .editor-nav {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 24px;
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }

    .editor-nav.is-open {
        display: flex;
    }

    .editor-nav > a {
        width: 100%;
    }

    .editor-profile-pill {
        max-width: 100%;
        width: fit-content;
    }

    .editor-logout-form {
        width: 100%;
    }

    .editor-logout-btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .navbar {
        padding: 12px 18px;
    }

    .editor-brand-text {
        font-size: 14px;
    }

    .editor-logo {
        width: 38px;
        height: 38px;
    }
}
</style></head><body>

<nav class="navbar">
<a href="dashboard.php" class="editor-brand">
    <span class="editor-logo">OJ</span>
    <span class="editor-brand-text">Editor Dashboard</span>
</a>

<button type="button"
    class="editor-menu-toggle"
    id="editor-menu-toggle"
    aria-label="Toggle navigation"
    aria-expanded="false"
    aria-controls="editor-nav">
    <span></span>
    <span></span>
    <span></span>
</button>

<div class="editor-nav" id="editor-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="manuscripts.php">Manuscripts</a>
    <a href="decisions.php">Editorial Decisions</a>
    <a href="issues.php" class="active">Issue Management</a>

    <a href="settings.php" class="editor-profile-pill">
        <svg xmlns="http://www.w3.org/2000/svg"
            width="18" height="18" viewBox="0 0 24 24"
            fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/>
            <path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/>
        </svg>
        <span><?= e($eicName) ?></span>
    </a>

    <form action="../auth/logout.php" method="POST" class="editor-logout-form">
        <button type="submit" class="editor-logout-btn">Log out</button>
    </form>
</div>
</nav>

<main class="container"><header class="page-header"><h1>Issue Management</h1><p>Manage journal issues, assign accepted manuscripts and publish articles.</p></header>
<?php if($successMessage): ?><div class="notice success"><?= e($successMessage) ?></div><?php endif; ?><?php if($errorMessage): ?><div class="notice error"><?= e($errorMessage) ?></div><?php endif; ?>
<section class="card"><h2><?= $editIssue?'Edit Issue':'Create New Issue' ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e($token) ?>"><input type="hidden" name="action" value="save_issue"><input type="hidden" name="issue_id" value="<?= (int)($editIssue['id']??0) ?>"><div class="form-grid"><div class="field"><label for="title">Issue Title *</label><input id="title" name="title" required value="<?= e($editIssue['title']??'') ?>"></div><div class="field"><label for="volume">Volume *</label><input id="volume" name="volume" required value="<?= e($editIssue['volume']??'') ?>"></div><div class="field"><label for="issue_number">Issue Number *</label><input id="issue_number" name="issue_number" required value="<?= e($editIssue['issue_number']??'') ?>"></div><div class="field"><label for="publication_date">Publication Date *</label><input type="date" id="publication_date" name="publication_date" required value="<?= e($editIssue['publication_date']??'') ?>"></div><div class="field full"><label for="description">Description</label><textarea id="description" name="description"><?= e($editIssue['description']??'') ?></textarea></div></div><p style="margin-top:20px"><button class="btn" type="submit"><?= $editIssue?'Save Changes':'Create Issue' ?></button><?php if($editIssue): ?> <a class="btn gray" href="issues.php">Cancel Edit</a><?php endif; ?></p></form></section>
<section class="card"><h2>Existing Issues</h2><?php if(!$issues): ?><p class="muted">No issues have been created yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Issue</th><th>Publication Date</th><th>Published Articles</th><th>Current</th><th>Actions</th></tr></thead><tbody><?php foreach($issues as $issue): ?><tr><td><strong><?= e($issue['title']) ?></strong><br><span class="muted">Volume <?= e($issue['volume']) ?> · Issue <?= e($issue['issue_number']) ?></span></td><td><?= e($issue['publication_date']) ?></td><td><?= (int)$issue['article_count'] ?></td><td><?php if($issue['is_current']): ?><span class="badge current">Current Issue</span><?php else: ?><span class="badge">Not Current</span><?php endif; ?></td><td><div class="actions"><a class="btn secondary" href="issues.php?edit=<?= (int)$issue['id'] ?>">Edit</a><?php if(!$issue['is_current']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($token) ?>"><input type="hidden" name="action" value="set_current"><input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>"><button class="btn" type="submit">Set Current</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="card"><h2>Assign &amp; Publish Manuscript</h2><p class="muted">Only accepted manuscripts (and published manuscripts not yet assigned to an issue) are available here. Assigning a manuscript will publish it.</p><?php if(!$acceptedArticles || !$issues): ?><p class="muted">You need at least one issue and one eligible manuscript before publishing.</p><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($token) ?>"><input type="hidden" name="action" value="assign_publish"><div class="form-grid"><div class="field"><label for="article_id">Manuscript</label><select id="article_id" name="article_id" required><option value="">Select manuscript</option><?php foreach($acceptedArticles as $article): ?><option value="<?= (int)$article['id'] ?>"><?= e($article['title']) ?> — <?= e($article['authors']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="issue_id">Issue</label><select id="issue_id" name="issue_id" required><option value="">Select issue</option><?php foreach($issues as $issue): ?><option value="<?= (int)$issue['id'] ?>">Vol. <?= e($issue['volume']) ?>, Issue <?= e($issue['issue_number']) ?> — <?= e($issue['title']) ?></option><?php endforeach; ?></select></div></div><p style="margin-top:20px"><button class="btn" type="submit" onclick="return confirm('Assign this manuscript to the selected issue and publish it?');">Assign &amp; Publish</button></p></form><?php endif; ?></section>
<section class="card"><h2>Assigned Articles</h2><?php if(!$assignedArticles): ?><p class="muted">No articles have been assigned to issues yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Article</th><th>Issue</th><th>Status</th></tr></thead><tbody><?php foreach($assignedArticles as $article): ?><tr><td><strong><?= e($article['title']) ?></strong><br><span class="muted"><?= e($article['authors']) ?></span></td><td><?= e($article['issue_title']??'') ?><br><span class="muted">Vol. <?= e($article['volume']??'') ?> · Issue <?= e($article['issue_number']??'') ?></span></td><td><span class="badge"><?= e($article['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></main>
    <footer class="footer footer-simple">
    <p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>
<script>
// Mobile navigation
const editorToggle = document.getElementById('editor-menu-toggle');
const editorNav = document.getElementById('editor-nav');

if (editorToggle && editorNav) {
    editorToggle.addEventListener('click', function() {
        const isOpen = editorNav.classList.toggle('is-open');
        editorToggle.setAttribute('aria-expanded', isOpen);
    });

    editorNav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function() {
            editorNav.classList.remove('is-open');
            editorToggle.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('click', function(event) {
        if (!editorNav.contains(event.target) &&
            !editorToggle.contains(event.target)) {
            editorNav.classList.remove('is-open');
            editorToggle.setAttribute('aria-expanded', 'false');
        }
    });
}
</script>
</body></html>
