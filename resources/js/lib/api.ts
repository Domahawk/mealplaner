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
