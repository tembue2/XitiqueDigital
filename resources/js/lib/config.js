export const publicBaseUrl = import.meta.url.replace(/\/assets\/[^/]+$/, '/');

export function appUrl(path) {
  return new URL(path.replace(/^\//, ''), publicBaseUrl).toString();
}

export function assetUrl(path) {
  return appUrl(path);
}

export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}
