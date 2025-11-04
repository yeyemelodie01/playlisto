import axios from "axios";

const API_URL = process.env.REACT_APP_API_URL;
/**
 * Creates an Axios instance with default configurations.
 */
const api = axios.create({
    baseURL: API_URL,
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
    },
});

/**
 * Request interceptor to automatically add the authentication token.
 */
api.interceptors.request.use(
    (config) => {
        const token = getToken();
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

/**
 * Response interceptor to handle authentication errors (401 Unauthorized).
 */
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            removeToken();
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

/**
 * Retrieves the authentication token from a secure cookie.
 * @returns {string|null} The authentication token if found, otherwise null.
 */
const getToken = () => {
    const name = 'auth_token=';
    const decodedCookie = decodeURIComponent(document.cookie);
    const cookies = decodedCookie.split(';');
    for (let cookie of cookies) {
        cookie = cookie.trim();
        if (cookie.startsWith(name)) {
            return cookie.substring(name.length);
        }
    }
    return null;
};

/**
 * Sets the authentication token in a secure cookie.
 * @param {string} token - The authentication token to store.
 */
const setToken = (token) => {
    const secureFlag = window.location.protocol === 'https:' ? 'Secure;' : '';
    document.cookie = `auth_token=${token}; path=/; ${secureFlag} samesite=strict;`;
}

/**
 * Removes the authentication token by setting its expiration date to the past.
 */
const removeToken = () => {
    const isHttps = window.location.protocol === 'https:';
    const attrs = `Path=/; SameSite=Strict;${isHttps ? ' Secure;' : ''} Expires=Thu, 01 Jan 1970 00:00:00 GMT`;

    document.cookie = `auth_token=; ${attrs}`;

    const parts = location.hostname.split('.');
    if (parts.length >= 2) {
        const parent = '.' + parts.slice(-2).join('.');
        document.cookie = `auth_token=; Domain=${parent}; ${attrs}`;
    }
};

/**
 * Logs out the user by removing the authentication token and redirecting to the login page.
 */
const logout = async () => {
    sessionStorage.setItem('logoutMessage', 'Déconnexion réussie');
    try {
        await api.post('api/logout');
    } finally {
        removeToken();

        window.location.href = '/login';
    }
}

/**
 * API service with methods for making HTTP requests and managing authentication tokens.
 */
const apiService = {
    get: (url, config = {}) => api.get(url, config),
    post: (url, data, config = {}) => api.post(url, data, config),
    put: (url, data, config = {}) => api.put(url, data, config),
    patch: (url, data, config = {}) => api.patch(url, data, config),
    delete: (url, config = {}) => api.delete(url, config),
    getToken,
    setToken,
    removeToken,
    logout,
};

export default apiService;