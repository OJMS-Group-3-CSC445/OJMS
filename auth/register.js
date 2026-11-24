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
  confirmPasswordInput.setCustomValidity(
    passwordInput.value === confirmPasswordInput.value
      ? ""
      : "Passwords do not match."
  );
}

passwordInput.addEventListener("input", setPasswordsValidity);
confirmPasswordInput.addEventListener("input", setPasswordsValidity);
nameInput.addEventListener("input", () => nameInput.setCustomValidity(""));

registerForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (submitButton.disabled) return;

  const nameParts = nameInput.value.trim().split(/\s+/);
  if (nameParts.length < 2 || !nameParts[0] || !nameParts[1]) {
    nameInput.setCustomValidity("Enter your first name and last name.");
    nameInput.reportValidity();
    return;
  }
  nameInput.setCustomValidity("");
  setPasswordsValidity();
  if (!registerForm.reportValidity()) return;

  const [firstName, ...lastNameParts] = nameParts;
  submitButton.disabled = true;
  submitButton.textContent = "Creating account...";
  setFeedback("");

  try {
    await api.request("/register", {
      method: "POST",
      body: JSON.stringify({
        first_name: firstName,
        last_name: lastNameParts.join(" "),
        email: emailInput.value.trim(),
        phone: phoneInput.value.trim(),
        password: passwordInput.value,
        password_confirmation: confirmPasswordInput.value,
      }),
    });
    const registeredEmail = emailInput.value.trim();
    registerSection.hidden = true;
    successSection.hidden = false;
    successSection.querySelector(".verified-message").textContent =
      `We sent a verification link to ${registeredEmail}. Verify your account before signing in.`;
    registerForm.reset();
  } catch (error) {
    setFeedback(error.message || "Registration failed. Please try again.");
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = submitLabel;
  }
});
