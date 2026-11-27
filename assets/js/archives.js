(function () {
    const container = document.getElementById("archives-container");
    if (!container) return;
  
    // Bind View Issue / Hide Issue toggles
    container.querySelectorAll(".view-issue").forEach((button) => {
      button.addEventListener("click", () => {
        const panel = document.getElementById(button.dataset.target);
        if (!panel) return;
  
        const isExpanded = button.getAttribute("aria-expanded") === "true";
        const label = button.querySelector(".view-issue-label");
  
        if (isExpanded) {
          button.setAttribute("aria-expanded", "false");
          if (label) label.textContent = "View Issue";
          panel.classList.remove("is-open");
          setTimeout(() => {
            if (button.getAttribute("aria-expanded") === "false") {
              panel.hidden = true;
            }
          }, 280);
        } else {
          panel.hidden = false;
          requestAnimationFrame(() => panel.classList.add("is-open"));
          button.setAttribute("aria-expanded", "true");
          if (label) label.textContent = "Hide Issue";
        }
      });
    });
  
    // Scroll-reveal animation
    const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const items = container.querySelectorAll("[data-reveal]");
    if (prefersReduced || !("IntersectionObserver" in window) || !items.length) {
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
      { threshold: 0.08, rootMargin: "0px 0px -20px 0px" }
    );
  
    items.forEach((el) => observer.observe(el));
  })();