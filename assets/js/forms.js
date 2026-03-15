/**
 * Dynamic Form UI Components & Submission Handlers
 */

document.addEventListener("DOMContentLoaded", () => {

    /* --- RAM & STORAGE CHECKBOX TOGGLES --- */
    const hasRam = document.getElementById('has_ram');
    const ramInput = document.getElementById('ram');
    
    if (hasRam && ramInput) {
        hasRam.addEventListener('change', (e) => {
            ramInput.disabled = !e.target.checked;
            if(!e.target.checked) ramInput.value = "";
        });
    }

    const hasStorage = document.getElementById('has_storage');
    const storageInput = document.getElementById('storage');
    
    if (hasStorage && storageInput) {
        hasStorage.addEventListener('change', (e) => {
            storageInput.disabled = !e.target.checked;
            if(!e.target.checked) storageInput.value = "";
        });
    }


    /* --- NEW LABEL SUBMISSION (Vibe Code Async fetch) --- */
    const newLabelForm = document.getElementById('newLabelForm');
    
    if (newLabelForm) {
        newLabelForm.addEventListener('submit', async (e) => {
            e.preventDefault(); // Stop full reload

            const btn = document.getElementById('submitLabelBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Processing...';
            btn.disabled = true;

            const formData = new FormData(newLabelForm);

            try {
                // Submit to our strict JSON endpoint
                const response = await fetch('api/add_label.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(`✅ Generated Location: \n${result.data.file_path}`);
                    newLabelForm.reset(); // Clear for next laptop
                    
                    // Reset custom toggles
                    if(ramInput) ramInput.disabled = true;
                    if(storageInput) storageInput.disabled = true;

                } else {
                    alert(`❌ Error: ${result.error}`);
                }
            } catch (err) {
                console.error(err);
                alert("❌ Critical Network Error attempting to connect to /api/add_label.php");
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    }

    /* --- NEW CRM CONTACT SUBMISSION --- */
    const newCustomerForm = document.getElementById('newCustomerForm');
    
    if (newCustomerForm) {
        newCustomerForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = document.getElementById('submitCustomerBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Saving...';
            btn.disabled = true;

            const formData = new FormData(newCustomerForm);

            try {
                const response = await fetch('api/add_customer.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(`✅ Contact Saved (ID: C-${result.data.customer_id})`);
                    newCustomerForm.reset(); 
                } else {
                    alert(`❌ Error: ${result.error}`);
                }
            } catch (err) {
                console.error(err);
                alert("❌ Critical Network Error attempting to connect to /api/add_customer.php");
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    }

});
