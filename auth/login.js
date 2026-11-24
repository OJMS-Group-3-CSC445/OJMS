const api = window.OJMSApi;
const form = document.querySelector(".login-form");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const passwordToggle = document.querySelector(".password-toggle");
const submitButton = form.querySelector('button[type="submit"]');
const feedback = document.getElementById("login-feedback");
const submitLabel = submitButton.textContent.trim();

function setFeedback(message, kind = "error") {
  feedback.textContent = message;
  feedback.dataset.kind = kind;
  feedback.hidden = !message;
}

function getRedirectTarget(role) {
  const isEditor = role === "editor_in_chief";
  const fallback = new URL(
    isEditor ? "../editor/dashboard.html" : "../author/dashboard.html",
    window.location.href
  );
  const requested = new URLSearchParams(window.location.search).get("next");
  if (!requested) return fallback.href;

  try {
    const target = new URL(requested, window.location.href);
    const allowedPaths = isEditor
      ? ["/editor/dashboard.html"]
      : [
      "/author/dashboard.html",
      "/author/submit-manuscript.html",
      ];
    const allowedTarget = allowedPaths.some((path) => target.pathname.endsWith(path));
    return target.origin === window.location.origin && allowedTarget
      ? target.href
      : fallback.href;
  } catch {
    return fallback.href;
  }
}

const query = new URLSearchParams(window.location.search);
if (query.get("signedOut") === "server") {
  setFeedback("You have been signed out.", "success");
} else if (query.get("signedOut") === "local") {
  setFeedback("Your local session was cleared, but the server sign-out could not be confirmed.", "error");
} else if (query.get("reason") === "role") {
  setFeedback("That account does not have access to this portal.", "error");
}

const supportedRoles = ["author", "editor_in_chief"];
if (api.getToken() && supportedRoles.includes(api.getUser()?.role)) {
  window.location.replace(getRedirectTarget(api.getUser().role));
} else if (api.getToken()) {
  api.clearSession();
}

passwordToggle.addEventListener("click", () => {
  const isVisible = passwordInput.type === "text";
  passwordInput.type = isVisible ? "password" : "text";
  passwordToggle.textContent = isVisible ? "Show" : "Hide";
  passwordToggle.setAttribute("aria-label", isVisible ? "Show password" : "Hide password");
  passwordToggle.setAttribute("aria-pressed", String(!isVisible));
});

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
    if (!supportedRoles.includes(user.role)) {
      try {
        await api.request("/logout", { method: "POST" });
      } finally {
        api.clearSession();
      }
      setFeedback("This frontend currently provides author and Editor-in-Chief portals only.", "error");
      return;
    }

    window.location.replace(getRedirectTarget(user.role));
  } catch (error) {
    setFeedback(error.message || "Unable to sign in. Please try again.", "error");
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = submitLabel;
  }
});