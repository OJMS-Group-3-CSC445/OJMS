/*
 * Admin pages: shared helpers.
 * Needs these loaded first (see the script tags in the HTML files):
 *   ../author/api.js   -> window.OJMSApi (token, base URL, request())
 *   ../auth/guard.js   -> checks the logged-in user has the right role (data-role="portal_admin")
 */
(() => {
  'use strict';

  const Api = window.OJMSApi;
  if (!Api) {
    console.error('OJMSApi is missing. Load ../author/api.js before admin-core.js.');
    return;
  }

  const Core = (window.AdminCore = {});
  const LOGIN_URL = '../auth/login.html';
  const FLASH_KEY = 'ojms_admin_flash';

  /* Labels and pill colours for article statuses (API documentation, section 1.4) */
  Core.ARTICLE_STATUS = {
    draft: 'Draft',
    pending_review: 'Pending Review',
    under_review: 'Under Review',
    accepted: 'Accepted',
    rejected: 'Rejected',
    published: 'Published'
  };

  const PILL_CLASS = {
    draft: 'pill--draft',
    pending_review: 'pill--pending',
    accepted: 'pill--accepted',
    rejected: 'pill--rejected',
    published: 'pill--published'
  };

  /** Build a status pill element. Applies the style classes to an existing element when one is passed. */
  Core.statusPill = (status, existing) => {
    const pill = existing || document.createElement('span');
    pill.className = 'pill ' + (PILL_CLASS[status] || '');
    pill.textContent = Core.ARTICLE_STATUS[status] || status || '';
    return pill;
  };

  /** Create an element. Text goes in with textContent, so API data can never inject HTML. */
  Core.el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  };

  /** ISO timestamp -> "16 Nov 2026". */
  Core.formatDate = (value) => {
    if (!value) return '';
    const d = new Date(value);
    if (isNaN(d.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
  };

  /** { first_name, last_name } -> "Ngozi Eze". */
  Core.fullName = (person) => (person ? [person.first_name, person.last_name].filter(Boolean).join(' ') : '');

  /** { first_name, last_name } -> "A. Okafor" (the short form used in the submissions table). */
  Core.shortName = (person) => {
    if (!person) return '';
    const initial = person.first_name ? person.first_name.trim().charAt(0).toUpperCase() + '.' : '';
    return [initial, person.last_name].filter(Boolean).join(' ');
  };

  /** Show one panel and hide the others. panels = { name: element }. */
  Core.show = (panels, name) => {
    Object.keys(panels).forEach((key) => { panels[key].hidden = key !== name; });
  };

  Core.setBusy = (button, busy) => {
    if (!button) return;
    button.disabled = !!busy;
    if (busy) button.setAttribute('aria-busy', 'true');
    else button.removeAttribute('aria-busy');
  };

  /** Send the user to the login page. withNext=true brings them back to this page after login. */
  Core.redirectToLogin = (withNext) => {
    const url = new URL(LOGIN_URL, window.location.href);
    if (withNext !== false) {
      url.searchParams.set('next', window.location.pathname + window.location.search + window.location.hash);
    }
    window.location.replace(url.href);
  };

  /**
   * Call the API with OJMSApi and return the "data" part of the response.
   * Throws an Error whose message is ready to show to the user.
   * api.js clears the session on a 401, so a missing token afterwards means "logged out": go to login.
   */
  Core.api = async (path, options) => {
    try {
      const payload = await Api.request(path, options);
      return payload ? payload.data : null;
    } catch (err) {
      if (!Api.getToken()) Core.redirectToLogin();
      throw err;
    }
  };

  /** POST a JSON body. */
  Core.post = (path, body) => Core.api(path, { method: 'POST', body: JSON.stringify(body) });

  /** Fetch every page of a paginated list endpoint and return all items. */
  Core.fetchAll = async (path) => {
    const joiner = path.indexOf('?') === -1 ? '?' : '&';
    let items = [];
    for (let page = 1; page <= 50; page++) {
      const paginator = await Core.api(path + joiner + 'page=' + page);
      items = items.concat((paginator && paginator.data) || []);
      if (!paginator || !paginator.next_page_url || page >= (paginator.last_page || 1)) break;
    }
    return items;
  };

  /* ---------- One-time message shown on the next page (e.g. after assigning a reviewer) ---------- */
  Core.flash = {
    set: (message) => { try { sessionStorage.setItem(FLASH_KEY, message); } catch (e) { /* ignore */ } },
    take: () => {
      try {
        const message = sessionStorage.getItem(FLASH_KEY);
        sessionStorage.removeItem(FLASH_KEY);
        return message;
      } catch (e) { return null; }
    }
  };

  /* ---------- Success banner (#banner, #banner-text, #banner-close) ---------- */
  Core.banner = {
    show: (message) => {
      const banner = document.getElementById('banner');
      if (!banner) return;
      document.getElementById('banner-text').textContent = message;
      banner.hidden = false;
    },
    hide: () => {
      const banner = document.getElementById('banner');
      if (banner) banner.hidden = true;
    }
  };
  const bannerClose = document.getElementById('banner-close');
  if (bannerClose) bannerClose.addEventListener('click', Core.banner.hide);

  /* ---------- Header and footer ---------- */
  document.querySelectorAll('[data-year]').forEach((el) => { el.textContent = String(new Date().getFullYear()); });

  document.querySelectorAll('[data-logout]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      Core.setBusy(btn, true);
      try { await Api.request('/logout', { method: 'POST' }); } catch (e) { /* log out locally anyway */ }
      Api.clearSession();
      Core.redirectToLogin(false);
    });
  });

  /* ---------- Dialogs: [data-close] closes; so does a click on the backdrop ---------- */
  document.addEventListener('click', (e) => {
    const closer = e.target.closest('[data-close]');
    if (!closer) return;
    const dialog = closer.closest('dialog');
    if (dialog) dialog.close();
  });

  document.querySelectorAll('dialog.modal').forEach((dialog) => {
    dialog.addEventListener('click', (e) => {
      if (e.target !== dialog) return;
      const r = dialog.getBoundingClientRect();
      const inside = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
      if (!inside) dialog.close();
    });
  });
})();
