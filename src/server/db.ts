import { connect, type Connection } from '@planetscale/database';

let connection: Connection | null = null;

export function isDatabaseConfigured() {
  return Boolean(
    process.env.DATABASE_HOST?.trim()
      && process.env.DATABASE_USERNAME?.trim()
      && process.env.DATABASE_PASSWORD,
  );
}

export function getDatabase(): Connection {
  if (connection) return connection;

  const host = process.env.DATABASE_HOST?.trim();
  const username = process.env.DATABASE_USERNAME?.trim();
  const password = process.env.DATABASE_PASSWORD;

  if (!host || !username || !password) {
    throw new Error('MySQL bağlantısı yapılandırılmamış.');
  }

  if (!/^[a-z0-9.-]+$/i.test(host)) {
    throw new Error('DATABASE_HOST geçersiz.');
  }

  connection = connect({ host, username, password });
  return connection;
}
