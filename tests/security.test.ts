import assert from 'node:assert/strict';
import test from 'node:test';
import { validateNewOrder, validateOrderUpdate, validatePaymentStart } from '../src/server/validation';

test('public order input cannot inject financial or status fields', () => {
  const order = validateNewOrder({
    name: 'Test Kullanıcı',
    phone: '+90 555 111 22 33',
    size: 'M',
    quantity: 2,
    deliveryType: 'Genel Merkezden Teslim',
    city: '',
    district: '',
    address: '',
    total: 1,
    paymentStatus: 'paid',
  });
  assert.deepEqual(Object.keys(order).sort(), [
    'address', 'city', 'deliveryType', 'district', 'name', 'phone', 'quantity', 'size',
  ]);
});

test('shipping orders require a complete address', () => {
  assert.throws(() => validateNewOrder({
    name: 'Test Kullanıcı',
    phone: '05551112233',
    size: 'L',
    quantity: 1,
    deliveryType: 'Adrese Kargo',
    city: '',
    district: '',
    address: '',
  }));
});

test('admin updates accept only workflow fields', () => {
  assert.throws(() => validateOrderUpdate({ total: 1, unitPrice: 1 }));
  assert.deepEqual(validateOrderUpdate({ paymentStatus: 'paid', notes: 'Kontrol edildi' }), {
    paymentStatus: 'paid',
    notes: 'Kontrol edildi',
  });
});

test('payment input accepts only gateway fields and normalizes card data', () => {
  const futureYear = String((new Date().getFullYear() + 2) % 100).padStart(2, '0');
  const payment = validatePaymentStart({
    orderId: 'd3f505eb-2f74-4b5a-8f4d-066361ee6d1b',
    cardHolderName: 'Test Kullanıcı',
    cardNumber: '4242 4242 4242 4242',
    expiryMonth: '8',
    expiryYear: `20${futureYear}`,
    cvv: '123',
    email: 'TEST@example.com',
    billingCity: 'İstanbul',
    billingState: '34',
    billingPostalCode: '34000',
    billingAddress: 'Test adresi',
    amount: 1,
    merchantId: 'attacker-value',
  });

  assert.equal(payment.cardNumber, '4242424242424242');
  assert.equal(payment.expiryMonth, '08');
  assert.equal(payment.expiryYear, futureYear);
  assert.equal(payment.email, 'test@example.com');
  assert.equal('amount' in payment, false);
  assert.equal('merchantId' in payment, false);
});

test('payment input rejects invalid or expired card details', () => {
  const validInput = {
    orderId: 'd3f505eb-2f74-4b5a-8f4d-066361ee6d1b',
    cardHolderName: 'Test Kullanıcı',
    cardNumber: '4242424242424242',
    expiryMonth: '12',
    expiryYear: '30',
    cvv: '123',
    email: 'test@example.com',
    billingCity: 'İstanbul',
    billingState: '34',
    billingPostalCode: '34000',
    billingAddress: 'Test adresi',
  };

  assert.throws(() => validatePaymentStart({ ...validInput, cardNumber: '4242424242424241' }));
  assert.throws(() => validatePaymentStart({ ...validInput, expiryYear: '20' }));
  assert.throws(() => validatePaymentStart({ ...validInput, cvv: '1234' }));
});
