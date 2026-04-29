/**
 * Marketing Hub - Main Application Script
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('Marketing Hub Initialized');
});

/**
 * Utility: Copy text to clipboard with feedback
 */
function copyToClipboard(elementId, buttonElement) {
    const copyText = document.getElementById(elementId);
    if (!copyText) return;

    copyText.select();
    copyText.setSelectionRange(0, 99999);
    
    try {
        navigator.clipboard.writeText(copyText.value).then(() => {
            const originalText = buttonElement.innerHTML;
            buttonElement.innerHTML = "✅ Copied!";
            buttonElement.classList.add('success');
            
            setTimeout(() => {
                buttonElement.innerHTML = originalText;
                buttonElement.classList.remove('success');
            }, 2000);
        });
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
}
