(function () {
  const root = document.body.dataset.root || "";
  const links = [
    { key: "home", label: "Home", href: `${root}index.php` },
    { key: "about", label: "About", href: `${root}about.php` },
    { key: "current-issue", label: "Current Issue", href: `${root}current-issue.php` },
    { key: "archives", label: "Archives", href: `${root}archives.php` },
    { key: "contact-us", label: "Contact Us", href: `${root}contact.php` },
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
        <a class="brand" href="${root}index.php">
          <span class="brand-mark">O</span>
          <span>Journal of Applied Scholarship</span>
        </a>
        <button
          class="menu-toggle"
          id="menuToggle"
          type="button"
          aria-label="Toggle navigation menu"
          aria-expanded="false"
          aria-controls="siteNavWrapper"
        >
          <span class="hamburger-line" aria-hidden="true"></span>
          <span class="hamburger-line" aria-hidden="true"></span>
          <span class="hamburger-line" aria-hidden="true"></span>
        </button>
        <div class="nav-collapse" id="siteNavWrapper">
          <div class="nav-collapse-inner">
            <nav class="nav" id="siteNav" aria-label="Main">${navLinks}</nav>
            <div class="auth">
              <a class="btn btn-outline" href="${root}auth/login.html">Log in</a>
              <a class="btn btn-pill" href="${root}auth/register.html">Register</a>
            </div>
          </div>
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
            <a href="${root}about.php">About</a>
            <a href="${root}policies.php">Policies</a>
            <a href="${root}support.php">Support</a>
            <a href="${root}contact.php">Contact us</a>
          </nav>
          <a class="footer-email" href="mailto:editorial@ojms.org">editorial@ojms.org</a>
        </div>
        <p class="copyright">&copy; ${new Date().getFullYear()} OJMS &middot; Academic publishing, made clear.</p>
      </div>
    </footer>`;

  const headerMount = document.getElementById("site-header");
  const footerMount = document.getElementById("site-footer");
  if (headerMount) headerMount.innerHTML = headerHTML;
  if (footerMount) footerMount.innerHTML = footerHTML;

  const header = document.getElementById("siteHeader");
  const toggle = document.getElementById("menuToggle");
  const navWrapper = document.getElementById("siteNavWrapper");

  if (header && toggle) {
    const closeMenu = () => {
      header.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    };

    toggle.addEventListener("click", () => {
      const isOpen = header.classList.toggle("open");
      toggle.setAttribute("aria-expanded", String(isOpen));
    });

    // Close mobile menu when any navigation or auth link is clicked
    if (navWrapper) {
      navWrapper.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", closeMenu);
      });
    }

    // Keyboard accessibility: close on Escape
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && header.classList.contains("open")) {
        closeMenu();
        toggle.focus();
      }
    });
  }
})();