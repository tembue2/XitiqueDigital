export function Panel({ actions, children, title }) {
  return (
    <section className="panel" aria-labelledby={title ? titleId(title) : undefined}>
      {title || actions ? (
        <div className="panel-head">
          {title ? <h3 className="panel-title" id={titleId(title)}>{title}</h3> : <span />}
          {actions ? <div className="actions">{actions}</div> : null}
        </div>
      ) : null}
      {children}
    </section>
  );
}

function titleId(title) {
  return `panel-${String(title).toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
}

