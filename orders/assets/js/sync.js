/**
 * AppSync — Scalable Livewire-style UI Synchronization Utility
 * Uses EventSource (Server-Sent Events) for instant, timer-free database change detection.
 */
window.AppSync = window.AppSync || {
    registrations: {},
    eventSource: null,

    /**
     * Register a container for event-driven synchronization.
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
    },

    /**
     * Initialize the Server-Sent Events stream to listen for database changes.
     */
    initStream() {
        if (this.eventSource) return;

        this.eventSource = new EventSource('api/sync_stream.php');

        this.eventSource.addEventListener('database-change', (event) => {
            // Instantly sync all registered components when a write is detected
            Object.keys(this.registrations).forEach(elementId => {
                this.sync(elementId);
            });
        });

        this.eventSource.onerror = () => {
            // EventSource handles automatic reconnection, but we log the status
            console.log("[AppSync] SSE Connection lost, attempting reconnection...");
        };
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

        if (Object.keys(this.registrations).length === 0 && this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    },

    /**
     * Perform the AJAX synchronization fetch and smart diff.
     */
    async sync(elementId) {
        const reg = this.registrations[elementId];
        if (!reg) return;

        const container = document.getElementById(elementId);
        if (!container) return;

        // Skip syncing if the user is actively typing inside this specific container
        if (document.activeElement && container.contains(document.activeElement)) {
            const tagName = document.activeElement.tagName;
            if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
                return;
            }
        }

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
     * Diff and patch the DOM container.
     */
    applyDiff(container, newHTML, reg, targetId) {
        let hasChanges = false;

        // If it's the main registered element, use smart row-by-row diffing
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

                const existingRow = oldRowMap.get(rowId);

                if (existingRow) {
                    const isChanged = (existingRow.innerHTML !== newRow.innerHTML) || 
                                      (existingRow.className !== newRow.className);

                    if (isChanged) {
                        existingRow.innerHTML = newRow.innerHTML;
                        Array.from(newRow.attributes).forEach(attr => {
                            existingRow.setAttribute(attr.name, attr.value);
                        });

                        existingRow.classList.remove('row-pulse-highlight');
                        void existingRow.offsetWidth;
                        existingRow.classList.add('row-pulse-highlight');

                        hasChanges = true;
                    }
                    if (container.children[index] !== existingRow) {
                        container.insertBefore(existingRow, container.children[index] || null);
                    }
                } else {
                    newRow.classList.add('row-pulse-highlight');
                    
                    if (index >= container.children.length) {
                        container.appendChild(newRow);
                    } else {
                        container.insertBefore(newRow, container.children[index] || null);
                    }
                    hasChanges = true;
                }
            });

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
