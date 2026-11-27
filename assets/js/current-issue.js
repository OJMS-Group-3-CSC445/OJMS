(function () {
    const root = document.body.dataset.root || "../";
    const ENDPOINT = `${root}api/current-issue.php`;
  
    const titleEl = document.getElementById("issue-title");
    const numberEl = document.getElementById("issue-number");
    const dividerEl = document.getElementById("meta-divider");
    const dateEl = document.getElementById("issue-date");
    const descEl = document.getElementById("issue-description");
    const countEl = document.getElementById("article-count");
    const container = document.getElementById("articles-container");
  
    function escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    }
  
    function formatDate(dateStr, options) {
      if (!dateStr) return "";
      const parsed = new Date(dateStr);
      if (Number.isNaN(parsed.getTime())) return "";
      return parsed.toLocaleDateString("en-US", options);
    }
  
    function truncate(text, max = 260) {
      const clean = String(text ?? "").trim();
      if (clean.length <= max) return clean;
      const cut = clean.slice(0, max);
      const space = cut.lastIndexOf(" ");
      return `${space > 0 ? cut.slice(0, space) : cut}…`;
    }
  
    function observeCards() {
      const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      const cards = container.querySelectorAll(".article-card[data-reveal]");
      if (prefersReduced || !("IntersectionObserver" in window) || !cards.length) {
        return;
      }
  
      document.documentElement.classList.add("js-reveal-ready");
      const observer = new IntersectionObserver(
        (entries, obs) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add("is-visible");
              obs.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.1, rootMargin: "0px 0px -20px 0px" }
      );
  
      cards.forEach((card) => observer.observe(card));
    }
  
    function renderIssue(issue) {
      titleEl.textContent = issue.title || "Current Issue";
  
      const volParts = [];
      if (issue.volume) volParts.push(`Volume ${issue.volume}`);
      if (issue.issue_number) volParts.push(`Issue ${issue.issue_number}`);
      numberEl.textContent = volParts.join(", ");
  
      const formattedDate = formatDate(issue.publication_date, {
        year: "numeric",
        month: "long",
      });
      dateEl.textContent = formattedDate;
      if (issue.publication_date) {
        dateEl.setAttribute("datetime", issue.publication_date);
      }
  
      dividerEl.hidden = !(volParts.length > 0 && formattedDate);
  
      if (issue.description && issue.description.trim() !== "") {
        descEl.textContent = issue.description.trim();
        descEl.hidden = false;
      } else {
        descEl.hidden = true;
      }
    }
  
    function renderArticles(articles) {
      if (!articles || articles.length === 0) {
        countEl.textContent = "0 articles";
        container.innerHTML = `
          <div class="state-box">
            <h3>No published articles in this issue yet</h3>
            <p>Peer-reviewed articles assigned to this issue will appear here once published.</p>
          </div>`;
        return;
      }
  
      countEl.textContent =
        articles.length === 1 ? "1 article" : `${articles.length} articles`;
  
      container.innerHTML = articles
        .map((article) => {
          const authors =
            (article.authors && article.authors.trim()) ||
            [article.author_first, article.author_last].filter(Boolean).join(" ").trim();
  
          const pubDate = formatDate(article.created_at, {
            year: "numeric",
            month: "short",
            day: "numeric",
          });
  
          const docPath = (article.document_path || "").trim();
          const fileHref = docPath
            ? `${root}storage/${encodeURI(docPath.replace(/^\/+/, ""))}`
            : "";
  
          const actionHtml = fileHref
            ? `<a href="${escapeHtml(fileHref)}" class="read-article" target="_blank" rel="noopener noreferrer">
                 Read Article <span aria-hidden="true">&rarr;</span>
               </a>`
            : `<span class="read-article-unavailable">Document unavailable</span>`;
  
          return `
            <article class="article-card" data-reveal>
              <div class="article-info">
                ${pubDate ? `<time class="article-pub-date">${escapeHtml(pubDate)}</time>` : ""}
                <h3>${escapeHtml(article.title)}</h3>
                ${authors ? `<p class="article-authors">${escapeHtml(authors)}</p>` : ""}
                <p class="article-abstract">${escapeHtml(truncate(article.abstract))}</p>
              </div>
              <div class="article-actions">
                ${actionHtml}
              </div>
            </article>`;
        })
        .join("");
  
      observeCards();
    }
  
    function renderNoIssue() {
      titleEl.textContent = "No Current Issue Published";
      numberEl.textContent = "";
      dateEl.textContent = "";
      dividerEl.hidden = true;
      descEl.hidden = true;
      countEl.textContent = "";
  
      container.innerHTML = `
        <div class="state-box">
          <h3>No current issue is available yet</h3>
          <p>The editorial office has not published a current issue. Please check back soon or browse the archives.</p>
        </div>`;
    }
  
    function renderError() {
      titleEl.textContent = "Current Issue";
      numberEl.textContent = "";
      dateEl.textContent = "";
      dividerEl.hidden = true;
      descEl.hidden = true;
      countEl.textContent = "";
  
      container.innerHTML = `
        <div class="state-box">
          <h3>Unable to load the current issue</h3>
          <p>We could not retrieve issue data right now. Please try again in a moment.</p>
          <button type="button" class="btn btn-primary btn-retry" id="retryLoad">Try Again</button>
        </div>`;
  
      const retryBtn = document.getElementById("retryLoad");
      if (retryBtn) {
        retryBtn.addEventListener("click", loadCurrentIssue);
      }
    }
  
    async function loadCurrentIssue() {
      container.innerHTML = `
        <div class="state-box state-loading">
          <div class="spinner" aria-hidden="true"></div>
          <p>Loading published articles…</p>
        </div>`;
  
      try {
        const response = await fetch(ENDPOINT, {
          headers: { Accept: "application/json" },
        });
  
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
  
        const data = await response.json();
  
        if (!data.issue) {
          renderNoIssue();
          return;
        }
  
        renderIssue(data.issue);
        renderArticles(data.articles || []);
      } catch (err) {
        renderError();
      }
    }
  
    loadCurrentIssue();
  })();