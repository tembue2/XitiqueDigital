export function money(value, currency = 'MZN') {
  try {
    return new Intl.NumberFormat('pt-MZ', {
      style: 'currency',
      currency,
    }).format(Number(value || 0));
  } catch {
    return `${Number(value || 0).toFixed(2)} ${currency}`;
  }
}

export function statusLabel(status) {
  return {
    draft: 'Rascunho',
    active: 'Activo',
    turn_completed: 'Turno concluído',
    dissolved: 'Dissolvido',
  }[status] || status;
}

export function activityLabel(action) {
  return {
    demo_created: 'Grupo de demonstração',
    group_created: 'Grupo criado',
    member_added: 'Membro adicionado',
    member_removed: 'Membro removido',
    turn_started: 'Turno iniciado',
    payment_confirmed: 'Pagamento confirmado',
    payment_updated: 'Pagamento actualizado',
    cycle_completed: 'Ciclo concluído',
    cycle_opened: 'Novo ciclo aberto',
    turn_completed: 'Turno concluído',
    payout_received: 'Recebimento confirmado',
  }[action] || action;
}

export function readableDate(value) {
  if (!value) {
    return '-';
  }

  const normalized = String(value).includes('T') ? value : String(value).replace(' ', 'T');
  const date = new Date(normalized);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('pt-MZ', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

