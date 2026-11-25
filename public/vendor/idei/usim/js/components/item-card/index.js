/**
 * ItemCard component (modular implementation, `type: 'item_card'`)
 *
 * First-class standardized card component usable standalone or as a managed
 * child of CardGrid with native USIM ComponentDiffer incremental updates.
 * Reuses existing <img> DOM nodes on update() when the image URL is unchanged.
 */
class UsimItemCardComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this._overflowOpen = false;
        this._boundDocClick = this._handleDocClick.bind(this);
        this._boundDocKeydown = this._handleDocKeydown.bind(this);
    }

    _normalizeStyle(value) {
        const normalized = String(value || 'default').toLowerCase().trim();
        const allowed = ['default', 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'muted'];
        return allowed.includes(normalized) ? normalized : 'default';
    }

    render() {
        const article = this._buildCardDom();
        document.addEventListener('click', this._boundDocClick);
        document.addEventListener('keydown', this._boundDocKeydown);
        this.element = this.applyCommonAttributes(article);
        return this.element;
    }

    update(newConfig) {
        const prevImage = this.config?.image ? String(this.config.image) : null;
        const prevOrder = this.config?.card_order ?? null;

        this.config = { ...this.config, ...newConfig };
        this._overflowOpen = false;

        if (!this.element || !this.element.isConnected) {
            this.element = document.querySelector(`[data-component-id="${this.getComponentId()}"]`);
        }

        if (!this.element) {
            return;
        }

        const oldNode = this.element;
        const newNode = this._buildCardDom();
        this.applyCommonAttributes(newNode);

        // Preserve existing <img> node when image URL is unchanged (zero flicker / zero re-decode)
        const nextImage = this.config?.image ? String(this.config.image) : null;
        if (prevImage && nextImage && prevImage === nextImage) {
            const oldImg = oldNode.querySelector('.usim-ic-media img');
            const newImg = newNode.querySelector('.usim-ic-media img');
            if (oldImg && newImg) {
                newImg.replaceWith(oldImg);
            }
        }

        oldNode.replaceWith(newNode);
        this.element = newNode;

        // Notify parent CardGrid if card_order changed so DOM order stays synchronized
        if (newConfig.card_order !== undefined && newConfig.card_order !== prevOrder) {
            const parentComp = window.globalRenderer?.components?.get?.(String(this.config.parent ?? ''));
            if (parentComp && typeof parentComp.scheduleOrderSync === 'function') {
                parentComp.scheduleOrderSync();
            }
        }
    }

    destroy() {
        super.destroy();
        document.removeEventListener('click', this._boundDocClick);
        document.removeEventListener('keydown', this._boundDocKeydown);
    }

    _handleDocClick(event) {
        if (!this._overflowOpen || !this.element) return;
        const inside = event.target?.closest?.('.usim-ic-overflow-wrap');
        if (!inside || !this.element.contains(inside)) {
            this._closeOverflowMenu();
        }
    }

    _handleDocKeydown(event) {
        if (event.key === 'Escape' && this._overflowOpen) {
            this._closeOverflowMenu();
            event.stopPropagation();
        }
    }

    _closeOverflowMenu() {
        this._overflowOpen = false;
        if (!this.element) return;
        this.element.querySelectorAll('.usim-ic-overflow-menu').forEach((m) => m.remove());
    }

    _buildCardDom() {
        const cfg = this.config || {};
        const card = document.createElement('article');

        const styleToken = this._normalizeStyle(cfg.style);
        const variant = ['elevated', 'outlined', 'flat'].includes(cfg.variant) ? cfg.variant : 'elevated';
        const density = ['compact', 'comfortable', 'spacious'].includes(cfg.density) ? cfg.density : 'comfortable';
        const layout = cfg.layout === 'horizontal' ? 'horizontal' : 'vertical';
        const disabled = Boolean(cfg.disabled);
        const isClickable = !disabled && Boolean(cfg.url || cfg.click_action);

        const classes = [
            'usim-ic-card',
            `style-${styleToken}`,
            `variant-${variant}`,
            `density-${density}`,
            `layout-${layout}`,
        ];
        if (cfg.featured) classes.push('is-featured');
        if (disabled) classes.push('is-disabled');
        if (isClickable) classes.push('is-clickable');
        if (cfg.accent_color) {
            classes.push('has-accent');
            card.style.setProperty('--usim-ic-accent', String(cfg.accent_color));
        }

        card.className = classes.join(' ');
        card.setAttribute('role', 'listitem');

        if (cfg.item_id !== undefined && cfg.item_id !== null) {
            card.dataset.itemId = String(cfg.item_id);
        }
        if (cfg.card_order !== undefined && cfg.card_order !== null) {
            card.dataset.cardOrder = String(cfg.card_order);
        }

        if (isClickable) {
            card.tabIndex = 0;
            card.addEventListener('click', () => this._handleCardClick());
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this._handleCardClick();
                }
            });
        }

        const hasMedia = Boolean(cfg.image);
        const titleText = String(cfg.title ?? '');

        // 1. Media Zone
        if (hasMedia) {
            const media = document.createElement('div');
            media.className = 'usim-ic-media';
            media.style.aspectRatio = String(cfg.aspect_ratio || '16/9');

            const img = document.createElement('img');
            img.src = String(cfg.image);
            img.alt = String(cfg.image_alt || titleText);
            img.loading = 'lazy';
            img.style.objectFit = cfg.image_fit === 'contain' ? 'contain' : 'cover';
            img.addEventListener('error', () => {
                img.remove();
                const fallback = document.createElement('div');
                fallback.className = 'usim-ic-media-fallback';
                if (cfg.icon) {
                    fallback.appendChild(this._createIconNode(cfg.icon, 34));
                } else {
                    fallback.textContent = titleText.slice(0, 2).toUpperCase();
                }
                media.appendChild(fallback);
            });
            media.appendChild(img);

            if (cfg.badge) {
                const badge = this._buildBadgeElement(String(cfg.badge), cfg.badge_style);
                badge.classList.add('usim-ic-media-badge');
                media.appendChild(badge);
            }

            card.appendChild(media);
        }

        // 2. Content Container
        const content = document.createElement('div');
        content.className = 'usim-ic-content';

        const header = document.createElement('div');
        header.className = 'usim-ic-header';

        if (!hasMedia && (cfg.avatar || cfg.icon)) {
            const visual = document.createElement('div');
            visual.className = `usim-ic-visual-badge${cfg.avatar ? ' is-avatar' : ''}`;
            if (cfg.avatar) {
                const avatarVal = String(cfg.avatar);
                if (/^(https?:\/\/|\/|data:image\/)/i.test(avatarVal)) {
                    const avImg = document.createElement('img');
                    avImg.src = avatarVal;
                    avImg.alt = titleText;
                    avImg.loading = 'lazy';
                    visual.appendChild(avImg);
                } else {
                    visual.textContent = avatarVal;
                }
            } else if (cfg.icon) {
                visual.appendChild(this._createIconNode(cfg.icon, 20));
            }
            header.appendChild(visual);
        }

        const headerMeta = document.createElement('div');
        headerMeta.className = 'usim-ic-header-meta';

        if (cfg.eyebrow || (!hasMedia && cfg.badge)) {
            const eyebrowRow = document.createElement('div');
            eyebrowRow.className = 'usim-ic-eyebrow-row';

            const eyebrow = document.createElement('span');
            eyebrow.className = 'usim-ic-eyebrow';
            eyebrow.textContent = cfg.eyebrow ? String(cfg.eyebrow) : '';
            eyebrowRow.appendChild(eyebrow);

            if (!hasMedia && cfg.badge) {
                eyebrowRow.appendChild(this._buildBadgeElement(String(cfg.badge), cfg.badge_style));
            }

            headerMeta.appendChild(eyebrowRow);
        }

        if (titleText !== '') {
            const titleEl = document.createElement('h4');
            titleEl.className = 'usim-ic-title';
            titleEl.textContent = titleText;
            titleEl.title = titleText;
            headerMeta.appendChild(titleEl);
        }

        if (cfg.subtitle) {
            const subEl = document.createElement('p');
            subEl.className = 'usim-ic-subtitle';
            subEl.textContent = String(cfg.subtitle);
            subEl.title = String(cfg.subtitle);
            headerMeta.appendChild(subEl);
        }

        header.appendChild(headerMeta);
        content.appendChild(header);

        // 3. Highlight Metric
        if (cfg.metric !== null && cfg.metric !== undefined && cfg.metric !== '') {
            const metricRow = document.createElement('div');
            metricRow.className = 'usim-ic-metric-row';

            const val = document.createElement('span');
            val.className = `usim-ic-metric-value style-${this._normalizeStyle(cfg.metric_style)}`;
            val.textContent = String(cfg.metric);
            metricRow.appendChild(val);

            if (cfg.metric_label) {
                const lbl = document.createElement('span');
                lbl.className = 'usim-ic-metric-label';
                lbl.textContent = String(cfg.metric_label);
                metricRow.appendChild(lbl);
            }

            content.appendChild(metricRow);
        }

        // 4. Description
        if (cfg.description) {
            const desc = document.createElement('p');
            desc.className = 'usim-ic-description';
            desc.textContent = String(cfg.description);
            content.appendChild(desc);
        }

        // 5. Attributes
        const attributes = Array.isArray(cfg.attributes) ? cfg.attributes : [];
        if (attributes.length > 0) {
            const list = document.createElement('ul');
            list.className = 'usim-ic-attributes';

            attributes.forEach((attr) => {
                if (!attr || typeof attr !== 'object') return;
                const li = document.createElement('li');
                li.className = 'usim-ic-attribute';
                if (attr.icon) {
                    li.appendChild(this._createIconNode(attr.icon, 13));
                }
                if (attr.label) {
                    const l = document.createElement('span');
                    l.className = 'usim-ic-attribute-label';
                    l.textContent = `${attr.label}:`;
                    li.appendChild(l);
                }
                const v = document.createElement('span');
                v.className = 'usim-ic-attribute-value';
                v.textContent = String(attr.value ?? '');
                li.appendChild(v);
                list.appendChild(li);
            });

            content.appendChild(list);
        }

        // 6. Tags
        const tags = Array.isArray(cfg.tags) ? cfg.tags.filter(Boolean) : [];
        if (tags.length > 0) {
            const maxTags = Math.max(0, Number(cfg.max_visible_tags ?? 3));
            const tagsWrap = document.createElement('div');
            tagsWrap.className = 'usim-ic-tags';

            const visibleTags = maxTags > 0 ? tags.slice(0, maxTags) : [];
            const hiddenTags = maxTags > 0 ? tags.slice(maxTags) : tags;

            visibleTags.forEach((tagText) => {
                const tag = document.createElement('span');
                tag.className = 'usim-ic-tag';
                tag.textContent = String(tagText);
                tagsWrap.appendChild(tag);
            });

            if (hiddenTags.length > 0) {
                const more = document.createElement('span');
                more.className = 'usim-ic-tag is-more';
                more.textContent = `+${hiddenTags.length}`;
                more.title = hiddenTags.join(', ');
                tagsWrap.appendChild(more);
            }

            content.appendChild(tagsWrap);
        }

        // 7. Footer & Actions
        const actions = Array.isArray(cfg.actions) ? cfg.actions : [];
        if (cfg.footer_text || actions.length > 0) {
            const footer = document.createElement('div');
            footer.className = 'usim-ic-footer';

            if (cfg.footer_text) {
                const ft = document.createElement('span');
                ft.className = 'usim-ic-footer-text';
                ft.textContent = String(cfg.footer_text);
                ft.title = String(cfg.footer_text);
                footer.appendChild(ft);
            }

            if (actions.length > 0) {
                footer.appendChild(this._buildActions(actions));
            }

            content.appendChild(footer);
        }

        card.appendChild(content);
        return card;
    }

    _buildBadgeElement(text, styleToken) {
        const badge = document.createElement('span');
        badge.className = `usim-ic-badge style-${this._normalizeStyle(styleToken)}`;
        badge.textContent = text;
        return badge;
    }

    _buildActions(actions) {
        const actionsWrap = document.createElement('div');
        actionsWrap.className = 'usim-ic-actions';

        const maxVisible = Math.max(0, Number(this.config?.max_visible_actions ?? 2));
        const inlineActions = [];
        const overflowActions = [];

        actions.forEach((raw) => {
            if (!raw || typeof raw !== 'object') return;
            const act = {
                label: String(raw.label ?? ''),
                action: raw.action ? String(raw.action) : null,
                parameters: raw.parameters && typeof raw.parameters === 'object' ? raw.parameters : {},
                url: raw.url ? String(raw.url) : null,
                target: raw.target ? String(raw.target) : '_self',
                icon: raw.icon ? String(raw.icon) : null,
                style: this._normalizeStyle(raw.style),
                variant: ['solid', 'outline', 'ghost'].includes(String(raw.variant)) ? String(raw.variant) : 'outline',
                disabled: Boolean(raw.disabled),
                overflow: Boolean(raw.overflow),
                tooltip: raw.tooltip ? String(raw.tooltip) : null,
            };

            if (act.overflow || inlineActions.length >= maxVisible) {
                overflowActions.push(act);
            } else {
                inlineActions.push(act);
            }
        });

        inlineActions.forEach((act) => {
            actionsWrap.appendChild(this._buildInlineActionButton(act));
        });

        if (overflowActions.length > 0) {
            const overflowWrap = document.createElement('div');
            overflowWrap.className = 'usim-ic-overflow-wrap';

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'usim-ic-action-btn variant-ghost usim-ic-overflow-trigger';
            trigger.textContent = '⋮';
            trigger.title = 'Más acciones';
            trigger.setAttribute('aria-label', 'Más acciones');
            trigger.setAttribute('aria-haspopup', 'menu');

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                if (this._overflowOpen) {
                    this._closeOverflowMenu();
                    return;
                }

                this._overflowOpen = true;
                const menu = document.createElement('div');
                menu.className = 'usim-ic-overflow-menu';
                menu.setAttribute('role', 'menu');
                menu.addEventListener('click', (ev) => ev.stopPropagation());

                overflowActions.forEach((act) => {
                    const menuItem = act.url && !act.disabled
                        ? document.createElement('a')
                        : document.createElement('button');
                    menuItem.className = `usim-ic-overflow-item style-${act.style}`;
                    menuItem.setAttribute('role', 'menuitem');

                    if (act.url && !act.disabled) {
                        menuItem.href = act.url;
                        menuItem.target = act.target;
                        if (act.target === '_blank') menuItem.rel = 'noopener noreferrer';
                    } else {
                        menuItem.type = 'button';
                        menuItem.disabled = act.disabled;
                        menuItem.addEventListener('click', () => {
                            this._closeOverflowMenu();
                            this._dispatchAction(act);
                        });
                    }

                    if (act.icon) {
                        menuItem.appendChild(this._createIconNode(act.icon, 14));
                    }
                    const lbl = document.createElement('span');
                    lbl.textContent = act.label;
                    menuItem.appendChild(lbl);
                    menu.appendChild(menuItem);
                });

                overflowWrap.appendChild(menu);
            });

            overflowWrap.appendChild(trigger);
            actionsWrap.appendChild(overflowWrap);
        }

        return actionsWrap;
    }

    _buildInlineActionButton(act) {
        const isLink = Boolean(act.url && !act.disabled);
        const el = document.createElement(isLink ? 'a' : 'button');
        el.className = `usim-ic-action-btn variant-${act.variant} style-${act.style}`;
        if (act.tooltip) el.title = act.tooltip;

        if (isLink) {
            el.href = act.url;
            el.target = act.target;
            if (act.target === '_blank') el.rel = 'noopener noreferrer';
            el.addEventListener('click', (e) => e.stopPropagation());
        } else {
            el.type = 'button';
            el.disabled = act.disabled;
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                this._dispatchAction(act);
            });
        }

        if (act.icon) {
            el.appendChild(this._createIconNode(act.icon, 14));
        }
        if (act.label) {
            const lbl = document.createElement('span');
            lbl.textContent = act.label;
            el.appendChild(lbl);
        }

        return el;
    }

    _handleCardClick() {
        const cfg = this.config || {};
        if (cfg.disabled) return;

        if (cfg.url) {
            if (cfg.target === '_blank') {
                window.open(String(cfg.url), '_blank', 'noopener,noreferrer');
            } else {
                window.location.assign(String(cfg.url));
            }
            return;
        }

        if (cfg.click_action) {
            const params = {
                ...(cfg.click_parameters && typeof cfg.click_parameters === 'object' ? cfg.click_parameters : {}),
            };
            if (cfg.item_id !== undefined && cfg.item_id !== null && params.id === undefined) {
                params.id = cfg.item_id;
            }
            this.sendEventToBackend('click', String(cfg.click_action), params);
        }
    }

    _dispatchAction(act) {
        if (act.disabled || !act.action) return;
        const params = { ...(act.parameters || {}) };
        if (this.config?.item_id !== undefined && this.config?.item_id !== null && params.id === undefined) {
            params.id = this.config.item_id;
        }
        this.sendEventToBackend('click', act.action, params);
    }

    _createIconNode(iconValue, size = 16) {
        const helpers = window.USIM_COMPONENT_HELPERS;
        if (helpers && typeof helpers.createIconElement === 'function') {
            return helpers.createIconElement(iconValue, {
                size,
                baseClass: 'usim-ic-icon',
            });
        }

        const fallback = document.createElement('span');
        fallback.className = 'usim-ic-icon';
        fallback.textContent = String(iconValue || '');
        return fallback;
    }
}

window.UsimItemCardComponent = UsimItemCardComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('item_card');
    window.USIM_COMPONENTS.register('item_card', (id, config) => new UsimItemCardComponent(id, config), {
        source: 'modular',
    });
}
