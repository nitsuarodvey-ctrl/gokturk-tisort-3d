'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'site.js'), 'utf8');
let stored = JSON.stringify([
  { id: 'selcuk-tshirt', size: 'M', quantity: 2, name: '<img src=x onerror=alert(1)>', image: 'javascript:alert(1)', price: 1 },
  { id: 'selcuk-tshirt', size: 'M', quantity: 3 },
  { id: 'selcuk-tshirt', size: 'XL', quantity: 0 },
  { id: 'unknown', size: 'S', quantity: 10 },
]);

const localStorage = {
  getItem: () => stored,
  setItem: (_key, value) => { stored = value; },
};
const window = {
  addEventListener: () => {},
  dispatchEvent: () => {},
};
const jQuery = (argument) => {
  if (typeof argument === 'function') return undefined;
  return {};
};

vm.runInNewContext(source, {
  window,
  localStorage,
  jQuery,
  CustomEvent: class CustomEvent {},
  Intl,
  JSON,
  Object,
  Number,
  Array,
  Math,
});

const cart = window.Shop.getCart();
assert.equal(cart.length, 1);
assert.deepEqual(JSON.parse(JSON.stringify(cart[0])), {
  id: 'selcuk-tshirt',
  name: 'SELÇUK T-SHIRT',
  subtitle: 'Çift başlı kartal işlemeli siyah oversize tişört.',
  price: 499,
  image: 'assets/img/selcuk-tshirt.png',
  size: 'M',
  quantity: 5,
});

window.Shop.addToCart('<script>', 10);
assert.equal(window.Shop.getCart().length, 1);
assert.equal(window.Shop.subtotal(), 2495);
window.Shop.addToCart('L', 10);
assert.equal(window.Shop.itemCount(), 10);
assert.equal(window.Shop.getCart().find((item) => item.size === 'L').quantity, 5);
window.Shop.updateItem('M', 8);
assert.equal(window.Shop.itemCount(), 10);
assert.equal(window.Shop.getCart().find((item) => item.size === 'M').quantity, 5);
console.log('client storage normalization: ok');
