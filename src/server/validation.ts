import {
  DELIVERY_OPTIONS,
  DELIVERY_STATUSES,
  type NewOrder,
  ORDER_STATUSES,
  type OrderUpdate,
  PAYMENT_STATUSES,
  PRODUCTION_STATUSES,
} from '../store/orders';
import { RequestError } from './http';

function objectValue(input: unknown) {
  if (!input || typeof input !== 'object' || Array.isArray(input)) {
    throw new RequestError('Geçersiz istek gövdesi.');
  }
  return input as Record<string, unknown>;
}

function text(value: unknown, field: string, max: number, required = true) {
  if (typeof value !== 'string') throw new RequestError(`${field} geçersiz.`);
  const normalized = value.trim();
  if (required && !normalized) throw new RequestError(`${field} zorunludur.`);
  if (normalized.length > max) throw new RequestError(`${field} çok uzun.`);
  return normalized;
}

function oneOf<const T extends readonly string[]>(value: unknown, options: T, field: string) {
  if (typeof value !== 'string' || !options.includes(value)) {
    throw new RequestError(`${field} geçersiz.`);
  }
  return value as T[number];
}

export function validateNewOrder(input: unknown): NewOrder {
  const value = objectValue(input);
  const quantity = Number(value.quantity);
  if (!Number.isInteger(quantity) || quantity < 1 || quantity > 20) {
    throw new RequestError('Adet 1–20 arasında olmalıdır.');
  }

  const phone = text(value.phone, 'Telefon', 32);
  const phoneDigits = phone.replace(/\D/gu, '');
  if (phoneDigits.length < 10 || phoneDigits.length > 15) {
    throw new RequestError('Telefon numarası geçersiz.');
  }

  const deliveryType = oneOf(value.deliveryType, DELIVERY_OPTIONS, 'Teslimat tipi');
  const shipping = deliveryType === 'Adrese Kargo';

  return {
    name: text(value.name, 'Ad soyad', 120),
    phone,
    size: oneOf(value.size, ['S', 'M', 'L', 'XL'] as const, 'Beden'),
    quantity,
    deliveryType,
    city: text(value.city ?? '', 'Şehir', 80, shipping),
    district: text(value.district ?? '', 'İlçe', 80, shipping),
    address: text(value.address ?? '', 'Adres', 500, shipping),
  };
}

export function validateOrderUpdate(input: unknown): OrderUpdate {
  const value = objectValue(input);
  const update: OrderUpdate = {};

  if ('paymentStatus' in value) {
    update.paymentStatus = oneOf(value.paymentStatus, PAYMENT_STATUSES, 'Ödeme durumu');
  }
  if ('orderStatus' in value) {
    update.orderStatus = oneOf(value.orderStatus, ORDER_STATUSES, 'Sipariş durumu');
  }
  if ('productionStatus' in value) {
    update.productionStatus = oneOf(value.productionStatus, PRODUCTION_STATUSES, 'Üretim durumu');
  }
  if ('deliveryStatus' in value) {
    update.deliveryStatus = oneOf(value.deliveryStatus, DELIVERY_STATUSES, 'Teslimat durumu');
  }
  if ('notes' in value) update.notes = text(value.notes, 'Not', 2_000, false);

  if (Object.keys(update).length === 0) {
    throw new RequestError('Güncellenecek geçerli bir alan bulunamadı.');
  }
  return update;
}

export function validateLogin(input: unknown) {
  const value = objectValue(input);
  const email = text(value.email, 'E-posta', 254).toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/u.test(email)) {
    throw new RequestError('E-posta veya şifre doğrulanamadı.', 401);
  }
  if (typeof value.password !== 'string' || !value.password || value.password.length > 128) {
    throw new RequestError('E-posta veya şifre doğrulanamadı.', 401);
  }
  return { email, password: value.password };
}
