import { LoaderCircle } from 'lucide-react';
import clsx from 'clsx';

export function Button({
  children,
  className,
  disabled,
  icon: Icon,
  isLoading = false,
  size = 'md',
  variant = 'dark',
  type = 'button',
  ...props
}) {
  return (
    <button
      className={clsx('btn', `btn-${variant}`, `btn-${size}`, className)}
      type={type}
      aria-busy={isLoading || undefined}
      {...props}
      disabled={isLoading || disabled}
    >
      {isLoading ? <LoaderCircle className="btn-icon spin" aria-hidden="true" /> : Icon ? <Icon className="btn-icon" aria-hidden="true" /> : null}
      <span>{children}</span>
    </button>
  );
}
