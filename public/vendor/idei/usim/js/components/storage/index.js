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
            const rawValue = this.config[key];
            try {
                const incomingObj = typeof rawValue === 'object' && rawValue !== null
                    ? rawValue
                    : (typeof safeParseJsonObject === 'function' ? safeParseJsonObject(rawValue) : null);

                if (incomingObj && typeof incomingObj === 'object') {
                    const existingRaw = sessionStorage.getItem(key);
                    const existingObj = (typeof safeParseJsonObject === 'function' ? safeParseJsonObject(existingRaw) : null) || {};

                    if (existingObj.store_token && (!incomingObj.store_token || incomingObj.store_token === '')) {
                        incomingObj.store_token = existingObj.store_token;
                    }

                    if (existingObj.store_token_crypt && (!incomingObj.store_token_crypt || incomingObj.store_token_crypt === '')) {
                        incomingObj.store_token_crypt = existingObj.store_token_crypt;
                    }

                    const merged = Object.assign({}, existingObj, incomingObj);
                    sessionStorage.setItem(key, JSON.stringify(merged));
                } else if (typeof rawValue === 'object' && rawValue !== null) {
                    sessionStorage.setItem(key, JSON.stringify(rawValue));
                } else {
                    sessionStorage.setItem(key, String(rawValue));
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
