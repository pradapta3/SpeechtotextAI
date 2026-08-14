export class ApiError extends Error {
    constructor(message, { status = 0, retryAfter = null, errors = {} } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.retryAfter = retryAfter;
        this.errors = errors;
    }

    get isRateLimited() {
        return this.status === 429;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function request(method, url, { body, signal } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };

    if (body !== undefined && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    let response;

    try {
        response = await fetch(url, {
            method,
            headers,
            signal,
            body: body instanceof FormData ? body : body === undefined ? undefined : JSON.stringify(body),
        });
    } catch (error) {
        if (error.name === 'AbortError') {
            throw error;
        }

        throw new ApiError('Koneksi ke server terputus. Periksa jaringan lalu coba lagi.');
    }

    if (response.status === 204) {
        return null;
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const retryAfterHeader = Number.parseInt(response.headers.get('Retry-After') ?? '', 10);

        throw new ApiError(payload.message ?? `Permintaan gagal (${response.status}).`, {
            status: response.status,
            retryAfter: payload.retry_after ?? (Number.isFinite(retryAfterHeader) ? retryAfterHeader : null),
            errors: payload.errors ?? {},
        });
    }

    return payload;
}

export const api = {
    createRecording: (data) => request('POST', '/api/recordings', { body: data }),
    updateRecording: (id, data) => request('PATCH', `/api/recordings/${id}`, { body: data }),
    deleteRecording: (id) => request('DELETE', `/api/recordings/${id}`),
    sendChunk: (id, formData, signal) =>
        request('POST', `/api/recordings/${id}/chunks`, { body: formData, signal }),
    generateMinutes: (id, data) => request('POST', `/api/recordings/${id}/minutes`, { body: data }),
    saveSettings: (data) => request('PUT', '/api/settings', { body: data }),
};

/** Jeda yang bisa dibatalkan — dipakai saat menunggu reset rate limit. */
export function sleep(seconds, signal) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(resolve, seconds * 1000);

        signal?.addEventListener(
            'abort',
            () => {
                clearTimeout(timer);
                reject(new DOMException('Dibatalkan', 'AbortError'));
            },
            { once: true },
        );
    });
}
