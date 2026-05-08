/**
 * IQA Global Notification System
 * A lightweight, glassmorphic toast notification engine.
 */

class Notifications {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        if (document.getElementById('toast-container')) return;
        
        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        document.body.appendChild(this.container);
    }

    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let icon = 'ℹ️';
        if (type === 'success') icon = '✨';
        if (type === 'error') icon = '⚠️';
        if (type === 'warning') icon = '🔔';

        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
        `;

        this.container.appendChild(toast);

        // Auto-remove
        setTimeout(() => this.hide(toast), duration);
        
        // Manual remove on click
        toast.onclick = () => this.hide(toast);
    }

    hide(toast) {
        if (toast.classList.contains('hiding')) return;
        
        toast.classList.add('hiding');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }

    // Shortcut methods
    success(msg, dur) { this.show(msg, 'success', dur); }
    error(msg, dur) { this.show(msg, 'error', dur); }
    warn(msg, dur) { this.show(msg, 'warning', dur); }
    info(msg, dur) { this.show(msg, 'info', dur); }
}

// Global instance
window.IQA_Notify = new Notifications();

// Check for session-based notifications (PHP flash messages)
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');
        if (msg === 'customer_deleted') IQA_Notify.success('Customer deleted successfully');
        if (msg === 'order_deleted') IQA_Notify.success('Order removed from registry');
    }
});
