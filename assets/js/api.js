/**
 * Global JavaScript helper file included in footer.php
 * Handles fetch() wrap functions and toast notifications
 */

/**
 * Standard Fetch Wrapper
 * usage: apiRequest('api/add_laptop.php', formData).then(alert('done'));
 */
async function apiRequest(url, formData) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });

        // Try to parse the standard JSON we built in functions.php
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Server returned an error status.');
        }

        return data;

    } catch (err) {
        console.error('API Request Failed:', err);
        throw err;
    }
}

/**
 * Basic Toast Notification UI (To be expanded in later phases)
 */
function showToast(message, type = 'success') {
    alert(`[${type.toUpperCase()}] ${message}`); 
    // In Phase 3, this will be swapped for a nice DOM popup div sliding in from the right.
}
