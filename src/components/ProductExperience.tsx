'use client';
/* eslint-disable @next/next/no-html-link-for-pages */

import { useCallback, useEffect, useRef, useState } from 'react';
import { OrderDrawer } from './OrderDrawer';
import { ProductInfo } from './ProductInfo';
import { SizeGuideModal } from './SizeGuideModal';
import { ProductSize, UNIT_PRICE } from '../store/orders';

type ViewerHandle = {
  dispose: () => void;
  goToView: (name: string) => void;
};

const viewButtons = [
  { id: 'front', label: 'Ön' },
  { id: 'back', label: 'Arka' },
  { id: 'sleeve', label: 'Kol' },
  { id: 'reset', label: 'Sıfırla' },
];

export function ProductExperience() {
  const containerRef = useRef<HTMLDivElement>(null);
  const viewerRef = useRef<ViewerHandle | null>(null);
  const [status, setStatus] = useState('loading');
  const [progress, setProgress] = useState(0);
  const [activeView, setActiveView] = useState('reset');
  const [size, setSize] = useState<ProductSize>('M');
  const [quantity, setQuantity] = useState(1);
  const [sizeGuideOpen, setSizeGuideOpen] = useState(false);
  const [orderOpen, setOrderOpen] = useState(false);

  useEffect(() => {
    if (!containerRef.current) return;

    let cancelled = false;

    import('../viewer.js')
      .then(({ TShirtViewer }) => {
        if (cancelled || !containerRef.current) return;
        viewerRef.current = new TShirtViewer(containerRef.current, {
          onProgress: (value: number) => setProgress(value),
          onReady: () => setStatus('ready'),
          onError: () => setStatus('error'),
        });
      })
      .catch((error) => {
        console.error('[T-shirt viewer] Viewer startup failed:', error);
        setStatus('error');
      });

    return () => {
      cancelled = true;
      viewerRef.current?.dispose();
      viewerRef.current = null;
    };
  }, []);

  const selectView = (id: string) => {
    setActiveView(id);
    viewerRef.current?.goToView(id);
  };

  const closeSizeGuide = useCallback(() => setSizeGuideOpen(false), []);
  const closeOrder = useCallback(() => setOrderOpen(false), []);

  return (
    <main className="storefront">
      <a className="viewer-home-link" href="/">← Mağaza</a>
      <section className="viewer-stage" aria-label="Ürün 3D görünümü">
        <div ref={containerRef} className="viewer-canvas" />

        <div className="viewer-mark" aria-hidden="true">
          <span>01</span>
          <span>360° ÜRÜN GÖRÜNÜMÜ</span>
        </div>

        {status !== 'ready' && (
          <div className={`load-status ${status}`} role="status" aria-live="polite">
            {status === 'error' ? (
              'Model yüklenemedi'
            ) : (
              <span
                style={
                  {
                    '--load-progress': `${Math.round(progress * 100)}%`,
                  } as React.CSSProperties
                }
              />
            )}
          </div>
        )}

        <nav className="view-controls" aria-label="Ürün görünümü">
          {viewButtons.map((button) => (
            <button
              key={button.id}
              type="button"
              className={activeView === button.id ? 'active' : ''}
              onClick={() => selectView(button.id)}
              disabled={status !== 'ready'}
            >
              {button.label}
            </button>
          ))}
        </nav>
      </section>

      <ProductInfo
        size={size}
        quantity={quantity}
        onSizeChange={setSize}
        onQuantityChange={setQuantity}
        onOpenSizeGuide={() => setSizeGuideOpen(true)}
        onPreorder={() => setOrderOpen(true)}
      />

      <button className="mobile-sticky-cta" type="button" onClick={() => setOrderOpen(true)}>
        <span>Ön Sipariş Ver</span>
        <span>{UNIT_PRICE * quantity} TL</span>
      </button>

      <SizeGuideModal open={sizeGuideOpen} onClose={closeSizeGuide} />
      {orderOpen && (
        <OrderDrawer
          open
          initialSize={size}
          initialQuantity={quantity}
          onClose={closeOrder}
        />
      )}
    </main>
  );
}
