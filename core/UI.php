<?php
/**
 * IQA Global UI Component Library
 * Standardized components for a premium, consistent warehouse experience.
 */

require_once __DIR__ . '/Security.php';

class UI {
    /**
     * Renders a hidden CSRF protection field
     */
    public static function csrf_field() {
        $token = Security::getToken();
        return "<input type='hidden' name='csrf_token' value='{$token}'>";
    }

    /**
     * Renders a premium Glassmorphic Stat Card
     */
    public static function stat_card($title, $value, $class = '', $id = '') {
        $id_attr = $id ? "id='" . htmlspecialchars($id) . "'" : "";
        $title_esc = htmlspecialchars($title);
        $value_esc = htmlspecialchars($value);
        return "
        <div {$id_attr} class='panel stat-card " . htmlspecialchars($class) . "'>
            <div class='stat-header'>
                <span class='stat-title'>{$title_esc}</span>
            </div>
            <div class='stat-value'>{$value_esc}</div>
        </div>";
    }

    /**
     * Renders a Status Badge
     */
    public static function badge($text, $type = 'default') {
        $type_class = "badge-" . strtolower(str_replace(' ', '-', $type));
        return "<span class='badge " . htmlspecialchars($type_class) . "'>" . htmlspecialchars($text) . "</span>";
    }

    /**
     * Renders a Premium Action Button
     */
    public static function button($text, $link = '#', $icon = '', $class = 'btn-primary') {
        $icon_html = $icon ? "<span class='btn-icon'>" . $icon . "</span>" : "";
        return "
        <a href='" . htmlspecialchars($link) . "' class='btn " . htmlspecialchars($class) . "'>
            {$icon_html}
            <span class='btn-text'>" . htmlspecialchars($text) . "</span>
        </a>";
    }

    /**
     * Renders a Quick Action Hub Button (Marketing style)
     */
    public static function action_button($text, $link, $emoji, $style = '') {
        return "
        <a href='" . htmlspecialchars($link) . "' class='btn action-hub-btn' style='" . htmlspecialchars($style) . "'>
            <span style='margin-right: 12px; font-size: 1.2rem;'>" . htmlspecialchars($emoji) . "</span>
            " . htmlspecialchars($text) . "
        </a>";
    }

    /**
     * Renders an Opportunity Card (Marketing style)
     */
    public static function opportunity_card($title, $desc, $link, $btn_text, $type = 'READY') {
        $icon = '💡';
        if ($type === 'NEED_PHOTO') $icon = '📸';
        if ($type === 'NEED_TEMPLATE') $icon = '📝';
        
        return "
        <div class='opportunity-card opp-" . htmlspecialchars($type) . "'>
            <div class='opp-icon'>{$icon}</div>
            <div class='opp-content'>
                <h3>" . htmlspecialchars($title) . "</h3>
                <p>" . htmlspecialchars($desc) . "</p>
                <a href='" . htmlspecialchars($link) . "' class='btn btn-small'>" . htmlspecialchars($btn_text) . "</a>
            </div>
        </div>";
    }

    /**
     * Renders an Activity Feed Item
     */
    public static function activity_item($icon, $title, $subtitle) {
        return "
        <div class='activity-item'>
            <div class='activity-icon'>" . $icon . "</div>
            <div class='activity-details'>
                <div class='activity-title'>".htmlspecialchars($title)."</div>
                <div class='activity-subtitle'>".htmlspecialchars($subtitle)."</div>
            </div>
        </div>";
    }

    /**
     * Smart formats specifications (handles pasted Google Sheets data)
     */
    public static function format_specs($text) {
        if (empty($text)) return "<span class='text-dim italic'>No specs defined.</span>";
        
        // Detect if it's tab-separated (spreadsheet paste)
        if (strpos($text, "\t") !== false) {
            $lines = explode("\n", str_replace("\r", "", trim($text)));
            $html = "<div class='spec-table-container'>";
            
            $isFirst = true;
            $gridTemplate = "";
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $parts = explode("\t", $line);
                
                if ($isFirst) {
                    $colCount = count($parts);
                    $gridTemplate = "grid-template-columns: repeat($colCount, 1fr);";
                    $html .= "<div class='spec-header-row' style='{$gridTemplate}'>";
                    foreach ($parts as $part) {
                        $cleanPart = htmlspecialchars(trim($part));
                        $html .= "<span>" . $cleanPart . "</span>";
                    }
                    $html .= "</div>";
                    $isFirst = false;
                    continue;
                }

                $html .= "<div class='spec-data-row' style='{$gridTemplate}'>";
                foreach ($parts as $part) {
                    $html .= "<span>" . htmlspecialchars(trim($part)) . "</span>";
                }
                $html .= "</div>";
            }
            $html .= "</div>";
            return $html;
        }

        return nl2br(htmlspecialchars($text));
    }

    /**
     * Renders a Dark Mode Toggle Switch
     */
    public static function theme_toggle() {
        return "
        <div class='theme-toggle-wrapper'>
            <button id='themeToggle' class='theme-btn' title='Toggle Dark Mode'>
                <span class='theme-icon-sun'>☀️</span>
                <span class='theme-icon-moon'>🌙</span>
            </button>
            <script>
                (function() {
                    const theme = localStorage.getItem('iqa_theme') || 'light';
                    document.documentElement.setAttribute('data-theme', theme);
                    
                    document.addEventListener('DOMContentLoaded', () => {
                        const btn = document.getElementById('themeToggle');
                        if (!btn) return;
                        
                        btn.addEventListener('click', () => {
                            const current = document.documentElement.getAttribute('data-theme');
                            const next = current === 'dark' ? 'light' : 'dark';
                            document.documentElement.setAttribute('data-theme', next);
                            localStorage.setItem('iqa_theme', next);
                        });
                    });
                })();
            </script>
        </div>";
    }

    /**
     * Renders any pending notifications from Session or URL
     */
    public static function render_notifications() {
        $msg = $_SESSION['notification_msg'] ?? null;
        $type = $_SESSION['notification_type'] ?? 'info';
        unset($_SESSION['notification_msg'], $_SESSION['notification_type']);

        $script = "";
        if ($msg) {
            $msg_esc = addslashes(htmlspecialchars($msg));
            $script .= "IQA_Notify.show('{$msg_esc}', '" . addslashes(htmlspecialchars($type)) . "');";
        }

        if ($script) {
            return "<script>document.addEventListener('DOMContentLoaded', () => { {$script} });</script>";
        }
        return "";
    }
}
