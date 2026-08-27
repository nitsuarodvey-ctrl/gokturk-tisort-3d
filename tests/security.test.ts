import assert from 'node:assert/strict';
import test from 'node:test';
import { hashPassword, verifyPassword } from '../src/server/crypto';
import { validateNewOrder, validateOrderUpdate } from '../src/server/validation';

test('admin passwords are salted, slow-hashed and verifiable', async () => {
  const password = 'uzun-ve-benzersiz-admin-sifresi';
  const first = await hashPassword(password);
  const second = await hashPassword(password);
  assert.notEqual(first, second);
  assert.equal(first.includes(password), false);
  assert.equal(await verifyPassword(password, first), true);
  assert.equal(await verifyPassword('yanlis-sifre-degeri', first), false);
});

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
