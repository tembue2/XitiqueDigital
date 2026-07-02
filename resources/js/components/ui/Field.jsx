import clsx from 'clsx';

export function Field({
  children,
  className,
  error,
  hint,
  id,
  label,
  required,
}) {
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;

  return (
    <label className={clsx('field', className)} htmlFor={id}>
      <span className="field-label">
        {label}
        {required ? <span aria-hidden="true"> *</span> : null}
      </span>
      {children({ describedBy: [hintId, errorId].filter(Boolean).join(' ') || undefined })}
      {hint ? <span className="field-hint" id={hintId}>{hint}</span> : null}
      {error ? <span className="field-error" id={errorId}>{error}</span> : null}
    </label>
  );
}

export function TextInput({ describedBy, invalid, ...props }) {
  return (
    <input
      aria-describedby={describedBy}
      aria-invalid={invalid || undefined}
      {...props}
    />
  );
}

export function SelectInput({ children, describedBy, invalid, ...props }) {
  return (
    <select
      aria-describedby={describedBy}
      aria-invalid={invalid || undefined}
      {...props}
    >
      {children}
    </select>
  );
}

