/* Admin dashboard ("All submissions").
 * API: GET /admin/articles?page=N  (paginated, 15 per page) */
(() => {
  'use strict';
  if (!window.OJMS_AUTHORIZED) return; // guard.js is redirecting to the login page

  const C = window.AdminCore;
  const $ = (id) => document.getElementById(id);

  /*
   * "View Details" has a button in the Figma design but no page and no design yet.
   * When that page exists, set its file name here (for example 'article-details.html').
   * It is opened as <page>?id=<article id>. While this is empty the button is not shown.
   */
  const DETAILS_PAGE = '';

  const panels = { loading: $('loading'), error: $('error'), empty: $('empty'), table: $('table') };
  const rows = $('rows');
  const pager = $('pager');
  const prev = $('prev');
  const next = $('next');
  let currentPage = 1;

  function cell(label, className) {
    const td = document.createElement('td');
    td.dataset.label = label; // used by the small-screen card layout
    if (className) td.className = className;
    return td;
  }

  /* Reviewer column: names if the API includes them, otherwise how many are assigned. */
  function reviewerText(article) {
    const names = (Array.isArray(article.assignments) ? article.assignments : [])
      .map((a) => C.fullName(a.reviewer))
      .filter(Boolean);
    if (names.length) return names.join(', ');
    const count = article.assignments_count || 0;
    if (!count) return 'Not assigned';
    return count === 1 ? '1 reviewer' : count + ' reviewers';
  }

  /* Review report column: Not yet / Submitted / "1 of 2 submitted". */
  function reportCell(td, article) {
    const assigned = article.assignments_count || 0;
    const done = article.reviews_count || 0;
    if (!done) {
      td.appendChild(C.el('span', 'report', 'Not yet'));
    } else if (done >= assigned) {
      td.appendChild(C.el('span', 'report report--done', 'Submitted'));
    } else {
      td.appendChild(C.el('span', 'report report--done', done + ' of ' + assigned + ' submitted'));
    }
  }

  function buildRow(article) {
    const tr = document.createElement('tr');

    const title = cell('Title', 'cell-title');
    title.textContent = article.title || '';
    tr.appendChild(title);

    const author = cell('Author', 'cell-muted');
    author.textContent = C.shortName(article.author);
    tr.appendChild(author);

    const date = cell('Date submitted', 'cell-muted');
    date.textContent = C.formatDate(article.submitted_at || article.created_at);
    tr.appendChild(date);

    const status = cell('Status');
    status.appendChild(C.statusPill(article.status));
    tr.appendChild(status);

    tr.appendChild(Object.assign(cell('Reviewer', 'cell-muted'), { textContent: reviewerText(article) }));

    const report = cell('Review report');
    reportCell(report, article);
    tr.appendChild(report);

    const actions = cell('Actions', 'cell-action');
    const group = C.el('div', 'actions');
    if (DETAILS_PAGE) {
      const details = C.el('a', 'btn btn--sm', 'View Details');
      details.href = DETAILS_PAGE + '?id=' + encodeURIComponent(article.id);
      details.setAttribute('aria-label', 'View details: ' + (article.title || ''));
      group.appendChild(details);
    }
    // Only a manuscript that is waiting for its first reviewer can be assigned from here.
    if (article.status === 'pending_review') {
      const assign = C.el('a', 'btn btn--sm btn--primary', 'Assign Reviewer');
      assign.href = 'assign-reviewer.html?id=' + encodeURIComponent(article.id);
      assign.setAttribute('aria-label', 'Assign reviewer: ' + (article.title || ''));
      group.appendChild(assign);
    }
    actions.appendChild(group);
    tr.appendChild(actions);

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
    list.forEach((article) => fragment.appendChild(buildRow(article)));
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
      render(await C.api('/admin/articles?page=' + page));
    } catch (err) {
      $('error-message').textContent = err.message;
      C.show(panels, 'error');
    }
  }

  prev.addEventListener('click', () => { load(currentPage - 1); window.scrollTo({ top: 0 }); });
  next.addEventListener('click', () => { load(currentPage + 1); window.scrollTo({ top: 0 }); });
  $('retry').addEventListener('click', () => load(currentPage));

  // A message left by the Assign reviewer page ("Reviewer assigned to ...")
  const flash = C.flash.take();
  if (flash) C.banner.show(flash);

  load(1);
})();
