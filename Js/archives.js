// TEMPORARY ARCHIVES DATA
// Set this to an empty array [] to test the empty state.

const archives = [
    {
        year: 2026,
        issues: [
            {
                volume: 12,
                issue: 2,
                date: "June 2026",
                articles: [
                    {
                        title: "Designing equitable peer review systems",
                        authors: "M. Okafor · J. Mensah",
                        abstract: "A practical framework for making peer review more transparent, inclusive, and constructive across disciplines.",
                        fileUrl: "#"
                    },
                    {
                        title: "Open data practices in emerging research fields",
                        authors: "A. Ibrahim · L. Chen",
                        abstract: "This article examines how responsible data sharing can strengthen reproducibility and collaboration in emerging fields.",
                        fileUrl: "#"
                    },
                    {
                        title: "The role of editorial leadership in scholarly trust",
                        authors: "E. Adeyemi · R. Patel",
                        abstract: "An analysis of the editorial practices that build confidence in research quality and scholarly communication.",
                        fileUrl: "#"
                    }
                ]
            },
            {
                volume: 12,
                issue: 1,
                date: "March 2026",
                articles: []
            }
        ]
    },
    {
        year: 2025,
        issues: [
            { volume: 11, issue: 4, date: "December 2025", articles: [] },
            { volume: 11, issue: 3, date: "September 2025", articles: [] },
            { volume: 11, issue: 2, date: "June 2025", articles: [] }
        ]
    }
];

// DISPLAY ARTICLE CARDS
function displayArchiveArticles(articles) {
    if (!articles || articles.length === 0) {
        return '<p class="empty-archives">No articles available for this issue yet.</p>';
    }

    return articles.map(article => `
        <article class="archive-article">
            <div class="archive-article-info">
                <h4>${article.title}</h4>
                <p class="article-authors">${article.authors}</p>
                <p class="article-abstract">${article.abstract}</p>
            </div>

            <a href="${article.fileUrl}"
               class="read-article"
               target="_blank"
               rel="noopener">
                Read Article
            </a>
        </article>
    `).join("");
}

// DISPLAY ARCHIVES
function displayArchives() {
    const container = document.getElementById("archives-container");

    if (!archives || archives.length === 0) {
        container.innerHTML = `
            <div class="archives-container">
                <p class="empty-archives">
                    There are no past issues yet.
                </p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="archives-container">
            ${archives.map(yearGroup => `
                <section class="archive-year">
                    <h2>${yearGroup.year}</h2>

                    ${yearGroup.issues.map((issue, index) => {
                        const issueId = `${yearGroup.year}-${issue.volume}-${issue.issue}`;

                        return `
                            <div class="archive-issue">
                                <div class="issue-row">
                                    <div class="issue-details">
                                        <h3>
                                            Volume ${issue.volume}, Issue ${issue.issue}
                                        </h3>
                                        <p>${issue.date}</p>
                                    </div>

                                    <button
                                        class="view-issue"
                                        type="button"
                                        aria-expanded="false"
                                        aria-controls="articles-${issueId}"
                                        data-issue-id="${issueId}">
                                        View Issue
                                    </button>
                                </div>

                                <div
                                    class="issue-articles"
                                    id="articles-${issueId}"
                                    hidden>
                                    ${displayArchiveArticles(issue.articles)}
                                </div>
                            </div>
                        `;
                    }).join("")}
                </section>
            `).join("")}
        </div>
    `;

    // Handle View Issue and Hide Issue buttons
    container.querySelectorAll(".view-issue").forEach(button => {
        button.addEventListener("click", () => {
            const issueId = button.dataset.issueId;
            const articles = document.getElementById(`articles-${issueId}`);

            const isExpanded = button.getAttribute("aria-expanded") === "true";

            articles.hidden = isExpanded;
            button.setAttribute("aria-expanded", String(!isExpanded));
            button.textContent = isExpanded ? "View Issue" : "Hide Issue";
        });
    });

    // Expand the first issue by default, as shown in Figma
    const firstIssueButton = container.querySelector(".view-issue");

    if (firstIssueButton) {
        firstIssueButton.click();
    }
}


// Display the Archives page
displayArchives();
