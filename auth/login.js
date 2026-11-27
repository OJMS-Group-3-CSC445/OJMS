(function () {
  const api = window.OJMSApi;
  const form = document.querySelector(".login-form");
  const emailInput = document.getElementById("email");
  const passwordInput = document.getElementById("password");
  const passwordToggle = document.querySelector(".password-toggle");
  const submitButton = form.querySelector('button[type="submit"]');
  const feedback = document.getElementById("login-feedback");
  const submitLabel = submitButton.textContent.trim();
  const eyeIcon = passwordToggle.querySelector(".icon-eye");
  const eyeOffIcon = passwordToggle.querySelector(".icon-eye-off");

  /**
   * Role-to-portal map aligned with the MySQL `users.role` enum.
   * Only confirmed, existing dashboard paths are configured.
   * Populate `defaultPath` and `allowedPaths` for portal_admin, managing_editor,
   * and reviewer as their portal pages are added.
   */
  const ROLE_PORTALS = {
    author: {
      label: "Author",
      defaultPath: "../author/dashboard.html",
      allowedPaths: [
        "/author/dashboard.html",
        "/author/submit-manuscript.html",
      ],
    },
    editor_in_chief: {
      label: "Editor-in-Chief",
      defaultPath: "../editor/dashboard.html",
      allowedPaths: [
        "/editor/dashboard.html",
      ],
    },
    portal_admin: {
      label: "Portal Administrator",
      defaultPath: null,
      allowedPaths: [],
    },
    managing_editor: {
      label: "Managing Editor",
      defaultPath: null,
      allowedPaths: [],
    },
    reviewer: {
      label: "Reviewer",
      defaultPath: null,
      allowedPaths: [],
    },
  };

  function setFeedback(message, kind = "error") {
    feedback.textContent = message;
    feedback.dataset.kind = kind;
    feedback.hidden = !message;
  }

  function getRedirectTarget(role) {
    const portal = ROLE_PORTALS[role];
    if (!portal || !portal.defaultPath) {
      return null;
    }

    const fallback = new URL(portal.defaultPath, window.location.href);
    const requested = new URLSearchParams(window.location.search).get("next");
    if (!requested) {
      return fallback.href;
    }

    // Reject protocol-relative or external URLs before resolution
    if (/^(\/\/|https?:|javascript:|data:)/i.test(requested.trim())) {
      return fallback.href;
    }

    try {
      const target = new URL(requested, window.location.href);
      const isSameOrigin = target.origin === window.location.origin;
      const isAllowedForRole = portal.allowedPaths.some((allowed) =>
        target.pathname.endsWith(allowed)
      );
      return isSameOrigin && isAllowedForRole ? target.href : fallback.href;
    } catch {
      return fallback.href;
    }
  }

  function handleAuthenticatedRole(role) {
    const portal = ROLE_PORTALS[role];
    if (!portal) {
      // Unrecognized role not in schema: clear invalid session
      api.clearSession();
      setFeedback("Your account role is not recognised. Please contact the editorial office.", "error");
      return;
    }

    const destination = getRedirectTarget(role);
    if (destination) {
      window.location.replace(destination);
      return;
    }

    // Valid authenticated role whose dashboard is not yet implemented:
    // keep the session intact and inform the user clearly.
    setFeedback(
      `Signed in as ${portal.label}. The ${portal.label} portal is not yet available in this interface.`,
      "info"
    );
  }

  // Query-string status messages
  const query = new URLSearchParams(window.location.search);
  if (query.get("signedOut") === "server") {
    setFeedback("You have been signed out.", "success");
  } else if (query.get("signedOut") === "local") {
    setFeedback(
      "Your local session was cleared, but the server sign-out could not be confirmed.",
      "error"
    );
  } else if (query.get("reason") === "role") {
    setFeedback("Your account does not have permission to access that portal.", "error");
  } else if (query.get("reason") === "expired") {
    setFeedback("Your session has expired. Please sign in again.", "error");
  }

  // Handle already-authenticated visitors without logging out valid roles
  if (api && api.getToken()) {
    const existingRole = api.getUser()?.role;
    if (existingRole && ROLE_PORTALS[existingRole]) {
      const existingDest = getRedirectTarget(existingRole);
      if (existingDest) {
        window.location.replace(existingDest);
        return;
      }
      setFeedback(
        `You are currently signed in as ${ROLE_PORTALS[existingRole].label}. That portal is not yet available in this interface.`,
        "info"
      );
    } else {
      api.clearSession();
    }
  }

  // Password visibility toggle
 
  
  passwordToggle.addEventListener("click", () => {
    const isVisible = passwordInput.type === "text";
    passwordInput.type = isVisible ? "password" : "text";
    if (eyeIcon && eyeOffIcon) {
      eyeIcon.hidden = !isVisible;
      eyeOffIcon.hidden = isVisible;
    }
    passwordToggle.setAttribute("aria-label", isVisible ? "Show password" : "Hide password");
    passwordToggle.setAttribute("aria-pressed", String(!isVisible));
  });

  // Login submission
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!form.reportValidity() || submitButton.disabled) return;

    submitButton.disabled = true;
    submitButton.textContent = "Signing in...";
    setFeedback("");

    try {
      const response = await api.request("/login", {
        method: "POST",
        body: JSON.stringify({
          email: emailInput.value.trim(),
          password: passwordInput.value,
        }),
      });

      const token = response?.data?.token;
      const user = response?.data?.user;
      if (typeof token !== "string" || !user || typeof user.role !== "string") {
        throw new Error("The login response was incomplete. Please try again.");
      }

      api.setSession(token, user);
      handleAuthenticatedRole(user.role);
    } catch (error) {
      // Ensure failed logins never leave a partial authenticated state
      if (api && typeof api.clearSession === "function") {
        api.clearSession();
      }
      setFeedback(
        error?.message || "Invalid email or password. Please try again.",
        "error"
      );
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = submitLabel;
    }
  });
})();