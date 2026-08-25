/**
 * AppSync — Scalable Livewire-style UI Synchronization Utility
 * Uses EventSource (Server-Sent Events) with automatic polling heartbeat fallback
 * and BroadcastChannel for instantaneous multi-user & multi-tab synchronization.
 */
window.AppSync = window.AppSync || {
    registrations: {},
    eventSource: null,
    pollTimer: null,
    broadcastChannel: null,
    isSyncing: false,

    /**
     * Register a container for real-time synchronization.
     */
    register({ elementId, url, rowSelector = 'tr', rowIdAttribute = 'data-id', onUpdate = null }) {
        this.registrations[elementId] = {
            elementId,
            url,
            rowSelector,
            rowIdAttribute,
            onUpdate,
            controller: null
        };

        this.initStream();
        this.initBroadcast();
        this.initHeartbeat();
    },

    /**
     * Broadcast changes to all open tabs immediately.
     */
    initBroadcast() {
        if (this.broadcastChannel || typeof BroadcastChannel === 'undefined') return;
        try {
            this.broadcastChannel = new BroadcastChannel('iqa_sync_channel');
            this.broadcastChannel.onmessage = (event) => {
                if (event.data && event.data.type === 'database-change') {
                    this.syncAll();
                }
            };
        } catch (e) {
            console.warn('[AppSync] BroadcastChannel not supported:', e);
        }
    },

    /**
     * Notify all components and tabs of a database change.
     */
    triggerChange() {
        if (this.broadcastChannel) {
            try {
                this.broadcastChannel.postMessage({ type: 'database-change', timestamp: Date.now() });
            } catch (e) {}
        }
        this.syncAll();
    },

    /**
     * Initialize the Server-Sent Events stream.
     */
    initStream() {
        if (this.eventSource) return;

        try {
            this.eventSource = new EventSource('api/sync_stream.php');

            this.eventSource.addEventListener('database-change', () => {
                this.syncAll();
            });

            this.eventSource.onerror = () => {
                // EventSource handles automatic reconnect; heartbeat polling ensures zero downtime
            };
        } catch (e) {
            console.warn('[AppSync] SSE initialization failed:', e);
        }
    },

    /**
     * Heartbeat polling fallback (checks every 3 seconds if SSE is blocked or idle).
     */
    initHeartbeat() {
        if (this.pollTimer) return;
        this.pollTimer = setInterval(() => {
            // Only sync if document is visible
            if (!document.hidden) {
                this.syncAll();
            }
        }, 3000);
    },

    /**
     * Sync all currently registered elements.
     */
    syncAll() {
        Object.keys(this.registrations).forEach(elementId => {
            this.sync(elementId);
        });
    },

    /**
     * Stop synchronization for an element.
     */
    stop(elementId) {
        const reg = this.registrations[elementId];
        if (reg) {
            if (reg.controller) reg.controller.abort();
            delete this.registrations[elementId];
        }

        if (Object.keys(this.registrations).length === 0) {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        }
    },

    /**
     * Perform the AJAX synchronization fetch and non-destructive smart diff.
     */
    async sync(elementId) {
        const reg = this.registrations[elementId];
        if (!reg) return;

        const container = document.getElementById(elementId);
        if (!container) return;

        if (reg.controller) {
            reg.controller.abort();
        }

        reg.controller = new AbortController();
        const signal = reg.controller.signal;

        try {
            const response = await fetch(reg.url, { signal });
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);

            const responseText = await response.text();
            let data = {};
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                data = { [elementId]: responseText };
            }

            for (const [targetId, newHTML] of Object.entries(data)) {
                const targetContainer = document.getElementById(targetId);
                if (targetContainer) {
                    this.applyDiff(targetContainer, newHTML, reg, targetId);
                }
            }

        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn(`[AppSync] Sync failed for #${elementId}:`, err);
            }
        } finally {
            reg.controller = null;
        }
    },

    /**
     * Smart diff and patch DOM without interrupting user typing or losing focus.
     */
    applyDiff(container, newHTML, reg, targetId) {
        let hasChanges = false;
        const activeEl = document.activeElement;

        // Smart row-by-row diffing
        if (targetId === reg.elementId) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(`<table><tbody id="temp-root">${newHTML}</tbody></table>`, 'text/html');
            const tempRoot = doc.getElementById('temp-root');
            if (!tempRoot) return;

            const newRows = Array.from(tempRoot.querySelectorAll(reg.rowSelector));
            const oldRows = Array.from(container.querySelectorAll(reg.rowSelector));

            const oldRowMap = new Map();
            oldRows.forEach(row => {
                const id = row.getAttribute(reg.rowIdAttribute);
                if (id) oldRowMap.set(id, row);
            });

            newRows.forEach((newRow, index) => {
                const rowId = newRow.getAttribute(reg.rowIdAttribute);
                if (!rowId) return;

                // For the permanent blank row at the bottom ('new'), do not overwrite if user is typing in it
                if (rowId === 'new') {
                    const existingBlank = oldRowMap.get('new');
                    if (existingBlank) {
                        if (!existingBlank.contains(activeEl)) {
                            if (existingBlank.innerHTML !== newRow.innerHTML) {
                                existingBlank.innerHTML = newRow.innerHTML;
                                hasChanges = true;
                            }
                        }
                        if (container.children[index] !== existingBlank) {
                            container.insertBefore(existingBlank, container.children[index] || null);
                        }
                    } else {
                        container.appendChild(newRow);
                        hasChanges = true;
                    }
                    return;
                }

                const existingRow = oldRowMap.get(rowId);

                if (existingRow) {
                    const isRowFocused = existingRow.contains(activeEl);

                    if (isRowFocused) {
                        // Fine-grained cell-by-cell update to never disturb the actively focused input
                        const oldCells = existingRow.querySelectorAll('.editable-cell');
                        const newCells = newRow.querySelectorAll('.editable-cell');

                        newCells.forEach(newCell => {
                            const field = newCell.getAttribute('data-field');
                            if (!field) return;
                            const oldCell = existingRow.querySelector(`.editable-cell[data-field="${field}"]`);
                            if (!oldCell) return;

                            const oldInput = oldCell.querySelector('.cell-input');
                            const newInput = newCell.querySelector('.cell-input');

                            if (oldInput && newInput) {
                                if (oldInput !== activeEl) {
                                    if (oldInput.value !== newInput.value) {
                                        oldInput.value = newInput.value;
                                        oldCell.style.backgroundColor = 'rgba(59, 130, 246, 0.15)';
                                        setTimeout(() => { oldCell.style.backgroundColor = ''; }, 600);
                                        hasChanges = true;
                                    }
                                }
                            } else if (oldCell.innerHTML !== newCell.innerHTML) {
                                if (!oldCell.contains(activeEl)) {
                                    oldCell.innerHTML = newCell.innerHTML;
                                    hasChanges = true;
                                }
                            }
                        });

                        // Sync non-cell attributes (like data-search, data-price)
                        Array.from(newRow.attributes).forEach(attr => {
                            if (existingRow.getAttribute(attr.name) !== attr.value) {
                                existingRow.setAttribute(attr.name, attr.value);
                            }
                        });

                    } else {
                        // Row is not currently focused: safe to update
                        const isChanged = (existingRow.innerHTML !== newRow.innerHTML) ||
                                          (existingRow.className !== newRow.className);

                        if (isChanged) {
                            // Preserve states of all checkboxes by index
                            const oldCheckboxes = existingRow.querySelectorAll('input[type="checkbox"]');
                            const checkboxCheckedStates = Array.from(oldCheckboxes).map(cb => cb.checked);
                            const hadSelectedClass = existingRow.classList.contains('selected-row');

                            existingRow.innerHTML = newRow.innerHTML;
                            Array.from(newRow.attributes).forEach(attr => {
                                existingRow.setAttribute(attr.name, attr.value);
                            });

                            // Restore checkbox states by index
                            const newCheckboxes = existingRow.querySelectorAll('input[type="checkbox"]');
                            newCheckboxes.forEach((cb, idx) => {
                                if (idx < checkboxCheckedStates.length) {
                                    cb.checked = checkboxCheckedStates[idx];
                                }
                            });
                            if (hadSelectedClass) {
                                existingRow.classList.add('selected-row');
                            }

                            existingRow.classList.remove('row-pulse-highlight');
                            void existingRow.offsetWidth;
                            existingRow.classList.add('row-pulse-highlight');

                            hasChanges = true;
                        }
                    }

                    if (container.children[index] !== existingRow) {
                        container.insertBefore(existingRow, container.children[index] || null);
                    }
                } else {
                    // Brand new row added by another user
                    newRow.classList.add('row-pulse-highlight');

                    if (index >= container.children.length) {
                        container.appendChild(newRow);
                    } else {
                        container.insertBefore(newRow, container.children[index] || null);
                    }
                    hasChanges = true;
                }
            });

            // Remove rows that were deleted by other users
            const newRowIds = new Set(newRows.map(r => r.getAttribute(reg.rowIdAttribute)).filter(Boolean));
            oldRows.forEach(oldRow => {
                const rowId = oldRow.getAttribute(reg.rowIdAttribute);
                if (rowId && !newRowIds.has(rowId)) {
                    oldRow.remove();
                    hasChanges = true;
                }
            });

        } else {
            // Simple DOM swap for other sections
            if (container.innerHTML !== newHTML) {
                container.innerHTML = newHTML;
                hasChanges = true;
            }
        }

        if (hasChanges && typeof reg.onUpdate === 'function') {
            reg.onUpdate();
        }
    }
};
