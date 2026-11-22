/**
 * NavTree component — vertical sidebar navigation with inline collapsible accordions
 * and automatic Activity Bar (compact icon-only) adaptation when inside a collapsed Split pane.
 */
class UsimNavTreeComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this.expandedGroups = new Set();
    }

    render() {
        const nav = document.createElement('nav');
        nav.className = 'usim-nav-tree';
        nav.setAttribute('aria-label', this.config.name || 'Navigation');

        if (this.config.compact === true) {
            nav.classList.add('is-compact');
        }

        this._renderItems(nav, Array.isArray(this.config.items) ? this.config.items : []);

        this.element = this.applyCommonAttributes(nav);
        return this.element;
    }

    update(changes) {
        this.config = {
            ...this.config,
            ...changes,
        };

        const element = document.querySelector(`[data-component-id="${this.id}"]`);
        if (!element) {
            return;
        }

        element.classList.toggle('is-compact', this.config.compact === true);

        if (
            changes.items !== undefined ||
            changes.active_screen !== undefined ||
            changes.active_route !== undefined ||
            changes.active_group !== undefined ||
            changes.on_group_click !== undefined
        ) {
            this._renderItems(element, Array.isArray(this.config.items) ? this.config.items : []);
        }

        if (changes.visible !== undefined) {
            element.style.display = changes.visible ? '' : 'none';
        }
    }

    _renderItems(container, items) {
        container.innerHTML = '';

        const list = document.createElement('ul');
        list.className = 'usim-nav-tree-list';

        items.forEach((item, index) => {
            list.appendChild(this._renderNode(item, `${index}`, 0));
        });

        container.appendChild(list);
    }

    _renderNode(item, pathKey, depth) {
        if (item.type === 'separator') {
            const sepLi = document.createElement('li');
            sepLi.className = 'usim-nav-tree-separator';
            sepLi.setAttribute('role', 'separator');
            return sepLi;
        }

        const hasSubmenu = Array.isArray(item.submenu) && item.submenu.length > 0;
        const delegateGroupClick = hasSubmenu
            && depth === 0
            && typeof this.config.on_group_click === 'string'
            && this.config.on_group_click.trim() !== '';

        const li = document.createElement('li');
        li.className = 'usim-nav-tree-node';
        li.dataset.depth = String(depth);

        const isActive = this._isItemActive(item);
        const hasActiveChild = hasSubmenu && this._hasActiveDescendant(item.submenu);
        const isGroupActive = hasSubmenu && this._isGroupActive(item, pathKey);

        if (hasSubmenu && !delegateGroupClick && hasActiveChild) {
            this.expandedGroups.add(pathKey);
        }

        const isExpanded = hasSubmenu && !delegateGroupClick && this.expandedGroups.has(pathKey);
        if (isExpanded) {
            li.classList.add('is-expanded');
        }

        const row = document.createElement(item.url && !hasSubmenu ? 'a' : 'button');
        if (row.tagName === 'BUTTON') {
            row.type = 'button';
        } else {
            row.href = item.url;
        }

        row.className = 'usim-nav-tree-item';
        if (depth > 0) {
            row.classList.add('is-nested');
            row.style.setProperty('--nav-depth', String(depth));
        }
        if (isActive) {
            row.classList.add('is-active');
            row.setAttribute('aria-current', 'page');
        }
        if (hasSubmenu) {
            row.classList.add('is-group-trigger');
            row.setAttribute('aria-expanded', (delegateGroupClick ? isGroupActive : isExpanded) ? 'true' : 'false');
            if (isGroupActive) {
                row.classList.add('is-group-active');
            } else if (delegateGroupClick && hasActiveChild) {
                row.classList.add('has-active-child');
            }
        }
        row.title = item.label || '';

        const iconSpan = document.createElement('span');
        iconSpan.className = 'usim-nav-tree-icon';
        if (item.icon) {
            this._renderIconInto(iconSpan, item.icon);
        } else {
            iconSpan.textContent = (item.label || '•').slice(0, 1).toUpperCase();
            iconSpan.classList.add('is-fallback-initial');
        }
        row.appendChild(iconSpan);

        const labelSpan = document.createElement('span');
        labelSpan.className = 'usim-nav-tree-label';
        labelSpan.textContent = item.label || '';
        row.appendChild(labelSpan);

        if (hasSubmenu && !delegateGroupClick) {
            const chevron = document.createElement('span');
            chevron.className = 'usim-nav-tree-chevron';
            chevron.textContent = '▸';
            row.appendChild(chevron);
        }

        if (hasSubmenu && delegateGroupClick) {
            row.addEventListener('click', (e) => {
                e.preventDefault();

                const leftPanelSlot = document.querySelector('[data-layout-slot="left_panel"]');
                const leftPane = leftPanelSlot?.closest('.ui-split-pane');
                const isLeftPanelCollapsed = Boolean(leftPane?.classList.contains('is-collapsed'));

                this.sendEventToBackend('click', this.config.on_group_click, {
                    group_key: pathKey,
                    group_label: item.label || '',
                    group_icon: item.icon || '',
                    left_panel_collapsed: isLeftPanelCollapsed,
                    _caller_service_id: this.config._caller_service_id,
                });
            });

            li.appendChild(row);
        } else if (hasSubmenu) {
            const subList = document.createElement('ul');
            subList.className = 'usim-nav-tree-sublist';
            if (!isExpanded) {
                subList.hidden = true;
            }

            item.submenu.forEach((child, childIndex) => {
                subList.appendChild(this._renderNode(child, `${pathKey}.${childIndex}`, depth + 1));
            });

            row.addEventListener('click', (e) => {
                e.preventDefault();

                // If sidebar is currently in collapsed Activity Bar mode, expand the parent Split first
                const collapsedSplitPane = row.closest('.ui-split-pane.is-collapsed');
                if (collapsedSplitPane) {
                    const splitEl = collapsedSplitPane.closest('.ui-split');
                    const collapseBtn = splitEl?.querySelector(':scope > .ui-split-divider > .ui-split-collapse-button');
                    if (collapseBtn) {
                        collapseBtn.click();
                    }
                    this.expandedGroups.add(pathKey);
                    li.classList.add('is-expanded');
                    row.setAttribute('aria-expanded', 'true');
                    subList.hidden = false;
                    return;
                }

                const willExpand = !this.expandedGroups.has(pathKey);

                if (willExpand && this.config.accordion === true) {
                    this._collapseSiblingGroups(li.parentElement, pathKey);
                }

                if (willExpand) {
                    this.expandedGroups.add(pathKey);
                } else {
                    this.expandedGroups.delete(pathKey);
                }

                li.classList.toggle('is-expanded', willExpand);
                row.setAttribute('aria-expanded', willExpand ? 'true' : 'false');
                subList.hidden = !willExpand;
            });

            li.appendChild(row);
            li.appendChild(subList);
        } else {
            if (item.action) {
                row.addEventListener('click', (e) => {
                    e.preventDefault();
                    this._setOptimisticActive(row, item);

                    const params = {
                        ...(item.params || {}),
                        _caller_service_id: this.config._caller_service_id,
                    };
                    this.sendEventToBackend('click', item.action, params);
                });
            }
            li.appendChild(row);
        }

        return li;
    }

    _collapseSiblingGroups(parentList, exceptKey) {
        if (!parentList) {
            return;
        }
        Array.from(parentList.children).forEach((sibling) => {
            if (!(sibling instanceof HTMLElement)) {
                return;
            }
            sibling.classList.remove('is-expanded');
            const trigger = sibling.querySelector(':scope > .usim-nav-tree-item.is-group-trigger');
            const sub = sibling.querySelector(':scope > .usim-nav-tree-sublist');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
            if (sub) {
                sub.hidden = true;
            }
        });
        // Keep only ancestors or the newly opened key
        for (const key of Array.from(this.expandedGroups)) {
            if (key !== exceptKey && !exceptKey.startsWith(`${key}.`)) {
                this.expandedGroups.delete(key);
            }
        }
    }

    _setOptimisticActive(clickedEl, item) {
        const root = clickedEl.closest('.usim-nav-tree');
        if (!root) {
            return;
        }
        root.querySelectorAll('.usim-nav-tree-item.is-active').forEach((node) => {
            node.classList.remove('is-active');
            node.removeAttribute('aria-current');
        });
        clickedEl.classList.add('is-active');
        clickedEl.setAttribute('aria-current', 'page');

        if (item.params?.screen) {
            this.config.active_screen = item.params.screen;
        }
    }

    _isGroupActive(item, pathKey) {
        const activeGroup = this.config.active_group;
        if (typeof activeGroup !== 'string' || activeGroup.trim() === '') {
            return false;
        }
        return activeGroup === item.label || activeGroup === pathKey;
    }

    _isItemActive(item) {
        if (this.config.active_screen && item.params?.screen) {
            if (item.params.screen === this.config.active_screen) {
                return true;
            }
        }

        const currentPath = this.config.active_route || window.location.pathname;
        if (item.url && currentPath) {
            const normalizedUrl = item.url.replace(/\/+$/, '') || '/';
            const normalizedCurrent = String(currentPath).replace(/\/+$/, '') || '/';
            if (normalizedUrl === normalizedCurrent) {
                return true;
            }
        }

        return false;
    }

    _hasActiveDescendant(items) {
        return items.some((child) => {
            if (this._isItemActive(child)) {
                return true;
            }
            if (Array.isArray(child.submenu) && child.submenu.length > 0) {
                return this._hasActiveDescendant(child.submenu);
            }
            return false;
        });
    }

    _renderIconInto(container, rawIcon) {
        const iconStr = String(rawIcon || '').trim();
        if (!iconStr) {
            return;
        }

        // Direct image/svg path or URL
        if (iconStr.startsWith('/') || iconStr.startsWith('http://') || iconStr.startsWith('https://') || iconStr.startsWith('data:')) {
            const img = document.createElement('img');
            img.src = iconStr;
            img.alt = '';
            img.className = 'usim-nav-tree-icon-img';
            container.appendChild(img);
            return;
        }

        // Check USIM icon registry manifest if available
        const manifest = window.USIM_ICONS;
        if (manifest && typeof manifest === 'object' && manifest[iconStr]) {
            const img = document.createElement('img');
            img.src = manifest[iconStr];
            img.alt = '';
            img.className = 'usim-nav-tree-icon-img';
            container.appendChild(img);
            return;
        }

        container.textContent = iconStr;
    }
}

window.UsimNavTreeComponent = UsimNavTreeComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('navtree');
    window.USIM_COMPONENTS.unregister('nav_tree');
    window.USIM_COMPONENTS.register('navtree', (id, config) => new UsimNavTreeComponent(id, config), {
        source: 'modular',
    });
    window.USIM_COMPONENTS.register('nav_tree', (id, config) => new UsimNavTreeComponent(id, config), {
        source: 'modular',
    });
}
