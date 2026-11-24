/* Admin: reviewers list and Add reviewer.
 * API: GET  /admin/users?role=reviewer&page=N   (paginated, 15 per page)
 *      POST /admin/users   body { first_name, last_name, middle_name?, email, phone?, password, role: 'reviewer' }
 *
 * Not built yet because the API has nothing for it: the Active/Deactivated status, the
 * "Manuscripts assigned" count and the Deactivate / Reactivate buttons from the Figma design. */
(() => {
  'use strict';
  if (!window.OJMS_AUTHORIZED) return; // guard.js is redirecting to the login page

  const C = window.AdminCore;
  const $ = (id) => document.getElementById(id);

  const panels = { loading: $('loading'), error: $('error'), empty: $('empty'), table: $('table') };
  const rows = $('rows');
  const pager = $('pager');
  const prev = $('prev');
  const next = $('next');
  const dialog = $('add-dialog');
  const form = $('add-form');
  const FIELDS = ['surname', 'other_names', 'email', 'phone', 'password'];
  let currentPage = 1;

  function setText(el, message) {
    el.textContent = message || '';
    el.hidden = !message;
  }

  /* ---------- List ---------- */
  function cell(label, className) {
    const td = document.createElement('td');
    td.dataset.label = label; // used by the small-screen card layout
    if (className) td.className = className;
    return td;
  }

  function buildRow(user) {
    const tr = document.createElement('tr');
    tr.appendChild(Object.assign(cell('Name', 'cell-title'), { textContent: C.fullName(user) }));
    tr.appendChild(Object.assign(cell('Email', 'cell-muted'), { textContent: user.email || '' }));
    return tr;
  }

  function render(paginator) {
    const list = (paginator && paginator.data) || [];
    rows.textContent = '';

    if (!list.length) {
      pager.hidden = true;
      C.show(panels, 'empty');
      return;
    }

    const fragment = document.createDocumentFragment();
    list.forEach((user) => fragment.appendChild(buildRow(user)));
    rows.appendChild(fragment);

    currentPage = paginator.current_page || 1;
    const lastPage = paginator.last_page || 1;
    pager.hidden = lastPage <= 1;
    $('page-info').textContent = 'Page ' + currentPage + ' of ' + lastPage;
    prev.disabled = !paginator.prev_page_url;
    next.disabled = !paginator.next_page_url;

    C.show(panels, 'table');
  }

  async function load(page) {
    C.show(panels, 'loading');
    pager.hidden = true;
    try {
      render(await C.api('/admin/users?role=reviewer&page=' + page));
    } catch (err) {
      $('error-message').textContent = err.message;
      C.show(panels, 'error');
    }
  }

  prev.addEventListener('click', () => { load(currentPage - 1); window.scrollTo({ top: 0 }); });
  next.addEventListener('click', () => { load(currentPage + 1); window.scrollTo({ top: 0 }); });
  $('retry').addEventListener('click', () => load(currentPage));

  /* ---------- Add reviewer ---------- */
  document.querySelectorAll('[data-add]').forEach((btn) => {
    btn.addEventListener('click', () => dialog.showModal());
  });

  function setFieldError(name, message) {
    const input = form.elements[name];
    setText($('error-' + name), message);
    input.classList.toggle('is-invalid', Boolean(message));
    if (message) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
  }

  function clearErrors() {
    FIELDS.forEach((name) => setFieldError(name, ''));
    setText($('add-error'), '');
  }

  function validate(values) {
    const errors = {};
    if (!values.surname) errors.surname = 'Enter a surname.';
    if (!values.other_names) errors.other_names = 'Enter other names.';
    if (!values.email) errors.email = 'Enter an email address.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) errors.email = 'Enter a valid email address.';
    if (!values.password) errors.password = 'Enter a password.';
    else if (values.password.length < 8) errors.password = 'Use at least 8 characters.';
    return errors;
  }

  /* The form has "Surname" and "Other names"; the API wants first_name, last_name and middle_name. */
  function buildPayload(values) {
    const parts = values.other_names.split(/\s+/);
    const payload = {
      first_name: parts[0],
      last_name: values.surname,
      email: values.email,
      password: values.password,
      role: 'reviewer'
    };
    if (parts.length > 1) payload.middle_name = parts.slice(1).join(' ');
    if (values.phone) payload.phone = values.phone;
    return payload;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();

    const f = form.elements;
    const values = {
      surname: f.surname.value.trim(),
      other_names: f.other_names.value.trim(),
      email: f.email.value.trim(),
      phone: f.phone.value.trim(),
      password: f.password.value
    };

    const errors = validate(values);
    const invalid = FIELDS.filter((name) => errors[name]);
    if (invalid.length) {
      invalid.forEach((name) => setFieldError(name, errors[name]));
      f[invalid[0]].focus();
      return;
    }

    const submit = $('add-submit');
    C.setBusy(submit, true);
    try {
      await C.post('/admin/users', buildPayload(values));
      dialog.close();
      C.banner.show('Reviewer added: ' + values.other_names + ' ' + values.surname + '.');
      await load(currentPage);
    } catch (err) {
      // api.js gives one joined message for validation errors (it does not keep the field names).
      setText($('add-error'), err.message);
    } finally {
      C.setBusy(submit, false);
    }
  });

  dialog.addEventListener('close', () => {
    form.reset();
    clearErrors();
  });

  load(1);
})();
