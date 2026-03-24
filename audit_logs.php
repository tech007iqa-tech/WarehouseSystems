<?php
// audit_logs.php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Helper for relative time (e.g. "2 minutes ago")
function time_ago($timestamp) {
    if (!$timestamp) return "—";
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return round($diff / 60) . "m ago";
    if ($diff < 86400) return round($diff / 3600) . "h ago";
    if ($diff < 604800) return round($diff / 86400) . "d ago";
    return date("M j", $time);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Fetch logs
try {
    $stmt = $pdo_audit->prepare("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

    $total = (int)$pdo_audit->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
    $totalPages = ceil($total / $limit);
} catch (Exception $e) {
    $logs = []; $total = 0; $totalPages = 1;
}
?>

<div class="panel mobile-header-panel" style="margin-bottom: var(--spacing); border-left: 5px solid var(--accent-color);">
    <div class="flex-between">
        <div>
            <h1 style="letter-spacing:-1px;">🛡️ Audit Logs</h1>
            <p style="color:var(--text-secondary); font-size: 0.9rem;">Real-time system security & change tracking.</p>
        </div>
        <div class="desktop-only" style="text-align:right;">
             <div style="font-size: 1.4rem; font-weight: 800; color: var(--accent-color);"><?= number_format($total) ?></div>
             <div style="font-size: 0.65rem; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);">Logged Events</div>
        </div>
    </div>
</div>

<div class="panel" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color);">
    <div style="padding: 20px; background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin:0; font-size: 1.1rem;">🔥 Activity Stream</h3>
        <div class="pagination-controls glass-pager">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="pager-btn">←</a>
            <?php endif; ?>
            <span class="pager-info">Page <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="pager-btn">→</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="audit-table">
            <thead>
                <tr>
                    <th style="width: 140px;">When</th>
                    <th style="width: 120px;">Action</th>
                    <th style="width: 150px;">Target</th>
                    <th>Change Description</th>
                    <th style="width: 100px;">By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" style="text-align:center; padding: 60px; color: var(--text-secondary); font-style: italic;">No system activity recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $statusClass = strtolower($log['action']);
                        
                        $icon = '📝';
                        if ($log['action'] === 'CREATED')   $icon = '➕';
                        if ($log['action'] === 'DELETED')   $icon = '🗑️';
                        if ($log['action'] === 'UPDATED')   $icon = '✏️';
                        if ($log['action'] === 'STATUS_CHANGE') $icon = '🔄';
                    ?>
                        <tr class="audit-row">
                            <td class="time-col">
                                <div style="font-weight: 800; color: var(--text-main);"><?= time_ago($log['timestamp']) ?></div>
                                <div style="font-size: 0.65rem; color: var(--text-secondary);"><?= date("M j, H:i", strtotime($log['timestamp'])) ?></div>
                            </td>
                            <td>
                                <div class="action-pill action-<?= $statusClass ?>">
                                    <?= $icon ?> <?= htmlspecialchars($log['action']) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 800;"><?= htmlspecialchars($log['entity_type']) ?></div>
                                <div style="font-weight: 800; color: var(--accent-color);">ID: <?= htmlspecialchars($log['entity_id']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 700; margin-bottom: 4px;"><?= htmlspecialchars($log['summary']) ?></div>
                                <?php if ($log['old_value'] || $log['new_value']): ?>
                                    <details class="data-diff">
                                        <summary>Inspect Raw JSON Changes</summary>
                                        <div class="diff-grid">
                                            <div class="diff-panel">
                                                <div class="panel-label">PRE-STATE</div>
                                                <pre><?= htmlspecialchars($log['old_value'] ?: '—') ?></pre>
                                            </div>
                                            <div class="diff-panel">
                                                <div class="panel-label">POST-STATE</div>
                                                <pre><?= htmlspecialchars($log['new_value'] ?: '—') ?></pre>
                                            </div>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="user-chip"><?= htmlspecialchars($log['user_name']) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.audit-table { width: 100%; border-collapse: collapse; }
.audit-table th { text-align: left; padding: 15px 20px; background: rgba(0,0,0,0.01); font-size: 0.65rem; text-transform: uppercase; color: var(--text-secondary); border-bottom: 2px solid var(--border-color); }
.audit-table td { padding: 18px 20px; border-bottom: 1px solid var(--border-color); vertical-align: top; }
.audit-row:hover { background: rgba(140, 198, 63, 0.02); }

/* PILLS */
.action-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; color: #fff; text-transform: uppercase; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
.action-created { background: #10b981; }
.action-updated { background: #3b82f6; }
.action-deleted { background: #ef4444; }
.action-status_change { background: #f59e0b; }

.user-chip { background: var(--bg-page); border: 1px solid var(--border-color); padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); display: inline-block; }

/* DATA DIFF GLASSMorphism */
.data-diff { margin-top: 8px; }
.data-diff summary { font-size: 0.7rem; color: var(--accent-color); font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; opacity: 0.7; transition: 0.2s; }
.data-diff summary:hover { opacity: 1; }
.diff-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; padding: 15px; background: #0f172a; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); }
.diff-panel { display: flex; flex-direction: column; gap: 8px; }
.panel-label { font-size: 0.6rem; font-weight: 900; color: #64748b; letter-spacing: 1px; }
.diff-panel pre { margin: 0; font-family: 'Courier New', monospace; font-size: 0.7rem; color: #cbd5e1; white-space: pre-wrap; word-break: break-all; max-height: 200px; overflow-y: auto; line-height: 1.4; }

/* PAGER STYLING */
.glass-pager { display: flex; align-items: center; gap: 12px; background: var(--bg-page); padding: 5px 12px; border-radius: 30px; border: 1px solid var(--border-color); }
.pager-btn { text-decoration: none; color: var(--text-main); font-weight: 800; font-size: 1.1rem; line-height: 1; transition: 0.2s; }
.pager-btn:hover { color: var(--accent-color); transform: scale(1.2); }
.pager-info { font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); }

@media (max-width: 768px) {
    .diff-grid { grid-template-columns: 1fr; }
    .audit-table th:nth-child(1), .audit-table th:nth-child(5) { display: none; }
    .audit-table td:nth-child(1), .audit-table td:nth-child(5) { display: none; }
}
</style>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const navLink = document.getElementById('nav-audit');
        if (navLink) navLink.classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
