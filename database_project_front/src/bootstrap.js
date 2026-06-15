import axios from 'axios';

const apiOrigin = import.meta.env.VITE_API_URL
    || (import.meta.env.DEV ? 'http://127.0.0.1:8000' : window.location.origin);

axios.defaults.baseURL = apiOrigin;
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common.Accept = 'application/json';

axios.interceptors.request.use((config) => {
    const url = config.url || '';

    if (url.startsWith('/') && !url.startsWith('/api/')) {
        config.url = `/api${url}`;
    }

    return config;
});

window.axios = axios;
