(function () {
  const API_BASE_URL = window.OJMS_API_BASE_URL || "http://172.20.10.2:8000/api";
  const TOKEN_KEY = "ojms_access_token";
  const USER_KEY = "ojms_user";

  function getToken() {
    return sessionStorage.getItem(TOKEN_KEY);
  }

  function getUser() {
    const storedUser = sessionStorage.getItem(USER_KEY);
    if (!storedUser) return null;

    try {
      const user = JSON.parse(storedUser);
      if (!user || typeof user !== "object" || Array.isArray(user)) {
        clearSession();
        return null;
      }
      return user;
    } catch {
      clearSession();
      return null;
    }
  }

  function setToken(token) {
    if (typeof token === "string" && token) {
      sessionStorage.setItem(TOKEN_KEY, token);
    } else {
      clearSession();
    }
  }

  function setSession(token, user) {
    if (
      typeof token !== "string" ||
      !token ||
      !user ||
      typeof user !== "object" ||
      Array.isArray(user)
    ) {
      throw new Error("The login response did not include a valid token and user.");
    }
    sessionStorage.setItem(TOKEN_KEY, token);
    sessionStorage.setItem(USER_KEY, JSON.stringify(user));
  }

  function clearSession() {
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
  }

  function redirectToLogin() {
    document.documentElement.hidden = true;
    const loginUrl = new URL("../auth/login.html", window.location.href);
    loginUrl.searchParams.set(
      "next",
      `${window.location.pathname}${window.location.search}${window.location.hash}`
    );
    window.location.replace(loginUrl.href);
  }

  function requireRole(role) {
    const token = getToken();
    const user = getUser();
    if (token && user && user.role === role) return true;

    const wrongRole = Boolean(token && user && user.role !== role);
    clearSession();
    const loginUrl = new URL("../auth/login.html", window.location.href);
    loginUrl.searchParams.set(
      "next",
      `${window.location.pathname}${window.location.search}${window.location.hash}`
    );
    if (wrongRole) loginUrl.searchParams.set("reason", "role");
    window.location.replace(loginUrl.href);
    return false;
  }

  function formatErrorMessage(message, fallback) {
    const messages = [];
    function collect(value) {
      if (typeof value === "string" && value.trim()) {
        messages.push(value.trim());
      } else if (Array.isArray(value)) {
        value.forEach(collect);
      } else if (value && typeof value === "object") {
        Object.values(value).forEach(collect);
      }
    }
    collect(message);
    return messages.join(" ") || fallback;
  }

  async function request(path, options = {}) {
    const headers = new Headers({ Accept: "application/json", ...(options.headers || {}) });
    const token = getToken();
    if (token) headers.set("Authorization", `Bearer ${token}`);

    const isFormData = options.body instanceof FormData;
    if (options.body && !isFormData && !headers.has("Content-Type")) {
      headers.set("Content-Type", "application/json");
    }

    let response;
    try {
      response = await fetch(`${API_BASE_URL}${path}`, {
        ...options,
        headers,
      });
    } catch {
      throw new Error(`Could not reach the journal API at ${API_BASE_URL}.`);
    }

    let payload;
    try {
      payload = await response.json();
    } catch {
      payload = null;
    }

    if (!response.ok || payload?.status === false) {
      if (response.status === 401 && token) {
        clearSession();
        if (window.location.pathname.includes("/author/")) redirectToLogin();
      }
      const fallback = response.status === 401 || response.status === 403
        ? "Your session has expired or does not have author access. Please sign in again."
        : `The request failed (${response.status}).`;
      throw new Error(formatErrorMessage(payload?.message, fallback));
    }

    return payload;
  }

  window.OJMSApi = {
    baseUrl: API_BASE_URL,
    getToken,
    getUser,
    setToken,
    setSession,
    clearSession,
    requireRole,
    request,
    formatErrorMessage,
  };
})();
