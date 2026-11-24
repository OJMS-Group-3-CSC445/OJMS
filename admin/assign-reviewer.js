/* Admin: assign a reviewer to a manuscript. Opened as assign-reviewer.html?id=<article id>.
 * API: GET  /admin/articles/{id}                       -> the manuscript (and its current assignments)
 *      GET  /admin/users?role=reviewer&page=N          -> reviewers (all pages are loaded)
 *      POST /admin/articles/{id}/assign-reviewer       -> body { reviewer_id }
 *
 * The API has no "active reviewers" filter yet, so every reviewer who is not already assigned
 * to this manuscript is offered. */
(() => {
  'use strict';
  if (!window.OJMS_AUTHORIZED) return; // guard.js is redirecting to the login page

  const C = window.AdminCore;
  const $ = (id) => document.getElementById(id);

  const articleId = new URLSearchParams(window.location.search).get('id');
  const articlePath = '/admin/articles/' + encodeURIComponent(articleId || '');

  const panels = { loading: $('loading'), error: $('error'), content: $('content') };
  const els = {
    title: $('man-title'),
    author: $('man-author'),
    date: $('man-date'),
    status: $('man-status'),
    selectWrap: $('select-wrap'),
    trigger: $('select-trigger'),
    value: $('select-value'),
    list: $('select-list'),
    selectError: $('select-error'),
    noReviewers: $('no-reviewers'),
    noReviewersText: $('no-reviewers-text'),
    assign: $('assign-btn'),
    assignError: $('assign-error'),
    dialog: $('confirm-dialog'),
    confirmText: $('confirm-text'),
    confirm: $('confirm-assign')
  };

  let article = null;
  let selected = null;

  function setText(el, message) {
    el.textContent = message || '';
    el.hidden = !message;
  }

  /* ---------- Dropdown (listbox) ---------- */
  const options = () => Array.from(els.list.querySelectorAll('.select__option'));
  const isOpen = () => !els.list.hidden;

  function openList() {
    els.list.hidden = false;
    els.trigger.setAttribute('aria-expanded', 'true');
  }

  function closeList(returnFocus) {
    els.list.hidden = true;
    els.trigger.setAttribute('aria-expanded', 'false');
    if (returnFocus) els.trigger.focus();
  }

  function choose(reviewer, li) {
    selected = reviewer;
    els.value.textContent = C.fullName(reviewer);
    els.trigger.classList.add('has-value');
    options().forEach((o) => o.setAttribute('aria-selected', String(o === li)));
    els.assign.disabled = false;
    setText(els.selectError, '');
    closeList(true);
  }

  function buildSelect(reviewers) {
    els.list.textContent = '';
    reviewers.forEach((reviewer) => {
      const li = C.el('li', 'select__option', C.fullName(reviewer));
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', 'false');
      li.tabIndex = -1;
      li.addEventListener('click', () => choose(reviewer, li));
      li.addEventListener('keydown', (e) => {
        const all = options();
        const i = all.indexOf(li);
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(reviewer, li); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); (all[i + 1] || li).focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); (all[i - 1] || li).focus(); }
        else if (e.key === 'Escape') { closeList(true); }
        else if (e.key === 'Tab') { closeList(false); }
      });
      els.list.appendChild(li);
    });
  }

  els.trigger.addEventListener('click', () => (isOpen() ? closeList(false) : openList()));
  els.trigger.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      openList();
      const first = options()[0];
      if (first) first.focus();
    } else if (e.key === 'Escape') {
      closeList(false);
    }
  });
  document.addEventListener('click', (e) => {
    if (isOpen() && !els.selectWrap.contains(e.target)) closeList(false);
  });

  /* ---------- Load ---------- */
  function render(art, allReviewers) {
    article = art;
    document.title = 'Assign reviewer | OJMS';
    els.title.textContent = art.title || '';
    els.author.textContent = C.shortName(art.author);
    els.date.textContent = C.formatDate(art.submitted_at || art.created_at);
    C.statusPill(art.status, els.status);

    const assignedIds = new Set((art.assignments || []).map((a) => a.reviewer_id));
    const available = allReviewers.filter((r) => !assignedIds.has(r.id));

    selected = null;
    els.value.textContent = 'Select a reviewer';
    els.trigger.classList.remove('has-value');
    els.assign.disabled = true; // enabled once a reviewer is chosen

    if (available.length) {
      buildSelect(available);
      els.selectWrap.hidden = false;
      els.noReviewers.hidden = true;
    } else {
      els.noReviewersText.textContent = allReviewers.length
        ? 'Every reviewer is already assigned to this manuscript.'
        : 'There are no active reviewers. Add one first.';
      els.selectWrap.hidden = true;
      els.noReviewers.hidden = false;
    }
    C.show(panels, 'content');
  }

  async function load() {
    if (!articleId) {
      $('error-message').textContent = 'No manuscript selected. Go back to the dashboard and choose one.';
      C.show(panels, 'error');
      return;
    }
    C.show(panels, 'loading');
    try {
      const [art, reviewers] = await Promise.all([
        C.api(articlePath),
        C.fetchAll('/admin/users?role=reviewer')
      ]);
      render(art, reviewers);
    } catch (err) {
      $('error-message').textContent = err.message;
      C.show(panels, 'error');
    }
  }

  /* ---------- Assign ---------- */
  els.assign.addEventListener('click', () => {
    setText(els.assignError, '');
    if (!selected) {
      setText(els.selectError, 'Choose a reviewer.');
      return;
    }
    els.confirmText.textContent = 'Assign ' + C.fullName(selected) + ' to review \u201C' + article.title + '\u201D?';
    els.dialog.showModal();
  });

  els.confirm.addEventListener('click', async () => {
    C.setBusy(els.confirm, true);
    try {
      await C.post(articlePath + '/assign-reviewer', { reviewer_id: selected.id });
      C.flash.set('Reviewer assigned to ' + article.title + '.');
      window.location.assign('dashboard.html');
    } catch (err) {
      els.dialog.close();
      setText(els.assignError, err.message);
      C.setBusy(els.confirm, false);
    }
  });

  $('retry').addEventListener('click', load);
  load();
})();
