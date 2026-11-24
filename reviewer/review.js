/* Review page: shows an assigned manuscript with the review form, or the submitted review (read only).
 * The page is opened as review.html?id=<assignment id>.
 * API: GET  /reviewer/assignments/{id}          -> { assignment, review }  (review is null until submitted)
 *      POST /reviewer/assignments/{id}/review   -> body { comments (min 10 chars), recommendation } */
(() => {
  'use strict';
  if (!window.OJMS_AUTHORIZED) return; // guard.js is redirecting to the login page

  const C = window.ReviewerCore;
  const $ = (id) => document.getElementById(id);

  const MIN_COMMENT_LENGTH = 10;
  const assignmentId = new URLSearchParams(window.location.search).get('id');
  const assignmentPath = '/reviewer/assignments/' + encodeURIComponent(assignmentId || '');

  const panels = { loading: $('loading'), error: $('error'), content: $('content') };
  const els = {
    heading: $('page-title'),
    title: $('man-title'),
    date: $('man-date'),
    abstract: $('man-abstract'),
    download: $('man-download'),
    formCard: $('form-card'),
    form: $('review-form'),
    comments: $('comments'),
    commentsError: $('comments-error'),
    recError: $('recommendation-error'),
    formError: $('form-error'),
    submittedCard: $('submitted-card'),
    subComments: $('submitted-comments'),
    subRec: $('submitted-recommendation'),
    subOn: $('submitted-on'),
    dialog: $('confirm-dialog'),
    confirm: $('confirm-submit')
  };

  function setText(el, message) {
    el.textContent = message || '';
    el.hidden = !message;
  }

  function markInvalid(invalid) {
    els.comments.classList.toggle('is-invalid', invalid);
    if (invalid) els.comments.setAttribute('aria-invalid', 'true');
    else els.comments.removeAttribute('aria-invalid');
  }

  function showSubmitted(review) {
    els.formCard.hidden = true;
    els.submittedCard.hidden = false;
    els.subComments.textContent = review.comments || '';
    els.subRec.textContent = C.RECOMMENDATION[review.recommendation] || review.recommendation || '';
    els.subOn.textContent = review.reviewed_at ? 'Submitted on ' + C.formatDate(review.reviewed_at) : '';
  }

  function render(data) {
    const assignment = (data && data.assignment) || {};
    const article = assignment.article || {};
    const title = article.title || '';

    document.title = title + ' | OJMS';
    els.heading.textContent = title;
    els.title.textContent = title;
    els.date.textContent = C.formatDate(assignment.assigned_at);
    els.abstract.textContent = article.abstract || '';

    if (article.document_url) {
      els.download.href = article.document_url;
      els.download.hidden = false;
    } else {
      els.download.hidden = true;
    }

    if (data.review) {
      showSubmitted(data.review);
    } else {
      els.formCard.hidden = false;
      els.submittedCard.hidden = true;
    }
    C.show(panels, 'content');
  }

  async function load() {
    if (!assignmentId) {
      $('error-message').textContent = 'No manuscript selected. Go back to your dashboard and choose one.';
      C.show(panels, 'error');
      return;
    }
    C.show(panels, 'loading');
    try {
      render(await C.api(assignmentPath));
    } catch (err) {
      $('error-message').textContent = err.message;
      C.show(panels, 'error');
    }
  }

  /* ---------- Form ---------- */
  function validate() {
    const comments = els.comments.value.trim();
    const recommendation = els.form.elements.recommendation.value;

    let commentsMessage = '';
    if (!comments) commentsMessage = 'Write your comments before submitting.';
    else if (comments.length < MIN_COMMENT_LENGTH) commentsMessage = 'Write at least ' + MIN_COMMENT_LENGTH + ' characters.';

    setText(els.commentsError, commentsMessage);
    markInvalid(Boolean(commentsMessage));
    setText(els.recError, recommendation ? '' : 'Choose a recommendation.');
    setText(els.formError, '');

    if (commentsMessage) { els.comments.focus(); return false; }
    if (!recommendation) { els.form.querySelector('input[name="recommendation"]').focus(); return false; }
    return true;
  }

  els.form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (validate()) els.dialog.showModal();
  });

  els.comments.addEventListener('input', () => {
    setText(els.commentsError, '');
    markInvalid(false);
  });
  els.form.addEventListener('change', () => setText(els.recError, ''));

  els.confirm.addEventListener('click', async () => {
    const payload = {
      comments: els.comments.value.trim(),
      recommendation: els.form.elements.recommendation.value
    };

    C.setBusy(els.confirm, true);
    try {
      const data = await C.post(assignmentPath + '/review', payload);
      els.dialog.close();
      showSubmitted((data && data.review) || Object.assign({ reviewed_at: new Date().toISOString() }, payload));
      window.scrollTo({ top: 0 });
    } catch (err) {
      els.dialog.close();
      // If the review was already saved (for example a double submit), show it instead of an error.
      try {
        const fresh = await C.api(assignmentPath);
        if (fresh && fresh.review) { render(fresh); return; }
      } catch (ignore) { /* fall through to the error message */ }
      setText(els.formError, err.message);
    } finally {
      C.setBusy(els.confirm, false);
    }
  });

  $('retry').addEventListener('click', load);
  load();
})();
