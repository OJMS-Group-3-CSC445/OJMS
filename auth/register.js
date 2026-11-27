(function () {
  const api = window.OJMSApi;
  const registerSection = document.querySelector(".register-section");
  const successSection = document.querySelector(".success-section");
  const registerForm = document.querySelector(".register-form");
  const nameInput = document.getElementById("name");
  const emailInput = document.getElementById("email");
  const phoneInput = document.getElementById("phone");
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirm-password");
  const submitButton = registerForm.querySelector('button[type="submit"]');
  const feedback = document.getElementById("register-feedback");
  const submitLabel = submitButton.textContent.trim();

  function setFeedback(message, kind = "error") {
    feedback.textContent = message;
    feedback.dataset.kind = kind;
    feedback.hidden = !message;
  }

  function setPasswordsValidity() {
    if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
      confirmPasswordInput.setCustomValidity("Passwords do not match.");
    } else {
      confirmPasswordInput.setCustomValidity("");
    }
  }

  // Independent Show/Hide toggles for Password and Confirm Password
  document.querySelectorAll("[data-toggle-password]").forEach((button) => {
    const targetId = button.dataset.togglePassword;
    const input = document.getElementById(targetId);
    if (!input) return;

    const eyeIcon = button.querySelector(".icon-eye");
    const eyeOffIcon = button.querySelector(".icon-eye-off");
    const fieldLabel = targetId === "confirm-password" ? "confirm password" : "password";

    button.addEventListener("click", () => {
      const isVisible = input.type === "text";
      input.type = isVisible ? "password" : "text";
      if (eyeIcon && eyeOffIcon) {
        eyeIcon.hidden = !isVisible;
        eyeOffIcon.hidden = isVisible;
      }
      button.setAttribute("aria-label", isVisible ? `Show ${fieldLabel}` : `Hide ${fieldLabel}`);
      button.setAttribute("aria-pressed", String(!isVisible));
    });
  });

  passwordInput.addEventListener("input", setPasswordsValidity);
  confirmPasswordInput.addEventListener("input", setPasswordsValidity);
  nameInput.addEventListener("input", () => nameInput.setCustomValidity(""));

  function extractErrorMessage(error) {
    if (error?.errors && typeof error.errors === "object") {
      const firstKey = Object.keys(error.errors)[0];
      const firstVal = error.errors[firstKey];
      if (Array.isArray(firstVal) && firstVal[0]) {
        return firstVal[0];
      }
    }
    return error?.message || "Registration failed. Please check your details and try again.";
  }

  registerForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (submitButton.disabled) return;

    const nameParts = nameInput.value.trim().split(/\s+/).filter(Boolean);
    if (nameParts.length < 2) {
      nameInput.setCustomValidity("Please enter both your first name and last name.");
      nameInput.reportValidity();
      return;
    }
    nameInput.setCustomValidity("");

    setPasswordsValidity();
    if (!registerForm.reportValidity()) return;

    const [firstName, ...lastNameParts] = nameParts;
    const registeredEmail = emailInput.value.trim();

    submitButton.disabled = true;
    submitButton.textContent = "Creating account...";
    setFeedback("");

    try {
      await api.request("/register", {
        method: "POST",
        body: JSON.stringify({
          first_name: firstName,
          last_name: lastNameParts.join(" "),
          email: registeredEmail,
          phone: phoneInput.value.trim(),
          password: passwordInput.value,
          password_confirmation: confirmPasswordInput.value,
        }),
      });

      registerSection.hidden = true;
      successSection.hidden = false;
      const msgEl = successSection.querySelector(".verified-message");
      if (msgEl) {
        msgEl.textContent = `We sent a verification link to ${registeredEmail}. Verify your account before signing in.`;
      }
      registerForm.reset();
    } catch (error) {
      setFeedback(extractErrorMessage(error), "error");
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = submitLabel;
    }
  });
})();