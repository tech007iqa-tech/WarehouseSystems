/**
 * Warehouse Modals & UI Interactions Module
 * Handles Working Zone and Shelf renaming dialogs, status creation, sticky scroll sync, and photo hover previews.
 */

function openRenameWorkingZoneModal(wzData) {
    const name = wzData.name;
    const oldInput = document.getElementById('rename-old-zone-name');
    const deleteInput = document.getElementById('delete-working-zone-name');
    const newInput = document.getElementById('rename-new-zone-name');
    const modal = document.getElementById('rename-working-zone-modal');

    if (oldInput) oldInput.value = name;
    if (deleteInput) deleteInput.value = name;
    if (newInput) newInput.value = name;

    if (modal) modal.style.display = 'flex';
    if (newInput) newInput.focus();
}

function closeRenameWorkingZoneModal() {
    const modal = document.getElementById('rename-working-zone-modal');
    if (modal) modal.style.display = 'none';
}

async function submitRenameWorkingZoneAjax(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const oldLoc = formData.get('old_zone_name');
    const newLoc = formData.get('new_zone_name');
    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Updating...';
    }

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok || response.redirected) {
            const url = new URL(window.location.href);
            if (url.searchParams.get('zone') === oldLoc) {
                url.searchParams.set('zone', newLoc);
                window.location.href = url.toString();
            } else {
                window.location.reload();
            }
        } else {
            alert("Failed to update.");
        }
    } catch (err) {
        console.error(err);
        alert("An error occurred.");
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Update Zone';
        }
    }
}

let currentModalLocation = '';
let currentModalStatus = '';
let cachedGlobalStatuses = [];
let cachedCustomStatus = null;

async function openRenameModal(locData) {
    const loc = locData.location_code;
    const status = locData.status || '';
    currentModalLocation = loc;
    currentModalStatus = status;

    const oldLocInput = document.getElementById('rename-old-loc');
    const deleteLocInput = document.getElementById('delete-zone-loc');
    const newLocInput = document.getElementById('rename-new-loc');
    const modal = document.getElementById('rename-modal');
    const locNameSpan = document.getElementById('add-status-loc-name');

    if (oldLocInput) oldLocInput.value = loc;
    if (deleteLocInput) deleteLocInput.value = loc;
    if (newLocInput) newLocInput.value = loc;
    if (locNameSpan) locNameSpan.textContent = loc;

    if (modal) modal.style.display = 'flex';
    if (newLocInput) newLocInput.focus();

    await loadLocationStatusData(loc, status);
}

function closeRenameModal() {
    const modal = document.getElementById('rename-modal');
    const statusBlock = document.getElementById('manage-statuses-block');
    const addBlock = document.getElementById('add-status-block');
    if (modal) modal.style.display = 'none';
    if (statusBlock) statusBlock.style.display = 'none';
    if (addBlock) addBlock.style.display = 'none';
}

async function submitRenameZoneAjax(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const oldLoc = formData.get('old_loc');
    const newLoc = formData.get('new_loc');
    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Updating...';
    }

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok || response.redirected) {
            const url = new URL(window.location.href);
            if (url.searchParams.get('loc') === oldLoc) {
                url.searchParams.set('loc', newLoc);
                window.location.href = url.toString();
            } else {
                window.location.reload();
            }
        } else {
            alert("Failed to update.");
        }
    } catch (err) {
        console.error(err);
        alert("An error occurred.");
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Update Zone';
        }
    }
}

async function loadLocationStatusData(loc, currentStatus) {
    try {
        const url = `api/manage_location_status.php?loc=${encodeURIComponent(loc || '')}`;
        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            cachedGlobalStatuses = data.global_statuses || [];
            cachedCustomStatus = data.custom_status || null;

            syncRenameModalStatusUI(loc, currentStatus);
            renderStatusManagementList(data.statuses || []);
        }
    } catch (err) {
        console.error('Failed to load status data:', err);
    }
}

function syncRenameModalStatusUI(loc, selectedStatus) {
    const select = document.getElementById('rename-status');
    const toggleBtn = document.getElementById('btn-toggle-add-status');
    const formTitle = document.getElementById('custom-status-form-title');
    const formHint = document.getElementById('add-status-scope-hint');
    const nameInput = document.getElementById('new-status-name');
    const colorInput = document.getElementById('new-status-color');
    const saveBtn = document.getElementById('btn-save-custom-status');

    if (!select) return;

    select.innerHTML = '';

    // If this location has a custom status, add it to the select as active custom option
    if (cachedCustomStatus) {
        const customOpt = document.createElement('option');
        customOpt.id = 'opt-current-custom';
        customOpt.value = cachedCustomStatus.name;
        customOpt.textContent = `✨ ${cachedCustomStatus.name} (Custom)`;
        customOpt.setAttribute('data-custom', '1');
        customOpt.setAttribute('data-id', cachedCustomStatus.id);
        customOpt.setAttribute('data-color', cachedCustomStatus.color || '#3b82f6');
        select.appendChild(customOpt);
    }

    // Add Global Statuses optgroup
    if (cachedGlobalStatuses.length > 0) {
        const globalGroup = document.createElement('optgroup');
        globalGroup.label = 'Global Statuses';
        globalGroup.id = 'optgroup-global-statuses';

        cachedGlobalStatuses.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.name;
            opt.textContent = s.name;
            opt.setAttribute('data-id', s.id);
            opt.setAttribute('data-color', s.color || '#64748b');
            opt.setAttribute('data-default', s.is_default ? '1' : '0');
            globalGroup.appendChild(opt);
        });
        select.appendChild(globalGroup);
    }

    // Set select value
    if (selectedStatus) {
        select.value = selectedStatus;
    } else if (cachedCustomStatus) {
        select.value = cachedCustomStatus.name;
    } else if (cachedGlobalStatuses.length > 0) {
        select.value = 'Idle';
    }

    // Update "+ Add New Status Type" vs "✏️ Edit Status Type"
    if (cachedCustomStatus) {
        if (toggleBtn) {
            toggleBtn.textContent = '✏️ Edit Status Type';
            toggleBtn.style.color = '#2563eb';
        }
        if (formTitle) formTitle.textContent = `Edit Custom Status (${loc})`;
        if (formHint) formHint.innerHTML = `Update custom status for this location (<b>${escapeHtml(loc)}</b>).`;
        if (nameInput) nameInput.value = cachedCustomStatus.name;
        if (colorInput) colorInput.value = cachedCustomStatus.color || '#3b82f6';
        if (saveBtn) saveBtn.textContent = 'Update';
    } else {
        if (toggleBtn) {
            toggleBtn.textContent = '+ Add New Status Type';
            toggleBtn.style.color = 'var(--accent-color)';
        }
        if (formTitle) formTitle.textContent = `Create Location Status`;
        if (formHint) formHint.innerHTML = `Status will be created for this location (<b>${escapeHtml(loc)}</b>).`;
        if (nameInput) nameInput.value = '';
        if (colorInput) colorInput.value = '#3b82f6';
        if (saveBtn) saveBtn.textContent = 'Save';
    }
}

function toggleCustomStatusForm(forceState) {
    const addBlock = document.getElementById('add-status-block');
    if (!addBlock) return;

    if (typeof forceState === 'boolean') {
        addBlock.style.display = forceState ? 'block' : 'none';
    } else {
        const isHidden = addBlock.style.display === 'none' || addBlock.style.display === '';
        addBlock.style.display = isHidden ? 'block' : 'none';
    }

    if (addBlock.style.display === 'block') {
        const nameInput = document.getElementById('new-status-name');
        if (nameInput) {
            nameInput.focus();
            if (typeof nameInput.select === 'function') nameInput.select();
        }
    }
}

async function saveCustomLocationStatus() {
    const nameInput = document.getElementById('new-status-name');
    const colorInput = document.getElementById('new-status-color');
    const saveBtn = document.getElementById('btn-save-custom-status');

    if (!nameInput) return;
    const name = nameInput.value.trim();
    const color = colorInput ? colorInput.value : '#3b82f6';

    if (!name) {
        alert('Please enter a status name.');
        return;
    }

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
    }

    try {
        const response = await fetch('api/manage_location_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_custom',
                name: name,
                color: color,
                location_code: currentModalLocation,
                csrf_token: csrfToken
            })
        });

        const result = await response.json();
        if (result.success) {
            if (window.IQA_Notify) {
                window.IQA_Notify.success(result.message || 'Custom status saved ✨');
            }
            cachedGlobalStatuses = result.global_statuses || cachedGlobalStatuses;
            cachedCustomStatus = result.custom_status || { name, color, location_code: currentModalLocation };
            syncRenameModalStatusUI(currentModalLocation, name);
            renderStatusManagementList(result.statuses || []);
            toggleCustomStatusForm(false);
        } else {
            alert(result.error || 'Failed to save status.');
        }
    } catch (err) {
        console.error('Error saving custom status:', err);
        alert('An error occurred while saving the status.');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = cachedCustomStatus ? 'Update' : 'Save';
        }
    }
}

async function toggleManageStatuses() {
    const manageBlock = document.getElementById('manage-statuses-block');
    if (!manageBlock) return;

    const isHidden = manageBlock.style.display === 'none' || manageBlock.style.display === '';
    manageBlock.style.display = isHidden ? 'block' : 'none';

    if (isHidden) {
        await loadLocationStatusData(currentModalLocation, currentModalStatus);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function renderStatusManagementList(statuses) {
    const container = document.getElementById('status-items-container');
    if (!container) return;

    container.innerHTML = '';

    if (statuses.length === 0) {
        container.innerHTML = '<div style="font-size:0.8rem; color:#94a3b8; text-align:center; padding:10px;">No statuses configured.</div>';
        return;
    }

    statuses.forEach(status => {
        const isDefault = parseInt(status.is_default) === 1;
        const isGlobal = status.is_global;
        const row = document.createElement('div');
        row.id = `status-row-${status.id}`;
        row.className = 'status-manage-item';
        row.style.cssText = 'background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; transition: all 0.15s; margin-bottom: 4px;';

        let badgeHtml = '';
        if (isDefault) {
            badgeHtml = '<span style="font-size:0.65rem; background:#f1f5f9; color:#64748b; padding:1px 6px; border-radius:4px; font-weight:700;">Default</span>';
        } else if (isGlobal) {
            badgeHtml = '<span style="font-size:0.65rem; background:#eff6ff; color:#2563eb; padding:1px 6px; border-radius:4px; font-weight:700;">Global</span>';
        } else {
            badgeHtml = `<span style="font-size:0.65rem; background:#fef3c7; color:#b45309; padding:1px 6px; border-radius:4px; font-weight:700;">📍 ${escapeHtml(status.location_code || currentModalLocation)}</span>`;
        }

        row.innerHTML = `
            <div class="status-view-mode" style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:${escapeHtml(status.color || '#64748b')}; box-shadow: 0 0 0 2px rgba(0,0,0,0.05);"></span>
                    <span class="status-name-text" style="font-weight:700; font-size:0.85rem; color:#1e293b;">${escapeHtml(status.name)}</span>
                    ${badgeHtml}
                </div>
                <div style="display:flex; align-items:center; gap:4px;">
                    ${(!isGlobal && !isDefault) ? `
                        <button type="button" onclick="promoteStatusToGlobal(${status.id}, '${escapeJs(status.name)}')" title="Promote to Global Status (available across all shelves)"
                            style="background:#eff6ff; border:1px solid #bfdbfe; color:#2563eb; cursor:pointer; font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:6px; transition:all 0.2s;"
                            onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">🌍 Make Global</button>
                    ` : ''}
                    <button type="button" onclick="startEditStatusRow(${status.id})" title="Edit Status Name/Color"
                        style="background:none; border:none; cursor:pointer; font-size:0.85rem; opacity:0.6; padding:3px 6px; border-radius:4px; transition:opacity 0.2s;"
                        onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">✏️</button>
                    ${isDefault ? `
                        <span title="Default system status cannot be deleted" style="font-size:0.85rem; opacity:0.3; padding:3px 6px; cursor:not-allowed;">🔒</span>
                    ` : `
                        <button type="button" onclick="deleteCustomStatus(${status.id}, '${escapeJs(status.name)}')" title="Delete Custom Status"
                            style="background:none; border:none; cursor:pointer; font-size:0.85rem; opacity:0.6; padding:3px 6px; border-radius:4px; transition:opacity 0.2s;"
                            onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">🗑️</button>
                    `}
                </div>
            </div>
            <div class="status-edit-mode" style="display:none; width:100%; gap:6px; align-items:center;">
                <input type="text" class="edit-status-name" value="${escapeHtml(status.name)}"
                    style="flex:2; height:32px; border-radius:6px; border:1px solid #cbd5e1; padding:0 8px; font-size:0.8rem; font-weight:700;">
                <input type="color" class="edit-status-color" value="${escapeHtml(status.color || '#64748b')}"
                    style="width:32px; height:32px; border:none; padding:0; background:none; cursor:pointer; border-radius:4px;">
                <button type="button" onclick="saveStatusRowEdit(${status.id})"
                    style="background:var(--accent-color); color:white; border:none; border-radius:6px; padding:0 8px; height:32px; font-weight:800; font-size:0.7rem; cursor:pointer;">Save</button>
                <button type="button" onclick="cancelStatusRowEdit(${status.id})"
                    style="background:#e2e8f0; color:#475569; border:none; border-radius:6px; padding:0 6px; height:32px; font-weight:800; font-size:0.7rem; cursor:pointer;">✕</button>
            </div>
        `;

        container.appendChild(row);
    });
}

function startEditStatusRow(id) {
    const row = document.getElementById(`status-row-${id}`);
    if (!row) return;
    row.querySelector('.status-view-mode').style.display = 'none';
    const editMode = row.querySelector('.status-edit-mode');
    editMode.style.display = 'flex';
    const input = editMode.querySelector('.edit-status-name');
    if (input) input.focus();
}

function cancelStatusRowEdit(id) {
    const row = document.getElementById(`status-row-${id}`);
    if (!row) return;
    row.querySelector('.status-view-mode').style.display = 'flex';
    row.querySelector('.status-edit-mode').style.display = 'none';
}

async function saveStatusRowEdit(id) {
    const row = document.getElementById(`status-row-${id}`);
    if (!row) return;

    const nameInput = row.querySelector('.edit-status-name');
    const colorInput = row.querySelector('.edit-status-color');
    const name = nameInput ? nameInput.value.trim() : '';
    const color = colorInput ? colorInput.value : '#64748b';

    if (!name) {
        alert('Status name cannot be empty.');
        return;
    }

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    try {
        const response = await fetch('api/manage_location_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'edit',
                id: id,
                name: name,
                color: color,
                location_code: currentModalLocation,
                csrf_token: csrfToken
            })
        });

        const result = await response.json();
        if (result.success) {
            if (window.IQA_Notify) {
                window.IQA_Notify.success(result.message || 'Status updated ✨');
            }
            cachedGlobalStatuses = result.global_statuses || cachedGlobalStatuses;
            cachedCustomStatus = result.custom_status || cachedCustomStatus;
            syncRenameModalStatusUI(currentModalLocation, select.value);
            renderStatusManagementList(result.statuses || []);
        } else {
            alert(result.error || 'Failed to update status.');
        }
    } catch (err) {
        console.error('Error updating status:', err);
        alert('An error occurred while updating the status.');
    }
}

async function promoteStatusToGlobal(id, name) {
    if (!confirm(`Promote "${name}" to Global Status?\n\nThis will make it available across all warehouse locations and zones.`)) {
        return;
    }

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    try {
        const response = await fetch('api/manage_location_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'promote_global',
                id: id,
                location_code: currentModalLocation,
                csrf_token: csrfToken
            })
        });

        const result = await response.json();
        if (result.success) {
            if (window.IQA_Notify) {
                window.IQA_Notify.success(result.message || 'Status promoted to Global 🌍');
            }
            cachedGlobalStatuses = result.global_statuses || cachedGlobalStatuses;
            cachedCustomStatus = result.custom_status || null;
            syncRenameModalStatusUI(currentModalLocation, name);
            renderStatusManagementList(result.statuses || []);
        } else {
            alert(result.error || 'Failed to promote status.');
        }
    } catch (err) {
        console.error('Error promoting status:', err);
        alert('An error occurred while promoting the status.');
    }
}

async function deleteCustomStatus(id, name) {
    if (!confirm(`Are you sure you want to delete the custom status "${name}"?\n\nAny locations currently assigned to this status will automatically revert to "Idle".`)) {
        return;
    }

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    try {
        const response = await fetch('api/manage_location_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                id: id,
                location_code: currentModalLocation,
                csrf_token: csrfToken
            })
        });

        const result = await response.json();
        if (result.success) {
            if (window.IQA_Notify) {
                window.IQA_Notify.success(result.message || 'Status deleted 🗑️');
            }
            cachedGlobalStatuses = result.global_statuses || cachedGlobalStatuses;
            cachedCustomStatus = result.custom_status || null;
            syncRenameModalStatusUI(currentModalLocation, 'Idle');
            renderStatusManagementList(result.statuses || []);
        } else {
            alert(result.error || 'Failed to delete status.');
        }
    } catch (err) {
        console.error('Error deleting status:', err);
        alert('An error occurred while deleting the status.');
    }
}

function initPhotoHoverPreviews() {
    document.querySelectorAll('.img-preview-container').forEach(container => {
        const preview = container.querySelector('.hover-preview');
        if (!preview) return;
        container.addEventListener('mouseenter', () => {
            preview.style.display = 'block';
        });
        container.addEventListener('mouseleave', () => {
            preview.style.display = 'none';
        });
        container.addEventListener('mousemove', (e) => {
            preview.style.left = (e.clientX + 20) + 'px';
            preview.style.top = (e.clientY - 150) + 'px';
        });
    });

    document.querySelectorAll('.img-preview-container-zone').forEach(container => {
        const preview = container.querySelector('.hover-preview-zone');
        if (!preview) return;
        container.addEventListener('mouseenter', () => {
            preview.style.display = 'block';
        });
        container.addEventListener('mouseleave', () => {
            preview.style.display = 'none';
        });
        container.addEventListener('mousemove', (e) => {
            preview.style.left = (e.clientX + 20) + 'px';
            preview.style.top = (e.clientY - 150) + 'px';
        });
    });
}

function initStickyTableHeaders() {
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                document.querySelectorAll('.spreadsheet-table-wrapper, .inventory-table-container').forEach(wrapper => {
                    const table = wrapper.querySelector('table');
                    if (!table) return;
                    const thead = table.querySelector('thead');
                    if (!thead) return;
                    const ths = thead.querySelectorAll('th');
                    const rect = wrapper.getBoundingClientRect();

                    if (rect.top < 0) {
                        const headerHeight = thead.offsetHeight;
                        const maxTranslate = rect.height - headerHeight - 60;
                        const translateVal = Math.min(-rect.top, maxTranslate);

                        if (translateVal > 0) {
                            ths.forEach(th => {
                                th.style.transform = `translateY(${translateVal - 1}px)`;
                                th.style.zIndex = '10';
                            });
                            ticking = false;
                            return;
                        }
                    }

                    ths.forEach(th => {
                        th.style.transform = '';
                    });
                });
                ticking = false;
            });
            ticking = true;
        }
    });
}
