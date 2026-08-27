import { backendRequest } from './backend';
import type { NewOrder, Order, OrderUpdate } from '../store/orders';
import { getSessionToken } from './sessions';

export async function getAllOrders(request: Request) {
  const result = await backendRequest<{ orders: Order[] }>('/orders', request, {
    sessionToken: getSessionToken(request) ?? undefined,
  });
  return result.orders;
}

export async function getOrderById(id: string, request: Request) {
  const result = await backendRequest<{ order: Order }>(`/orders/${encodeURIComponent(id)}`, request, {
    sessionToken: getSessionToken(request) ?? undefined,
  });
  return result.order;
}

export async function createOrder(input: NewOrder, request: Request) {
  const result = await backendRequest<{ order: Order }>('/orders', request, {
    method: 'POST',
    body: input,
  });
  return result.order;
}

export async function updateOrder(id: string, input: OrderUpdate, request: Request) {
  const result = await backendRequest<{ order: Order }>(`/orders/${encodeURIComponent(id)}`, request, {
    method: 'PATCH',
    body: input,
    sessionToken: getSessionToken(request) ?? undefined,
  });
  return result.order;
}

export async function deleteOrder(id: string, request: Request) {
  await backendRequest(`/orders/${encodeURIComponent(id)}`, request, {
    method: 'DELETE',
    sessionToken: getSessionToken(request) ?? undefined,
  });
  return true;
}
