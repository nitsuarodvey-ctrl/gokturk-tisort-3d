import { deleteOrder, getOrderById, updateOrder } from '../../../../src/server/order-db';
import { json, publicError, readJson, RequestError, verifySameOrigin } from '../../../../src/server/http';
import { getAdminSession } from '../../../../src/server/sessions';
import { validateOrderUpdate } from '../../../../src/server/validation';

type RouteContext = { params: Promise<{ id: string }> };

async function authorizedId(request: Request, context: RouteContext) {
  if (!await getAdminSession(request)) throw new RequestError('Yetkisiz.', 401);
  const { id } = await context.params;
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iu.test(id)) {
    throw new RequestError('Sipariş kimliği geçersiz.');
  }
  return id;
}

export async function GET(request: Request, context: RouteContext) {
  try {
    const order = await getOrderById(await authorizedId(request, context), request);
    if (!order) throw new RequestError('Sipariş bulunamadı.', 404);
    return json({ order });
  } catch (error) {
    return publicError(error, 'Sipariş yüklenemedi.');
  }
}

export async function PATCH(request: Request, context: RouteContext) {
  try {
    if (!verifySameOrigin(request)) throw new RequestError('İstek kaynağı doğrulanamadı.', 403);
    const id = await authorizedId(request, context);
    const order = await updateOrder(id, validateOrderUpdate(await readJson(request)), request);
    if (!order) throw new RequestError('Sipariş bulunamadı.', 404);
    return json({ order });
  } catch (error) {
    return publicError(error, 'Sipariş güncellenemedi.');
  }
}

export async function DELETE(request: Request, context: RouteContext) {
  try {
    if (!verifySameOrigin(request)) throw new RequestError('İstek kaynağı doğrulanamadı.', 403);
    const deleted = await deleteOrder(await authorizedId(request, context), request);
    if (!deleted) throw new RequestError('Sipariş bulunamadı.', 404);
    return json({ ok: true });
  } catch (error) {
    return publicError(error, 'Sipariş silinemedi.');
  }
}
