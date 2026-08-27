import { getDatabase } from './db';
import {
  type NewOrder,
  type Order,
  type OrderUpdate,
  UNIT_PRICE,
} from '../store/orders';

type OrderRow = {
  id: string;
  name: string;
  phone: string;
  size: Order['size'];
  quantity: number | string;
  delivery_type: Order['deliveryType'];
  city: string | null;
  district: string | null;
  address: string | null;
  unit_price: number | string;
  total: number | string;
  payment_status: Order['paymentStatus'];
  order_status: Order['orderStatus'];
  production_status: Order['productionStatus'];
  delivery_status: Order['deliveryStatus'];
  notes: string | null;
  created_at: string | Date;
  updated_at: string | Date;
};

function timestamp(value: string | Date) {
  return value instanceof Date ? value.toISOString() : new Date(`${value}Z`).toISOString();
}

function fromRow(row: OrderRow): Order {
  return {
    id: row.id,
    name: row.name,
    phone: row.phone,
    size: row.size,
    quantity: Number(row.quantity),
    deliveryType: row.delivery_type,
    city: row.city ?? '',
    district: row.district ?? '',
    address: row.address ?? '',
    unitPrice: Number(row.unit_price),
    total: Number(row.total),
    paymentStatus: row.payment_status,
    orderStatus: row.order_status,
    productionStatus: row.production_status,
    deliveryStatus: row.delivery_status,
    notes: row.notes ?? '',
    createdAt: timestamp(row.created_at),
    updatedAt: timestamp(row.updated_at),
  };
}

export async function getAllOrders() {
  const result = await getDatabase().execute<OrderRow>(
    'SELECT * FROM orders ORDER BY created_at DESC LIMIT 1000',
  );
  return result.rows.map(fromRow);
}

export async function getOrderById(id: string) {
  const result = await getDatabase().execute<OrderRow>(
    'SELECT * FROM orders WHERE id = ? LIMIT 1',
    [id],
  );
  return result.rows[0] ? fromRow(result.rows[0]) : null;
}

export async function createOrder(input: NewOrder) {
  const id = crypto.randomUUID();
  await getDatabase().execute(
    `INSERT INTO orders (
      id, name, phone, size, quantity, delivery_type, city, district, address,
      unit_price, total, payment_status, order_status, production_status, delivery_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting', 'preorder', 'waiting', 'waiting')`,
    [
      id,
      input.name,
      input.phone,
      input.size,
      input.quantity,
      input.deliveryType,
      input.city || null,
      input.district || null,
      input.address || null,
      UNIT_PRICE,
      UNIT_PRICE * input.quantity,
    ],
  );
  return await getOrderById(id);
}

export async function updateOrder(id: string, input: OrderUpdate) {
  const columns: string[] = [];
  const values: unknown[] = [];
  const mapping: Array<[keyof OrderUpdate, string]> = [
    ['paymentStatus', 'payment_status'],
    ['orderStatus', 'order_status'],
    ['productionStatus', 'production_status'],
    ['deliveryStatus', 'delivery_status'],
    ['notes', 'notes'],
  ];

  for (const [key, column] of mapping) {
    if (input[key] !== undefined) {
      columns.push(`${column} = ?`);
      values.push(input[key]);
    }
  }
  columns.push('updated_at = CURRENT_TIMESTAMP(3)');
  values.push(id);

  await getDatabase().execute(
    `UPDATE orders SET ${columns.join(', ')} WHERE id = ? LIMIT 1`,
    values,
  );
  return await getOrderById(id);
}

export async function deleteOrder(id: string) {
  const result = await getDatabase().execute('DELETE FROM orders WHERE id = ? LIMIT 1', [id]);
  return result.rowsAffected > 0;
}
