(function () {
    const prefersReducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)"
    ).matches;
  
    if (prefersReducedMotion || !("IntersectionObserver" in window)) {
      return;
    }
  
    const targets = document.querySelectorAll("[data-reveal]");
    if (!targets.length) return;
  
    // Enable initial hidden state only after JS is confirmed active
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
      {
        threshold: 0.12,
        rootMargin: "0px 0px -30px 0px",
      }
    );
  
    targets.forEach((el) => observer.observe(el));
  })();