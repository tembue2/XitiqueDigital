import { AlertCircle, CheckCircle2, Info, LoaderCircle } from 'lucide-react';
import clsx from 'clsx';

export function Toast({ notice }) {
  if (!notice) {
    return null;
  }

  const Icon = notice.type === 'error' ? AlertCircle : CheckCircle2;

  return (
    <div className={clsx('toast', notice.type === 'error' && 'toast-error')} role={notice.type === 'error' ? 'alert' : 'status'} aria-live="polite">
      <Icon className="toast-icon" aria-hidden="true" />
      <span>{notice.message}</span>
    </div>
  );
}

export function InlineAlert({ children, tone = 'info' }) {
  const Icon = tone === 'error' ? AlertCircle : Info;

  return (
    <div className={clsx('inline-alert', `inline-alert-${tone}`)} role={tone === 'error' ? 'alert' : 'status'}>
      <Icon aria-hidden="true" />
      <span>{children}</span>
    </div>
  );
}

export function Spinner({ label = 'A carregar' }) {
  return (
    <div className="spinner" role="status">
      <LoaderCircle className="spin" aria-hidden="true" />
      <span>{label}</span>
    </div>
  );
}

export function SkeletonBlock({ rows = 3 }) {
  return (
    <div className="skeleton-stack" aria-hidden="true">
      {Array.from({ length: rows }).map((_, index) => (
        <div className="skeleton-line" key={index} />
      ))}
    </div>
  );
}

export function EmptyState({ action, children, title }) {
  return (
    <section className="empty-state">
      <h2>{title}</h2>
      <p>{children}</p>
      {action ? <div className="empty-action">{action}</div> : null}
    </section>
  );
}

