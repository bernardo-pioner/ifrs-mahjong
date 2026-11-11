import { checkEmailAvailability } from "./check-email-availability.js";
import { checkUsernameAvailability } from "./check-username-availability.js";
import {
  checkEmailInput,
  checkPasswordInput,
  checkUsernameInput,
  setElementHidden,
} from "./auth-form-utils.js";

document.addEventListener("DOMContentLoaded", () => {
  const submitRegisterButton = document.getElementById("submitRegisterButton");

  submitRegisterButton?.addEventListener("click", async (event) => {
    event.preventDefault();

    if (submitRegisterButton.disabled) return;

    submitRegisterButton.disabled = true;

    try {
      await checkUserRegisterInput();
    } finally {
      submitRegisterButton.disabled = false;
    }
  });
});

async function checkUserRegisterInput() {
  const registerError = document.getElementById("registerError");
  setElementHidden(registerError, true);

  const username = document.getElementById("usernameInput").value;
  const email = document.getElementById("emailInput").value;
  const password = document.getElementById("passwordInput").value;

  const usernameAvailabilityMessage = document.getElementById(
    "usernameAvailabilityMessage",
  );
  const emailAvailabilityMessage = document.getElementById(
    "emailAvailabilityMessage",
  );

  const usernameInputErrorDiv = document.getElementById("usernameErrorMessage");
  const usernameInputErrorTxt = document.getElementById("usernameErrorTxt");
  const emailInputErrorTxt = document.getElementById("emailErrorTxt");
  const emailInputErrorDiv = document.getElementById("emailErrorMessage");
  const passwordInputErrorTxt = document.getElementById("passwordErrorTxt");
  const passwordInputErrorDiv = document.getElementById("passwordErrorMessage");

  const isUsernameValid = checkUsernameInput(username);
  const isEmailValid = checkEmailInput(email);
  const isPasswordValid = checkPasswordInput(password);

  const isUsernameAvailable = isUsernameValid
    ? await checkUsernameAvailability(username)
    : false;
  const isEmailAvailable = isEmailValid
    ? await checkEmailAvailability(email)
    : false;

  if (!isUsernameValid) {
    setElementHidden(usernameAvailabilityMessage, true);
    usernameInputErrorTxt.textContent =
      "O apelido deve ter de 3 a 20 caracteres, apenas letras e números";
    setElementHidden(usernameInputErrorDiv, false);
  } else {
    setElementHidden(usernameInputErrorDiv, true);
  }

  if (!isEmailValid) {
    setElementHidden(emailAvailabilityMessage, true);
    emailInputErrorTxt.textContent =
      "O e-mail informado deve estar em um formato válido";
    setElementHidden(emailInputErrorDiv, false);
  } else {
    setElementHidden(emailInputErrorDiv, true);
  }

  if (!isPasswordValid) {
    passwordInputErrorTxt.textContent =
      "A senha deve ter no mínimo 8 caracteres e conter pelo menos um número";
    setElementHidden(passwordInputErrorDiv, false);
  } else {
    setElementHidden(passwordInputErrorDiv, true);
  }

  if (
    !isUsernameValid ||
    !isEmailValid ||
    !isPasswordValid ||
    isUsernameAvailable !== true ||
    isEmailAvailable !== true
  ) {
    return false;
  }

  document.getElementById("registerForm").requestSubmit();
}
