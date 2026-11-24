(function () {
  const api = window.OJMSApi;
  if (!window.OJMS_AUTHORIZED) return;
  const tableScroll = document.querySelector(".table-scroll");
  const emptyState = document.querySelector(".empty-state");
  const submissionRows = document.querySelector("#submission-rows");
  const feedback = document.querySelector("#dashboard-feedback");
  const pagination = document.querySelector(".submission-pagination");
  const detailsDialog = document.querySelector(".submission-dialog");
  const pageLabel = pagination.querySelector("span");
  let currentPage = 1;
  let lastPage = 1;
  const user = api.getUser();

  if (user) {
    const displayName = [user.first_name, user.middle_name, user.last_name]
      .filter(Boolean)
      .join(" ") || user.email;
    document.querySelectorAll(".author-name").forEach((element) => {
      element.textContent = displayName;
    });
  }

  const statusLabels = {
    draft: "Draft",
    pending_review: "Pending Review",
    under_review: "Under Review",
    accepted: "Accepted",
    accept: "Accept",
    rejected: "Rejected",
    reject: "Reject",
    revisions_required: "Revisions Required",
    published: "Published",
  };

  function setFeedback(message, kind = "info") {
    feedback.textContent = message;
    feedback.dataset.kind = kind;
    feedback.hidden = !message;
  }

  function formatDate(value) {
    if (!value) return "Not available";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(undefined, { day: "2-digit", month: "short", year: "numeric" }).format(date);
  }

  function readableStatus(status) {
    return statusLabels[String(status || "").toLowerCase()] || String(status || "Unknown").replaceAll("_", " ");
  }

  function statusClass(status) {
    switch (String(status || "").toLowerCase()) {
      case "pending_review": return "status-pending";
      case "under_review": return "status-review";
      case "accepted":
      case "published": return "status-accepted";
      case "rejected": return "status-rejected";
      default: return "status-review";
    }
  }

  function addCell(row, text, tagName = "td", className = "") {
    const cell = document.createElement(tagName);
    cell.textContent = text;
    if (className) cell.className = className;
    row.append(cell);
    return cell;
  }

  function renderRows(articles) {
    submissionRows.replaceChildren();
    articles.forEach((article) => {
      const row = document.createElement("tr");
      const titleCell = addCell(row, article.title || "Untitled manuscript", "th");
      titleCell.scope = "row";
      addCell(row, formatDate(article.submitted_at || article.created_at));

      const statusCell = row.insertCell();
      const status = document.createElement("span");
      status.className = `status ${statusClass(article.status)}`;
      status.textContent = readableStatus(article.status);
      statusCell.append(status);

      const actionCell = row.insertCell();
      const detailsButton = document.createElement("button");
      detailsButton.className = "details-button";
      detailsButton.type = "button";
      detailsButton.dataset.articleId = String(article.id);
      detailsButton.textContent = "View Details";
      actionCell.append(detailsButton);
      submissionRows.append(row);
    });
  }

  function updatePagination(meta) {
    currentPage = Number(meta.current_page || currentPage || 1);
    lastPage = Number(meta.last_page || 1);
    pageLabel.textContent = `Page ${currentPage} of ${lastPage}`;
    pagination.hidden = lastPage <= 1;
    pagination.querySelector('[data-page="previous"]').disabled = currentPage <= 1;
    pagination.querySelector('[data-page="next"]').disabled = currentPage >= lastPage;
  }

  function showListState(hasRows, showEmpty = false) {
    tableScroll.hidden = !hasRows;
    emptyState.hidden = !showEmpty;
    document.body.classList.toggle("is-empty", showEmpty);
  }

  async function loadArticles(page = 1) {
    currentPage = page;
    setFeedback("Loading your submissions…");
    showListState(false);
    pagination.hidden = true;
    try {
      const payload = await api.request(`/my-articles?page=${encodeURIComponent(page)}`);
      const result = payload.data || {};
      const articles = Array.isArray(result) ? result : (result.data || []);
      renderRows(articles);
      showListState(articles.length > 0, articles.length === 0);
      updatePagination(Array.isArray(result) ? {} : result);
      setFeedback("");
    } catch (error) {
      showListState(false);
      setFeedback(error.message || "Could not load your submissions.", "error");
    }
  }

  function setText(selector, value) {
    const target = detailsDialog.querySelector(selector);
    target.textContent = value || "Not available";
  }

  function renderReviews(reviews) {
    const section = document.querySelector("#dialog-review-section");
    const list = document.querySelector("#dialog-reviews");
    list.replaceChildren();
    const visibleReviews = Array.isArray(reviews) ? reviews : [];
    visibleReviews.forEach((review) => {
      const item = document.createElement("li");
      const recommendation = review.recommendation ? `${readableStatus(review.recommendation)}: ` : "";
      item.textContent = `${recommendation}${review.comments || review.comment || "Feedback provided"}`;
      list.append(item);
    });
    section.hidden = visibleReviews.length === 0;
  }

  async function openDetails(articleId) {
    const title = document.querySelector("#dialog-title");
    const download = document.querySelector("#file-download");
    title.textContent = "Loading submission…";
    setText("#dialog-abstract", "");
    setText("#dialog-file", "");
    setText("#dialog-date", "");
    setText("#dialog-status", "Loading…");
    setText("#dialog-status-note", "");
    setText("#dialog-decision-date", "");
    download.hidden = true;
    renderReviews([]);
    detailsDialog.showModal();

    try {
      const payload = await api.request(`/my-articles/${encodeURIComponent(articleId)}`);
      const article = payload.data?.article || payload.data;
      title.textContent = article.title || "Untitled manuscript";
      setText("#dialog-abstract", article.abstract);
      setText("#dialog-file", article.original_filename || "Manuscript file");
      setText("#dialog-date", formatDate(article.submitted_at || article.created_at));
      const status = detailsDialog.querySelector("#dialog-status");
      status.textContent = readableStatus(article.status);
      status.className = `status ${statusClass(article.status)}`;
      setText("#dialog-status-note", article.decision_remarks || "Your manuscript status is up to date.");
      setText("#dialog-decision-date", formatDate(article.decided_at));
      if (article.document_url) {
        download.href = article.document_url;
        download.hidden = false;
      }
      renderReviews(article.reviews);
    } catch (error) {
      title.textContent = "Submission details unavailable";
      setText("#dialog-abstract", error.message || "Could not load this manuscript.");
    }
  }

  submissionRows.addEventListener("click", (event) => {
    const button = event.target.closest("[data-article-id]");
    if (button) openDetails(button.dataset.articleId);
  });

  document.querySelectorAll(".dialog-close, .dialog-bottom-close").forEach((button) => {
    button.addEventListener("click", () => detailsDialog.close());
  });
  detailsDialog.addEventListener("click", (event) => {
    if (event.target === detailsDialog) detailsDialog.close();
  });

  pagination.addEventListener("click", (event) => {
    const direction = event.target.dataset.page;
    if (direction === "previous" && currentPage > 1) loadArticles(currentPage - 1);
    if (direction === "next" && currentPage < lastPage) loadArticles(currentPage + 1);
  });

  document.querySelector('[data-action="logout"]').addEventListener("click", async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    let signedOut = "server";
    try {
      await api.request("/logout", { method: "POST" });
    } catch {
      signedOut = "local";
    } finally {
      api.clearSession();
      const loginUrl = new URL("../auth/login.html", window.location.href);
      loginUrl.searchParams.set("signedOut", signedOut);
      window.location.replace(loginUrl.href);
    }
  });
  loadArticles();
})();
