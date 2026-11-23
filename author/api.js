(function () {
  const API_BASE_URL = window.OJMS_API_BASE_URL || "http://127.0.0.1:8000/api";
  const TOKEN_KEY = "ojms_access_token";

  function getToken() {
    return sessionStorage.getItem(TOKEN_KEY);
  }

  function setToken(token) {
    if (token) sessionStorage.setItem(TOKEN_KEY, token);
    else sessionStorage.removeItem(TOKEN_KEY);
  }

  function formatErrorMessage(message, fallback) {
    if (typeof message === "string" && message.trim()) return message;
    if (message && typeof message === "object") {
      return Object.values(message).flat().filter(Boolean).join(" ") || fallback;
    }
    return fallback;
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
    setToken,
    request,
    formatErrorMessage,
  };
})();
