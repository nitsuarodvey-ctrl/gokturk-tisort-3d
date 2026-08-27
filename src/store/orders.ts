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
    'paymentStatus' | 'orderStatus' | 'productionStatus' | 'deliveryStatus' | 'notes'
  >
>;

async function api<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, {
    ...init,
    credentials: 'same-origin',
    headers: init?.body
      ? { 'Content-Type': 'application/json', ...init.headers }
      : init?.headers,
  });
  const payload = await response.json().catch(() => ({})) as T & { error?: string };
  if (!response.ok) throw new Error(payload.error || 'İstek tamamlanamadı.');
  return payload;
}

export const orderRepository = {
  async getAll(): Promise<Order[]> {
    return (await api<{ orders: Order[] }>('/api/orders')).orders;
  },

  async getById(id: string): Promise<Order | null> {
    try {
      return (await api<{ order: Order }>(`/api/orders/${encodeURIComponent(id)}`)).order;
    } catch (error) {
      if (error instanceof Error && error.message === 'Sipariş bulunamadı.') return null;
      throw error;
    }
  },

  async create(input: NewOrder): Promise<Order> {
    return (await api<{ order: Order }>('/api/orders', {
      method: 'POST',
      body: JSON.stringify(input),
    })).order;
  },

  async update(id: string, input: OrderUpdate): Promise<Order> {
    return (await api<{ order: Order }>(`/api/orders/${encodeURIComponent(id)}`, {
      method: 'PATCH',
      body: JSON.stringify(input),
    })).order;
  },

  async delete(id: string): Promise<void> {
    await api(`/api/orders/${encodeURIComponent(id)}`, { method: 'DELETE' });
  },

  subscribe(onChange: () => void) {
    const timer = window.setInterval(onChange, 5_000);
    return () => window.clearInterval(timer);
  },
};
