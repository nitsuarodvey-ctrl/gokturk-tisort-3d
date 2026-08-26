'use client';

import { useEffect, useRef, useState } from 'react';
import '../src/style.css';

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

export default function Home() {
  const containerRef = useRef<HTMLDivElement>(null);
  const viewerRef = useRef<ViewerHandle | null>(null);
  const [status, setStatus] = useState('loading');
  const [progress, setProgress] = useState(0);
  const [activeView, setActiveView] = useState('reset');

  useEffect(() => {
    if (!containerRef.current) return;

    let cancelled = false;

    import('../src/viewer.js')
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

  return (
    <main className="viewer-shell">
      <div ref={containerRef} className="viewer-canvas" />

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
    </main>
  );
}
