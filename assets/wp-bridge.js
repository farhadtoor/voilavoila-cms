/* Guest CSRF transport for explicitly allowed WordPress actions. */
(() => {
  'use strict';
  const token = document.querySelector('meta[name="vv-guest"]')?.content;
  if (!token) return;
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || new URL(form.action, location.href).origin !== location.origin) return;
    let input = form.querySelector('input[name="vv_guest"]');
    if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'vv_guest'; form.append(input); }
    input.value = token;
  });
  const fetchOriginal = window.fetch;
  window.fetch = function (input, init) {
    const request = new Request(input, init);
    if (new URL(request.url).origin === location.origin && request.method === 'POST') {
      const headers = new Headers(request.headers); headers.set('X-VV-Guest', token);
      return fetchOriginal.call(this, new Request(request, { headers }));
    }
    return fetchOriginal.call(this, request);
  };
  const open = XMLHttpRequest.prototype.open, send = XMLHttpRequest.prototype.send;
  const marked = new WeakSet();
  XMLHttpRequest.prototype.open = function (method, url, ...args) {
    marked.delete(this);
    if (String(method).toUpperCase() === 'POST' && new URL(url, location.href).origin === location.origin) marked.add(this);
    return open.call(this, method, url, ...args);
  };
  XMLHttpRequest.prototype.send = function (body) { if (marked.has(this)) this.setRequestHeader('X-VV-Guest', token); return send.call(this, body); };
})();
