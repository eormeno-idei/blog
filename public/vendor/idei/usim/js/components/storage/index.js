/**
 * Storage component (modular override)
 */
class UsimStorageComponent extends UIComponent {
    render() {
        this.storeData();
        return document.createDocumentFragment();
    }

    storeData() {
        const storageKeys = Object.keys(this.config).filter(key => !key.startsWith('_') && key !== 'type');

        if (storageKeys.length > 0) {
            const currentKey = getActiveUsimStorageKey();
            const preferredKey = storageKeys.includes(currentKey) ? currentKey : storageKeys[0];
            setActiveUsimStorageKey(preferredKey);
        }

        storageKeys.forEach(key => {
            const value = this.config[key];
            try {
                if (typeof value === 'object' && value !== null) {
                    sessionStorage.setItem(key, JSON.stringify(value));
                } else {
                    sessionStorage.setItem(key, String(value));
                }
            } catch (error) {
                // Ignore storage errors (private mode / blocked storage)
            }
        });
    }

    updateComponent(newConfig) {
        this.config = { ...this.config, ...newConfig };
        this.storeData();
    }
}

window.UsimStorageComponent = UsimStorageComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('storage');
    window.USIM_COMPONENTS.register('storage', (id, config) => new UsimStorageComponent(id, config), {
        source: 'modular',
    });
}
