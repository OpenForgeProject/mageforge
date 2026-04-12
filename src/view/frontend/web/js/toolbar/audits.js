/**
 * MageForge Toolbar - Audit dispatcher
 */

import { audits, auditGroups } from './audits/index.js';

export const auditMethods = {
    /**
     * Toggles an audit on/off and updates the menu item state.
     *
     * @param {string} auditKey
     */
    runAudit(auditKey) {
        const audit = audits.find(a => a.key === auditKey);
        if (!audit) return;

        const isActive = this.activeAudits.has(auditKey);
        if (isActive) {
            this.activeAudits.delete(auditKey);
        } else {
            this.activeAudits.add(auditKey);
        }
        audit.run(this, !isActive);
        this.setAuditActive(auditKey, !isActive);
    },

    /**
     * Activates all inactive audits or deactivates all if all are already active.
     */
    toggleAllAudits() {
        const allActive = this.activeAudits.size === audits.length;
        if (allActive) {
            this.deactivateAllAudits();
        } else {
            audits.forEach(audit => {
                if (!this.activeAudits.has(audit.key)) {
                    this.runAudit(audit.key);
                }
            });
        }
    },

    /**
     * Deactivates all currently active audits (called when closing the toolbar).
     */
    deactivateAllAudits() {
        const keys = [...this.activeAudits];
        keys.forEach(key => {
            this.activeAudits.delete(key);
            const audit = audits.find(a => a.key === key);
            if (audit) audit.run(this, false);
            this.setAuditActive(key, false);
        });
        this.activeAudits.clear();
        this.updateToggleAllButton();
    },

    /**
     * Returns all registered audits (used by UI to build menu items)
     *
     * @returns {import('./audits/index.js').AuditDefinition[]}
     */
    getAudits() {
        return audits;
    },

    /**
     * Returns all registered audit groups.
     *
     * @returns {import('./audits/index.js').AuditGroup[]}
     */
    getAuditGroups() {
        return auditGroups;
    },

    /**
     * Toggle collapsed state of a menu group.
     *
     * @param {string} key
     */
    toggleGroup(key) {
        if (this.collapsedGroups.has(key)) {
            this.collapsedGroups.delete(key);
        } else {
            this.collapsedGroups.add(key);
        }
        if (!this.menu) return;
        const group = this.menu.querySelector(`[data-group-key="${key}"]`);
        if (group) {
            group.classList.toggle('mageforge-toolbar-menu-group--collapsed', this.collapsedGroups.has(key));
        }
    },

    /**
     * Set the inline counter badge of an audit menu item.
     *
     * @param {string} key
     * @param {string} message
     * @param {'success'|'error'} type
     */
    setAuditCounterBadge(key, message, type = 'success') {
        if (!this.menu) return;
        const item = this.menu.querySelector(`[data-audit-key="${key}"]`);
        if (!item) return;
        const status = item.querySelector('.mageforge-toolbar-menu-status');
        if (!status) return;
        status.textContent = message;
        status.className = `mageforge-toolbar-menu-status mageforge-toolbar-menu-status--${type}`;
        // Reflect error/success on the active item background
        item.classList.toggle('mageforge-active--error', type === 'error');
    },
};
