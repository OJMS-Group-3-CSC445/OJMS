(function () {
  const api = window.OJMSApi;
  if (!window.OJMS_AUTHORIZED) return;
  const form = document.querySelector("#submit-form");
  const feedback = document.querySelector("#submit-feedback");
  const submitButton = form.querySelector('[type="submit"]');
  const fileInput = document.querySelector("#manuscript-file");
  const user = api.getUser();

  if (user) {
    const displayName = [user.first_name, user.middle_name, user.last_name]
      .filter(Boolean)
      .join(" ") || user.email;
    document.querySelectorAll(".author-name").forEach((element) => {
      element.textContent = displayName;
    });
  }

  function setFeedback(message, kind = "info") {
    feedback.textContent = message;
    feedback.dataset.kind = kind;
    feedback.hidden = !message;
  }

  function setFormReady(ready) {
    submitButton.disabled = !ready;
  }

  document.querySelector('[data-action="logout"]').addEventListener("click", async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    let signedOut = "server";
    try {
      await api.request("/logout", { method: "POST" });
    } catch {
      signedOut = "local";
    } finally {
      api.clearSession();
      const loginUrl = new URL("../auth/login.html", window.location.href);
      loginUrl.searchParams.set("signedOut", signedOut);
      window.location.replace(loginUrl.href);
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!form.reportValidity()) return;
    const file = fileInput.files[0];
    const allowedExtensions = /\.(pdf|doc|docx)$/i;
    if (!file || !allowedExtensions.test(file.name)) {
      setFeedback("Choose a PDF, DOC, or DOCX manuscript file.", "error");
      fileInput.focus();
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      setFeedback("The manuscript file must be 10 MB or smaller.", "error");
      fileInput.focus();
      return;
    }

    const formData = new FormData();
    formData.append("title", form.elements.title.value.trim());
    formData.append("abstract", form.elements.abstract.value.trim());
    formData.append("manuscript", file);
    const authors = form.elements.authors.value.split(",").map((name) => name.trim()).filter(Boolean);
    if (authors.length) formData.append("authors", authors.join(", "));

    setFormReady(false);
    submitButton.textContent = "Submitting…";
    setFeedback("Uploading your manuscript…");
    try {
      const result = await api.request("/articles", { method: "POST", body: formData });
      setFeedback(result.message || "Your manuscript was submitted successfully. You can track it from your dashboard.", "success");
      form.reset();
    } catch (error) {
      setFeedback(error.message || "Your manuscript could not be submitted. Please try again.", "error");
    } finally {
      submitButton.textContent = "Submit manuscript";
      setFormReady(Boolean(api.getToken()));
    }
  });
})();
