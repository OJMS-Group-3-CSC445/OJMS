(function () {
  const api = window.OJMSApi;
  const form = document.querySelector("#submit-form");
  const feedback = document.querySelector("#submit-feedback");
  const submitButton = form.querySelector('[type="submit"]');
  const fileInput = document.querySelector("#manuscript-file");

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
    try {
      await api.request("/logout", { method: "POST" });
      setFeedback("You have been signed out.");
    } catch (error) {
      setFeedback(`${error.message} Your local session has been cleared.`, "error");
    } finally {
      api.setToken(null);
      setFormReady(false);
      button.disabled = false;
    }
  });

  if (!api.getToken()) {
    setFeedback("Sign in before submitting a manuscript. This page needs an author access token.", "error");
    setFormReady(false);
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!api.getToken()) {
      setFeedback("Sign in before submitting a manuscript.", "error");
      return;
    }

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
