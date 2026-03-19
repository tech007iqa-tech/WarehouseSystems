const newLabelForm = document.getElementById('newLabelForm');

document.addEventListener("DOMContentLoaded", () => {
    const F = window.HW_FIELDS;
    if (!F) return; // Guard against missing mapping

    /* --- SECTION TOGGLING (Untested vs Refurbished) --- */
    const conditionSelect = document.getElementById(F.DESCRIPTION);
    const technicalSection = document.getElementById('technicalSpecsSection');
    const biosSelect = document.getElementById(F.BIOS_STATE);
    const statusSelect = document.getElementById(F.STATUS);

    if (conditionSelect) {
        const updateStatusOptions = (cond) => {
            if (!statusSelect) return;

            Array.from(statusSelect.options).forEach(opt => {
                let show = false;
                if (cond === 'Refurbished') show = (opt.value === 'Tested');
                else if (cond === 'Untested') show = ['In Warehouse', 'Grade A', 'Grade B', 'Grade C'].includes(opt.value);
                else if (cond === 'For Parts') show = ['In Warehouse', 'No Post', 'No Power'].includes(opt.value);

                opt.style.display = show ? 'block' : 'none';
                opt.disabled = !show;
            });

            // Force automatic status selection
            if (cond === 'Refurbished') {
                statusSelect.value = 'Tested';
            } else if (cond === 'Untested' && !['In Warehouse', 'Grade A', 'Grade B', 'Grade C'].includes(statusSelect.value)) {
                statusSelect.value = 'In Warehouse';
            } else if (statusSelect.options[statusSelect.selectedIndex] && statusSelect.options[statusSelect.selectedIndex].disabled) {
                const validOpt = Array.from(statusSelect.options).find(o => !o.disabled);
                if (validOpt) statusSelect.value = validOpt.value;
            }
        };

        conditionSelect.addEventListener('change', (e) => {
            const cond = e.target.value;

            if (technicalSection) technicalSection.style.display = (cond === 'Refurbished') ? 'block' : 'none';

            if (biosSelect) {
                if (cond === 'Untested' || cond === 'For Parts') biosSelect.value = 'Unknown';
                else if (cond === 'Refurbished') biosSelect.value = 'Unlocked';
            }

            updateStatusOptions(cond);
        });

        // Initialize state without overwriting BIOS defaults
        const initialCond = conditionSelect.value;
        if (technicalSection) technicalSection.style.display = (initialCond === 'Refurbished') ? 'block' : 'none';
        updateStatusOptions(initialCond);
    }

    /* --- PROFILE CLONING LOGIC --- */
    document.querySelectorAll('.clone-trigger').forEach(card => {
        card.addEventListener('click', () => {
            const data = card.dataset;

            // Mapping keys to their logic names in dataset (camelCase)
            const fieldsToClone = [
                F.BRAND, F.MODEL, F.SERIES, F.CPU_GEN, F.CPU_SPECS,
                F.CPU_CORES, F.CPU_SPEED, F.RAM, F.STORAGE
            ];

            fieldsToClone.forEach(f => {
                const el = document.getElementById(f);
                if (el) {
                    const val = data[f.replace(/_([a-z])/g, (g) => g[1].toUpperCase())] || '';
                    el.value = val;

                    // SPECIAL HANDLE FOR CPU SPECS CLONING
                    if (f === F.CPU_SPECS) {
                        const prefixDisplay = document.getElementById('cpu_prefix_display');
                        const mainInput = document.getElementById('cpu_specs_main');
                        if (prefixDisplay && mainInput) {
                            if (val.includes('-')) {
                                const parts = val.split('-');
                                prefixDisplay.textContent = parts[0] + '-';
                                mainInput.value = parts[1];
                            } else {
                                prefixDisplay.textContent = '';
                                mainInput.value = val;
                            }
                        }
                    }
                }
            });

            // Visual feedback
            card.style.borderColor = 'var(--accent-color)';
            card.style.background = 'rgba(140, 198, 63, 0.05)';
            setTimeout(() => {
                card.style.borderColor = 'var(--border-color)';
                card.style.background = 'var(--bg-panel)';
            }, 500);
        });
    });

    /* --- CUSTOM NARROWING CPU SEARCH --- */
    const cpuInput = document.getElementById(F.CPU_GEN);
    const specsHidden = document.getElementById(F.CPU_SPECS); // Hidden system field
    const prefixDisplay = document.getElementById('cpu_prefix_display');
    const mainSpecsInput = document.getElementById('cpu_specs_main'); // User visible part
    const coresInput = document.getElementById(F.CPU_CORES);
    const speedInput = document.getElementById(F.CPU_SPEED);
    const cpuWrapper = document.getElementById('cpuSearchWrapper');

    // Helper to sync split UI to hidden field
    const syncCpuSpecs = () => {
        if (!specsHidden || !prefixDisplay || !mainSpecsInput) return;
        const prefix = prefixDisplay.textContent.replace('-', '');
        const val = mainSpecsInput.value.trim();
        specsHidden.value = val ? `${prefix}-${val}` : '';
    };

    if (mainSpecsInput) {
        mainSpecsInput.addEventListener('input', syncCpuSpecs);
    }

    // Structured CPU Catalog for Auto-Fill
    const cpuCatalog = {
        // Intel Core i3
        "i3 · 6th Gen": { gen: "6th Gen", specs: "i3-6", cores: "2 Cores", speed: "2.30GHz" },
        "i3 · 7th Gen": { gen: "7th Gen", specs: "i3-7", cores: "2 Cores", speed: "2.40GHz" },
        "i3 · 8th Gen": { gen: "8th Gen", specs: "i3-8", cores: "2 Cores", speed: "2.10GHz" },
        "i3 · 10th Gen": { gen: "10th Gen", specs: "i3-10", cores: "2 Cores", speed: "2.10GHz" },
        "i3 · 12th Gen": { gen: "12th Gen", specs: "i3-12", cores: "6 Cores", speed: "1.20GHz" },

        // Intel Core i5
        "i5 · 6th Gen": { gen: "6th Gen", specs: "i5-6", cores: "2 Cores", speed: "2.40GHz" },
        "i5 · 7th Gen": { gen: "7th Gen", specs: "i5-7", cores: "2 Cores", speed: "2.50GHz" },
        "i5 · 8th Gen": { gen: "8th Gen", specs: "i5-8", cores: "4 Cores", speed: "1.70GHz" },
        "i5 · 9th Gen": { gen: "9th Gen", specs: "i5-9", cores: "4 Cores", speed: "2.40GHz" },
        "i5 · 10th Gen": { gen: "10th Gen", specs: "i5-10", cores: "4 Cores", speed: "1.60GHz" },
        "i5 · 11th Gen": { gen: "11th Gen", specs: "i5-11", cores: "4 Cores", speed: "2.40GHz" },
        "i5 · 12th Gen": { gen: "12th Gen", specs: "i5-12", cores: "10 Cores", speed: "1.30GHz" },
        "i5 · 13th Gen": { gen: "13th Gen", specs: "i5-13", cores: "10 Cores", speed: "1.30GHz" },

        // Intel Core i7
        "i7 · 6th Gen": { gen: "6th Gen", specs: "i7-6", cores: "2 Cores", speed: "2.60GHz" },
        "i7 · 7th Gen": { gen: "7th Gen", specs: "i7-7", cores: "2 Cores", speed: "2.80GHz" },
        "i7 · 8th Gen": { gen: "8th Gen", specs: "i7-8", cores: "4 Cores", speed: "1.90GHz" },
        "i7 · 9th Gen": { gen: "9th Gen", specs: "i7-9", cores: "6 Cores", speed: "2.60GHz" },
        "i7 · 10th Gen": { gen: "10th Gen", specs: "i7-10", cores: "4 Cores", speed: "1.80GHz" },
        "i7 · 11th Gen": { gen: "11th Gen", specs: "i7-11", cores: "4 Cores", speed: "3.00GHz" },
        "i7-11850H (11th)": { gen: "11th Gen", specs: "i7-11850H", cores: "8 Cores", speed: "2.50GHz" },
        "i7 · 12th Gen": { gen: "12th Gen", specs: "i7-12", cores: "10 Cores", speed: "1.80GHz" },
        "i7 · 13th Gen": { gen: "13th Gen", specs: "i7-13", cores: "10 Cores", speed: "1.80GHz" },

        // Intel Core i9
        "i9 · 12th Gen": { gen: "12th Gen", specs: "i9-12", cores: "14 Cores", speed: "2.50GHz" },
        "i9 · 13th Gen": { gen: "13th Gen", specs: "i9-13", cores: "14 Cores", speed: "2.60GHz" },

        // AMD (Single Option)
        "AMD": { gen: "AMD", specs: "AMD-", cores: "", speed: "" }
    };

    const cpuKeys = Object.keys(cpuCatalog);

    if (cpuInput && cpuWrapper) {
        cpuInput.addEventListener('input', () => {
            const val = cpuInput.value.toLowerCase();
            cpuWrapper.innerHTML = '';

            if (val.length < 1) {
                cpuWrapper.style.display = 'none';
                return;
            }

            const matches = cpuKeys.filter(g => g.toLowerCase().includes(val));

            if (matches.length > 0) {
                cpuWrapper.style.display = 'block';
                matches.forEach(g => {
                    const item = document.createElement('div');
                    item.className = 'search-suggestion-item cpu-opt';
                    item.style.padding = '10px';
                    item.style.cursor = 'pointer';
                    item.style.borderBottom = '1px solid var(--border-color)';
                    item.innerHTML = `<strong>${g}</strong> <span style="font-size:0.7rem; color:var(--text-secondary); float:right;">Auto-Fill Specs</span>`;

                    item.addEventListener('mouseover', () => item.style.background = 'rgba(140, 198, 63, 0.1)');
                    item.addEventListener('mouseout', () => item.style.background = 'transparent');

                    item.addEventListener('click', () => {
                        const data = cpuCatalog[g];

                        // 1. Set the Generation
                        cpuInput.value = data.gen;

                        // 2. Auto-fill technical fields
                        if (prefixDisplay) {
                            const p = data.specs.split('-')[0];
                            prefixDisplay.textContent = p + '-';
                        }
                        if (mainSpecsInput) {
                            mainSpecsInput.value = data.specs.split('-')[1] || '';
                        }

                        syncCpuSpecs(); // Update hidden field

                        if (coresInput) coresInput.value = data.cores;
                        if (speedInput) speedInput.value = data.speed;

                        cpuWrapper.style.display = 'none';

                        // 3. Set focus and select
                        if (mainSpecsInput) {
                            mainSpecsInput.focus();
                            const len = mainSpecsInput.value.length;
                            mainSpecsInput.setSelectionRange(len, len);
                        }
                    });
                    cpuWrapper.appendChild(item);
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
    const successMsg = document.getElementById('successMsg');
    let lastInsertedId = null;

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
                    const name = (formData.get(F.BRAND) || '') + ' ' + (formData.get(F.MODEL) || '');

                    let msg = `Saved <strong>${name}</strong> to ID #${String(lastInsertedId).padStart(5, '0')}.<br>Profile is ready for printing.`;
                    if (result.data.is_duplicate) {
                        msg = `Found existing profile for <strong>${name}</strong> (ID #${String(lastInsertedId).padStart(5, '0')}).`;
                    }

                    successMsg.innerHTML = msg;
                    successOverlay.style.display = 'flex';

                    // Trigger the print configuration modal for the new item
                    if (window.openPrintConfig) window.openPrintConfig(lastInsertedId);

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
    if (successOverlay) {
        // "Print Another" - Now opens the config modal for variety/quantity
        document.getElementById('btnAgain').addEventListener('click', () => {
            if (window.openPrintConfig) window.openPrintConfig(lastInsertedId);
        });

        // "Add New Hardware" - Clears form and hides overlay
        document.getElementById('btnReset').addEventListener('click', () => {
            const pinLoc = document.getElementById('pin_location');
            const locField = document.getElementById(F.LOCATION);
            const savedLoc = locField ? locField.value : '';

            newLabelForm.reset();

            // Restore location if pinned
            if (pinLoc && pinLoc.checked && locField) {
                locField.value = savedLoc;
            }

            // Ensure RAM/Storage are enabled by default after reset (matching HTML defaults)
            if (typeof hasRam !== 'undefined') ramInput.disabled = !hasRam.checked;
            if (typeof hasStorage !== 'undefined') storageInput.disabled = !hasStorage.checked;

            // Reset CPU Prefix Display
            if (prefixDisplay) prefixDisplay.textContent = '';

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

    /* --- PHONE NUMBER FORMATTING (+1 (XXX) XXX-XXXX) --- */
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', (e) => {
            const input = e.target.value;
            // Clean digits only
            let digits = input.replace(/\D/g, '');

            // If the user starts with a '+' that isn't +1, assume international and stop formatting
            if (input.startsWith('+') && !input.startsWith('+1')) {
                // Just keep it as + and digits
                e.target.value = '+' + digits;
                return;
            }

            // Support defaulting to +1 if not provided or starting with '1'
            if (digits.length > 0 && digits[0] !== '1') {
                digits = '1' + digits;
            }

            let formatted = "";
            if (digits.length > 0) {
                // Country Code
                formatted = "+" + digits.substring(0, 1);

                // Area Code
                if (digits.length > 1) {
                    formatted += " (" + digits.substring(1, 4);
                }

                // Prefix
                if (digits.length > 4) {
                    formatted += ") " + digits.substring(4, 7);
                }

                // Line Number
                if (digits.length > 7) {
                    formatted += "-" + digits.substring(7, 11);
                }
            }

            // Only update if the value changed to avoid cursor issues in some browsers
            if (e.target.value !== formatted) {
                e.target.value = formatted;
            }
        });

        // Auto-fill +1 when clicking an empty field to guide the user
        phoneInput.addEventListener('focus', (e) => {
            if (!e.target.value) {
                e.target.value = '+1 ';
            }
        });
    }

});

