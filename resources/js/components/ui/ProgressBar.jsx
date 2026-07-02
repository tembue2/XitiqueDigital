export function ProgressBar({ label = 'Progresso', value }) {
  const normalized = Math.max(0, Math.min(100, Number(value || 0)));

  return (
    <div className="progress-wrap">
      <div className="progress-meta">
        <span>{label}</span>
        <strong>{normalized}%</strong>
      </div>
      <div
        className="progress"
        role="progressbar"
        aria-label={label}
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow={normalized}
      >
        <div style={{ width: `${normalized}%` }} />
      </div>
    </div>
  );
}

