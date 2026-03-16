/**
 * Dynamic Form UI Components & Submission Handlers
 */
const newLabelForm = document.getElementById('newLabelForm');

document.addEventListener("DOMContentLoaded", () => {
    /* --- RAM & STORAGE CHECKBOX TOGGLES --- */
    const hasRam = document.getElementById('has_ram');
    const ramInput = document.getElementById('ram');
    const hasStorage = document.getElementById('has_storage');
    const storageInput = document.getElementById('storage');
    
    if (hasRam && ramInput) {
        hasRam.addEventListener('change', (e) => {
            ramInput.disabled = !e.target.checked;
            if (e.target.checked) {
                // Auto-set common default if currently empty
                if (!ramInput.value) ramInput.value = "8GB";
            } else {
                ramInput.value = "";
            }
        });
    }

    if (hasStorage && storageInput) {
        hasStorage.addEventListener('change', (e) => {
            storageInput.disabled = !e.target.checked;
            if (e.target.checked) {
                // Auto-set common default if currently empty
                if (!storageInput.value) storageInput.value = "256GB NVMe";
            } else {
                storageInput.value = "";
            }
        });
    }

    /* --- SMART BIOS DEFAULTS BASED ON CONDITION --- */
    const conditionSelect = document.getElementById('description');
    const biosSelect      = document.getElementById('bios_state');

    if (conditionSelect && biosSelect) {
        conditionSelect.addEventListener('change', (e) => {
            const cond = e.target.value;
            if (cond === 'Untested' || cond === 'For Parts') {
                biosSelect.value = 'Unknown';
            } else if (cond === 'Refurbished') {
                biosSelect.value = 'Unlocked';
            }
        });
    }

    /* --- CUSTOM NARROWING CPU SEARCH --- */
    const cpuInput   = document.getElementById('cpu_gen');
    const cpuWrapper = document.getElementById('cpuSearchWrapper');
    const cpuGens = [
        "6th - 7th Gen",
        "i5 · 8th Gen", "i5 · 9th Gen", "i5 · 10th Gen", "i5 · 11th Gen", "i5 · 12th Gen", "i5 · 13th Gen",
        "i7 · 8th Gen", "i7 · 9th Gen", "i7 · 10th Gen", "i7 · 11th Gen", "i7 · 12th Gen", "i7 · 13th Gen", "i7 · 14th Gen"
    ];

    if (cpuInput && cpuWrapper) {
        cpuInput.addEventListener('input', () => {
            const val = cpuInput.value.toLowerCase().trim();
            if (!val) {
                cpuWrapper.style.display = 'none';
                return;
            }

            const matches = cpuGens.filter(g => g.toLowerCase().includes(val));
            
            if (matches.length > 0) {
                cpuWrapper.innerHTML = matches.map(m => `
                    <div class="cpu-opt" style="padding: 10px 15px; cursor: pointer; border-bottom: 1px solid var(--border-color); font-size: 0.9rem;" onmouseover="this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.background='transparent'">
                        ${m}
                    </div>
                `).join('');
                cpuWrapper.style.display = 'block';

                // Handle Selection
                cpuWrapper.querySelectorAll('.cpu-opt').forEach((opt, idx) => {
                    opt.addEventListener('click', () => {
                        cpuInput.value = matches[idx];
                        cpuWrapper.style.display = 'none';
                    });
                });
            } else {
                cpuWrapper.style.display = 'none';
            }
        });

        // Hide when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target !== cpuInput) cpuWrapper.style.display = 'none';
        });
    }

    /* --- NEW LABEL SUBMISSION (Success Overlay Logic) --- */
    const successOverlay = document.getElementById('successOverlay');
    const successMsg     = document.getElementById('successMsg');
    let lastInsertedId   = null;

    if (newLabelForm) {
        newLabelForm.addEventListener('submit', async (e) => {
            e.preventDefault(); 

            const btn = document.getElementById('submitLabelBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Processing...';
            btn.disabled = true;

            const formData = new FormData(newLabelForm);

            try {
                const response = await fetch('api/add_label.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    lastInsertedId = result.data.id;
                    const name = (formData.get('brand') || '') + ' ' + (formData.get('model') || '');
                    
                    let msg = `Saved <strong>${name}</strong> to ID #${String(lastInsertedId).padStart(5, '0')}.<br>The ODT label has been generated.`;
                    if (result.data.is_duplicate) {
                        msg = `Found existing profile for <strong>${name}</strong> (ID #${String(lastInsertedId).padStart(5, '0')}).<br>The ODT label has been refreshed.`;
                    }
                    
                    successMsg.innerHTML = msg;
                    successOverlay.style.display = 'flex';

                    // Optional: Try to open file automatically
                    if(result.data.file_path) window.open(result.data.file_path, '_blank');

                } else {
                    alert(`❌ Error: ${result.error}`);
                }
            } catch (err) {
                console.error("Submission Error:", err);
                alert(`❌ Network Error: Could not connect to the label engine. (Details: ${err.message})`);
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    }

    // Success Overlay Buttons
    if(successOverlay) {
        // "Print Another" - Calls reprint API for the exact same ID
        document.getElementById('btnAgain').addEventListener('click', () => {
            const btn = document.getElementById('btnAgain');
            btn.disabled = true;
            btn.textContent = '⏳ Printing...';

            const fd = new FormData();
            fd.append('id', lastInsertedId);

            fetch('api/reprint_label.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(json => {
                    if(json.success && json.data.file_path) {
                        window.open(json.data.file_path, '_blank');
                    }
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = '🔄 Print Another (Same Profile)';
                });
        });

        // "Add New Hardware" - Clears form and hides overlay
        document.getElementById('btnReset').addEventListener('click', () => {
            const pinLoc     = document.getElementById('pin_location');
            const locField   = document.getElementById('warehouse_location');
            const savedLoc   = locField ? locField.value : '';

            newLabelForm.reset();

            // Restore location if pinned
            if (pinLoc && pinLoc.checked && locField) {
                locField.value = savedLoc;
            }

            // Ensure RAM/Storage are enabled by default after reset (matching HTML defaults)
            if (hasRam)      ramInput.disabled     = !hasRam.checked;
            if (hasStorage)  storageInput.disabled = !hasStorage.checked;

            successOverlay.style.display = 'none';
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
    /* --- EDIT CRM CONTACT SUBMISSION --- */
    const editCustomerForm = document.getElementById('editCustomerForm');
    
    if (editCustomerForm) {
        editCustomerForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = document.getElementById('submitEditCustomerBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Saving...';
            btn.disabled = true;

            const formData = new FormData(editCustomerForm);

            try {
                const response = await fetch('api/edit_customer.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(`✅ Changes Saved Successfully.`);
                    // Redirect back to the view card
                    window.location.href = `customer_view.php?id=${formData.get('customer_id')}`;
                } else {
                    alert(`❌ Error: ${result.error}`);
                }
            } catch (err) {
                console.error(err);
                alert("❌ Critical Network Error attempting to connect to /api/edit_customer.php");
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    }

});
