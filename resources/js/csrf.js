/**
 * Laravel sets the XSRF-TOKEN cookie on every response; raw fetch() calls
 * (outside Inertia's own router, which handles this automatically) need to
 * echo it back as a header for POST/PATCH/DELETE requests to pass CSRF
 * verification.
 */
export function withCsrfHeader(headers = {}) {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    const token = match ? decodeURIComponent(match[1]) : null;

    return token ? { ...headers, 'X-XSRF-TOKEN': token } : headers;
}
