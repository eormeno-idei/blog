/**
 * MenuDropdown component (modular override)
 */
class UsimMenuDropdownComponent extends UIComponent {
    render() {
        const menuContainer = document.createElement('div');
        menuContainer.className = 'menu-dropdown';

        const trigger = document.createElement('button');
        trigger.className = 'menu-dropdown-trigger';

        const triggerConfig = this.config.trigger || {};
        const triggerLabel = triggerConfig.label || '☰ Menu';
        const triggerIcon = triggerConfig.icon;
        const triggerImage = triggerConfig.image;
        const triggerAlt = triggerConfig.alt || 'Menu';
        const triggerStyle = triggerConfig.style || 'default';

        trigger.className += ` menu-trigger-${triggerStyle}`;

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

        const content = document.createElement('div');
        content.className = 'menu-dropdown-content';

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
                content.classList.add('show');
                trigger.classList.add('active');
                content.style.animationDuration = '0.3s';

                const firstMenuItem = content.querySelector('.menu-item:not([disabled])');
                if (firstMenuItem) {
                    setTimeout(() => firstMenuItem.focus(), 100);
                }
            }
        });

        document.addEventListener('click', (e) => {
            if (!menuContainer.contains(e.target) && !e.target.closest('.submenu')) {
                this.closeMenu(content, trigger);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && content.classList.contains('show')) {
                this.closeMenu(content, trigger);
                trigger.focus();
            }
        });

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

    renderMenuItem(item) {
        if (item.type === 'separator') {
            const separator = document.createElement('div');
            separator.className = 'menu-separator';
            return separator;
        }

        const hasSubmenu = item.submenu && item.submenu.length > 0;
        const menuItem = document.createElement(item.url ? 'a' : 'button');
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
                submenu.style.setProperty('display', 'block', 'important');
                submenu.style.setProperty('opacity', '1', 'important');
                submenu.style.setProperty('visibility', 'visible', 'important');
                submenu.classList.add('show');
            };

            const hideSubmenu = () => {
                submenu.style.setProperty('display', 'none', 'important');
                submenu.style.setProperty('opacity', '0', 'important');
                submenu.style.setProperty('visibility', 'hidden', 'important');
                submenu.classList.remove('show');
            };

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
            const content = element.querySelector('.menu-dropdown-content');
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
            const trigger = element.querySelector('.menu-dropdown-trigger');
            if (trigger) {
                const triggerConfig = changes.trigger;
                const triggerLabel = triggerConfig.label || '☰ Menu';
                const triggerIcon = triggerConfig.icon;
                const triggerImage = triggerConfig.image;
                const triggerAlt = triggerConfig.alt || 'Menu';
                const triggerStyle = triggerConfig.style || 'default';

                trigger.className = `menu-dropdown-trigger menu-trigger-${triggerStyle}`;

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
        }
    }

    closeAllMenus() {
        document.querySelectorAll('.menu-dropdown-content.show').forEach(content => {
            content.classList.remove('show');
        });
        document.querySelectorAll('.menu-dropdown-trigger.active').forEach(trigger => {
            trigger.classList.remove('active');
        });
    }

    closeMenu(content, trigger) {
        content.classList.remove('show');
        trigger.classList.remove('active');
    }
}

window.UsimMenuDropdownComponent = UsimMenuDropdownComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('menudropdown');
    window.USIM_COMPONENTS.register('menudropdown', (id, config) => new UsimMenuDropdownComponent(id, config), {
        source: 'modular',
    });
}
