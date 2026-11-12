/*!
 * SBA Invento front-end (vanilla JS, no framework, CSP friendly - no inline handlers).
 * - AJAX list engine (search, sort, filters panel, pagination, stats, CSV export)
 * - Right drawer forms with Enter-to-next-field, Ctrl+S save, Esc close
 * - Keyboard shortcuts (Tally / desktop style), "Go to" palette
 * - Journal voucher grid, reports loader, chart-of-accounts tree
 */
(() => {
  'use strict';

  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const meta = (n) => $(`meta[name="${n}"]`)?.content || '';
  const BASE = meta('base-url');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  const isTyping = (el) => el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
  const store = {
    get(k, d) { try { const v = localStorage.getItem('sba:' + k); return v === null ? d : JSON.parse(v); } catch { return d; } },
    set(k, v) { try { localStorage.setItem('sba:' + k, JSON.stringify(v)); } catch { /* private mode */ } },
  };

  // ------------------------------------------------------------------ HTTP
  async function http(url, { method = 'GET', body = null, html = false, signal } = {}) {
    const headers = { 'X-Requested-With': 'XMLHttpRequest', Accept: html ? 'text/html' : 'application/json' };
    if (method !== 'GET') headers['X-CSRF-Token'] = meta('csrf-token');
    const res = await fetch(url, { method, body, headers, credentials: 'same-origin', signal });
    const type = res.headers.get('content-type') || '';
    const data = type.includes('json') ? await res.json().catch(() => ({})) : await res.text();
    if (res.status === 401) { location.href = (data && data.redirect) || BASE + '/login'; throw new Error('Session expired'); }
    if (!res.ok) {
      const err = new Error((data && data.message) || `Request failed (${res.status})`);
      err.data = data; err.status = res.status; throw err;
    }
    return data;
  }

  // ------------------------------------------------------------------ Toasts
  function toast(message, type = 'success') {
    const box = $('[data-toasts]');
    if (!box || !message) return;
    const tones = { success: 'border-emerald-200 bg-white text-emerald-800', error: 'border-rose-200 bg-white text-rose-800', info: 'border-sky-200 bg-white text-sky-800' };
    const dot = { success: 'bg-emerald-500', error: 'bg-rose-500', info: 'bg-sky-500' };
    const el = document.createElement('div');
    el.className = `pointer-events-auto flex items-start gap-2 rounded-lg border px-3 py-2.5 text-sm shadow-lg transition duration-300 ${tones[type] || tones.info}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `<span class="mt-1.5 size-2 shrink-0 rounded-full ${dot[type] || dot.info}"></span><span class="flex-1">${esc(message)}</span>`;
    box.appendChild(el);
    setTimeout(() => { el.classList.add('opacity-0', 'translate-x-4'); setTimeout(() => el.remove(), 300); }, type === 'error' ? 6000 : 3200);
  }

  // ------------------------------------------------------------------ Confirm dialog
  let confirmResolve = null;
  function confirmBox(text, title = 'Please confirm') {
    const box = $('[data-confirm-dialog]');
    if (!box) return Promise.resolve(window.confirm(text));
    $('[data-confirm-text]', box).textContent = text;
    $('[data-confirm-title]', box).textContent = title;
    box.classList.remove('hidden'); box.classList.add('flex');
    setTimeout(() => $('[data-confirm-yes]', box).focus(), 20);
    return new Promise((resolve) => { confirmResolve = resolve; });
  }
  function closeConfirm(result) {
    const box = $('[data-confirm-dialog]');
    box.classList.add('hidden'); box.classList.remove('flex');
    if (confirmResolve) { confirmResolve(result); confirmResolve = null; }
  }
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-confirm-yes]')) closeConfirm(true);
    else if (e.target.closest('[data-confirm-no]') || e.target.matches('[data-confirm-dialog]')) closeConfirm(false);
  });
  const confirmOpen = () => !!confirmResolve;

  // ------------------------------------------------------------------ Dropdowns (top menu, profile)
  function closeDropdowns(except) {
    $$('[data-dropdown]').forEach((d) => {
      if (d === except) return;
      $('[data-dropdown-panel]', d)?.classList.add('hidden');
      $('[data-dropdown-trigger]', d)?.setAttribute('aria-expanded', 'false');
    });
  }
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-dropdown-trigger]');
    if (trigger) {
      const dd = trigger.closest('[data-dropdown]');
      const panel = $('[data-dropdown-panel]', dd);
      const open = panel.classList.contains('hidden');
      closeDropdowns(dd);
      panel.classList.toggle('hidden', !open);
      trigger.setAttribute('aria-expanded', String(open));
      if (open) $('a,button:not([disabled])', panel)?.focus({ preventScroll: true });
      return;
    }
    if (!e.target.closest('[data-dropdown-panel]')) closeDropdowns();
  });
  // menubar feel: when one menu is open, hovering another switches to it
  document.addEventListener('mouseover', (e) => {
    const trigger = e.target.closest('nav [data-dropdown-trigger]');
    if (!trigger) return;
    const anyOpen = $$('nav [data-dropdown-panel]').some((p) => !p.classList.contains('hidden'));
    const dd = trigger.closest('[data-dropdown]');
    if (anyOpen && $('[data-dropdown-panel]', dd).classList.contains('hidden')) {
      closeDropdowns(dd);
      $('[data-dropdown-panel]', dd).classList.remove('hidden');
      trigger.setAttribute('aria-expanded', 'true');
    }
  });

  // ------------------------------------------------------------------ Small global behaviours
  document.addEventListener('click', (e) => {
    const t = e.target;
    const toggle = t.closest('[data-toggle]');
    if (toggle) { $(toggle.dataset.toggle)?.classList.toggle('hidden'); return; }
    if (t.closest('[data-dismiss]')) { t.closest('[data-flash]')?.remove(); return; }
    if (t.closest('[data-history-back]')) { history.length > 1 ? history.back() : (location.href = BASE + '/dashboard'); return; }
    if (t.closest('[data-print]')) { window.print(); return; }
    const pw = t.closest('[data-toggle-password]');
    if (pw) { const i = $(pw.dataset.togglePassword); if (i) i.type = i.type === 'password' ? 'text' : 'password'; return; }
    const row = t.closest('tr[data-href]');
    if (row && !t.closest('a,button,input,select')) { location.href = row.dataset.href; }
  });
  document.addEventListener('input', (e) => {
    const sel = e.target.dataset?.passwordMeter;
    if (!sel) return;
    const v = e.target.value; const bar = $(sel);
    let s = 0; if (v.length >= 8) s++; if (v.length >= 12) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++;
    bar.style.width = `${(s / 5) * 100}%`;
    bar.className = `h-full transition-all ${s <= 2 ? 'bg-rose-500' : s <= 3 ? 'bg-amber-500' : 'bg-emerald-500'}`;
  });
  // login form: capture time zone & machine details for the login log
  const login = $('[data-login-form]');
  if (login) {
    const set = (n, v) => { const i = login.querySelector(`[name="${n}"]`); if (i) i.value = v || ''; };
    try { set('client_tz', Intl.DateTimeFormat().resolvedOptions().timeZone); } catch { /* old browser */ }
    set('client_screen', `${screen.width}x${screen.height}`);
    set('client_platform', (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || '');
    set('client_lang', navigator.language || '');
  }

  // ------------------------------------------------------------------ Enter = next field (desktop accounting habit)
  const focusables = (root) => $$('input:not([type=hidden]):not([disabled]):not([readonly]),select:not([disabled]),textarea:not([disabled]),button[type=submit]:not([disabled])', root)
    .filter((el) => el.offsetParent !== null && el.tabIndex !== -1);
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
    const el = e.target;
    const root = el.closest('[data-enter-nav]');
    if (!root || el.tagName === 'TEXTAREA' || el.tagName === 'BUTTON' || el.type === 'submit') return;
    if (el.closest('[data-no-enter-nav]')) return;
    const form = el.closest('form') || root;
    const list = focusables(form).filter((x) => x.type !== 'submit');
    const idx = list.indexOf(el);
    if (idx === -1) return;
    e.preventDefault();
    const next = e.shiftKey ? list[idx - 1] : list[idx + 1];
    if (next) { next.focus(); if (next.select && next.type !== 'checkbox' && next.tagName === 'INPUT') next.select(); }
    else if (!e.shiftKey) {
      if (form.hasAttribute('data-enter-submit')) form.requestSubmit();
      else ($('[data-save-btn]', form) || $('button[type=submit]', form))?.focus(); // "Accept?" like desktop apps
    }
  });
  // format decimal inputs on blur
  document.addEventListener('focusout', (e) => {
    if (e.target.matches && e.target.matches('[data-decimal]') && e.target.value.trim() !== '') {
      const n = parseFloat(e.target.value.replace(/,/g, ''));
      if (!Number.isNaN(n)) e.target.value = String(n);
    }
  });

  // ------------------------------------------------------------------ Form errors helper
  function showErrors(form, data) {
    clearErrors(form);
    const box = $('[data-form-error]', form);
    if (box) { box.textContent = data?.message || 'Please correct the highlighted fields.'; box.classList.remove('hidden'); }
    let first = null;
    Object.entries(data?.errors || {}).forEach(([field, msg]) => {
      const holder = $(`[data-error-for="${CSS.escape(field)}"]`, form);
      if (holder) { holder.textContent = msg; holder.classList.remove('hidden'); }
      const input = form.querySelector(`[name="${CSS.escape(field)}"]:not([type=hidden])`) || form.querySelector(`[name="${CSS.escape(field)}[]"]`);
      if (input) { input.classList.add('is-invalid'); first ||= input; }
    });
    (first || box)?.focus?.();
    if (first) first.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
  function clearErrors(form) {
    $$('[data-form-error]', form).forEach((b) => b.classList.add('hidden'));
    $$('[data-error-for],[data-line-error]', form).forEach((b) => { b.textContent = ''; b.classList.add('hidden'); });
    $$('.is-invalid', form).forEach((i) => i.classList.remove('is-invalid'));
  }

  // ------------------------------------------------------------------ Drawer
  const Drawer = {
    el: $('[data-drawer]'), backdrop: $('[data-drawer-backdrop]'), body: $('[data-drawer-body]'),
    onSaved: null, dirty: false, url: null, lastFocus: null,
    isOpen() { return this.el && !this.el.hidden; },
    async open(url, { width = 'max-w-2xl', onSaved = null } = {}) {
      if (!this.el) return;
      this.lastFocus = document.activeElement;
      this.url = url; this.onSaved = onSaved; this.dirty = false;
      this.el.className = this.el.className.replace(/max-w-\S+/, '') + ' ' + width;
      this.body.innerHTML = '<div class="p-6 space-y-3"><div class="skeleton h-5 w-1/3"></div><div class="skeleton h-9 w-full"></div><div class="skeleton h-9 w-full"></div><div class="skeleton h-9 w-2/3"></div></div>';
      this.el.hidden = false; this.backdrop.classList.remove('hidden');
      requestAnimationFrame(() => { this.el.classList.add('is-open'); this.backdrop.classList.add('is-open'); });
      try {
        this.body.innerHTML = await http(url, { html: true });
        this.bind();
      } catch (err) {
        this.body.innerHTML = `<div class="p-6"><p class="text-rose-700">${esc(err.message)}</p><button class="btn mt-4" data-drawer-close>Close</button></div>`;
      }
    },
    bind() {
      const form = $('[data-drawer-form]', this.body);
      if (!form) return;
      const first = focusables(form).find((x) => x.type !== 'submit');
      setTimeout(() => { first?.focus(); first?.select?.(); }, 220);
      form.addEventListener('input', () => { this.dirty = true; });
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const after = e.submitter?.value === 'new';
        const btns = $$('button[type=submit]', form); btns.forEach((b) => (b.disabled = true));
        clearErrors(form);
        try {
          const res = await http(form.action, { method: 'POST', body: new FormData(form) });
          toast(res.message || 'Saved.');
          this.dirty = false;
          if (this.onSaved) this.onSaved(res);
          if (after) { const u = this.url.replace(/\/form\/\d+$/, '/form'); this.open(u, { width: this.currentWidth(), onSaved: this.onSaved }); }
          else this.close(true);
        } catch (err) {
          showErrors(form, err.data || { message: err.message });
        } finally { btns.forEach((b) => (b.disabled = false)); }
      });
    },
    currentWidth() { return (this.el.className.match(/max-w-\S+/) || ['max-w-2xl'])[0]; },
    async close(force = false) {
      if (!this.isOpen()) return;
      if (!force && this.dirty && !(await confirmBox('Discard unsaved changes?', 'Unsaved changes'))) return;
      this.el.classList.remove('is-open'); this.backdrop.classList.remove('is-open');
      setTimeout(() => { this.el.hidden = true; this.backdrop.classList.add('hidden'); this.body.innerHTML = ''; }, 200);
      this.lastFocus?.focus?.({ preventScroll: true });
    },
  };
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-drawer-close]') || e.target.matches('[data-drawer-backdrop]')) Drawer.close();
  });

  // ------------------------------------------------------------------ AJAX forms (profile, company settings)
  $$('[data-ajax-form]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (form.dataset.confirmSubmit && !(await confirmBox(form.dataset.confirmSubmit))) return;
      clearErrors(form);
      const body = new FormData(form, e.submitter || undefined); // includes the clicked button's name/value
      const btn = $('button[type=submit]', form); if (btn) btn.disabled = true;
      try {
        const res = await http(form.action, { method: 'POST', body });
        toast(res.message || 'Saved.');
        if (form.hasAttribute('data-reset-on-success')) form.reset();
        if (res.redirect && form.hasAttribute('data-redirect-on-success')) setTimeout(() => { location.href = res.redirect; }, 400);
      } catch (err) { showErrors(form, err.data || { message: err.message }); }
      finally { if (btn) btn.disabled = false; }
    });
  });

  // ------------------------------------------------------------------ Date helpers (filter presets)
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  function presetRange(p, fyStart = 1) {
    const now = new Date(); const y = now.getFullYear(); const m = now.getMonth();
    switch (p) {
      case 'today': return [iso(now), iso(now)];
      case 'month': return [iso(new Date(y, m, 1)), iso(new Date(y, m + 1, 0))];
      case 'last-month': return [iso(new Date(y, m - 1, 1)), iso(new Date(y, m, 0))];
      case 'quarter': { const q = Math.floor(m / 3) * 3; return [iso(new Date(y, q, 1)), iso(new Date(y, q + 3, 0))]; }
      case 'year': return [iso(new Date(y, 0, 1)), iso(new Date(y, 11, 31))];
      case 'fy': case 'last-fy': {
        let sy = m + 1 >= fyStart ? y : y - 1; if (p === 'last-fy') sy -= 1;
        return [iso(new Date(sy, fyStart - 1, 1)), iso(new Date(sy + 1, fyStart - 1, 0))];
      }
      default: return ['', ''];
    }
  }

  // ------------------------------------------------------------------ Data list engine
  class DataList {
    constructor(root) {
      this.root = root;
      this.d = root.dataset;
      this.key = this.d.key;
      this.table = $('[data-table]', root);
      this.tbody = $('[data-rows]', root);
      this.form = $('[data-filter-form]', root);
      this.panel = $('[data-filter-panel]', root);
      this.search = $('[data-search]', root);
      this.selected = -1;
      this.rows = [];
      this.ctrl = null;
      const url = new URLSearchParams(location.search);
      const saved = store.get('list:' + this.key, {});
      this.state = {
        page: parseInt(url.get('page') || '1', 10) || 1,
        per_page: parseInt(url.get('per_page') || saved.per_page || this.d.perPage, 10),
        sort: url.get('sort') || saved.sort || '',
        dir: url.get('dir') || saved.dir || '',
        q: url.get('q') || '',
      };
      if (this.search) this.search.value = this.state.q;
      const pp = $('[data-per-page-select]', root);
      if (pp) { if ([...pp.options].some((o) => +o.value === this.state.per_page)) pp.value = String(this.state.per_page); else this.state.per_page = +pp.value; }
      // restore filters from URL (f[...] params) - panel itself stays hidden by default
      if (this.form) {
        url.forEach((v, k) => {
          if (!k.startsWith('f[')) return;
          $$(`[name="${CSS.escape(k)}"]`, this.form).forEach((el) => { if (el.type === 'checkbox') { if (el.value === v) el.checked = true; } else el.value = v; });
        });
      }
      if (saved.dense) this.table.classList.add('is-dense');
      this.navCollapsed = new Set(store.get('nav:collapsed:' + this.key, []));
      this.bind();
      this.load();
      if (this.d.nav) { if (store.get('nav:hidden:' + this.key, false)) this.toggleNav(false); else this.loadNav(); }
    }

    params(extra = {}) {
      const p = new URLSearchParams();
      Object.entries({ ...this.state, ...extra }).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) p.set(k, v); });
      if (this.form) new FormData(this.form).forEach((v, k) => { if (v !== '') p.append(k, v); });
      return p;
    }

    syncUrl() {
      const p = this.params();
      p.delete('stats');
      if (+p.get('page') === 1) p.delete('page');
      if (+p.get('per_page') === +this.d.perPage) p.delete('per_page');
      const qs = p.toString();
      history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
      store.set('list:' + this.key, { per_page: this.state.per_page, sort: this.state.sort, dir: this.state.dir, dense: this.table.classList.contains('is-dense') });
    }

    async load(force = false) {
      if (this.ctrl) this.ctrl.abort();
      this.ctrl = new AbortController();
      // The overview cards depend only on search + filters: skip them on paging / sorting.
      const params = this.params();
      const statsKey = [...params].filter(([k]) => !['page', 'per_page', 'sort', 'dir'].includes(k)).map((x) => x.join('=')).join('&');
      const needStats = force || statsKey !== this._statsKey;
      params.set('stats', needStats ? '1' : '0');
      this.root.classList.add('is-loading');
      this.tbody.classList.add('opacity-60');
      try {
        const data = await http(this.d.endpoint + '?' + params.toString(), { signal: this.ctrl.signal });
        if (needStats) this._statsKey = statsKey;
        this.render(data);
        this.syncUrl();
      } catch (err) {
        if (err.name === 'AbortError') return;
        this.tbody.innerHTML = `<tr><td colspan="99" class="py-10 text-center text-rose-700">${esc(err.message)} <button type="button" class="btn btn-sm ml-2" data-refresh>Retry</button></td></tr>`;
      } finally {
        this.tbody.classList.remove('opacity-60');
        this.root.classList.remove('is-loading');
      }
    }

    render(data) {
      this.rows = data.rows;
      this.state.page = data.page;
      const headers = $$('thead th', this.table);
      const aligns = headers.map((h) => (h.classList.contains('text-right') ? 'text-right' : ''));
      const keys = Object.keys(data.rows[0]?.cells || {});
      this.tbody.innerHTML = data.rows.map((r, i) => {
        const tds = keys.map((k, ci) => `<td class="${aligns[ci] || ''}">${r.cells[k] ?? ''}</td>`).join('');
        const more = (r.actions.length || r.delete)
          ? `<div class="relative inline-block" data-dropdown><button type="button" class="btn btn-ghost btn-sm" data-dropdown-trigger aria-label="More actions" tabindex="-1">⋯</button>
             <div class="dropdown-panel right-0 hidden w-48 text-left" data-dropdown-panel>
             ${r.actions.map((a) => `<button type="button" class="menu-item ${a.tone === 'rose' ? 'text-rose-600' : ''}" data-act="${esc(a.key)}" data-confirm-text="${esc(a.confirm || '')}">${esc(a.label)}</button>`).join('')}
             ${r.delete ? '<button type="button" class="menu-item text-rose-600" data-act="__delete">Delete</button>' : ''}
             </div></div>` : '';
        const edit = r.url ? `<a class="btn btn-ghost btn-sm" href="${esc(r.url)}" tabindex="-1">Open</a>` : (r.view ? `<button type="button" class="btn btn-ghost btn-sm" data-act="__edit" tabindex="-1">${r.edit ? 'Edit' : 'View'}</button>` : '');
        return `<tr data-index="${i}" class="${r.url || r.view ? 'cursor-pointer' : ''}">${tds}<td class="text-right whitespace-nowrap">${edit}${more}</td></tr>`;
      }).join('');
      const empty = $('[data-empty]', this.root);
      empty.classList.toggle('hidden', data.rows.length > 0); empty.classList.toggle('flex', data.rows.length === 0);
      this.table.classList.toggle('hidden', data.rows.length === 0 && data.total === 0);
      $('[data-summary]', this.root).textContent = data.total ? `Showing ${data.from.toLocaleString()}–${data.to.toLocaleString()} of ${data.total.toLocaleString()}` : 'No records';
      this.renderPager(data);
      if (data.stats) this.renderStats(data.stats);
      if (this.d.nav) this.syncNav();
      this.renderSortIcons();
      this.renderActiveFilters();
      if (this.selected >= data.rows.length) this.selected = data.rows.length - 1;
      if (this.selected >= 0) this.select(this.selected, false);
    }

    renderStats(stats) {
      const box = $('[data-stats]', this.root);
      if (!box) return;
      const tones = { slate: 'bg-slate-100 text-slate-600', cyan: 'bg-cyan-50 text-cyan-700', emerald: 'bg-emerald-50 text-emerald-700', amber: 'bg-amber-50 text-amber-700', rose: 'bg-rose-50 text-rose-700', indigo: 'bg-indigo-50 text-indigo-700', violet: 'bg-violet-50 text-violet-700' };
      box.innerHTML = stats.map((s) => `<div class="card flex items-center gap-3 px-4 py-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg ${tones[s.tone] || tones.slate}">${s.svg || ''}</span>
        <div class="min-w-0"><div class="truncate text-xs font-semibold text-slate-500">${esc(s.label)}</div>
        <div class="truncate text-lg font-extrabold tabular-nums text-slate-900">${esc(s.value)}</div>${s.hint ? `<div class="truncate text-[11px] text-slate-400">${esc(s.hint)}</div>` : ''}</div></div>`).join('');
    }

    renderPager(d) {
      const pager = $('[data-pager]', this.root);
      const btn = (label, page, disabled, current = false, title = '') => `<button type="button" class="btn btn-sm ${current ? 'btn-primary' : ''}" data-page="${page}" ${disabled ? 'disabled' : ''} ${title ? `title="${title}"` : ''} ${current ? 'aria-current="page"' : ''}>${label}</button>`;
      if (d.pages <= 1) { pager.innerHTML = ''; return; }
      const out = [btn('«', 1, d.page === 1, false, 'First'), btn('‹', d.page - 1, d.page === 1, false, 'Previous (Page Up)')];
      const start = Math.max(1, Math.min(d.page - 2, d.pages - 4)); const end = Math.min(d.pages, start + 4);
      for (let p = start; p <= end; p++) out.push(btn(String(p), p, false, p === d.page));
      out.push(btn('›', d.page + 1, d.page === d.pages, false, 'Next (Page Down)'), btn('»', d.pages, d.page === d.pages, false, 'Last'));
      out.push(`<span class="ml-1 hidden items-center gap-1 text-xs sm:flex">Page <input type="number" min="1" max="${d.pages}" value="${d.page}" class="input w-16 py-1 text-xs" data-goto-page aria-label="Go to page"> of ${d.pages}</span>`);
      pager.innerHTML = out.join('');
    }

    renderSortIcons() {
      $$('thead th[data-sort]', this.table).forEach((th) => {
        const icon = $('[data-sort-icon]', th);
        const active = th.dataset.sort === this.state.sort;
        th.setAttribute('aria-sort', active ? (this.state.dir === 'desc' ? 'descending' : 'ascending') : 'none');
        icon.classList.toggle('opacity-0', !active);
        icon.textContent = active ? (this.state.dir === 'desc' ? '↓' : '↑') : '';
      });
    }

    activeFilters() {
      if (!this.form) return [];
      const out = [];
      $$('[name^="f["]', this.form).forEach((el) => {
        if (el.type === 'checkbox' ? !el.checked : el.value === '') return;
        const label = el.dataset.filterLabel || el.name;
        const value = el.type === 'checkbox' ? el.dataset.optionLabel : (el.tagName === 'SELECT' ? el.options[el.selectedIndex].text : el.value);
        out.push({ el, text: `${label}: ${value}` });
      });
      return out;
    }

    renderActiveFilters() {
      const box = $('[data-active-filters]', this.root);
      const list = this.activeFilters();
      const count = $('[data-filter-count]', this.root);
      if (count) { count.textContent = list.length; count.classList.toggle('hidden', list.length === 0); }
      if (!box) return;
      box.innerHTML = list.slice(0, 6).map((f, i) => `<span class="badge badge-cyan gap-1">${esc(f.text)}<button type="button" class="ml-0.5 opacity-60 hover:opacity-100" data-remove-filter="${i}" aria-label="Remove filter">×</button></span>`).join('')
        + (list.length ? '<button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-800" data-clear-filters>Clear all</button>' : '');
      this._filters = list;
    }

    togglePanel(force) {
      if (!this.panel) return;
      const show = force ?? this.panel.classList.contains('hidden');
      this.panel.classList.toggle('hidden', !show);
      const btn = $('[data-filter-toggle]', this.root);
      btn?.setAttribute('aria-expanded', String(show));
      btn?.classList.toggle('is-active', show);
      if (show) setTimeout(() => focusables(this.form)[0]?.focus(), 30);
    }

    select(i, scroll = true) {
      const trs = $$('tr[data-index]', this.tbody);
      trs.forEach((tr) => tr.classList.remove('is-selected'));
      if (i < 0 || !trs[i]) { this.selected = -1; return; }
      this.selected = i;
      trs[i].classList.add('is-selected');
      if (scroll) trs[i].scrollIntoView({ block: 'nearest' });
    }

    openRow(i) {
      const r = this.rows[i];
      if (!r || (!r.url && !r.view)) return;
      if (r.url) { location.href = r.url; return; }
      Drawer.open(`${this.d.form}/${r.id}`, { width: this.d.drawerWidth, onSaved: () => this.reloadAll() });
    }

    newRecord() {
      if (this.d.newUrl) { location.href = this.d.newUrl; return; }
      if (!$('[data-new]', this.root)) return;
      Drawer.open(this.d.form + (this.d.newQuery || ''), { width: this.d.drawerWidth, onSaved: () => this.reloadAll() });
    }

    /** Data changed (save / delete / action): reload rows, overview cards and the hierarchy panel */
    reloadAll() { this.load(true); if (this.d.nav) this.loadNav(); }

    // ---------- hierarchy panel: click a group to list everything under it
    async loadNav() {
      const panel = $('[data-nav-panel]', this.root);
      if (!panel) return;
      try {
        $('[data-nav-body]', panel).innerHTML = await http(this.d.nav, { html: true });
        this.bindNavRows(panel);
        this.syncNav();
      } catch (err) { $('[data-nav-body]', panel).innerHTML = `<p class="p-4 text-sm text-rose-700">${esc(err.message)}</p>`; }
    }

    navSelect(id) {
      const sel = this.form?.querySelector(`[name="f[${this.d.navFilter}]"]`);
      if (!sel) return;
      sel.value = id || '';
      this.state.page = 1;
      this.load();
    }

    syncNav() {
      const panel = $('[data-nav-panel]', this.root);
      if (!panel) return;
      const v = this.form?.querySelector(`[name="f[${this.d.navFilter}]"]`)?.value || '';
      $$('[data-nav-id]', panel).forEach((b) => {
        const on = b.dataset.navId === v;
        b.classList.toggle('bg-cyan-50', on); b.classList.toggle('text-cyan-900', on); b.classList.toggle('font-semibold', on);
        b.setAttribute('aria-current', on ? 'true' : 'false');
        if (on) { // make sure the selected node is visible
          let row = b.closest('[data-nav-row]');
          while (row && row.dataset.parent && row.dataset.parent !== '0') {
            const parent = $(`[data-nav-row][data-id="${row.dataset.parent}"]`, panel);
            if (parent && this.navCollapsed.has(parent.dataset.id)) { this.navCollapsed.delete(parent.dataset.id); }
            row = parent;
          }
        }
      });
      this.applyNav(panel);
    }

    applyNav(panel) {
      const rows = $$('[data-nav-row]', panel);
      const byId = new Map(rows.map((r) => [r.dataset.id, r]));
      rows.forEach((r) => {
        let p = r.dataset.parent; let hidden = false;
        while (p && p !== '0') { if (this.navCollapsed.has(p)) { hidden = true; break; } p = byId.get(p)?.dataset.parent; }
        r.classList.toggle('hidden', hidden);
        const t = $('[data-nav-toggle]', r);
        if (t) { const c = this.navCollapsed.has(r.dataset.id); t.setAttribute('aria-expanded', String(!c)); t.firstElementChild?.classList.toggle('-rotate-90', c); }
      });
      store.set('nav:collapsed:' + this.key, [...this.navCollapsed]);
    }

    bindNavRows(panel) {
      if (panel._bound) return;
      panel._bound = true;
      panel.addEventListener('click', (e) => {
        const tg = e.target.closest('[data-nav-toggle]');
        if (tg) { const id = tg.closest('[data-nav-row]').dataset.id; this.navCollapsed.has(id) ? this.navCollapsed.delete(id) : this.navCollapsed.add(id); this.applyNav(panel); return; }
        const b = e.target.closest('[data-nav-id]');
        if (b) { this.navSelect(b.dataset.navId); return; }
        if (e.target.closest('[data-nav-expand]')) { this.navCollapsed.clear(); this.applyNav(panel); return; }
        if (e.target.closest('[data-nav-collapse]')) { $$('[data-nav-row]', panel).filter((r) => r.dataset.depth !== '0' && $('[data-nav-toggle]', r)).forEach((r) => this.navCollapsed.add(r.dataset.id)); this.applyNav(panel); return; }
        if (e.target.closest('[data-nav-hide]')) { this.toggleNav(false); }
      });
      // Keyboard: Up/Down move, Right/Left expand/collapse, Enter/Space select, Tab goes on to the list
      panel.addEventListener('keydown', (e) => {
        const b = e.target.closest('[data-nav-id]');
        if (!b) return;
        const visible = $$('[data-nav-id]', panel).filter((x) => !x.closest('[data-nav-row].hidden'));
        const i = visible.indexOf(b);
        const row = b.closest('[data-nav-row]');
        if (e.key === 'ArrowDown') { e.preventDefault(); visible[Math.min(visible.length - 1, i + 1)]?.focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); visible[Math.max(0, i - 1)]?.focus(); }
        else if (e.key === 'ArrowRight' && row && $('[data-nav-toggle]', row)) {
          e.preventDefault();
          if (this.navCollapsed.has(row.dataset.id)) { this.navCollapsed.delete(row.dataset.id); this.applyNav(panel); } else visible[i + 1]?.focus();
        } else if (e.key === 'ArrowLeft' && row) {
          e.preventDefault();
          if ($('[data-nav-toggle]', row) && !this.navCollapsed.has(row.dataset.id)) { this.navCollapsed.add(row.dataset.id); this.applyNav(panel); }
          else $(`[data-nav-row][data-id="${row.dataset.parent}"] [data-nav-id]`, panel)?.focus();
        } else if (e.key === 'Enter' && !e.ctrlKey && !e.altKey) {
          // Enter selects the group and moves into the list so ↑ ↓ Enter work there
          e.preventDefault(); this.navSelect(b.dataset.navId); this.select(0, false); b.blur();
        }
      });
    }

    toggleNav(force) {
      const panel = $('[data-nav-panel]', this.root);
      if (!panel) return;
      const show = force ?? panel.classList.contains('hidden');
      panel.classList.toggle('hidden', !show);
      panel.classList.toggle('lg:block', show);
      $('[data-nav-show]', this.root)?.classList.toggle('hidden', show);
      store.set('nav:hidden:' + this.key, !show);
      if (show && !$('[data-nav-row]', panel)) this.loadNav();
    }

    exportCsv() { if (this.d.export) location.href = this.d.export + '?' + this.params({ page: '', per_page: '' }).toString(); }

    async act(i, key, confirmText) {
      const r = this.rows[i];
      if (!r) return;
      if (key === '__edit') return this.openRow(i);
      if (key === '__delete') {
        if (!(await confirmBox('Delete this record? This cannot be undone.', 'Delete'))) return;
        try { const res = await http(`${this.d.delete}/${r.id}`, { method: 'POST' }); toast(res.message); this.reloadAll(); } catch (err) { toast(err.message, 'error'); }
        return;
      }
      if (confirmText && !(await confirmBox(confirmText))) return;
      try { const res = await http(`${this.d.action}/${key}/${r.id}`, { method: 'POST' }); toast(res.message); if (res.redirect) { location.href = res.redirect; return; } this.reloadAll(); } catch (err) { toast(err.message, 'error'); }
    }

    bind() {
      const reload = debounce(() => { this.state.page = 1; this.load(); }, 300);
      this.search?.addEventListener('input', () => { this.state.q = this.search.value.trim(); reload(); });
      this.search?.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { this.search.value = ''; this.state.q = ''; this.load(); this.search.blur(); }
        if (e.key === 'ArrowDown') { e.preventDefault(); this.search.blur(); this.select(0); }
        if (e.key === 'Enter') { e.preventDefault(); this.state.q = this.search.value.trim(); this.state.page = 1; this.load(); }
      });
      $('[data-per-page-select]', this.root)?.addEventListener('change', (e) => { this.state.per_page = +e.target.value; this.state.page = 1; this.load(); });
      if (this.form) {
        this.form.addEventListener('submit', (e) => { e.preventDefault(); this.state.page = 1; this.load(); });
        this.form.addEventListener('change', debounce(() => { this.state.page = 1; this.load(); }, 250));
        $('[data-filter-reset]', this.form)?.addEventListener('click', () => { this.form.reset(); $$('input,select', this.form).forEach((i) => { if (i.type === 'checkbox') i.checked = false; else i.value = ''; }); this.state.page = 1; this.load(); });
        $$('[data-date-presets]', this.form).forEach((box) => box.addEventListener('click', (e) => {
          const b = e.target.closest('[data-preset]'); if (!b) return;
          const [from, to] = presetRange(b.dataset.preset);
          const k = box.dataset.datePresets;
          this.form.querySelector(`[name="f[${k}_from]"]`).value = from;
          this.form.querySelector(`[name="f[${k}_to]"]`).value = to;
          this.state.page = 1; this.load();
        }));
      }
      this.root.addEventListener('click', (e) => {
        const t = e.target;
        if (t.closest('[data-filter-toggle]')) { this.togglePanel(); return; }
        if (t.closest('[data-nav-show]')) { this.toggleNav(true); return; }
        if (t.closest('[data-filter-close]')) { this.togglePanel(false); return; }
        if (t.closest('[data-refresh]')) { this.reloadAll(); return; }
        if (t.closest('[data-new]')) { this.newRecord(); return; }
        if (t.closest('[data-export-btn]')) { this.exportCsv(); return; }
        if (t.closest('[data-density]')) { this.table.classList.toggle('is-dense'); this.syncUrl(); return; }
        if (t.closest('[data-clear-filters]')) { $('[data-filter-reset]', this.form)?.click(); return; }
        const rm = t.closest('[data-remove-filter]');
        if (rm) { const f = this._filters[+rm.dataset.removeFilter]; if (f) { if (f.el.type === 'checkbox') f.el.checked = false; else f.el.value = ''; } this.state.page = 1; this.load(); return; }
        const pg = t.closest('[data-page]');
        if (pg && !pg.disabled) { this.state.page = +pg.dataset.page; this.load(); this.root.scrollIntoView({ block: 'start', behavior: 'smooth' }); return; }
        const th = t.closest('th[data-sort]');
        if (th) { this.sortBy(th.dataset.sort); return; }
        const act = t.closest('[data-act]');
        const tr = t.closest('tr[data-index]');
        if (act && tr) { closeDropdowns(); this.act(+tr.dataset.index, act.dataset.act, act.dataset.confirmText); return; }
        if (tr && !t.closest('a,button,[data-dropdown]')) { this.select(+tr.dataset.index, false); this.openRow(+tr.dataset.index); }
      });
      this.root.addEventListener('keydown', (e) => {
        const th = e.target.closest?.('th[data-sort]');
        if (th && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); this.sortBy(th.dataset.sort); }
        if (e.target.matches?.('[data-goto-page]') && e.key === 'Enter') { e.preventDefault(); const p = Math.max(1, +e.target.value || 1); this.state.page = p; this.load(); }
      });
      // view switch (list / tree) for chart of accounts
      $$('[data-view]', this.root).forEach((b) => b.addEventListener('click', () => this.switchView(b.dataset.view)));
      if (this.d.tree && store.get('view:' + this.key) === 'tree') this.switchView('tree');
    }

    sortBy(key) {
      if (this.state.sort === key) this.state.dir = this.state.dir === 'desc' ? 'asc' : 'desc';
      else { this.state.sort = key; this.state.dir = 'asc'; }
      this.state.page = 1; this.load();
    }

    async switchView(view) {
      $$('[data-view]', this.root).forEach((b) => b.classList.toggle('is-active', b.dataset.view === view));
      const tree = $('[data-tree-panel]', this.root);
      $('[data-list-body]', this.root).classList.toggle('hidden', view === 'tree');
      tree.classList.toggle('hidden', view !== 'tree');
      store.set('view:' + this.key, view);
      if (view === 'tree') await this.loadTree();
    }

    async loadTree() {
      const panel = $('[data-tree-panel]', this.root);
      try { panel.innerHTML = await http(this.d.tree, { html: true }); bindTree(panel, this); } catch (err) { panel.innerHTML = `<p class="p-6 text-rose-700">${esc(err.message)}</p>`; }
    }

    handleKey(e) {
      if (isTyping(e.target) || Drawer.isOpen() || e.target.closest?.('[data-nav-panel]')) return false;
      const n = this.rows.length;
      if (e.key === 'ArrowDown') { e.preventDefault(); this.select(Math.min(n - 1, this.selected + 1)); return true; }
      if (e.key === 'ArrowUp') { e.preventDefault(); if (this.selected <= 0) { this.select(-1); this.search?.focus(); } else this.select(this.selected - 1); return true; }
      if (e.key === 'Enter' && this.selected >= 0) { e.preventDefault(); this.openRow(this.selected); return true; }
      if (e.key === 'PageDown') { e.preventDefault(); $('[data-page]:not([disabled])[title^="Next"]', this.root)?.click(); return true; }
      if (e.key === 'PageUp') { e.preventDefault(); $('[data-page]:not([disabled])[title^="Previous"]', this.root)?.click(); return true; }
      if (e.key === 'Home' && n) { e.preventDefault(); this.select(0); return true; }
      if (e.key === 'End' && n) { e.preventDefault(); this.select(n - 1); return true; }
      if (e.key === '/') { e.preventDefault(); this.search?.focus(); this.search?.select(); return true; }
      return false;
    }
  }

  // Chart of accounts tree interactions
  function bindTree(panel, list) {
    const rows = $$('[data-tree-row]', panel);
    const byId = new Map(rows.map((r) => [r.dataset.id, r]));
    const collapsed = new Set(store.get('tree:collapsed:' + list.key, []));
    const hiddenByParent = (r) => { let p = r.dataset.parent; while (p && p !== '0') { if (collapsed.has(p)) return true; p = byId.get(p)?.dataset.parent; } return false; };
    const apply = () => {
      const q = ($('[data-tree-search]', panel)?.value || '').trim().toLowerCase();
      if (q) {
        const show = new Set();
        rows.forEach((r) => { if (r.dataset.text.includes(q)) { let x = r; while (x) { show.add(x.dataset.id); x = byId.get(x.dataset.parent); } } });
        rows.forEach((r) => r.classList.toggle('hidden', !show.has(r.dataset.id)));
      } else rows.forEach((r) => r.classList.toggle('hidden', hiddenByParent(r)));
      rows.forEach((r) => { const b = $('[data-tree-toggle]', r); if (b) { b.setAttribute('aria-expanded', String(!collapsed.has(r.dataset.id))); b.firstElementChild?.classList.toggle('-rotate-90', collapsed.has(r.dataset.id)); } });
      store.set('tree:collapsed:' + list.key, [...collapsed]);
    };
    panel.addEventListener('click', (e) => {
      const tg = e.target.closest('[data-tree-toggle]');
      if (tg) { const id = tg.closest('[data-tree-row]').dataset.id; collapsed.has(id) ? collapsed.delete(id) : collapsed.add(id); apply(); return; }
      if (e.target.closest('[data-tree-expand]')) { collapsed.clear(); apply(); return; }
      if (e.target.closest('[data-tree-collapse]')) { rows.filter((r) => r.dataset.group === '1' && r.dataset.depth !== '0').forEach((r) => collapsed.add(r.dataset.id)); apply(); return; }
      const add = e.target.closest('[data-tree-add]');
      if (add) { Drawer.open(`${list.d.form}?parent_id=${add.dataset.treeAdd}`, { width: list.d.drawerWidth, onSaved: () => list.loadTree() }); return; }
      const ed = e.target.closest('[data-tree-edit]');
      if (ed) { Drawer.open(`${list.d.form}/${ed.dataset.treeEdit}`, { width: list.d.drawerWidth, onSaved: () => list.loadTree() }); }
    });
    $('[data-tree-search]', panel)?.addEventListener('input', debounce(apply, 150));
    apply();
  }

  const lists = $$('[data-list]').map((el) => new DataList(el));

  // ------------------------------------------------------------------ Row actions on detail pages (journal post / void)
  $$('[data-journal-actions]').forEach((box) => box.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-row-action]');
    if (!b) return;
    if (b.dataset.confirm && !(await confirmBox(b.dataset.confirm))) return;
    try { const res = await http(`${box.dataset.actionUrl}/${b.dataset.rowAction}/${b.dataset.id}`, { method: 'POST' }); toast(res.message); setTimeout(() => { if (res.redirect) location.href = res.redirect; else location.reload(); }, 500); }
    catch (err) { toast(err.message, 'error'); }
  }));

  // ------------------------------------------------------------------ Reports
  $$('[data-report]').forEach((root) => {
    const form = $('[data-report-form]', root);
    const body = $('[data-report-body]', root);
    const params = () => new URLSearchParams(new FormData(form));
    const run = async () => {
      body.classList.add('opacity-60');
      try {
        body.innerHTML = await http(root.dataset.endpoint + '?' + params().toString(), { html: true });
        history.replaceState(null, '', location.pathname + '?' + params().toString());
      } catch (err) { body.innerHTML = `<p class="p-6 text-rose-700">${esc(err.message)}</p>`; }
      finally { body.classList.remove('opacity-60'); }
    };
    form.addEventListener('submit', (e) => { e.preventDefault(); run(); });
    form.addEventListener('change', debounce(run, 200));
    root.addEventListener('click', (e) => {
      const p = e.target.closest('[data-report-preset]');
      if (p) {
        const [f, t] = presetRange(p.dataset.reportPreset, +p.dataset.fyStart || 1);
        if (form.from) form.from.value = f; form.to.value = t; run(); return;
      }
      if (e.target.closest('[data-report-csv]')) { const q = params(); q.set('format', 'csv'); location.href = root.dataset.endpoint + '?' + q.toString(); return; }
      if (e.target.closest('[data-filter-toggle]')) {
        const panel = $('[data-filter-panel]', root); panel.classList.toggle('hidden');
        e.target.closest('[data-filter-toggle]').classList.toggle('is-active', !panel.classList.contains('hidden'));
      }
    });
    run();
  });

  // ------------------------------------------------------------------ Journal voucher entry
  const journal = $('[data-journal]');
  if (journal) initJournal(journal);

  function initJournal(form) {
    const cfg = JSON.parse($('[data-journal-config]', form).textContent);
    const tbody = $('[data-lines]', form);
    const tpl = $('[data-line-template]');
    const curSel = $('[data-currency]', form);
    const rateIn = $('[data-rate]', form);
    let dirty = false;
    const searchCache = new Map();
    const cur = () => cfg.currencies.find((c) => c.code === curSel.value) || { decimals: 2, rate: 1, base: true };
    const num = (v) => { const n = parseFloat(String(v || '').replace(/,/g, '')); return Number.isFinite(n) ? n : 0; };
    const fmt = (n) => n.toLocaleString(undefined, { minimumFractionDigits: cur().decimals, maximumFractionDigits: cur().decimals });
    const lines = () => $$('tr[data-line]', tbody);
    const field = (tr, f) => $(`[data-f="${f}"]`, tr);

    function addLine(data = {}, focus = false) {
      const tr = tpl.content.firstElementChild.cloneNode(true);
      tbody.appendChild(tr);
      field(tr, 'account_id').value = data.account_id || '';
      $('[data-account-input]', tr).value = data.account_label || '';
      $('[data-account-input]', tr).dataset.label = data.account_label || '';
      field(tr, 'description').value = data.description || '';
      field(tr, 'debit').value = data.debit ? data.debit : '';
      field(tr, 'credit').value = data.credit ? data.credit : '';
      renumber(); recalc();
      if (focus) $('[data-account-input]', tr).focus();
      return tr;
    }
    function renumber() { lines().forEach((tr, i) => { $('[data-line-no]', tr).textContent = i + 1; }); }
    function totals() {
      let dr = 0, cr = 0;
      lines().forEach((tr) => { dr += num(field(tr, 'debit').value); cr += num(field(tr, 'credit').value); });
      const d = cur().decimals; const r = (x) => Math.round(x * 10 ** d) / 10 ** d;
      return { dr: r(dr), cr: r(cr), diff: r(dr - cr) };
    }
    function recalc() {
      const t = totals();
      $('[data-total-dr]', form).textContent = fmt(t.dr);
      $('[data-total-cr]', form).textContent = fmt(t.cr);
      const diff = $('[data-diff]', form);
      if (t.dr === 0 && t.cr === 0) diff.innerHTML = '<span class="text-slate-400">Enter debit and credit lines</span>';
      else if (t.diff === 0) diff.innerHTML = '<span class="font-semibold text-emerald-700">✓ Balanced</span>';
      else diff.innerHTML = `<span class="font-semibold text-rose-600">Difference ${fmt(Math.abs(t.diff))} ${t.diff > 0 ? 'Dr' : 'Cr'}</span> <span class="kbd">Alt B</span>`;
      const c = cur(); const rate = c.base ? 1 : num(rateIn.value);
      $('[data-base-note]', form).textContent = c.base ? '' : `≈ ${(t.dr * rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${cfg.base} at rate ${rate}`;
    }
    function balanceInto(tr) {
      const t = totals();
      if (t.diff === 0) return;
      const dr = num(field(tr, 'debit').value), cr = num(field(tr, 'credit').value);
      const net = dr - cr - t.diff; // what this line must be so the voucher balances
      field(tr, 'debit').value = net > 0 ? String(net) : '';
      field(tr, 'credit').value = net < 0 ? String(-net) : '';
      recalc();
    }
    function nextLine(tr) {
      const next = tr.nextElementSibling || addLine({});
      $('[data-account-input]', next).focus();
    }
    // Suggest the balancing amount on a line that has no amount yet (Tally / Zoho behaviour)
    function suggest(tr) {
      if (num(field(tr, 'debit').value) || num(field(tr, 'credit').value)) return;
      const t = totals();
      if (t.diff > 0) field(tr, 'credit').value = t.diff.toFixed(cur().decimals);
      else if (t.diff < 0) field(tr, 'debit').value = (-t.diff).toFixed(cur().decimals);
      recalc();
    }

    // ----- account combobox
    let active = { tr: null, items: [], index: -1 };
    const listOf = (tr) => $('[data-account-list]', tr);
    function closeList(tr) { const l = listOf(tr); l.classList.add('hidden'); $('[data-account-input]', tr).setAttribute('aria-expanded', 'false'); active = { tr: null, items: [], index: -1 }; }
    function renderList(tr, items) {
      const l = listOf(tr);
      active = { tr, items, index: items.length ? 0 : -1 };
      l.innerHTML = items.length ? items.map((a, i) => `<li role="option" data-i="${i}" class="cursor-pointer px-3 py-1.5 text-sm ${i === 0 ? 'bg-cyan-50' : ''} hover:bg-slate-100"><span class="font-mono text-xs text-slate-500">${esc(a.code)}</span> <span class="font-medium text-slate-900">${esc(a.name)}</span> <span class="text-xs text-slate-400">${esc(a.parent || '')}</span></li>`).join('')
        : '<li class="px-3 py-2 text-sm text-slate-500">No ledger found</li>';
      l.classList.remove('hidden'); $('[data-account-input]', tr).setAttribute('aria-expanded', 'true');
    }
    function highlight() { $$('li[data-i]', listOf(active.tr)).forEach((li, i) => { li.classList.toggle('bg-cyan-50', i === active.index); if (i === active.index) li.scrollIntoView({ block: 'nearest' }); }); }
    function choose(tr, a) {
      const input = $('[data-account-input]', tr);
      field(tr, 'account_id').value = a.id;
      input.value = input.dataset.label = `${a.code} · ${a.name}`;
      input.classList.remove('is-invalid');
      closeList(tr); dirty = true;
      suggest(tr);
      field(tr, 'description').focus();
    }
    const doSearch = debounce(async (tr, q) => {
      try {
        if (!searchCache.has(q)) searchCache.set(q, (await http(cfg.searchUrl + '?q=' + encodeURIComponent(q))).items);
        if (document.activeElement === $('[data-account-input]', tr)) renderList(tr, searchCache.get(q));
      } catch (err) { toast(err.message, 'error'); }
    }, 120);

    tbody.addEventListener('input', (e) => {
      dirty = true;
      const tr = e.target.closest('tr[data-line]');
      if (e.target.matches('[data-account-input]')) {
        if (e.target.value !== e.target.dataset.label) field(tr, 'account_id').value = '';
        doSearch(tr, e.target.value.trim());
      } else if (e.target.matches('[data-f="debit"]') && e.target.value !== '') { field(tr, 'credit').value = ''; recalc(); }
      else if (e.target.matches('[data-f="credit"]') && e.target.value !== '') { field(tr, 'debit').value = ''; recalc(); }
      else recalc();
    });
    tbody.addEventListener('focusin', (e) => { if (e.target.matches('[data-account-input]')) { e.target.select(); doSearch(e.target.closest('tr'), e.target.value === e.target.dataset.label ? '' : e.target.value.trim()); } });
    tbody.addEventListener('focusout', (e) => {
      const tr = e.target.closest('tr[data-line]');
      if (e.target.matches('[data-account-input]')) setTimeout(() => { if (!tr.contains(document.activeElement)) closeList(tr); }, 150);
      if (e.target.matches('[data-f="debit"],[data-f="credit"]') && e.target.value.trim() !== '') { const n = num(e.target.value); e.target.value = n ? n.toFixed(cur().decimals) : ''; recalc(); }
    });
    tbody.addEventListener('mousedown', (e) => { const li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); choose(active.tr, active.items[+li.dataset.i]); } });
    tbody.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-remove-line]');
      if (rm) { const tr = rm.closest('tr'); if (lines().length > 2) tr.remove(); else { $$('input', tr).forEach((i) => (i.value = '')); } renumber(); recalc(); dirty = true; }
    });
    tbody.addEventListener('keydown', (e) => {
      const tr = e.target.closest('tr[data-line]');
      if (!tr) return;
      const isAcc = e.target.matches('[data-account-input]');
      const listOpen = active.tr === tr && !listOf(tr).classList.contains('hidden');
      if (isAcc && listOpen && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
        e.preventDefault(); active.index = Math.max(0, Math.min(active.items.length - 1, active.index + (e.key === 'ArrowDown' ? 1 : -1))); highlight(); return;
      }
      if (isAcc && e.key === 'Escape' && listOpen) { e.preventDefault(); e.stopPropagation(); closeList(tr); return; }
      if (e.key === 'Delete' && e.ctrlKey) { e.preventDefault(); const prev = tr.previousElementSibling || tr.nextElementSibling; if (lines().length > 2) { tr.remove(); renumber(); recalc(); $('[data-account-input]', prev)?.focus(); } return; }
      if (e.key !== 'Enter' || e.ctrlKey || e.altKey) return;
      e.preventDefault();
      if (isAcc) {
        if (listOpen && active.index >= 0 && active.items[active.index] && e.target.value.trim() !== '') return choose(tr, active.items[active.index]);
        if (field(tr, 'account_id').value) return field(tr, 'description').focus();
        // blank account on a new line ends the entry (Tally style)
        if (e.target.value.trim() === '') {
          const filled = lines().filter((l) => field(l, 'account_id').value);
          if (filled.length >= 2) {
            lines().forEach((l) => { if (!field(l, 'account_id').value && lines().length > 2) l.remove(); });
            renumber(); recalc();
            ($('[data-submit="post"]:not([data-after-new])', form) || $('#narration', form)).focus(); // "Accept?"
            return;
          }
        }
        return;
      }
      if (e.target.matches('[data-f="description"]')) return field(tr, 'debit').focus();
      if (e.target.matches('[data-f="debit"]')) return num(e.target.value) ? nextLine(tr) : field(tr, 'credit').focus();
      if (e.target.matches('[data-f="credit"]')) return nextLine(tr);
    });

    // header behaviour
    function onCurrency() {
      const c = cur();
      $('[data-rate-wrap]', form).classList.toggle('hidden', c.base);
      if (!c.base && (!rateIn.value || num(rateIn.value) === 1)) rateIn.value = c.rate;
      if (c.base) rateIn.value = 1;
      $$('[data-cur-label]', form).forEach((s) => (s.textContent = c.code));
      recalc();
    }
    curSel.addEventListener('change', () => { onCurrency(); dirty = true; });
    rateIn.addEventListener('input', recalc);
    $('[data-add-line]', form).addEventListener('click', () => addLine({}, true));
    // Enter on narration -> first line; header Enter navigation handled by data-enter-nav
    $('#narration', form).addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); const empty = lines().find((l) => !field(l, 'account_id').value); (empty ? $('[data-account-input]', empty) : $('[data-submit="post"]:not([data-after-new])', form)).focus(); } });

    async function submit(action, after = '') {
      $('[data-submit-action]', form).value = action;
      $('[data-after]', form).value = after;
      clearErrors(form);
      const fd = new FormData();
      new FormData(form).forEach((v, k) => fd.append(k, v));
      lines().forEach((tr, i) => ['account_id', 'description', 'debit', 'credit'].forEach((f) => fd.append(`lines[${i}][${f}]`, field(tr, f).value)));
      const btns = $$('button[type=submit]', form); btns.forEach((b) => (b.disabled = true));
      try {
        const res = await http(form.action, { method: 'POST', body: fd });
        dirty = false; toast(res.message);
        setTimeout(() => { location.href = res.redirect; }, 350);
      } catch (err) {
        const data = err.data || { message: err.message };
        showErrors(form, data);
        Object.entries(data.errors || {}).forEach(([k, msg]) => {
          const m = k.match(/^lines\.(\d+)\.(\w+)$/); if (!m) return;
          const tr = lines()[+m[1]]; if (!tr) return;
          const input = m[2] === 'account_id' ? $('[data-account-input]', tr) : field(tr, m[2]);
          input?.classList.add('is-invalid');
          const holder = $(`[data-line-error="${m[2]}"]`, tr); if (holder) { holder.textContent = msg; holder.classList.remove('hidden'); }
        });
        if (data.errors?.lines) { const h = $('[data-error-for="lines"]', form); h.textContent = data.errors.lines; h.classList.remove('hidden'); }
      } finally { btns.forEach((b) => (b.disabled = false)); }
    }
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const b = e.submitter;
      submit(b?.dataset.submit || 'draft', b?.hasAttribute('data-after-new') ? 'new' : '');
    });
    form.addEventListener('input', () => { dirty = true; });
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    form.journalApi = {
      save: () => submit('draft'), post: () => submit('post'), add: () => addLine({}, true),
      balance: () => { const tr = document.activeElement?.closest?.('tr[data-line]') || lines().at(-1); balanceInto(tr); },
    };

    (cfg.lines.length ? cfg.lines : [{}, {}]).forEach((l) => addLine(l));
    if (cfg.lines.length === 1) addLine();
    onCurrency();
    dirty = false;
  }

  // ------------------------------------------------------------------ Online shop behaviours
  $$('form[data-autosubmit]').forEach((f) => f.addEventListener('change', (e) => { if (e.target.type !== 'number') f.requestSubmit(); }));
  $$('[data-width]').forEach((el) => { el.style.width = Math.max(0, Math.min(100, +el.dataset.width)) + '%'; });
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-gallery-thumb]');
    if (!t) return;
    const g = t.closest('[data-gallery]');
    $('[data-gallery-main]', g).src = t.dataset.galleryThumb;
    $$('[data-gallery-thumb]', g).forEach((b) => { b.classList.toggle('border-cyan-600', b === t); b.classList.toggle('border-slate-200', b !== t); });
  });
  document.addEventListener('submit', async (e) => {
    const f = e.target.closest('form[data-confirm-submit]:not([data-ajax-form])');
    if (!f || f.dataset.confirmed) return;
    e.preventDefault();
    if (await confirmBox(f.dataset.confirmSubmit)) { f.dataset.confirmed = '1'; f.requestSubmit(); }
  });

  // ------------------------------------------------------------------ Receive goods: live landed-cost preview
  $$('[data-receive]').forEach((form) => {
    const calc = () => {
      const rows = $$('[data-r-line]', form);
      const num = (v) => { const n = parseFloat(String(v || '').replace(/,/g, '')); return Number.isFinite(n) ? n : 0; };
      const freight = num($('[data-r-freight]', form).value);
      const value = rows.reduce((t, r) => t + num($('[data-r-qty]', r).value) * +r.dataset.net, 0);
      rows.forEach((r) => {
        const q = num($('[data-r-qty]', r).value); const net = +r.dataset.net;
        const share = value > 0 && q > 0 ? (q * net) / value * freight / q : 0;
        $('[data-r-cost]', r).textContent = (net + share).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      });
    };
    form.addEventListener('input', calc); calc();
  });

  // ------------------------------------------------------------------ Quote / invoice / purchase order entry grid
  const docForm = $('[data-document]');
  if (docForm) initDocument(docForm);

  function initDocument(form) {
    const cfg = JSON.parse($('[data-document-config]', form).textContent);
    const tbody = $('[data-lines]', form);
    const tpl = $('[data-line-template]');
    const curSel = $('[data-currency]', form);
    const rateIn = $('[data-rate]', form);
    const freightIn = $('[data-freight]', form);
    let dirty = false;
    const cache = new Map();
    const cur = () => cfg.currencies.find((c) => c.code === curSel.value) || { decimals: 2, rate: 1, base: true };
    const num = (v) => { const n = parseFloat(String(v ?? '').replace(/,/g, '')); return Number.isFinite(n) ? n : 0; };
    const round = (x) => { const d = 10 ** cur().decimals; return Math.round(x * d) / d; };
    const fmt = (n) => n.toLocaleString(undefined, { minimumFractionDigits: cur().decimals, maximumFractionDigits: cur().decimals });
    const lines = () => $$('tr[data-line]', tbody);
    const f = (tr, k) => $(`[data-f="${k}"]`, tr);
    const taxOptions = '<option value="">No tax</option>' + cfg.taxes.map((t) => `<option value="${t.id}">${esc(t.name)}</option>`).join('');

    function addLine(d = {}, focus = false) {
      const tr = tpl.content.firstElementChild.cloneNode(true);
      tbody.appendChild(tr);
      f(tr, 'tax_rate_id').innerHTML = taxOptions;
      f(tr, 'item_id').value = d.item_id || '';
      const inp = $('[data-item-input]', tr); inp.value = inp.dataset.label = d.item_label || '';
      f(tr, 'description').value = d.description || '';
      f(tr, 'qty').value = d.qty ?? '';
      f(tr, 'unit_price').value = d.unit_price ?? '';
      f(tr, 'discount_pct').value = d.discount_pct ? d.discount_pct : '';
      f(tr, 'tax_rate_id').value = d.tax_rate_id ?? (d.item_id === undefined ? (cfg.defaultTax || '') : '');
      renumber(); recalc();
      if (focus) inp.focus();
      return tr;
    }
    function renumber() { lines().forEach((tr, i) => { $('[data-line-no]', tr).textContent = i + 1; }); }
    function recalc() {
      let sub = 0, disc = 0, tax = 0;
      lines().forEach((tr) => {
        const gross = round(num(f(tr, 'qty').value) * num(f(tr, 'unit_price').value));
        const net = round(gross * (1 - Math.min(100, num(f(tr, 'discount_pct').value)) / 100));
        const t = cfg.taxes.find((x) => String(x.id) === f(tr, 'tax_rate_id').value);
        const ta = round(net * (t ? t.rate : 0) / 100);
        $('[data-amount]', tr).textContent = fmt(net);
        sub += net; disc += gross - net; tax += ta;
      });
      const fr = round(num(freightIn.value));
      const total = round(sub + tax + fr);
      $('[data-t-sub]', form).textContent = fmt(sub);
      $('[data-t-disc]', form).textContent = fmt(disc);
      $('[data-t-tax]', form).textContent = fmt(tax);
      $('[data-t-total]', form).textContent = fmt(total);
      const c = cur(); const rate = c.base ? 1 : num(rateIn.value);
      $('[data-base-note]', form).textContent = c.base ? '' : `≈ ${(total * rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${cfg.base}`;
    }

    // item combobox
    let active = { tr: null, items: [], index: -1 };
    const listOf = (tr) => $('[data-item-list]', tr);
    const closeList = (tr) => { listOf(tr).classList.add('hidden'); active = { tr: null, items: [], index: -1 }; };
    function renderList(tr, items) {
      active = { tr, items, index: items.length ? 0 : -1 };
      listOf(tr).innerHTML = items.length ? items.map((it, i) => `<li data-i="${i}" class="cursor-pointer px-3 py-1.5 text-sm ${i === 0 ? 'bg-cyan-50' : ''} hover:bg-slate-100"><span class="font-mono text-xs text-slate-500">${esc(it.sku)}</span> <span class="font-medium text-slate-900">${esc(it.name)}</span>
        <span class="float-right text-xs text-slate-500">${it.on_hand === null ? 'service' : 'stock ' + (+it.on_hand)}${' · ' + (+(cfg.kind === 'sale' ? it.sales_price : it.purchase_price)).toFixed(2)}</span></li>`).join('') : '<li class="px-3 py-2 text-sm text-slate-500">No item found - type a description instead</li>';
      listOf(tr).classList.remove('hidden');
    }
    const highlight = () => $$('li[data-i]', listOf(active.tr)).forEach((li, i) => { li.classList.toggle('bg-cyan-50', i === active.index); if (i === active.index) li.scrollIntoView({ block: 'nearest' }); });
    function choose(tr, it) {
      const inp = $('[data-item-input]', tr);
      f(tr, 'item_id').value = it.id;
      inp.value = inp.dataset.label = `${it.sku} · ${it.name}`;
      inp.classList.remove('is-invalid');
      if (!f(tr, 'qty').value) f(tr, 'qty').value = 1;
      f(tr, 'unit_price').value = +(cfg.kind === 'sale' ? it.sales_price : it.purchase_price) || '';
      f(tr, 'tax_rate_id').value = it.tax_rate_id || '';
      closeList(tr); recalc(); dirty = true;
      f(tr, 'description').focus();
    }
    const search = debounce(async (tr, q) => {
      try {
        if (!cache.has(q)) cache.set(q, (await http(cfg.searchUrl + '?q=' + encodeURIComponent(q))).items);
        if (document.activeElement === $('[data-item-input]', tr)) renderList(tr, cache.get(q));
      } catch (err) { toast(err.message, 'error'); }
    }, 120);

    tbody.addEventListener('input', (e) => {
      dirty = true;
      const tr = e.target.closest('tr[data-line]');
      if (e.target.matches('[data-item-input]')) {
        if (e.target.value !== e.target.dataset.label) f(tr, 'item_id').value = '';
        search(tr, e.target.value.trim());
      } else recalc();
    });
    tbody.addEventListener('change', recalc);
    tbody.addEventListener('focusin', (e) => { if (e.target.matches('[data-item-input]')) { e.target.select(); search(e.target.closest('tr'), e.target.value === e.target.dataset.label ? '' : e.target.value.trim()); } });
    tbody.addEventListener('focusout', (e) => {
      const tr = e.target.closest('tr[data-line]');
      if (e.target.matches('[data-item-input]')) setTimeout(() => { if (!tr.contains(document.activeElement)) closeList(tr); }, 150);
    });
    tbody.addEventListener('mousedown', (e) => { const li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); choose(active.tr, active.items[+li.dataset.i]); } });
    tbody.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-remove-line]');
      if (rm) { const tr = rm.closest('tr'); if (lines().length > 1) tr.remove(); else $$('input', tr).forEach((i) => (i.value = '')); renumber(); recalc(); dirty = true; }
    });
    const order = ['item', 'description', 'qty', 'unit_price', 'discount_pct', 'tax_rate_id'];
    tbody.addEventListener('keydown', (e) => {
      const tr = e.target.closest('tr[data-line]');
      if (!tr) return;
      const isItem = e.target.matches('[data-item-input]');
      const listOpen = active.tr === tr && !listOf(tr).classList.contains('hidden');
      if (isItem && listOpen && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); active.index = Math.max(0, Math.min(active.items.length - 1, active.index + (e.key === 'ArrowDown' ? 1 : -1))); highlight(); return; }
      if (isItem && e.key === 'Escape' && listOpen) { e.preventDefault(); e.stopPropagation(); closeList(tr); return; }
      if (e.key === 'Delete' && e.ctrlKey) { e.preventDefault(); const p = tr.previousElementSibling || tr.nextElementSibling; if (lines().length > 1) { tr.remove(); renumber(); recalc(); $('[data-item-input]', p)?.focus(); } return; }
      if (e.key !== 'Enter' || e.ctrlKey || e.altKey) return;
      e.preventDefault();
      if (isItem) {
        if (listOpen && active.index >= 0 && active.items[active.index] && e.target.value.trim() !== '' && e.target.value !== e.target.dataset.label) return choose(tr, active.items[active.index]);
        if (f(tr, 'item_id').value || e.target.value.trim() !== '') { closeList(tr); return f(tr, 'description').focus(); }
        // blank item ends the entry
        if (lines().filter((l) => f(l, 'item_id').value || f(l, 'description').value).length) { if (lines().length > 1) { tr.remove(); renumber(); recalc(); } freightIn.focus(); }
        return;
      }
      const k = e.target.dataset.f;
      const i = order.indexOf(k);
      if (i >= 0 && i < order.length - 1) { const next = f(tr, order[i + 1]); next.focus(); next.select?.(); return; }
      if (k === 'tax_rate_id') { const next = tr.nextElementSibling || addLine({}); $('[data-item-input]', next).focus(); }
    });

    // header
    const contactSel = $('[data-contact]', form);
    contactSel.addEventListener('change', () => {
      const c = cfg.contacts.find((x) => String(x.id) === contactSel.value);
      if (!c) return;
      if (c.currency && cfg.currencies.some((x) => x.code === c.currency)) { curSel.value = c.currency; onCurrency(); }
      const pos = $('[data-pos]', form); if (pos && c.pos) pos.value = c.pos;
      const due = $('[data-due]', form);
      if (due && !due.value && cfg.type === 'invoice') { const d = new Date($('#doc_date', form).value || Date.now()); d.setDate(d.getDate() + (c.terms || 0)); due.value = iso(d); }
      dirty = true;
    });
    function onCurrency() {
      const c = cur();
      $('[data-rate-wrap]', form).classList.toggle('hidden', c.base);
      if (!c.base && (!rateIn.value || num(rateIn.value) === 1)) rateIn.value = c.rate;
      if (c.base) rateIn.value = 1;
      $$('[data-cur-label]', form).forEach((x) => (x.textContent = c.code));
      recalc();
    }
    curSel.addEventListener('change', () => { onCurrency(); dirty = true; });
    rateIn.addEventListener('input', recalc);
    freightIn.addEventListener('input', recalc);
    $('[data-add-line]', form).addEventListener('click', () => addLine({}, true));

    async function submit(action, after = '') {
      $('[data-submit-action]', form).value = action;
      $('[data-after]', form).value = after;
      clearErrors(form);
      const fd = new FormData();
      new FormData(form).forEach((v, k) => fd.append(k, v));
      lines().forEach((tr, i) => ['item_id', 'description', 'qty', 'unit_price', 'discount_pct', 'tax_rate_id'].forEach((k) => fd.append(`lines[${i}][${k}]`, f(tr, k).value)));
      const btns = $$('button[type=submit]', form); btns.forEach((b) => (b.disabled = true));
      try {
        const res = await http(form.action, { method: 'POST', body: fd });
        dirty = false; toast(res.message);
        setTimeout(() => { location.href = res.redirect; }, 350);
      } catch (err) {
        const data = err.data || { message: err.message };
        showErrors(form, data);
        Object.entries(data.errors || {}).forEach(([k, msg]) => {
          const m = k.match(/^lines\.(\d+)\.(\w+)$/); if (!m) return;
          const tr = lines()[+m[1]]; if (!tr) return;
          (m[2] === 'item_id' ? $('[data-item-input]', tr) : f(tr, m[2]))?.classList.add('is-invalid');
          const h = $(`[data-line-error="${m[2]}"]`, tr); if (h) { h.textContent = msg; h.classList.remove('hidden'); }
        });
        if (data.errors?.lines) { const h = $('[data-error-for="lines"]', form); h.textContent = data.errors.lines; h.classList.remove('hidden'); }
      } finally { btns.forEach((b) => (b.disabled = false)); }
    }
    form.addEventListener('submit', (e) => { e.preventDefault(); const b = e.submitter; submit(b?.dataset.submit || 'draft', b?.hasAttribute('data-after-new') ? 'new' : ''); });
    form.addEventListener('input', () => { dirty = true; });
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    form.journalApi = { save: () => submit('draft'), post: () => submit('post'), add: () => addLine({}, true), balance: () => {} };

    (cfg.lines.length ? cfg.lines : [{}]).forEach((l) => addLine(l));
    onCurrency();
    dirty = false;
  }

  // ------------------------------------------------------------------ "Go to" palette (Ctrl+K / Alt+G)
  const goto = $('[data-goto]');
  const gotoItems = goto ? JSON.parse($('[data-goto-data]', goto).textContent) : [];
  let gotoIndex = 0;
  function gotoRender() {
    const q = $('[data-goto-input]', goto).value.trim().toLowerCase();
    const words = q.split(/\s+/).filter(Boolean);
    const found = gotoItems.filter((i) => words.every((w) => (i.label + ' ' + i.group).toLowerCase().includes(w))).slice(0, 30);
    gotoIndex = Math.min(gotoIndex, Math.max(0, found.length - 1));
    $('[data-goto-list]', goto).innerHTML = found.map((i, n) => `<li><a href="${esc(i.url)}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm ${n === gotoIndex ? 'bg-cyan-50 text-cyan-900' : 'text-slate-700 hover:bg-slate-50'}" data-goto-item="${n}"><span>${esc(i.label)}${i.soon ? ' <span class="badge badge-slate ml-1">Planned</span>' : ''}</span><span class="text-xs text-slate-400">${esc(i.group)}</span></a></li>`).join('')
      || '<li class="px-3 py-6 text-center text-sm text-slate-500">No matching screen</li>';
    goto._found = found;
  }
  function gotoOpen() { if (!goto) return; goto.classList.remove('hidden'); goto.classList.add('flex'); const i = $('[data-goto-input]', goto); i.value = ''; gotoIndex = 0; gotoRender(); setTimeout(() => i.focus(), 10); }
  function gotoClose() { goto?.classList.add('hidden'); goto?.classList.remove('flex'); }
  if (goto) {
    $('[data-goto-input]', goto).addEventListener('input', () => { gotoIndex = 0; gotoRender(); });
    $('[data-goto-input]', goto).addEventListener('keydown', (e) => {
      const n = goto._found?.length || 0;
      if (e.key === 'ArrowDown') { e.preventDefault(); gotoIndex = Math.min(n - 1, gotoIndex + 1); gotoRender(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); gotoIndex = Math.max(0, gotoIndex - 1); gotoRender(); }
      if (e.key === 'Enter' && goto._found?.[gotoIndex]) { e.preventDefault(); location.href = goto._found[gotoIndex].url; }
    });
    goto.addEventListener('click', (e) => { if (e.target === goto) gotoClose(); });
  }
  document.addEventListener('click', (e) => { if (e.target.closest('[data-goto-open]')) gotoOpen(); });

  const shortcuts = $('[data-shortcuts]');
  const shortcutsToggle = (show) => { shortcuts?.classList.toggle('hidden', !show); shortcuts?.classList.toggle('flex', show); };
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-shortcuts-open]')) { closeDropdowns(); shortcutsToggle(true); }
    if (e.target.closest('[data-shortcuts-close]') || e.target === shortcuts) shortcutsToggle(false);
  });

  // ------------------------------------------------------------------ Global keyboard shortcuts
  document.addEventListener('keydown', (e) => {
    if (confirmOpen()) {
      if (e.key === 'Escape') { e.preventDefault(); closeConfirm(false); }
      if (e.key === 'Enter') { e.preventDefault(); closeConfirm(true); }
      return;
    }
    if (goto && !goto.classList.contains('hidden')) { if (e.key === 'Escape') { e.preventDefault(); gotoClose(); } return; }
    if (shortcuts && !shortcuts.classList.contains('hidden') && e.key === 'Escape') { shortcutsToggle(false); return; }
    const k = e.key.toLowerCase();
    if ((e.ctrlKey || e.metaKey) && k === 'k' || e.altKey && k === 'g') { e.preventDefault(); gotoOpen(); return; }

    if (Drawer.isOpen()) {
      if (e.key === 'Escape') { e.preventDefault(); Drawer.close(); return; }
      if ((e.ctrlKey || e.metaKey) && (k === 's' || e.key === 'Enter')) { e.preventDefault(); $('[data-drawer-form]', Drawer.body)?.requestSubmit($('[data-save-btn]', Drawer.body) || undefined); return; }
      return;
    }
    if (e.key === 'Escape') { closeDropdowns(); if (document.activeElement && isTyping(document.activeElement)) document.activeElement.blur(); }

    const j = journal?.journalApi || docForm?.journalApi;
    if (j) {
      if ((e.ctrlKey || e.metaKey) && k === 's') { e.preventDefault(); j.save(); return; }
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); j.post(); return; }
      if (e.altKey && k === 'p') { e.preventDefault(); j.post(); return; }
      if (e.altKey && k === 'a') { e.preventDefault(); j.add(); return; }
      if (e.altKey && k === 'b') { e.preventDefault(); j.balance(); return; }
      if (e.key === 'Escape' && !isTyping(e.target)) { location.href = journal ? BASE + '/accountant/journals' : (docForm.action.replace(/\/store$/, '')); return; }
    }

    const list = lists.find((l) => !l.root.closest('.hidden'));
    if (list) {
      if (e.altKey && k === 'n') { e.preventDefault(); list.newRecord(); return; }
      if (e.altKey && k === 'f') { e.preventDefault(); list.togglePanel(); return; }
      if (e.altKey && k === 'e') { e.preventDefault(); list.exportCsv(); return; }
      if (list.handleKey(e)) return;
    }
    if (e.key === '?' && !isTyping(e.target)) { e.preventDefault(); shortcutsToggle(true); }
  });
})();
