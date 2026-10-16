/**
 * Card component (modular override)
 */
class UsimCardComponent extends UIComponent {
    render() {
        let card = document.createElement('div');
        card.className = this.getCardClasses();

        if (this.config.clickable) {
            if (this.config.url) {
                const link = document.createElement('a');
                link.href = this.config.url;
                link.target = this.config.target || '_self';
                link.className = card.className;
                link.style.textDecoration = 'none';
                link.style.color = 'inherit';
                card = link;
            } else if (this.config.action) {
                card.style.cursor = 'pointer';
                card.addEventListener('click', () => {
                    this.sendEventToBackend('click', this.config.action, this.config.parameters || {});
                });
            }
        }

        if (this.config.badge) {
            const badge = document.createElement('div');
            badge.className = `ui-card-badge ${this.config.badge_position || 'top-right'}`;
            badge.textContent = this.config.badge;
            card.appendChild(badge);
        }

        if (this.config.image && this.config.image_position !== 'background') {
            const imageContainer = this.createImageElement();
            if (this.config.image_position === 'top' || !this.config.image_position) {
                card.appendChild(imageContainer);
            }
        }

        const content = document.createElement('div');
        content.className = 'ui-card-content';

        if (this.config.show_header !== false && (this.config.title || this.config.subtitle || this.config.header)) {
            const header = this.createHeader();
            content.appendChild(header);
        }

        const body = this.createBody();
        if (body.children.length > 0 || body.textContent.trim()) {
            content.appendChild(body);
        }

        if (this.config.show_footer !== false && (this.config.actions?.length > 0 || this.config.footer)) {
            const footer = this.createFooter();
            content.appendChild(footer);
        }

        card.appendChild(content);

        if (this.config.image && this.config.image_position === 'bottom') {
            const imageContainer = this.createImageElement();
            card.appendChild(imageContainer);
        }

        if (this.config.image && this.config.image_position === 'background') {
            card.style.backgroundImage = `url(${this.config.image})`;
            card.style.backgroundSize = this.config.image_fit || 'cover';
            card.style.backgroundPosition = 'center';
            card.style.backgroundRepeat = 'no-repeat';
        }

        return this.applyCommonAttributes(card);
    }

    getCardClasses() {
        let classes = 'ui-card';

        if (this.config.style) classes += ` ui-card-${this.config.style}`;
        if (this.config.variant) classes += ` ui-card-${this.config.variant}`;
        if (this.config.size) classes += ` ui-card-${this.config.size}`;
        if (this.config.elevation) classes += ` ui-card-elevation-${this.config.elevation}`;
        if (this.config.status) classes += ` ui-card-status-${this.config.status}`;
        if (this.config.theme) classes += ` ui-card-theme-${this.config.theme}`;
        if (this.config.orientation) classes += ` ui-card-${this.config.orientation}`;
        if (this.config.hover_effect !== false) classes += ` ui-card-hover`;
        if (this.config.clickable) classes += ` ui-card-clickable`;

        return classes;
    }

    createImageElement() {
        const imageContainer = document.createElement('div');
        imageContainer.className = 'ui-card-image';

        const img = document.createElement('img');
        img.src = this.config.image;
        img.alt = this.config.image_alt || this.config.title || '';
        img.style.objectFit = this.config.image_fit || 'cover';

        imageContainer.appendChild(img);
        return imageContainer;
    }

    createHeader() {
        const header = document.createElement('div');
        header.className = 'ui-card-header';

        if (this.config.header) {
            header.innerHTML = this.config.header;
        } else {
            if (this.config.title) {
                const title = document.createElement('h3');
                title.className = 'ui-card-title';
                title.textContent = this.config.title;
                header.appendChild(title);
            }

            if (this.config.subtitle) {
                const subtitle = document.createElement('p');
                subtitle.className = 'ui-card-subtitle';
                subtitle.textContent = this.config.subtitle;
                header.appendChild(subtitle);
            }
        }

        return header;
    }

    createBody() {
        const body = document.createElement('div');
        body.className = 'ui-card-body';

        if (this.config.content) {
            body.innerHTML = this.config.content;
        } else if (this.config.description) {
            const description = document.createElement('p');
            description.className = 'ui-card-description';
            description.textContent = this.config.description;
            body.appendChild(description);
        }

        return body;
    }

    createFooter() {
        const footer = document.createElement('div');
        footer.className = 'ui-card-footer';

        if (this.config.footer) {
            footer.innerHTML = this.config.footer;
        } else if (this.config.actions?.length > 0) {
            const actionsContainer = document.createElement('div');
            actionsContainer.className = 'ui-card-actions';

            this.config.actions.forEach(actionConfig => {
                const button = document.createElement('button');
                button.className = `ui-button ${actionConfig.style || 'primary'}`;
                button.textContent = actionConfig.label;

                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.sendEventToBackend('click', actionConfig.action, actionConfig.parameters || {});
                });

                actionsContainer.appendChild(button);
            });

            footer.appendChild(actionsContainer);
        }

        return footer;
    }
}

window.UsimCardComponent = UsimCardComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('card');
    window.USIM_COMPONENTS.register('card', (id, config) => new UsimCardComponent(id, config), {
        source: 'modular',
    });
}
