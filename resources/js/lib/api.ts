export interface HealthResponse {
    status: 'ok';
    database: 'connected';
}

export async function checkHealth(): Promise<HealthResponse> {
    const response = await fetch('/api/ready', {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
        signal: AbortSignal.timeout(8000),
    });

    if (!response.ok) {
        throw new Error(
            response.status === 503
                ? 'The server is reachable, but the database is unavailable.'
                : 'The server could not complete the connectivity check.',
        );
    }

    const body: unknown = await response.json();
    if (
        typeof body !== 'object' ||
        body === null ||
        !('status' in body) ||
        body.status !== 'ok' ||
        !('database' in body) ||
        body.database !== 'connected'
    ) {
        throw new Error('The server returned an unexpected response.');
    }

    return { status: 'ok', database: 'connected' };
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
}

export class AuthenticationError extends Error {
    constructor(
        message: string,
        readonly status: number,
        readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

export async function authRequest(
    action: 'session' | 'login' | 'register' | 'logout',
    body?: Record<string, string>,
): Promise<AuthUser | null> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (body) {
        headers['Content-Type'] = 'application/json';
        const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));
        if (cookie)
            headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
    }

    const response = await fetch(`/api/auth/${action}`, {
        method: body ? 'POST' : 'GET',
        headers,
        credentials: 'same-origin',
        cache: 'no-store',
        signal: AbortSignal.timeout(8000),
        ...(body ? { body: JSON.stringify(body) } : {}),
    });

    if (response.status === 204 && action === 'logout') return null;
    // Preserve authentication status handling even for an empty/non-JSON error body.
    const data: unknown = await response.json().catch(() => null);
    if (!response.ok) {
        const errors: Record<string, string[]> = {};
        if (response.status === 422 && isRecord(data) && isRecord(data.errors)) {
            for (const [field, messages] of Object.entries(data.errors)) {
                if (
                    Array.isArray(messages) &&
                    messages.every((message) => typeof message === 'string')
                ) {
                    errors[field] = messages;
                }
            }
        }
        const message =
            response.status === 419
                ? 'Your session has expired. Please try again.'
                : response.status === 429
                  ? 'Too many attempts. Please wait a minute and try again.'
                  : response.status === 422
                    ? 'Please check the highlighted fields.'
                    : 'Unable to complete authentication. Please try again.';
        throw new AuthenticationError(message, response.status, errors);
    }

    if (isRecord(data) && data.user === null && action === 'session') return null;
    if (
        isRecord(data) &&
        isRecord(data.user) &&
        typeof data.user.id === 'number' &&
        typeof data.user.name === 'string' &&
        typeof data.user.email === 'string'
    ) {
        return { id: data.user.id, name: data.user.name, email: data.user.email };
    }
    throw new Error('The server returned an unexpected authentication response.');
}
