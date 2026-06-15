import { createReadStream, existsSync, statSync } from 'node:fs';
import { createServer, request as httpRequest } from 'node:http';
import { extname, join, normalize, resolve } from 'node:path';

const port = Number(process.env.PORT || 5173);
const apiTarget = new URL(process.env.API_TARGET || 'http://127.0.0.1:8000');
const distDir = resolve('dist');

const mimeTypes = {
    '.css': 'text/css; charset=utf-8',
    '.html': 'text/html; charset=utf-8',
    '.ico': 'image/x-icon',
    '.js': 'text/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.map': 'application/json; charset=utf-8',
    '.png': 'image/png',
    '.svg': 'image/svg+xml',
    '.txt': 'text/plain; charset=utf-8',
    '.webp': 'image/webp',
};

const safePath = (urlPath) => {
    const decodedPath = decodeURIComponent(urlPath.split('?')[0]);
    const normalizedPath = normalize(decodedPath).replace(/^(\.\.[/\\])+/, '');
    const filePath = join(distDir, normalizedPath);

    return filePath.startsWith(distDir) ? filePath : join(distDir, 'index.html');
};

const serveFile = (response, filePath) => {
    if (!existsSync(filePath) || !statSync(filePath).isFile()) {
        response.writeHead(404, { 'content-type': 'text/plain; charset=utf-8' });
        response.end('Not found');
        return;
    }

    response.writeHead(200, {
        'content-type': mimeTypes[extname(filePath)] || 'application/octet-stream',
    });
    createReadStream(filePath).pipe(response);
};

const proxyApi = (clientRequest, clientResponse) => {
    const targetUrl = new URL(clientRequest.url, apiTarget);
    const headers = {
        ...clientRequest.headers,
        host: apiTarget.host,
        'x-forwarded-host': clientRequest.headers.host || '',
        'x-forwarded-proto': 'https',
    };

    const proxyRequest = httpRequest(
        {
            protocol: apiTarget.protocol,
            hostname: apiTarget.hostname,
            port: apiTarget.port,
            method: clientRequest.method,
            path: `${targetUrl.pathname}${targetUrl.search}`,
            headers,
        },
        (proxyResponse) => {
            clientResponse.writeHead(proxyResponse.statusCode || 500, proxyResponse.headers);
            proxyResponse.pipe(clientResponse);
        },
    );

    proxyRequest.on('error', () => {
        clientResponse.writeHead(502, { 'content-type': 'application/json; charset=utf-8' });
        clientResponse.end(JSON.stringify({ message: 'API proxy failed' }));
    });

    clientRequest.pipe(proxyRequest);
};

createServer((request, response) => {
    if (request.url?.startsWith('/api/')) {
        proxyApi(request, response);
        return;
    }

    const requestedPath = safePath(request.url || '/');
    const filePath = existsSync(requestedPath) && statSync(requestedPath).isFile()
        ? requestedPath
        : join(distDir, 'index.html');

    serveFile(response, filePath);
}).listen(port, '0.0.0.0', () => {
    console.log(`Serving frontend and proxying /api to ${apiTarget.origin}`);
    console.log(`Local: http://127.0.0.1:${port}`);
});
