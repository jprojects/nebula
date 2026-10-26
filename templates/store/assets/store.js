/* Nebula · tienda: carrito, ficha de producto, buscador, listados, checkout y cuenta por AJAX (sin dependencias) */
(() => {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const cfgEl = document.getElementById('nb-config');
  const cfg = cfgEl ? JSON.parse(cfgEl.textContent) : { texts: {} };
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  /** Petición a la tienda. POST con cuerpo JSON; siempre devuelve el JSON (también en errores 4xx). */
  async function api(url, data = null, headers = {}) {
    const opts = {
      method: data === null ? 'GET' : 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf(), ...headers },
      credentials: 'same-origin',
    };
    if (data !== null) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(data);
    }
    let res;
    try {
      res = await fetch(url, opts);
    } catch (e) {
      return { ok: false, error: cfg.texts.error };
    }
    try {
      return await res.json();
    } catch (e) {
      return { ok: false, error: cfg.texts.error };
    }
  }

  function toast(message, type = 'info', link = null) {
    const box = $('[data-toasts]');
    if (!box || !message) return;
    const el = document.createElement('div');
    el.className = 'nb-toast' + (type === 'error' ? ' is-error' : '');
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.textContent = message;
    if (link) {
      const a = document.createElement('a');
      a.href = link.href;
      a.textContent = link.text;
      if (link.onClick) a.addEventListener('click', (e) => { e.preventDefault(); link.onClick(); el.remove(); });
      el.appendChild(a);
    }
    box.appendChild(el);
    setTimeout(() => el.remove(), 4500);
  }

  function busy(btn, on) {
    if (!btn) return;
    btn.classList.toggle('is-busy', on);
    btn.disabled = on;
  }

  /** Objeto con los campos de un formulario (checkbox marcado = "1"). */
  function formData(form) {
    const out = {};
    for (const el of form.elements) {
      if (!el.name || el.disabled) continue;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
      out[el.name] = el.value;
    }
    return out;
  }

  function showErrors(form, errors = {}, message = null) {
    $$('.nb-field-error', form).forEach((e) => e.remove());
    $$('.has-error', form).forEach((e) => e.classList.remove('has-error'));
    let first = null;
    for (const [name, text] of Object.entries(errors)) {
      const input = form.querySelector(`[name="${CSS.escape(name)}"]`);
      const box = form.querySelector(`[data-error-for="${CSS.escape(name)}"]`) || input?.closest('.nb-field, .nb-check');
      if (!box) continue;
      box.classList.add('has-error');
      const msg = document.createElement('span');
      msg.className = 'nb-field-error';
      msg.textContent = text;
      box.appendChild(msg);
      first ??= input || box;
    }
    const general = $('[data-form-error]', form);
    if (general) {
      general.hidden = !message;
      general.textContent = message || '';
    }
    if (first) {
      first.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (first.focus) first.focus({ preventScroll: true });
    }
  }

  // ------------------------------------------------------------------ carrito

  const drawer = $('[data-cart-drawer]');
  const drawerBody = $('[data-cart-body]');
  let lastFocus = null;

  function setCount(n, bump = false) {
    $$('[data-cart-count]').forEach((b) => {
      b.textContent = n;
      b.hidden = !n;
      if (bump) { b.classList.remove('is-bump'); void b.offsetWidth; b.classList.add('is-bump'); }
    });
  }

  function renderCart(cart) {
    if (!cart) return;
    if (drawerBody) drawerBody.innerHTML = cart.html;
    setCount(cart.count);
    (cart.notices || []).forEach((n) => toast(n, 'error'));
  }

  async function openCart(preloaded = null) {
    if (!drawer) return;
    lastFocus = document.activeElement;
    drawer.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-cart-close].nb-icon-btn', drawer)?.focus();
    if (preloaded) {
      renderCart(preloaded);
      return;
    }
    const res = await api(cfg.cartUrl);
    if (res.ok) renderCart(res.cart);
  }

  function closeCart() {
    if (!drawer || drawer.hidden) return;
    drawer.hidden = true;
    document.body.style.overflow = '';
    lastFocus?.focus?.();
  }

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-cart-open]')) { e.preventDefault(); openCart(); }
    if (e.target.closest('[data-cart-close]')) { e.preventDefault(); closeCart(); }
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeCart(); });

  const cartPage = $('[data-cart-page]');

  async function updateLine(key, qty, root) {
    root?.classList.add('is-loading');
    const res = await api(cfg.updateUrl, { key, qty }, cartPage ? { 'X-Cart-Page': '1' } : {});
    root?.classList.remove('is-loading');
    if (!res.ok) { toast(res.error || cfg.texts.error, 'error'); return; }
    renderCart(res.cart);
    if (cartPage && res.page !== null) cartPage.innerHTML = res.page;
  }

  // Cantidades y quitar en el panel lateral y en la página del carrito
  document.addEventListener('click', (e) => {
    const line = e.target.closest('[data-line]');
    if (!line) return;
    const input = $('[data-line-qty]', line);
    const step = e.target.closest('[data-line-step]');
    if (step) {
      const max = parseInt(input.max || '999', 10);
      const qty = Math.max(0, Math.min(max, (parseInt(input.value, 10) || 0) + parseInt(step.dataset.lineStep, 10)));
      input.value = qty;
      updateLine(line.dataset.line, qty, line.closest('[data-cart-body], [data-cart-page]'));
    }
    if (e.target.closest('[data-line-remove]')) {
      updateLine(line.dataset.line, 0, line.closest('[data-cart-body], [data-cart-page]'));
    }
  });
  document.addEventListener('change', (e) => {
    const input = e.target.closest('[data-line-qty]');
    if (input) updateLine(input.closest('[data-line]').dataset.line, Math.max(0, parseInt(input.value, 10) || 0), input.closest('[data-cart-body], [data-cart-page]'));
  });

  // Cupón (página del carrito)
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('[data-coupon]');
    if (!form) return;
    e.preventDefault();
    const btn = $('button[type="submit"]', form);
    busy(btn, true);
    const res = await api(cfg.couponUrl, { code: form.code.value }, { 'X-Cart-Page': '1' });
    busy(btn, false);
    if (!res.ok) { toast(res.error, 'error'); if (res.cart) renderCart(res.cart); return; }
    renderCart(res.cart);
    if (cartPage && res.page) cartPage.innerHTML = res.page;
    toast(res.message, 'success');
  });
  document.addEventListener('click', async (e) => {
    if (!e.target.closest('[data-coupon-remove]')) return;
    const res = await api(cfg.couponUrl, { code: '' }, { 'X-Cart-Page': '1' });
    if (res.ok) { renderCart(res.cart); if (cartPage && res.page) cartPage.innerHTML = res.page; }
  });

  // ------------------------------------------------------------------ ficha de producto

  document.addEventListener('click', (e) => {
    const step = e.target.closest('[data-qty-step]');
    if (!step) return;
    const input = $('input', step.closest('[data-qty]'));
    const min = parseInt(input.min || '1', 10);
    const max = parseInt(input.max || '999', 10);
    input.value = Math.max(min, Math.min(max, (parseInt(input.value, 10) || min) + parseInt(step.dataset.qtyStep, 10)));
  });

  const product = $('[data-product]');
  if (product) {
    const colors = JSON.parse(product.dataset.colors || '[]');
    const main = $('[data-gallery-main]', product);
    const thumbs = $('[data-gallery-thumbs]', product);

    const showImage = (src) => {
      if (!main || main.src.endsWith(src)) return;
      main.classList.add('is-swapping');
      const img = new Image();
      img.onload = () => { main.src = src; main.classList.remove('is-swapping'); };
      img.onerror = () => main.classList.remove('is-swapping');
      img.src = src;
    };

    const setGallery = (images) => {
      if (!thumbs || !images.length) return;
      thumbs.innerHTML = '';
      images.forEach((im, i) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'nb-thumb' + (i === 0 ? ' is-active' : '');
        b.dataset.full = im.full;
        const t = document.createElement('img');
        t.src = im.thumb;
        t.alt = '';
        b.appendChild(t);
        thumbs.appendChild(b);
      });
      thumbs.hidden = images.length < 2;
      showImage(images[0].full);
    };

    thumbs?.addEventListener('click', (e) => {
      const b = e.target.closest('.nb-thumb');
      if (!b) return;
      $$('.nb-thumb', thumbs).forEach((t) => t.classList.toggle('is-active', t === b));
      showImage(b.dataset.full);
    });

    product.addEventListener('change', (e) => {
      const input = e.target.closest('[data-color-input]');
      if (!input) return;
      const c = colors.find((x) => x.code === input.value);
      if (!c) return;
      $('[data-color-name]', product).textContent = c.name;
      $('[data-price]', product).textContent = c.price;
      const cmp = $('[data-compare]', product);
      cmp.textContent = c.compare || '';
      cmp.hidden = !c.compare;
      setGallery(c.images);
    });
  }

  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('[data-add-to-cart]');
    if (!form) return;
    e.preventDefault();
    const btn = $('button[type="submit"]', form);
    busy(btn, true);
    const res = await api(cfg.addUrl, formData(form));
    busy(btn, false);
    if (!res.ok) { toast(res.error, 'error'); return; }
    setCount(res.cart.count, true);
    openCart(res.cart);
  });

  // ------------------------------------------------------------------ buscador con sugerencias

  const search = $('[data-search]');
  if (search) {
    const input = $('[data-search-input]', search);
    const box = $('[data-suggest]', search);
    let active = -1;
    let seq = 0;

    const close = () => { box.hidden = true; active = -1; };
    const render = (res) => {
      box.innerHTML = '';
      if (!res.items.length) {
        const p = document.createElement('p');
        p.textContent = cfg.texts.noResults;
        box.appendChild(p);
      }
      res.items.forEach((it) => {
        const a = document.createElement('a');
        a.href = it.url;
        const img = it.image ? Object.assign(document.createElement('img'), { src: it.image, alt: '' }) : Object.assign(document.createElement('span'), { className: 'nb-suggest-ph' });
        const name = Object.assign(document.createElement('span'), { className: 'nb-suggest-name', textContent: it.name });
        const price = Object.assign(document.createElement('strong'), { textContent: it.price });
        a.append(img, name, price);
        box.appendChild(a);
      });
      if (res.total > res.items.length) {
        box.appendChild(Object.assign(document.createElement('a'), { href: res.all, className: 'nb-suggest-all', textContent: cfg.texts.seeAll.replace('{n}', res.total) }));
      }
      box.hidden = false;
    };
    const suggest = debounce(async () => {
      const q = input.value.trim();
      if (q.length < 2) { close(); return; }
      const mine = ++seq;
      const res = await api(input.dataset.suggestUrl + '?q=' + encodeURIComponent(q));
      if (mine === seq && res.ok) render(res);
    }, 200);

    input.addEventListener('input', suggest);
    input.addEventListener('keydown', (e) => {
      const links = $$('a', box);
      if (box.hidden || !links.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        active = (active + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
        links.forEach((l, i) => l.classList.toggle('is-active', i === active));
      } else if (e.key === 'Enter' && active >= 0) {
        e.preventDefault();
        location.href = links[active].href;
      } else if (e.key === 'Escape') {
        close();
      }
    });
    document.addEventListener('click', (e) => { if (!search.contains(e.target)) close(); });
  }

  // ------------------------------------------------------------------ listados (orden y paginación sin recargar)

  const listing = $('[data-listing]');
  if (listing) {
    const base = listing.dataset.listingUrl;
    const load = async (params, push = true) => {
      const url = base + (base.includes('?') ? '&' : '?') + new URLSearchParams(params).toString();
      listing.classList.add('is-loading');
      const res = await api(url.replace(/[?&]$/, ''));
      listing.classList.remove('is-loading');
      if (!res.ok) { location.href = url; return; }
      listing.innerHTML = res.html;
      const count = $('[data-listing-count]');
      if (count) count.textContent = res.count;
      document.title = res.title;
      if (push) history.pushState({ listing: params }, '', url.replace(/[?&]$/, ''));
      listing.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    const current = () => Object.fromEntries(new URLSearchParams(location.search));
    $('[data-listing-sort]')?.addEventListener('change', (e) => {
      const p = current();
      delete p.page;
      if (e.target.value === 'featured') delete p.sort; else p.sort = e.target.value;
      load(p);
    });
    listing.addEventListener('click', (e) => {
      const a = e.target.closest('a[data-page]');
      if (!a || e.ctrlKey || e.metaKey) return;
      e.preventDefault();
      const p = current();
      if (a.dataset.page === '1') delete p.page; else p.page = a.dataset.page;
      load(p);
    });
    window.addEventListener('popstate', () => {
      const p = current();
      const sel = $('[data-listing-sort]');
      if (sel) sel.value = p.sort || 'featured';
      load(p, false);
    });
  }

  // ------------------------------------------------------------------ mostrar/ocultar bloques con un checkbox

  const syncToggle = (cb) => {
    const target = $(cb.dataset.toggleTarget);
    if (target) target.hidden = cb.hasAttribute('data-toggle-invert') ? cb.checked : !cb.checked;
  };
  $$('[data-toggle-target]').forEach(syncToggle);
  document.addEventListener('change', (e) => { if (e.target.matches('[data-toggle-target]')) syncToggle(e.target); });

  // ------------------------------------------------------------------ checkout

  const checkout = $('[data-checkout]');
  if (checkout) {
    const shipBox = $('[data-shipping-options]', checkout);
    const payBox = $('[data-payment-options]', checkout);
    const summary = $('[data-checkout-summary]', checkout);
    const sameBox = $('[data-ship-same-box]', checkout);
    let seq = 0;

    const applyQuote = (res) => {
      if (res.shipping_html !== undefined) shipBox.innerHTML = res.shipping_html;
      if (res.payment_html !== undefined) payBox.innerHTML = res.payment_html;
      if (res.summary_html !== undefined) summary.innerHTML = res.summary_html;
      if (res.total !== undefined) $('[data-expected-total]', checkout).value = Number(res.total).toFixed(2);
      if (sameBox) sameBox.hidden = !!res.pickup;
      if (res.pickup) $('#nb-ship-box').hidden = true; else syncToggle($('[name="ship_same"]', checkout));
      if (res.empty) location.reload();
    };
    const quote = debounce(async () => {
      const mine = ++seq;
      [shipBox, payBox, summary].forEach((b) => b.classList.add('is-loading'));
      const res = await api(checkout.dataset.quoteUrl, formData(checkout));
      if (mine !== seq) return;
      [shipBox, payBox, summary].forEach((b) => b.classList.remove('is-loading'));
      if (res.ok) applyQuote(res);
    }, 350);

    checkout.addEventListener('change', (e) => { if (e.target.matches('[data-quote-trigger]')) quote(); });
    checkout.addEventListener('input', (e) => { if (e.target.matches('input[data-quote-trigger][name$="postcode"]')) quote(); });

    // Libreta de direcciones del cliente
    const book = $('[data-address-book]', checkout);
    book?.addEventListener('change', () => {
      const a = JSON.parse(book.dataset.addresses)[book.value] || {};
      const prefix = book.dataset.addressBook;
      ['name', 'address', 'city', 'postcode', 'province', 'country'].forEach((k) => {
        const el = checkout.elements[prefix + k];
        if (el && a[k] !== undefined) el.value = a[k];
      });
      quote();
    });

    checkout.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = $('[data-place-button]', checkout);
      busy(btn, true);
      const res = await api(checkout.dataset.placeUrl, formData(checkout));
      if (res.ok && res.redirect) { location.href = res.redirect; return; }
      busy(btn, false);
      applyQuote(res);
      showErrors(checkout, res.errors || {}, res.error);
      if (!res.errors) toast(res.error, 'error');
    });
  }

  // ------------------------------------------------------------------ formularios de la cuenta

  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('[data-ajax-form]');
    if (!form) return;
    e.preventDefault();
    const btn = $('button[type="submit"]', form);
    busy(btn, true);
    const res = await api(form.action, formData(form));
    if (res.ok && res.redirect) { location.href = res.redirect; return; }
    busy(btn, false);
    showErrors(form, res.errors || {}, res.ok ? null : res.error);
    if (res.ok) {
      toast(res.message, 'success');
      $$('input[type="password"]', form).forEach((i) => { i.value = ''; });
    }
  });

  const addrForm = $('[data-addresses-form]');
  if (addrForm) {
    const list = $('[data-address-list]', addrForm);
    const tpl = $('[data-template]', list);
    $('[data-address-add]', addrForm).addEventListener('click', () => {
      const item = tpl.cloneNode(true);
      item.hidden = false;
      item.disabled = false;
      item.removeAttribute('data-template');
      list.insertBefore(item, tpl);
      $('input', item).focus();
    });
    list.addEventListener('click', (e) => { if (e.target.closest('[data-address-remove]')) e.target.closest('[data-address-item]').remove(); });
    addrForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const addresses = $$('[data-address-item]:not([data-template])', list).map((item) => {
        const a = {};
        $$('input, select', item).forEach((el) => { a[el.name] = el.value; });
        return a;
      });
      const btn = $('button[type="submit"]', addrForm);
      busy(btn, true);
      const res = await api(addrForm.action, { addresses });
      busy(btn, false);
      toast(res.ok ? res.message : res.error, res.ok ? 'success' : 'error');
    });
  }

  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-logout]');
    if (!b) return;
    const res = await api(b.dataset.logout, {});
    location.href = res.redirect || '/';
  });

  // ------------------------------------------------------------------ página del pedido: espera al pago

  const poll = $('[data-order-poll]');
  if (poll) {
    let tries = 0;
    const check = async () => {
      const res = await api(poll.dataset.orderPoll);
      if (res.ok && res.payment_status === 'paid') { location.reload(); return; }
      if (++tries < 20) setTimeout(check, 3000);
    };
    setTimeout(check, 2500);
  }

  // Formulario de la pasarela (Redsys): se envía solo
  const auto = $('[data-autosubmit]');
  if (auto) setTimeout(() => auto.submit(), 400);

  // Menú en móvil
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-toggle="nav"]');
    if (!t) return;
    const nav = $('[data-nav]');
    const open = !nav.classList.contains('is-open');
    nav.classList.toggle('is-open', open);
    t.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();
