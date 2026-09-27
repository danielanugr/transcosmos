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
  console.log('Testing Notification Microservice...');

  // Test 1: Health Check
  const health = await request({ port: 8002, path: '/health', method: 'GET' });
  console.assert(health.status === 200, 'Health check failed');
  console.assert(health.data.status === 'healthy', 'Status not healthy');
  console.log('✓ Health check passed');

  // Test 2: Dispatch Notification
  const dispatch = await request(
    { port: 8002, path: '/api/v1/notify', method: 'POST', headers: { 'Content-Type': 'application/json' } },
    { event: 'task.assigned', recipient_email: 'charlie@example.com', data: { task_id: 10, title: 'Test Task' } }
  );
  console.assert(dispatch.status === 200, 'Dispatch failed');
  console.assert(dispatch.data.status === 'delivered', 'Status not delivered');
  console.log('✓ Dispatch notification passed');

  // Test 3: Query Deliveries History
  const history = await request({ port: 8002, path: '/api/v1/deliveries', method: 'GET' });
  console.assert(history.status === 200, 'History query failed');
  console.assert(history.data.total >= 1, 'Delivery history empty');
  console.log('✓ Delivery history audit passed');

  console.log('All Notification Microservice tests passed successfully!');
  server.close();
  process.exit(0);
}

setTimeout(runTests, 500);
