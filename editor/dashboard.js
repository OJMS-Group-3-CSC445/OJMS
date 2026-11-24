(function () {
  const api = window.OJMSApi;
  if (!window.OJMS_AUTHORIZED) return;

  const emptyState = document.querySelector("#editor-empty-state");
  const feedback = document.querySelector("#editor-feedback");
  const tableWrap = document.querySelector("#editor-table-wrap");
  const rows = document.querySelector("#editor-submission-rows");
  const pagination = document.querySelector("#editor-pagination");
  const pageLabel = pagination.querySelector("span");
  const logoutButton = document.querySelector('[data-action="logout"]');
  let currentPage = 1;
  let lastPage = 1;

  const user = api.getUser();
  if (user) {
    const name = [user.first_name, user.middle_name, user.last_name]
      .filter(Boolean)
      .join(" ") || user.email;
    document.querySelector(".editor-name").textContent = name;
  }
  document.querySelector("#copyright-year").textContent = new Date().getFullYear();

  function formatDate(value) {
    if (!value) return "Not available";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(undefined, {
      day: "2-digit",
      month: "short",
      year: "numeric",
    }).format(date);
  }

  function setEmptyState(message, kind = "") {
    emptyState.textContent = message;
    emptyState.dataset.kind = kind;
    emptyState.hidden = false;
    tableWrap.hidden = true;
    pagination.hidden = true;
  }

  function renderRows(articles) {
    rows.replaceChildren();
    articles.forEach((article) => {
      const row = document.createElement("tr");
      const title = document.createElement("td");
      title.textContent = article.title || "Untitled manuscript";
      const author = document.createElement("td");
      const authorName = article.author
        ? [article.author.first_name, article.author.last_name].filter(Boolean).join(" ")
        : "";
      author.textContent = authorName || article.author?.email || "Not available";
      const submitted = document.createElement("td");
      submitted.textContent = formatDate(article.submitted_at || article.created_at);
      const status = document.createElement("td");
      status.textContent = String(article.status || "Unknown").replaceAll("_", " ");
      row.append(title, author, submitted, status);
      rows.append(row);
    });
  }

  function updatePagination(data) {
    currentPage = Number(data.current_page || 1);
    lastPage = Number(data.last_page || 1);
    pageLabel.textContent = `Page ${currentPage} of ${lastPage}`;
    pagination.hidden = lastPage <= 1;
    pagination.querySelector('[data-page="previous"]').disabled = currentPage <= 1;
    pagination.querySelector('[data-page="next"]').disabled = currentPage >= lastPage;
  }

  async function loadSubmissions(page = 1) {
    currentPage = page;
    feedback.hidden = true;
    tableWrap.hidden = true;
    pagination.hidden = true;
    setEmptyState("Loading submissions…");

    try {
      const response = await api.request(`/editor/articles?page=${encodeURIComponent(page)}`);
      const data = response.data || {};
      const articles = Array.isArray(data) ? data : (data.data || []);
      if (articles.length === 0) {
        setEmptyState("No manuscripts have been submitted yet");
        return;
      }

      emptyState.hidden = true;
      renderRows(articles);
      tableWrap.hidden = false;
      updatePagination(Array.isArray(data) ? {} : data);
    } catch (error) {
      feedback.textContent = error.message || "Could not load submissions.";
      feedback.hidden = false;
      setEmptyState("Submissions are unavailable.", "error");
    }
  }

  pagination.addEventListener("click", (event) => {
    const direction = event.target.dataset.page;
    if (direction === "previous" && currentPage > 1) loadSubmissions(currentPage - 1);
    if (direction === "next" && currentPage < lastPage) loadSubmissions(currentPage + 1);
  });

  logoutButton.addEventListener("click", async () => {
    logoutButton.disabled = true;
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

  loadSubmissions();
})();
