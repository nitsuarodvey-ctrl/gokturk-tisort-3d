import { createOrder, getAllOrders } from '../../../src/server/order-db';
import { json, publicError, readJson, RequestError, verifySameOrigin } from '../../../src/server/http';
import { enforceRateLimit } from '../../../src/server/rate-limit';
import { getAdminSession } from '../../../src/server/sessions';
import { validateNewOrder } from '../../../src/server/validation';

export async function GET(request: Request) {
  try {
    if (!await getAdminSession(request)) throw new RequestError('Yetkisiz.', 401);
    return json({ orders: await getAllOrders() });
  } catch (error) {
    return publicError(error, 'Siparişler yüklenemedi.');
  }
}

export async function POST(request: Request) {
  try {
    if (!verifySameOrigin(request)) throw new RequestError('İstek kaynağı doğrulanamadı.', 403);
    const allowed = await enforceRateLimit(request, 'public_order', 5, 60_000);
    if (!allowed) throw new RequestError('Çok fazla sipariş denemesi. Lütfen kısa süre sonra tekrar deneyin.', 429);

    const order = await createOrder(validateNewOrder(await readJson(request)));
    if (!order) throw new Error('Oluşturulan sipariş okunamadı.');
    return json({ order }, 201);
  } catch (error) {
    return publicError(error, 'Sipariş kaydedilemedi.');
  }
}
