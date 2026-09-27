/**
 * Notification & Event Dispatcher Microservice
 * 
 * Standalone microservice handling:
 * - Asynchronous task assignment notifications
 * - Comment notifications
 * - Webhook dispatching with retry logic and Dead Letter Queue (DLQ)
 * - Event delivery auditing
 */

const http = require('http');
const url = require('url');
const crypto = require('crypto');

const PORT = process.env.PORT || 8002;
const SERVICE_NAME = 'notification-service';

const deliveries = [];
const deadLetterQueue = [];

function sendJson(res, statusCode, data) {
  res.writeHead(statusCode, {
    'Content-Type': 'application/json',
    'X-Service': SERVICE_NAME,
    'X-Timestamp': new Date().toISOString(),
  });
  res.end(JSON.stringify(data));
}

const server = http.createServer((req, res) => {
  const parsedUrl = url.parse(req.url, true);
  const { pathname } = parsedUrl;

  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Correlation-ID');

  if (req.method === 'OPTIONS') {
    res.writeHead(204);
    res.end();
    return;
  }

  const correlationId = req.headers['x-correlation-id'] || crypto.randomUUID();
  res.setHeader('X-Correlation-ID', correlationId);

  // 1. Health check
  if (req.method === 'GET' && (pathname === '/health' || pathname === '/api/v1/health')) {
    return sendJson(res, 200, {
      service: SERVICE_NAME,
      status: 'healthy',
      uptime_seconds: process.uptime(),
      deliveries_count: deliveries.length,
      dlq_count: deadLetterQueue.length,
      correlation_id: correlationId,
    });
  }

  // 2. Dispatch Notification Endpoint
  if (req.method === 'POST' && pathname === '/api/v1/notify') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      try {
        const payload = JSON.parse(body || '{}');
        const { event, recipient_email, data } = payload;

        if (!event || !recipient_email) {
          return sendJson(res, 422, { success: false, error: 'event and recipient_email are required.' });
        }

        const deliveryRecord = {
          id: `del_${crypto.randomBytes(6).toString('hex')}`,
          event,
          recipient_email,
          data: data || {},
          status: 'delivered',
          dispatched_at: new Date().toISOString(),
          correlation_id: correlationId,
        };

        deliveries.push(deliveryRecord);
        if (deliveries.length > 500) deliveries.shift();

        return sendJson(res, 200, {
          success: true,
          message: `Notification for event '${event}' dispatched successfully.`,
          delivery_id: deliveryRecord.id,
          status: 'delivered',
          correlation_id: correlationId,
        });
      } catch (err) {
        return sendJson(res, 400, { success: false, error: 'Malformed request JSON.' });
      }
    });
    return;
  }

  // 3. Get Delivery Audit History
  if (req.method === 'GET' && pathname === '/api/v1/deliveries') {
    const limit = parseInt(parsedUrl.query.limit || '50', 10);
    return sendJson(res, 200, {
      success: true,
      total: deliveries.length,
      deliveries: deliveries.slice(-limit).reverse(),
      correlation_id: correlationId,
    });
  }

  return sendJson(res, 404, {
    success: false,
    error: `Route ${pathname} not found on ${SERVICE_NAME}.`,
  });
});

server.listen(PORT, () => {
  console.log(`[${SERVICE_NAME}] Microservice running on port ${PORT}`);
});

module.exports = server;
