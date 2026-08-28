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

function passesLuhn(cardNumber: string) {
  let sum = 0;
  let alternate = false;
  for (let index = cardNumber.length - 1; index >= 0; index -= 1) {
    let digit = Number(cardNumber[index]);
    if (alternate) {
      digit *= 2;
      if (digit > 9) digit -= 9;
    }
    sum += digit;
    alternate = !alternate;
  }
  return sum % 10 === 0;
}

export function validatePaymentStart(input: unknown) {
  const value = objectValue(input);
  const cardNumber = text(value.cardNumber, 'Kart numarası', 32).replace(/\D/gu, '');
  if (!/^\d{13,19}$/u.test(cardNumber) || !passesLuhn(cardNumber)) {
    throw new RequestError('Kart numarası geçersiz.');
  }

  const expiryMonth = text(value.expiryMonth, 'Son kullanma ayı', 2).padStart(2, '0');
  const expiryYear = text(value.expiryYear, 'Son kullanma yılı', 4).replace(/\D/gu, '').slice(-2);
  if (!/^(0[1-9]|1[0-2])$/u.test(expiryMonth) || !/^\d{2}$/u.test(expiryYear)) {
    throw new RequestError('Kartın son kullanma tarihi geçersiz.');
  }
  const fullYear = 2000 + Number(expiryYear);
  const now = new Date();
  if (fullYear < now.getFullYear() || (fullYear === now.getFullYear() && Number(expiryMonth) < now.getMonth() + 1)) {
    throw new RequestError('Kartın son kullanma tarihi geçmiş.');
  }

  const cvv = text(value.cvv, 'CVV', 4).replace(/\D/gu, '');
  if (!/^\d{3}$/u.test(cvv)) throw new RequestError('CVV üç haneli olmalıdır.');

  const email = text(value.email, 'E-posta', 254).toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/u.test(email)) throw new RequestError('E-posta geçersiz.');

  const billingState = text(value.billingState, 'İl kodu', 3).replace(/\D/gu, '');
  if (!/^\d{1,3}$/u.test(billingState)) throw new RequestError('İl kodu geçersiz.');

  return {
    orderId: text(value.orderId, 'Sipariş', 36),
    cardHolderName: text(value.cardHolderName, 'Kart sahibi', 45),
    cardNumber,
    expiryMonth,
    expiryYear,
    cvv,
    email,
    billingCity: text(value.billingCity, 'Fatura şehri', 80),
    billingState,
    billingPostalCode: text(value.billingPostalCode, 'Posta kodu', 10),
    billingAddress: text(value.billingAddress, 'Fatura adresi', 250),
  };
}
