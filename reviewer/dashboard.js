/* Reviewer dashboard: the manuscripts assigned to the logged-in reviewer.
 * API: GET /reviewer/assignments?page=N  (paginated, 15 per page) */
(() => {
  'use strict';
  if (!window.OJMS_AUTHORIZED) return; // guard.js is redirecting to the login page

  const C = window.ReviewerCore;
  const $ = (id) => document.getElementById(id);

  const panels = { loading: $('loading'), error: $('error'), empty: $('empty'), table: $('table') };
  const rows = $('rows');
  const pager = $('pager');
  const prev = $('prev');
  const next = $('next');
  let currentPage = 1;

  function cell(label, className) {
    const td = document.createElement('td');
    td.dataset.label = label; // used by the mobile card layout
    if (className) td.className = className;
    return td;
  }

  function buildRow(assignment) {
    const article = assignment.article || {};
    const tr = document.createElement('tr');

    const title = cell('Title', 'cell-title');
    title.textContent = article.title || '';
    tr.appendChild(title);

    const date = cell('Date assigned', 'cell-muted');
    date.textContent = C.formatDate(assignment.assigned_at);
    tr.appendChild(date);

    const status = cell('Status');
    status.appendChild(C.el('span', 'pill', C.ARTICLE_STATUS[article.status] || article.status || ''));
    tr.appendChild(status);

    // Pending -> the reviewer still has to write the review. Completed -> read-only view.
    const done = assignment.status === 'Completed';
    const label = done ? 'View Review' : 'Review';
    const action = cell('Action', 'cell-action');
    const link = C.el('a', 'btn btn--sm' + (done ? '' : ' btn--primary'), label);
    link.href = 'review.html?id=' + encodeURIComponent(assignment.id);
    link.setAttribute('aria-label', label + ': ' + (article.title || ''));
    action.appendChild(link);
    tr.appendChild(action);

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
    list.forEach((assignment) => fragment.appendChild(buildRow(assignment)));
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
      render(await C.api('/reviewer/assignments?page=' + page));
    } catch (err) {
      $('error-message').textContent = err.message;
      C.show(panels, 'error');
    }
  }

  prev.addEventListener('click', () => { load(currentPage - 1); window.scrollTo({ top: 0 }); });
  next.addEventListener('click', () => { load(currentPage + 1); window.scrollTo({ top: 0 }); });
  $('retry').addEventListener('click', () => load(currentPage));

  load(1);
})();
