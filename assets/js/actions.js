/**
 * assets/js/actions.js
 * Universal Hardware Actions: Open, Print, and Launch.
 */
'use strict';

/**
 * Universal Bridge to open any file in Windows via the server.
 * @param {string} relativePath - Path relative to the project root (e.g., 'exports/labels/file.odt').
 */
async function launchFile(relativePath) {
    try {
        const formData = new FormData();
        formData.append('path', relativePath);

        const response = await fetch('api/open_windows_file.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (!result.success) {
            alert('Launch Error: ' + result.error);
        }
    } catch (err) {
        console.error(err);
        alert('Network error: Could not connect to Windows Bridge.');
    }
}

/**
 * Flash Launch a Label ODT.
 * Checks for existence first, then opens or generates.
 * @param {number} id 
 * @param {string} brand 
 * @param {string} model 
 * @param {HTMLElement|null} btn - Optional button to show loading state
 */
async function flashOpenLabel(id, brand, model, btn = null) {
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '📂 ⏳';
    }

    try {
        const safeName = (brand + model).replace(/[^a-zA-Z0-9]/g, '');
        const fileName = `Label_${id}_${safeName}.odt`;
        const filePath = `exports/labels/${fileName}`;

        // Check if file exists
        const checkRes = await fetch(`api/check_file_exists.php?path=${encodeURIComponent(filePath)}`);
        const checkJson = await checkRes.json();

        if (checkJson.success && checkJson.data.exists) {
            // EXISTS: Direct Launch
            await launchFile(filePath);
        } else {
            // NOT FOUND: Generate then open
            const formData = new FormData();
            formData.append('id', id);
            formData.append('mode', 'open');
            formData.append('qty', 1);
            formData.append('print_a', 1);
            formData.append('print_b', 1);

            const genRes = await fetch('api/reprint_label.php', { method: 'POST', body: formData });
            const genJson = await genRes.json();
            if (!genJson.success) alert('Generation failed: ' + genJson.error);
        }
    } catch (err) {
        console.error(err);
        alert('Action error: Could not open technical label.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}
