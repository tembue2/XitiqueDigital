import clsx from 'clsx';

export function Badge({ children, tone = 'neutral' }) {
  return <span className={clsx('badge', `badge-${tone}`)}>{children}</span>;
}

