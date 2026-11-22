/**
 * MenuDropdown component (modular override)
 * Supports standard dropdowns and viewport-portaled floating popovers
 * (right-top, right-bottom, top-left, top-right) so menus inside Split panes never clip.
 */
class UsimMenuDropdownComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this.menuContainer = null;
        this.triggerEl = null;
        this.contentEl = null;
        this._isPortaled = false;
        this._boundOutsideClick = null;
        this._boundKeydown = null;
        this._boundViewportChange = null;
    }

    render() {
        const menuContainer = document.createElement('div');
        menuContainer.className = 'menu-dropdown';
        this.menuContainer = menuContainer;

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'menu-dropdown-trigger';
        this.triggerEl = trigger;

        this._renderTriggerContent(trigger, this.config.trigger || {});

        const content = document.createElement('div');
        content.className = 'menu-dropdown-content';
        content.dataset.ownerDropdownId = String(this.id);
        this.contentEl = content;

        const position = this.config.position || 'bottom-left';
        content.classList.add(`position-${position}`);

        if (this.config.width) {
            content.style.minWidth = this.config.width;
        }

        if (this.config.items && this.config.items.length > 0) {
            this.config.items.forEach(item => {
                content.appendChild(this.renderMenuItem(item));
            });
        }

        if (!this.config.items || this.config.items.length === 0) {
            menuContainer.style.display = 'none';
        }

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isActive = content.classList.contains('show');
            this.closeAllMenus();

            if (!isActive) {
                this._openMenu();
            }
        });

        this._boundOutsideClick = (e) => {
            if (
                !menuContainer.contains(e.target) &&
                !content.contains(e.target) &&
                !e.target.closest('.submenu')
            ) {
                this.closeMenu(content, trigger);
            }
        };

        this._boundKeydown = (e) => {
            if (e.key === 'Escape' && content.classList.contains('show')) {
                this.closeMenu(content, trigger);
                trigger.focus();
            }
        };

        this._boundViewportChange = () => {
            if (content.classList.contains('show') && this._isPortaled) {
                this._positionFloatingContent();
            }
        };

        document.addEventListener('click', this._boundOutsideClick);
        document.addEventListener('keydown', this._boundKeydown);
        window.addEventListener('resize', this._boundViewportChange);

        menuContainer.appendChild(trigger);
        menuContainer.appendChild(content);

        const originalWidth = this.config.width;
        delete this.config.width;

        const result = this.applyCommonAttributes(menuContainer);

        if (originalWidth) {
            this.config.width = originalWidth;
        }

        return result;
    }

    _renderTriggerContent(trigger, triggerConfig) {
        const triggerLabel = triggerConfig.label || '☰ Menu';
        const triggerIcon = triggerConfig.icon;
        const triggerImage = triggerConfig.image;
        const triggerAlt = triggerConfig.alt || 'Menu';
        const triggerStyle = triggerConfig.style || 'default';

        trigger.className = `menu-dropdown-trigger menu-trigger-${triggerStyle}`;
        trigger.title = triggerLabel || triggerAlt;

        let triggerContent = '';
        if (triggerImage) {
            triggerContent += `<img src="${triggerImage}" alt="${triggerAlt}" class="trigger-image">`;
            if (triggerLabel) {
                triggerContent += `<span class="trigger-label">${triggerLabel}</span>`;
            }
        } else {
            if (triggerIcon) {
                triggerContent += `<span class="trigger-icon">${triggerIcon}</span>`;
            }
            triggerContent += `<span class="trigger-label">${triggerLabel}</span>`;
        }

        trigger.innerHTML = triggerContent;
    }

    _shouldUseFloatingPortal() {
        if (this.config.floating === true) {
            return true;
        }
        const position = String(this.config.position || 'bottom-left');
        if (position.startsWith('right-') || position.startsWith('top-')) {
            return true;
        }
        return Boolean(this.menuContainer?.closest('.ui-split-pane'));
    }

    _openMenu() {
        const content = this.contentEl;
        const trigger = this.triggerEl;
        if (!content || !trigger) {
            return;
        }

        if (this._shouldUseFloatingPortal()) {
            this._isPortaled = true;
            content.classList.add('is-floating');
            if (content.parentElement !== document.body) {
                document.body.appendChild(content);
            }
            content.classList.add('show');
            trigger.classList.add('active');
            this._positionFloatingContent();
        } else {
            content.classList.add('show');
            trigger.classList.add('active');
            content.style.animationDuration = '0.2s';
        }

        const firstMenuItem = content.querySelector('.menu-item:not([disabled])');
        if (firstMenuItem) {
            setTimeout(() => firstMenuItem.focus(), 60);
        }
    }

    _positionFloatingContent() {
        const content = this.contentEl;
        const trigger = this.triggerEl;
        if (!content || !trigger) {
            return;
        }

        const triggerRect = trigger.getBoundingClientRect();
        const contentRect = content.getBoundingClientRect();
        const viewportW = window.innerWidth;
        const viewportH = window.innerHeight;
        const gap = 8;
        const position = String(this.config.position || 'bottom-left');

        let top = triggerRect.bottom + gap;
        let left = triggerRect.left;

        if (position === 'right-top') {
            left = triggerRect.right + gap;
            top = triggerRect.bottom - contentRect.height;
        } else if (position === 'right-bottom') {
            left = triggerRect.right + gap;
            top = triggerRect.top;
        } else if (position === 'top-left') {
            left = triggerRect.left;
            top = triggerRect.top - contentRect.height - gap;
        } else if (position === 'top-right') {
            left = triggerRect.right - contentRect.width;
            top = triggerRect.top - contentRect.height - gap;
        } else if (position === 'bottom-right') {
            left = triggerRect.right - contentRect.width;
            top = triggerRect.bottom + gap;
        }

        // Clamp within viewport margins
        const margin = 8;
        left = Math.max(margin, Math.min(left, viewportW - contentRect.width - margin));
        top = Math.max(margin, Math.min(top, viewportH - contentRect.height - margin));

        content.style.position = 'fixed';
        content.style.top = `${Math.round(top)}px`;
        content.style.left = `${Math.round(left)}px`;
        content.style.right = 'auto';
        content.style.bottom = 'auto';
        content.style.zIndex = '10050';
    }

    renderMenuItem(item) {
        if (item.type === 'separator') {
            const separator = document.createElement('div');
            separator.className = 'menu-separator';
            return separator;
        }

        const hasSubmenu = item.submenu && item.submenu.length > 0;
        const menuItem = document.createElement(item.url ? 'a' : 'button');
        if (menuItem.tagName === 'BUTTON') {
            menuItem.type = 'button';
        }
        menuItem.className = 'menu-item';

        if (hasSubmenu) {
            menuItem.classList.add('has-submenu');
        }

        if (item.icon) {
            const icon = document.createElement('span');
            icon.className = 'icon';
            icon.textContent = item.icon;
            menuItem.appendChild(icon);
        }

        const label = document.createElement('span');
        label.textContent = item.label;
        menuItem.appendChild(label);

        if (item.url) {
            menuItem.href = item.url;
        }

        if (item.action) {
            menuItem.addEventListener('click', (e) => {
                e.preventDefault();
                menuItem.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    menuItem.style.transform = '';
                }, 150);

                this.closeAllMenus();

                const params = {
                    ...(item.params || {}),
                    _caller_service_id: this.config._caller_service_id,
                };

                this.sendEventToBackend('click', item.action, params);
            });

            menuItem.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    menuItem.click();
                }
            });
        }

        if (hasSubmenu) {
            const submenu = document.createElement('div');
            submenu.className = 'submenu';
            submenu.style.display = 'none';

            item.submenu.forEach(subitem => {
                submenu.appendChild(this.renderMenuItem(subitem));
            });

            menuItem.appendChild(submenu);

            let hideTimeout = null;

            const showSubmenu = () => {
                if (hideTimeout) {
                    clearTimeout(hideTimeout);
                    hideTimeout = null;
                }
                submenu.style.top = '0';
                submenu.style.bottom = 'auto';
                submenu.style.left = '100%';
                submenu.style.right = 'auto';
                submenu.style.marginLeft = '';
                submenu.style.marginRight = '';
                submenu.style.maxHeight = '';
                submenu.style.overflowY = '';

                submenu.style.setProperty('display', 'block', 'important');
                submenu.style.setProperty('opacity', '1', 'important');
                submenu.style.setProperty('visibility', 'visible', 'important');
                submenu.classList.add('show');

                // Keep submenu strictly inside viewport bounds (never clip above top or below bottom)
                const margin = 8;
                const viewportH = window.innerHeight;
                const viewportW = window.innerWidth;
                const parentRect = menuItem.getBoundingClientRect();
                const maxAllowedHeight = Math.max(120, viewportH - margin * 2);
                let rect = submenu.getBoundingClientRect();

                if (rect.height > maxAllowedHeight) {
                    submenu.style.maxHeight = `${maxAllowedHeight}px`;
                    submenu.style.overflowY = 'auto';
                    rect = submenu.getBoundingClientRect();
                }

                const effectiveHeight = Math.min(rect.height, maxAllowedHeight);
                let desiredTop = parentRect.top;
                if (desiredTop + effectiveHeight > viewportH - margin) {
                    desiredTop = viewportH - margin - effectiveHeight;
                }
                desiredTop = Math.max(margin, desiredTop);

                submenu.style.top = `${Math.round(desiredTop - parentRect.top)}px`;
                submenu.style.bottom = 'auto';

                if (rect.right > viewportW - margin) {
                    submenu.style.left = 'auto';
                    submenu.style.right = '100%';
                    submenu.style.marginLeft = '0';
                    submenu.style.marginRight = '6px';
                }
            };

            const hideSubmenu = () => {
                submenu.style.setProperty('display', 'none', 'important');
                submenu.style.setProperty('opacity', '0', 'important');
                submenu.style.setProperty('visibility', 'hidden', 'important');
                submenu.classList.remove('show');
            };

            if (!item.action) {
                menuItem.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (submenu.classList.contains('show')) {
                        hideSubmenu();
                    } else {
                        showSubmenu();
                    }
                });
            }

            menuItem.addEventListener('mouseenter', () => {
                showSubmenu();
            });

            menuItem.addEventListener('mouseleave', () => {
                hideTimeout = setTimeout(hideSubmenu, 200);
            });

            submenu.addEventListener('mouseenter', () => {
                showSubmenu();
            });

            submenu.addEventListener('mouseleave', () => {
                hideTimeout = setTimeout(hideSubmenu, 200);
            });
        }

        return menuItem;
    }

    update(changes) {
        this.config = {
            ...this.config,
            ...changes,
        };

        const element = document.querySelector(`[data-component-id="${this.id}"]`);
        if (!element) return;

        if (changes.items !== undefined) {
            this.config.items = changes.items;
            const content = this.contentEl || element.querySelector('.menu-dropdown-content');
            if (content) {
                content.innerHTML = '';
                const items = Array.isArray(changes.items) ? changes.items : [];
                items.forEach(item => {
                    content.appendChild(this.renderMenuItem(item));
                });

                if (items.length > 0 && element.style.display === 'none' && changes.visible !== false) {
                    element.style.display = '';
                } else if (items.length === 0 && changes.visible === undefined) {
                    element.style.display = 'none';
                }
            }
        }

        if (changes.trigger !== undefined) {
            this.config.trigger = changes.trigger;
            const trigger = this.triggerEl || element.querySelector('.menu-dropdown-trigger');
            if (trigger) {
                this._renderTriggerContent(trigger, changes.trigger);
            }
        }

        if (changes.visible !== undefined) {
            element.style.display = changes.visible ? '' : 'none';
        }
    }

    closeAllMenus() {
        document.querySelectorAll('.menu-dropdown-content.show').forEach(content => {
            content.classList.remove('show');
            if (content.classList.contains('is-floating')) {
                content.classList.remove('is-floating');
                content.style.position = '';
                content.style.top = '';
                content.style.left = '';
                content.style.right = '';
                content.style.bottom = '';
                content.style.zIndex = '';
                const ownerId = content.dataset.ownerDropdownId;
                const owner = ownerId ? document.querySelector(`[data-component-id="${ownerId}"]`) : null;
                if (owner && content.parentElement === document.body) {
                    owner.appendChild(content);
                }
            }
        });
        document.querySelectorAll('.menu-dropdown-trigger.active').forEach(trigger => {
            trigger.classList.remove('active');
        });
    }

    closeMenu(content, trigger) {
        content.classList.remove('show');
        trigger.classList.remove('active');
        if (this._isPortaled) {
            this._isPortaled = false;
            content.classList.remove('is-floating');
            content.style.position = '';
            content.style.top = '';
            content.style.left = '';
            content.style.right = '';
            content.style.bottom = '';
            content.style.zIndex = '';
            if (this.menuContainer && content.parentElement === document.body) {
                this.menuContainer.appendChild(content);
            }
        }
    }

    destroy() {
        if (this._boundOutsideClick) {
            document.removeEventListener('click', this._boundOutsideClick);
        }
        if (this._boundKeydown) {
            document.removeEventListener('keydown', this._boundKeydown);
        }
        if (this._boundViewportChange) {
            window.removeEventListener('resize', this._boundViewportChange);
        }
        if (this.contentEl && this.contentEl.parentElement === document.body) {
            this.contentEl.remove();
        }
    }
}

window.UsimMenuDropdownComponent = UsimMenuDropdownComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('menudropdown');
    window.USIM_COMPONENTS.register('menudropdown', (id, config) => new UsimMenuDropdownComponent(id, config), {
        source: 'modular',
    });
}
