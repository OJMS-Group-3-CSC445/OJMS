// Sample issue information
const currentIssue = {
    title: "Current issue",
    volume: 12,
    issue: 2,
    publicationDate: "June 2026"
};


// Sample article information
const articles = [
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
        abstract: "An analysis of the editorial practices that build confidence in research quality, fairness, and scholarly communication.",
        fileUrl: "#"
    },
    {
        title: "Community-based methods for public scholarship",
        authors: "S. Ndlovu · K. Ahmed",
        abstract: "Exploring participatory methods that connect academic research with the communities it seeks to serve.",
        fileUrl: "#"
    },
    {
        title: "Measuring research impact beyond citations",
        authors: "L. Williams · T. Adeyemi",
        abstract: "A broader approach to understanding influence through engagement, application, and meaningful change beyond citation counts.",
        fileUrl: "#"
    },
    {
        title: "Editorial infrastructures for the open-access future",
        authors: "N. Mensah · C. Park",
        abstract: "Designing sustainable publishing systems that keep rigorous scholarship accessible to a global audience.",
        fileUrl: "#"
    }
];


// DISPLAY ISSUE INFORMATION
function displayIssue(issue) {

    document.getElementById("issue-title").textContent =
        issue.title;

    document.getElementById("issue-number").textContent =
        `Volume ${issue.volume}, Issue ${issue.issue}`;

    document.getElementById("issue-date").textContent =
        issue.publicationDate;
}


// DISPLAY ARTICLE CARDS
function displayArticles(articleList) {

    const articlesContainer =
        document.getElementById("articles-container");

    // Handle empty articles
    if (!articleList || articleList.length === 0) {
        articlesContainer.innerHTML =
            "<p>No articles have been published in this issue yet.</p>";
        return;
    }

    // Generate article cards
    articlesContainer.innerHTML = articleList.map(article => `
        <article class="article-card">
            <div class="article-info">
                <h2>${article.title}</h2>
                <p class="article-authors">
                    ${article.authors}
                </p>
                <p class="article-abstract">
                    ${article.abstract}
                </p>
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



// DISPLAY CURRENT ISSUE PAGE
function displayCurrentIssue() {
    const container = document.getElementById("articles-container");
    const browseArchives = document.querySelector(".browse-archives");
    // Check whether an issue exists
    if (!currentIssue) {
        document.getElementById("issue-title").textContent =
            "Current issue";
        document.getElementById("issue-number").textContent = "Volume 00, Issue 0, [Month Year]";
        document.getElementById("issue-date").textContent = "";
        
        container.innerHTML = `
            <p class ="empty-state">
                No issue has been published yet.
            </p>
        `;
        browseArchives.hidden = true;
        return;
    }

    browseArchives.hidden = false;

    // Display issue information
    displayIssue(currentIssue);

    // Display its articles
    displayArticles(articles);
}


// Run the function
displayCurrentIssue();