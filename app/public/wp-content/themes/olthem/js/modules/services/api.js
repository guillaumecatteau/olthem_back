export function createApiClient(baseUrl, nonce) {
    async function request(path, options) {
        const url = baseUrl + String(path || '').replace(/^\//, '');
        const response = await fetch(url, {
            method: (options && options.method) || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce || '',
                ...(options && options.headers ? options.headers : {}),
            },
            credentials: 'include',
            body: options && options.body ? JSON.stringify(options.body) : undefined,
        });

        if (!response.ok) {
            throw new Error('API request failed: ' + response.status);
        }

        return response.json();
    }

    return { request };
}
