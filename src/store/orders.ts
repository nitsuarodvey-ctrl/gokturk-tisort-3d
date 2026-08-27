import { requireSupabase } from '../lib/supabase.js';

export const UNIT_PRICE = 499;

export const DELIVERY_OPTIONS = [
  'Genel Merkezden Teslim',
  'İzmir Elden Teslim',
  'Adrese Kargo',
] as const;

export const PAYMENT_STATUSES = ['waiting', 'paid', 'rejected'] as const;
export const ORDER_STATUSES = ['preorder', 'confirmed', 'cancelled'] as const;
export const PRODUCTION_STATUSES = ['waiting', 'queued', 'in_production', 'ready'] as const;
export const DELIVERY_STATUSES = ['waiting', 'ready_for_pickup', 'shipped', 'delivered'] as const;

export type DeliveryType = (typeof DELIVERY_OPTIONS)[number];
export type ProductSize = 'S' | 'M' | 'L' | 'XL';
export type PaymentStatus = (typeof PAYMENT_STATUSES)[number];
export type OrderStatus = (typeof ORDER_STATUSES)[number];
export type ProductionStatus = (typeof PRODUCTION_STATUSES)[number];
export type DeliveryStatus = (typeof DELIVERY_STATUSES)[number];

export type Order = {
  id: string;
  name: string;
  phone: string;
  size: ProductSize;
  quantity: number;
  deliveryType: DeliveryType;
  city: string;
  district: string;
  address: string;
  unitPrice: number;
  total: number;
  paymentStatus: PaymentStatus;
  orderStatus: OrderStatus;
  productionStatus: ProductionStatus;
  deliveryStatus: DeliveryStatus;
  notes: string;
  createdAt: string;
  updatedAt: string;
};

export type NewOrder = Pick<
  Order,
  'name' | 'phone' | 'size' | 'quantity' | 'deliveryType' | 'city' | 'district' | 'address'
>;

export type OrderUpdate = Partial<
  Pick<
    Order,
    | 'name'
    | 'phone'
    | 'size'
    | 'quantity'
    | 'deliveryType'
    | 'city'
    | 'district'
    | 'address'
    | 'paymentStatus'
    | 'orderStatus'
    | 'productionStatus'
    | 'deliveryStatus'
    | 'notes'
  >
>;

type OrderRow = {
  id: string;
  name: string;
  phone: string;
  size: ProductSize;
  quantity: number;
  delivery_type: DeliveryType;
  city: string | null;
  district: string | null;
  address: string | null;
  unit_price: number;
  total: number;
  payment_status: PaymentStatus;
  order_status: OrderStatus;
  production_status: ProductionStatus;
  delivery_status: DeliveryStatus;
  notes: string | null;
  created_at: string;
  updated_at: string;
};

function fromRow(row: OrderRow): Order {
  return {
    id: row.id,
    name: row.name,
    phone: row.phone,
    size: row.size,
    quantity: row.quantity,
    deliveryType: row.delivery_type,
    city: row.city ?? '',
    district: row.district ?? '',
    address: row.address ?? '',
    unitPrice: row.unit_price,
    total: row.total,
    paymentStatus: row.payment_status,
    orderStatus: row.order_status,
    productionStatus: row.production_status,
    deliveryStatus: row.delivery_status,
    notes: row.notes ?? '',
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  };
}

function updateToRow(input: OrderUpdate) {
  const row: Record<string, unknown> = {};
  const mappings: Array<[keyof OrderUpdate, string]> = [
    ['name', 'name'],
    ['phone', 'phone'],
    ['size', 'size'],
    ['quantity', 'quantity'],
    ['deliveryType', 'delivery_type'],
    ['city', 'city'],
    ['district', 'district'],
    ['address', 'address'],
    ['paymentStatus', 'payment_status'],
    ['orderStatus', 'order_status'],
    ['productionStatus', 'production_status'],
    ['deliveryStatus', 'delivery_status'],
    ['notes', 'notes'],
  ];

  mappings.forEach(([source, target]) => {
    if (input[source] !== undefined) row[target] = input[source];
  });

  if (input.quantity !== undefined) row.total = input.quantity * UNIT_PRICE;
  return row;
}

export const orderRepository = {
  async getAll(): Promise<Order[]> {
    const client = requireSupabase();
    const { data, error } = await client
      .from('orders')
      .select('*')
      .order('created_at', { ascending: false });
    if (error) throw error;
    return (data as OrderRow[]).map(fromRow);
  },

  async getById(id: string): Promise<Order | null> {
    const client = requireSupabase();
    const { data, error } = await client
      .from('orders')
      .select('*')
      .eq('id', id)
      .maybeSingle();
    if (error) throw error;
    return data ? fromRow(data as OrderRow) : null;
  },

  async create(input: NewOrder): Promise<Order> {
    const client = requireSupabase();
    const now = new Date().toISOString();
    const row: OrderRow = {
      id: crypto.randomUUID(),
      name: input.name,
      phone: input.phone,
      size: input.size,
      quantity: input.quantity,
      delivery_type: input.deliveryType,
      city: input.city || null,
      district: input.district || null,
      address: input.address || null,
      unit_price: UNIT_PRICE,
      total: UNIT_PRICE * input.quantity,
      payment_status: 'waiting',
      order_status: 'preorder',
      production_status: 'waiting',
      delivery_status: 'waiting',
      notes: null,
      created_at: now,
      updated_at: now,
    };

    // Anonymous storefront users can INSERT but cannot SELECT, so the insert
    // deliberately does not request a representation from the database.
    const { error } = await client.from('orders').insert(row);
    if (error) throw error;
    return fromRow(row);
  },

  async update(id: string, input: OrderUpdate): Promise<Order> {
    const client = requireSupabase();
    const { data, error } = await client
      .from('orders')
      .update(updateToRow(input))
      .eq('id', id)
      .select('*')
      .single();
    if (error) throw error;
    return fromRow(data as OrderRow);
  },

  async delete(id: string): Promise<void> {
    const client = requireSupabase();
    const { error } = await client.from('orders').delete().eq('id', id);
    if (error) throw error;
  },

  subscribe(onChange: () => void) {
    const client = requireSupabase();
    const channel = client
      .channel('admin-orders-live')
      .on(
        'postgres_changes',
        { event: '*', schema: 'public', table: 'orders' },
        onChange,
      )
      .subscribe();

    return () => {
      void client.removeChannel(channel);
    };
  },
};
