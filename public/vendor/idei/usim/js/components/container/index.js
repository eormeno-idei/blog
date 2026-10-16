/**
 * Container component (modular override)
 */
class UsimContainerComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this.contentElement = null;
        this.tabListElement = null;
        this.tabBodyElement = null;
        this.tabPanels = new Map();
        this.tabButtons = new Map();
    }

    render() {
        const container = document.createElement('div');
        this.element = this.applyCommonAttributes(container);
        this._buildShell();
        this._applyConfig();
        return this.element;
    }

    update(newConfig) {
        this.config = {
            ...this.config,
            ...newConfig,
        };

        if (!this.element) {
            return;
        }

        this.applyCommonAttributes(this.element);
        this._rebuildShellPreservingChildren();
        this._applyConfig();
    }

    getChildMountTarget(childConfig) {
        if (!this._hasTabs()) {
            return this.contentElement || this.element;
        }

        const requestedTab = this._resolveRequestedTab(childConfig?.tab);
        const tabId = requestedTab || this._getActiveTabId();

        return this.tabPanels.get(tabId) || this.tabBodyElement || this.element;
    }

    _buildShell() {
        if (!this.element) {
            return;
        }

        this.element.innerHTML = '';

        if (this._hasTabs()) {
            this.tabListElement = document.createElement('div');
            this.tabListElement.className = 'ui-container-tabs';
            this.tabListElement.setAttribute('role', 'tablist');
            this.element.appendChild(this.tabListElement);

            this.tabBodyElement = document.createElement('div');
            this.tabBodyElement.className = 'ui-container-tab-panels';
            this.element.appendChild(this.tabBodyElement);

            this.contentElement = this.tabBodyElement;
            this._renderTabs();
            return;
        }

        this.contentElement = this.element;
        this.tabListElement = null;
        this.tabBodyElement = null;
        this.tabPanels = new Map();
        this.tabButtons = new Map();
    }

    _rebuildShellPreservingChildren() {
        if (!this.element) {
            return;
        }

        const preservedChildren = Array.from(this.element.querySelectorAll('[data-component-id]'))
            .filter((node) => node !== this.element && node.parentElement?.closest('[data-component-id]') === this.element);

        this._buildShell();

        for (const child of preservedChildren) {
            const childId = child.getAttribute('data-component-id');
            const runtimeRenderer = window.globalRenderer || (typeof globalRenderer !== 'undefined' ? globalRenderer : null);
            const runtimeComponent = childId && runtimeRenderer?.components ? runtimeRenderer.components.get(String(childId)) : null;
            const target = this.getChildMountTarget(runtimeComponent?.config || {});
            target.appendChild(child);
        }
    }

    _applyConfig() {
        if (!this.element) {
            return;
        }

        const layout = this.config.layout || 'vertical';
        const appearance = this._normalizeAppearance(this.config.appearance);

        this.element.classList.remove('vertical', 'horizontal', 'grid', 'flex', 'ui-container-card', 'ui-container-plain', 'ui-container--tabs');
        this.element.classList.add('ui-container', layout, `ui-container-${appearance}`);
        if (this._hasTabs()) {
            this.element.classList.add('ui-container--tabs');
        }

        if (this._hasTabs()) {
            this._renderTabs();
            this._applyTabTheme();
            this._syncActiveTabVisibility();
        }
    }

    _renderTabs() {
        if (!this.tabListElement || !this.tabBodyElement) {
            return;
        }

        const previousPanels = this.tabPanels;
        const nextPanels = new Map();
        const nextButtons = new Map();
        const tabs = Array.isArray(this.config.tabs) ? this.config.tabs : [];

        this.tabListElement.innerHTML = '';
        this.tabBodyElement.innerHTML = '';

        for (const tab of tabs) {
            const tabId = this._tabId(tab);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ui-container-tab';
            button.setAttribute('role', 'tab');
            button.dataset.tabId = tabId;
            button.dataset.disabled = tab.disabled === true ? '1' : '0';
            button.disabled = tab.disabled === true;

            const label = document.createElement('span');
            label.className = 'ui-container-tab-label';
            label.textContent = tab.label || tab.name || tab.id || tabId;
            button.appendChild(label);

            if (tab.closable === true) {
                const closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'ui-container-tab-close';
                closeButton.setAttribute('aria-label', `Close ${label.textContent}`);
                closeButton.textContent = '×';
                closeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    this._closeTab(tab);
                });
                button.appendChild(closeButton);
            }

            button.addEventListener('click', () => {
                if (tab.disabled === true) {
                    return;
                }
                this._activateTab(tabId, true);
            });

            this._applyTabButtonTheme(button, tab, false);
            this.tabListElement.appendChild(button);
            nextButtons.set(tabId, button);

            const panel = previousPanels.get(tabId) || document.createElement('div');
            panel.className = 'ui-container-tab-panel';
            panel.dataset.tabId = tabId;
            panel.setAttribute('role', 'tabpanel');
            this.tabBodyElement.appendChild(panel);
            nextPanels.set(tabId, panel);
        }

        this.tabButtons = nextButtons;
        this.tabPanels = nextPanels;
    }

    _syncActiveTabVisibility() {
        const activeTabId = this._getActiveTabId();

        for (const [tabId, panel] of this.tabPanels.entries()) {
            const isActive = tabId === activeTabId;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        }

        const tabs = Array.isArray(this.config.tabs) ? this.config.tabs : [];
        for (const tab of tabs) {
            const tabId = this._tabId(tab);
            const button = this.tabButtons.get(tabId);
            if (!button) {
                continue;
            }

            const isActive = tabId === activeTabId;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            this._applyTabButtonTheme(button, tab, isActive);
        }
    }

    _applyTabTheme() {
        if (!this.tabListElement) {
            return;
        }

        const colors = this.config.tabs_colors || {};
        const applyVar = (name, value, fallback = '') => {
            if (value !== undefined && value !== null && value !== '') {
                this.element.style.setProperty(name, String(value));
                return;
            }

            if (fallback !== '') {
                this.element.style.setProperty(name, fallback);
                return;
            }

            this.element.style.removeProperty(name);
        };

        applyVar('--usim-tabs-list-bg', colors.list_background_color);
        applyVar('--usim-tabs-border-color', colors.border_color);
        applyVar('--usim-tabs-tab-bg', colors.tab_color);
        applyVar('--usim-tabs-tab-text', colors.tab_text_color);
        applyVar('--usim-tabs-active-bg', colors.active_tab_color);
        applyVar('--usim-tabs-active-text', colors.active_tab_text_color);
        applyVar('--usim-tabs-disabled-bg', colors.disabled_tab_color);
        applyVar('--usim-tabs-disabled-text', colors.disabled_tab_text_color);
        applyVar('--usim-tabs-close-color', colors.close_color);
        applyVar('--usim-tabs-close-hover-color', colors.close_hover_color);
    }

    _applyTabButtonTheme(button, tab, isActive) {
        if (!(button instanceof HTMLElement)) {
            return;
        }

        const tabColor = tab.color || '';
        const textColor = tab.text_color || '';
        const activeColor = tab.active_color || '';
        const activeTextColor = tab.active_text_color || '';
        const disabledColor = tab.disabled_color || '';
        const disabledTextColor = tab.disabled_text_color || '';
        const isDisabled = tab.disabled === true;

        button.style.removeProperty('--usim-tab-item-bg');
        button.style.removeProperty('--usim-tab-item-text');

        if (isDisabled) {
            if (disabledColor) {
                button.style.setProperty('--usim-tab-item-bg', disabledColor);
            }
            if (disabledTextColor) {
                button.style.setProperty('--usim-tab-item-text', disabledTextColor);
            }
            return;
        }

        if (isActive) {
            if (activeColor || tabColor) {
                button.style.setProperty('--usim-tab-item-bg', activeColor || tabColor);
            }
            if (activeTextColor || textColor) {
                button.style.setProperty('--usim-tab-item-text', activeTextColor || textColor);
            }
            return;
        }

        if (tabColor) {
            button.style.setProperty('--usim-tab-item-bg', tabColor);
        }
        if (textColor) {
            button.style.setProperty('--usim-tab-item-text', textColor);
        }
    }

    async _activateTab(tabId, notifyBackend) {
        const resolvedTab = this._resolveRequestedTab(tabId);
        if (!resolvedTab || resolvedTab === this.config.tabs_active) {
            this.config.tabs_active = resolvedTab || this.config.tabs_active;
            this._syncActiveTabVisibility();
            return;
        }

        this.config.tabs_active = resolvedTab;
        this._syncActiveTabVisibility();

        if (!notifyBackend || !this.config.tabs_on_change || !window.USIM_COMPONENT_HELPERS?.sendUiEvent) {
            return;
        }

        const tab = this._findTab(resolvedTab);
        const response = await window.USIM_COMPONENT_HELPERS.sendUiEvent({
            componentId: this.getComponentId(),
            event: 'change',
            action: this.config.tabs_on_change,
            parameters: {
                tab_id: resolvedTab,
            },
        });

        if (response.ok) {
            window.USIM_COMPONENT_HELPERS.applyUiUpdate(response.result);
        }
    }

    async _closeTab(tab) {
        const tabId = this._tabId(tab);
        if (!this.config.tabs_on_close || !window.USIM_COMPONENT_HELPERS?.sendUiEvent) {
            this.config.tabs = (this.config.tabs || []).filter((entry) => this._tabId(entry) !== tabId);
            if (this.config.tabs_active === tabId) {
                this.config.tabs_active = this._firstAvailableTabId();
            }
            this._rebuildShellPreservingChildren();
            this._applyConfig();
            return;
        }

        const response = await window.USIM_COMPONENT_HELPERS.sendUiEvent({
            componentId: this.getComponentId(),
            event: 'action',
            action: this.config.tabs_on_close,
            parameters: {
                tab_id: tabId,
            },
        });

        if (response.ok) {
            window.USIM_COMPONENT_HELPERS.applyUiUpdate(response.result);
        }
    }

    _getActiveTabId() {
        const active = this._resolveRequestedTab(this.config.tabs_active);
        if (active) {
            return active;
        }

        const fallback = this._firstAvailableTabId();
        this.config.tabs_active = fallback;
        return fallback;
    }

    _firstAvailableTabId() {
        const tabs = Array.isArray(this.config.tabs) ? this.config.tabs : [];
        const enabled = tabs.find((tab) => tab.disabled !== true);
        return enabled ? this._tabId(enabled) : (tabs[0] ? this._tabId(tabs[0]) : null);
    }

    _resolveRequestedTab(requestedTab) {
        if (!requestedTab) {
            return null;
        }

        const normalizedRequested = String(requestedTab).trim().toLowerCase();
        const tabs = Array.isArray(this.config.tabs) ? this.config.tabs : [];

        for (const tab of tabs) {
            const tabId = this._tabId(tab);
            const tabName = String(tab.name || '').trim().toLowerCase();
            const tabLabel = String(tab.label || '').trim().toLowerCase();

            if (normalizedRequested === tabId.toLowerCase() || normalizedRequested === tabName || normalizedRequested === tabLabel) {
                return tabId;
            }
        }

        return null;
    }

    _findTab(tabId) {
        const resolvedId = this._resolveRequestedTab(tabId) || tabId;
        return (this.config.tabs || []).find((tab) => this._tabId(tab) === resolvedId) || null;
    }

    _tabId(tab) {
        return String(tab?.id || '').trim();
    }

    _hasTabs() {
        return Array.isArray(this.config.tabs) && this.config.tabs.length > 0;
    }

    _normalizeAppearance(appearance) {
        const normalized = typeof appearance === 'string' ? appearance.trim().toLowerCase() : 'plain';
        return normalized === 'card' ? 'card' : 'plain';
    }
}

window.UsimContainerComponent = UsimContainerComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('container');
    window.USIM_COMPONENTS.register('container', (id, config) => new UsimContainerComponent(id, config), {
        source: 'modular',
    });
}
