(function () {
  const root = document.body.dataset.root || "";
  const links = [
    { key: "home", label: "Home", href: `${root}index.html` },
    { key: "about", label: "About", href: `${root}about.html` },
    { key: "current-issue", label: "Current issue", href: `${root}current-issue.html` },
    { key: "archives", label: "Archives", href: `${root}archives.html` },
    { key: "contact", label: "Contact us", href: `${root}contact.html` },
  ];

  const current = document.body.dataset.page;

  const navLinks = links
    .map(
      (l) =>
        `<a href="${l.href}"${l.key === current ? ' aria-current="page"' : ""}>${l.label}</a>`
    )
    .join("");

  const headerHTML = `
    <header class="site-header" id="siteHeader">
      <div class="container header-inner">
        <a class="brand" href="${root}index.html">
          <span class="brand-mark">O</span>
          <span>Journal of Applied Scholarship</span>
        </a>
        <button class="menu-toggle" id="menuToggle" aria-expanded="false" aria-controls="siteNav">Menu</button>
        <nav class="nav" id="siteNav" aria-label="Main">${navLinks}</nav>
        <div class="auth">
          <a class="btn btn-outline" href="${root}auth/login.html">Log in</a>
          <a class="btn btn-pill" href="${root}auth/register.html">Register</a>
        </div>
      </div>
    </header>`;

  const footerHTML = `
    <footer class="site-footer">
      <div class="container">
        <div class="footer-top">
          <div class="footer-brand">
            <strong>OJMS</strong>
            <span>Online Journal Management System</span>
          </div>
          <nav class="footer-links" aria-label="Footer">
            <a href="about.html">About</a>
            <a href="policies.html">Policies</a>
            <a href="support.html">Support</a>
            <a href="contact.html">Contact us</a>
          </nav>
          <a class="footer-email" href="mailto:editorial@ojms.org">editorial@ojms.org</a>
        </div>
        <p class="copyright">&copy; ${new Date().getFullYear()} OJMS &middot; Academic publishing, made clear.</p>
      </div>
    </footer>`;

  document.getElementById("site-header").innerHTML = headerHTML;
  document.getElementById("site-footer").innerHTML = footerHTML;

  // Mobile menu toggle
  const header = document.getElementById("siteHeader");
  const toggle = document.getElementById("menuToggle");
  toggle.addEventListener("click", () => {
    const open = header.classList.toggle("open");
    toggle.setAttribute("aria-expanded", String(open));
  });
})();