const port = process.env.VITE_PORT ?? '5173';

try {
    const response = await fetch(`http://127.0.0.1:${port}/@vite/client`);
    process.exit(response.ok ? 0 : 1);
} catch {
    process.exit(1);
}
