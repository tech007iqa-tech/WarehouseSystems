/**
 * Marketing Hub - Main Application Script
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('Marketing Hub Initialized');
});

/**
 * Utility: Copy text to clipboard with feedback
 */
function copyToClipboard(text, description = 'Content') {
    if (!text) return;

    try {
        navigator.clipboard.writeText(text).then(() => {
            notify(`${description} copied to clipboard!`, 'success', '📋 Copied');
        });
    } catch (err) {
        console.error('Failed to copy: ', err);
        notify('Failed to copy to clipboard.', 'error');
    }
}
/**
 * Toast Notification System
 */
const notify = (message, type = 'success', title = '') => {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    const defaultTitles = {
        success: 'Success',
        error: 'Error',
        warning: 'Attention',
        info: 'Update'
    };

    toast.innerHTML = `
        <div class="toast-icon">${icons[type]}</div>
        <div class="toast-content">
            <span class="toast-title">${title || defaultTitles[type]}</span>
            <span class="toast-message">${message}</span>
        </div>
    `;

    container.appendChild(toast);

    // Auto remove
    const timer = setTimeout(() => {
        dismissToast(toast);
    }, 4000);

    toast.onclick = () => {
        clearTimeout(timer);
        dismissToast(toast);
    };
};

const dismissToast = (toast) => {
    toast.classList.add('hide');
    setTimeout(() => {
        toast.remove();
    }, 300);
};

// Global access
window.notify = notify;
