export const UNIT_PRICE = 499;

export const DELIVERY_OPTIONS = [
  'Genel Merkezden Teslim',
  'İzmir Elden Teslim',
  'Adrese Kargo',
] as const;

export type DeliveryType = (typeof DELIVERY_OPTIONS)[number];
export type ProductSize = 'S' | 'M' | 'L' | 'XL';

export type LocalOrder = {
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
  paymentStatus: 'waiting';
  orderStatus: 'preorder';
  createdAt: string;
};

export type NewOrder = Omit<
  LocalOrder,
  'id' | 'unitPrice' | 'total' | 'paymentStatus' | 'orderStatus' | 'createdAt'
>;

const STORAGE_KEY = 'gub-merch-orders';

export function createOrder(input: NewOrder): LocalOrder {
  return {
    ...input,
    id: globalThis.crypto?.randomUUID?.() ?? `gub-${Date.now()}`,
    unitPrice: UNIT_PRICE,
    total: UNIT_PRICE * input.quantity,
    paymentStatus: 'waiting',
    orderStatus: 'preorder',
    createdAt: new Date().toISOString(),
  };
}

export function persistOrder(order: LocalOrder) {
  if (typeof window === 'undefined') return;

  let orders: LocalOrder[] = [];
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    orders = stored ? JSON.parse(stored) : [];
  } catch {
    orders = [];
  }

  window.localStorage.setItem(STORAGE_KEY, JSON.stringify([...orders, order]));
}
