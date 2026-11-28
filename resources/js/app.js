import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

window.addEventListener('pos:sync', event => {
    const path = window.location.pathname.replace(/\/+$/, '') || '/';
    const resource = event.detail?.resource;
    const editing = /\/(create|edit)(\/|$)/.test(path);
    const focusedInput = document.activeElement?.matches('input,textarea,select');
    const formChanged = [...document.forms].some(form => [...form.elements].some(control => {
        if (control.disabled || ['button', 'hidden', 'reset', 'submit'].includes(control.type)) return false;
        if (control.type === 'checkbox' || control.type === 'radio') return control.checked !== control.defaultChecked;
        if (control.tagName === 'SELECT') return [...control.options].some(option => option.selected !== option.defaultSelected);
        return control.value !== control.defaultValue;
    }));
    const routesByResource = {
        product: ['/products'],
        user: ['/users'],
        customer: ['/customers'],
        sale: ['/sales'],
        shift: ['/sales'],
        settings: ['/settings/store'],
    };

    const shouldRefresh = (routesByResource[resource] || []).includes(path)
        || (path === '/dashboard' && ['product', 'sale', 'shift'].includes(resource));

    if (shouldRefresh && !editing && !focusedInput && !formChanged) window.location.reload();
});
