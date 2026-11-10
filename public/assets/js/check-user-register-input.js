import { checkEmailAvailability } from "./check-email-availability.js";
import { checkUsernameAvailability, debounce } from "./check-username-availability.js";

document.addEventListener("DOMContentLoaded", () => {
  const submitRegisterButton = document.getElementById("submitRegisterButton");

  submitRegisterButton.addEventListener("click", async (event) => {
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

export function checkUsernameInput(username) {
  return !(
    username.trim() === "" ||
    username.length < 3 ||
    username.includes(" ")
  );
}

export function checkEmailInput(email) {
  const emailRegexPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegexPattern.test(email);
}

function checkPasswordInput(password) {
  const nonSpecialCharactersRegexPattern = /[^a-zA-Z0-9]/;
  const lowerUpperLettersRegexPattern = /(?=.*\p{Ll})(?=.*\p{Lu})/u;
  return !(
    password.length < 8 ||
    !nonSpecialCharactersRegexPattern.test(password) ||
    !lowerUpperLettersRegexPattern.test(password)
  );
}

async function checkUserRegisterInput() {
  const registerError = document.getElementById("registerError");

  if (
    registerError !== null &&
    registerError !== undefined &&
    !registerError.classList.contains("hidden")
  ) {
    registerError.classList.add("hidden");
  }

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

  const isUsernameAvailable = await checkUsernameAvailability(username);
  const isEmailAvailable = await checkEmailAvailability(email);

  const isUsernameValid = checkUsernameInput(username);
  const isEmailValid = checkEmailInput(email);
  const isPasswordValid = checkPasswordInput(password);

  if (!isUsernameValid) {
    if (!usernameAvailabilityMessage.classList.contains("hidden")) {
      usernameAvailabilityMessage.classList.add("hidden");
    }
    usernameInputErrorTxt.textContent =
      "Deve conter, no mínimo, 3 caracteres e não haver espaços";
    usernameInputErrorDiv.classList.remove("hidden");
  } else {
    usernameInputErrorDiv.classList.add("hidden");
  }

  if (!isEmailValid) {
    if (!emailAvailabilityMessage.classList.contains("hidden")) {
      emailAvailabilityMessage.classList.add("hidden");
    }
    emailInputErrorTxt.textContent =
      "O e-mail informado deve estar em um formato válido";
    emailInputErrorDiv.classList.remove("hidden");
  } else {
    emailInputErrorDiv.classList.add("hidden");
  }

  if (!isPasswordValid) {
    passwordInputErrorTxt.textContent =
      "Deve incluir caractere especial, maiúsculas e minúsculas";
    passwordInputErrorDiv.classList.remove("hidden");
  } else {
    passwordInputErrorDiv.classList.add("hidden");
  }

  if (
    !isUsernameValid ||
    !isEmailValid ||
    !isPasswordValid ||
    !isUsernameAvailable ||
    !isEmailAvailable
  ) {
    return false;
  }

  document.getElementById("registerForm").requestSubmit();
}
