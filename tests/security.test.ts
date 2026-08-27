import assert from 'node:assert/strict';
import test from 'node:test';
import { validateNewOrder, validateOrderUpdate } from '../src/server/validation';

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
