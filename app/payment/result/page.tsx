import Link from 'next/link';

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

export default async function PaymentResultPage({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  const status = Array.isArray(params.status) ? params.status[0] : params.status;
  const reference = Array.isArray(params.reference) ? params.reference[0] : params.reference;
  const paid = status === 'paid';
  const unknown = status === 'unknown';

  return (
    <main className="payment-result-shell">
      <section className="payment-result-panel">
        <p className="eyebrow">GUB MERCH / ÖDEME</p>
        <h1>{paid ? 'Ödeme tamamlandı' : unknown ? 'Ödeme kontrol ediliyor' : 'Ödeme tamamlanamadı'}</h1>
        <p>
          {paid
            ? 'Ödemeniz banka tarafından onaylandı ve siparişiniz kesinleştirildi.'
            : unknown
              ? 'Banka yanıtı kesinleştirilemedi. Tekrar ödeme yapmayın; siparişiniz banka panelinden kontrol edilecek.'
              : 'Kartınızdan tahsilat yapılamadı. Bilgilerinizi kontrol ederek yeniden deneyebilirsiniz.'}
        </p>
        {reference && <small>Referans: {reference}</small>}
        <Link className="primary-cta result-link" href="/">Mağazaya dön</Link>
      </section>
    </main>
  );
}
