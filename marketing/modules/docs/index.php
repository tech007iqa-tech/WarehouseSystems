<?php
/**
 * Documentation Module - Knowledge Base & Guidelines
 */

$docsDir = __DIR__ . '/../../DOCS';
$selectedDoc = $_GET['file'] ?? null;
$docContent = null;

if ($selectedDoc) {
    $filePath = realpath($docsDir . '/' . $selectedDoc);
    // Security check to ensure the file is within the DOCS directory
    if ($filePath && strpos($filePath, realpath($docsDir)) === 0 && file_exists($filePath)) {
        $docContent = file_get_contents($filePath);
    } else {
        $error = "Document not found or access denied.";
    }
}

// Get all .md files
$files = glob($docsDir . '/*.md');
?>

<header class="page-header">
    <h1>Knowledge Base</h1>
    <p>Standard operating procedures and technical documentation for the Marketing Hub.</p>
</header>

<div style="display: grid; grid-template-columns: 300px 1fr; gap: 2rem; align-items: start;">
    <!-- SIDEBAR: DOC LIST -->
    <section class="card" style="position: sticky; top: 100px; padding: 1.5rem;">
        <h2 style="font-size: 0.8rem; text-transform: uppercase; margin-bottom: 1.5rem;">Documentation</h2>
        <nav class="docs-nav">
            <?php foreach ($files as $file): 
                $basename = basename($file);
                $title = str_replace(['_', '.md'], [' ', ''], $basename);
                $active = ($selectedDoc === $basename) ? 'active' : '';
            ?>
                <a href="?page=docs&file=<?php echo urlencode($basename); ?>" class="docs-link <?php echo $active; ?>">
                    <?php echo ucwords(strtolower($title)); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </section>

    <!-- CONTENT: DOC VIEWER -->
    <section class="card" style="min-height: 600px; padding: 3rem;">
        <?php if ($docContent): ?>
            <div class="markdown-view">
                <?php 
                    // Basic Markdown-to-HTML conversion for headings and lists
                    $html = htmlspecialchars($docContent);
                    $html = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $html);
                    $html = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $html);
                    $html = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $html);
                    $html = preg_replace('/^- (.*)$/m', '<li>$1</li>', $html);
                    $html = preg_replace('/\n\n/', '<p></p>', $html);
                    echo nl2br($html); 
                ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding-top: 4rem; color: var(--text-dim);">
                <div style="font-size: 4rem; margin-bottom: 1.5rem;">📖</div>
                <h2>Select a guide from the left</h2>
                <p>Read the standard operating procedures for the warehouse marketing team.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<style>
.docs-nav {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.docs-link {
    text-decoration: none;
    color: var(--text-dim);
    font-size: 0.9rem;
    padding: 10px 12px;
    border-radius: 8px;
    transition: all 0.2s;
    font-weight: 600;
}
.docs-link:hover {
    background: var(--accent-tertiary);
    color: var(--accent-primary);
}
.docs-link.active {
    background: var(--accent-primary);
    color: white;
}
.markdown-view h1 { font-size: 2rem; margin-bottom: 1.5rem; color: var(--text-main); }
.markdown-view h2 { font-size: 1.4rem; margin-top: 2rem; margin-bottom: 1rem; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
.markdown-view h3 { font-size: 1.1rem; margin-top: 1.5rem; margin-bottom: 0.75rem; color: var(--text-main); }
.markdown-view li { margin-left: 1.5rem; margin-bottom: 0.5rem; list-style-type: disc; }
</style>
