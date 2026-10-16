/**
 * USIM Component Registry
 *
 * Lightweight plugin registry to create components by type without
 * changing the renderer core every time a new component is added.
 */
(function initUsimComponentRegistry(global) {
    if (global.USIM_COMPONENTS && typeof global.USIM_COMPONENTS.register === 'function') {
        return;
    }

    const factories = new Map();

    function normalizeType(type) {
        if (typeof type !== 'string') {
            return '';
        }

        return type.trim().toLowerCase();
    }

    function register(type, factory, metadata = {}) {
        const normalizedType = normalizeType(type);
        if (!normalizedType) {
            console.warn('USIM_COMPONENTS.register ignored: empty type');
            return false;
        }

        if (typeof factory !== 'function') {
            console.warn(`USIM_COMPONENTS.register ignored: factory for "${normalizedType}" is not a function`);
            return false;
        }

        if (factories.has(normalizedType)) {
            console.warn(`USIM_COMPONENTS.register ignored: "${normalizedType}" is already registered`);
            return false;
        }

        factories.set(normalizedType, {
            factory,
            metadata: metadata && typeof metadata === 'object' ? metadata : {},
        });

        return true;
    }

    function unregister(type) {
        const normalizedType = normalizeType(type);
        if (!normalizedType) {
            return false;
        }

        return factories.delete(normalizedType);
    }

    function has(type) {
        return factories.has(normalizeType(type));
    }

    function list() {
        return Array.from(factories.keys());
    }

    function create(type, id, config) {
        const normalizedType = normalizeType(type);
        if (!normalizedType || !factories.has(normalizedType)) {
            return null;
        }

        try {
            const entry = factories.get(normalizedType);
            return entry.factory(id, config);
        } catch (error) {
            console.error(`USIM_COMPONENTS.create failed for "${normalizedType}"`, error);
            return null;
        }
    }

    global.USIM_COMPONENTS = {
        register,
        unregister,
        has,
        list,
        create,
    };
})(window);
