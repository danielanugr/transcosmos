const server = require('./server');
const http = require('http');

function request(options, data) {
  return new Promise((resolve, reject) => {
    const req = http.request(options, (res) => {
      let body = '';
      res.on('data', (chunk) => { body += chunk; });
      res.on('end', () => {
        resolve({ status: res.statusCode, data: JSON.parse(body || '{}') });
      });
    });
    req.on('error', reject);
    if (data) req.write(JSON.stringify(data));
    req.end();
  });
}

async function runTests() {
  const health = await request({ port: 8001, path: '/health', method: 'GET' });
  console.assert(health.status === 200, 'Health check failed');
  console.assert(health.data.status === 'healthy', 'Status not healthy');

  const scan = await request(
    { port: 8001, path: '/api/v1/scan-threat', method: 'POST', headers: { 'Content-Type': 'application/json' } },
    { content_sample: 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' }
  );
  console.assert(scan.status === 200, 'Scan request failed');
  console.assert(scan.data.clean === false, 'EICAR was not flagged');

  const media = await request(
    { port: 8001, path: '/api/v1/process-media', method: 'POST', headers: { 'Content-Type': 'application/json' } },
    { file_name: 'demo.mp4', file_size: 15000000, mime_type: 'video/mp4' }
  );
  console.assert(media.status === 200, 'Media process failed');
  console.assert(media.data.processed === true, 'Processed flag false');
  console.assert(media.data.thumbnail_path.includes('thumb_video_'), 'Video thumb not generated');

  server.close();
  process.exit(0);
}

setTimeout(runTests, 500);
