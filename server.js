const http = require('http');
const fs = require('fs');
const path = require('path');
const root = process.cwd();
const mime = { '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8' };
http.createServer((req, res) => {
  const pathname = decodeURIComponent(req.url.split('?')[0]);
  const filePath = path.join(root, pathname === '/' ? 'index.html' : pathname.slice(1));
  if (!filePath.startsWith(root) || !fs.existsSync(filePath)) { res.writeHead(404); res.end('Not found'); return; }
  res.writeHead(200, { 'Content-Type': mime[path.extname(filePath)] || 'text/plain; charset=utf-8' });
  fs.createReadStream(filePath).pipe(res);
}).listen(4173, '127.0.0.1', () => console.log('L10 mock on http://127.0.0.1:4173'));
