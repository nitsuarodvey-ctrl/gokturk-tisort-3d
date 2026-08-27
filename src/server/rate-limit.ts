import { getDatabase } from './db';
import { sha256 } from './crypto';

function clientAddress(request: Request) {
  return request.headers.get('cf-connecting-ip')
    ?? request.headers.get('x-forwarded-for')?.split(',')[0]?.trim()
    ?? 'unknown';
}

export async function enforceRateLimit(
  request: Request,
  scope: string,
  limit: number,
  windowMs: number,
  discriminator = '',
) {
  const windowKey = Math.floor(Date.now() / windowMs);
  const key = await sha256(`${clientAddress(request)}|${discriminator.toLowerCase()}`);
  const db = getDatabase();

  await db.execute(
    `INSERT INTO rate_limits (scope, client_key, window_key, request_count)
     VALUES (?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE request_count = request_count + 1, updated_at = CURRENT_TIMESTAMP(3)`,
    [scope, key, windowKey],
  );
  const result = await db.execute<{ request_count: number | string }>(
    `SELECT request_count FROM rate_limits
     WHERE scope = ? AND client_key = ? AND window_key = ? LIMIT 1`,
    [scope, key, windowKey],
  );
  const count = Number(result.rows[0]?.request_count ?? limit + 1);
  return count <= limit;
}
