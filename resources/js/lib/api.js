import { appUrl, csrfToken } from './config.js';

export class ApiError extends Error {
  constructor(message, status, details = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.details = details;
  }
}

export async function apiRequest(path, options = {}) {
  const response = await fetch(appUrl(path), {
    method: options.method || 'GET',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken(),
      ...(options.headers || {}),
    },
    credentials: 'same-origin',
    body: options.body ? JSON.stringify(options.body) : undefined,
    signal: options.signal,
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok || payload.ok === false) {
    throw new ApiError(
      payload.message || 'Não foi possível concluir a operação.',
      response.status,
      payload.details || {},
    );
  }

  return payload;
}

export function readForm(form) {
  return Object.fromEntries(new FormData(form).entries());
}

