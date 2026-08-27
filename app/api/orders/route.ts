import { createOrder, getAllOrders } from '../../../src/server/order-db';
import { json, publicError, readJson, RequestError, verifySameOrigin } from '../../../src/server/http';
import { getAdminSession } from '../../../src/server/sessions';
import { validateNewOrder } from '../../../src/server/validation';

export async function GET(request: Request) {
  try {
    if (!await getAdminSession(request)) throw new RequestError('Yetkisiz.', 401);
    return json({ orders: await getAllOrders(request) });
  } catch (error) {
    return publicError(error, 'Siparişler yüklenemedi.');
  }
}

export async function POST(request: Request) {
  try {
    if (!verifySameOrigin(request)) throw new RequestError('İstek kaynağı doğrulanamadı.', 403);
    const order = await createOrder(validateNewOrder(await readJson(request)), request);
    return json({ order }, 201);
  } catch (error) {
    return publicError(error, 'Sipariş kaydedilemedi.');
  }
}
