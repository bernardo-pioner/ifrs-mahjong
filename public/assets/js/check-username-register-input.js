import { checkUsernameAvailability } from "./check-username-availability.js";

document.addEventListener("DOMContentLoaded", () => {
  const submitRegisterButton = document.getElementById("submitRegisterButton");
  submitRegisterButton.addEventListener("click", function (event) {
    event.preventDefault();
    checkUserRegisterInput();
  });
});

export function checkUsernameInput() {
  const username = document.getElementById("usernameInput").value;
  const usernameInputErrorDiv = document.getElementById("usernameErrorMessage");
  const usernameInputErrorTxt = document.getElementById("usernameErrorTxt");

  const availabilityMessage = document.getElementById("availabilityMessage");

  if (username.trim() === "" || username.length < 3 || username.includes(" ")) {
    if (!availabilityMessage.classList.contains("hidden")) {
      availabilityMessage.classList.add("hidden");
    }
    usernameInputErrorTxt.textContent =
      "Deve conter, no mínimo, 3 caracteres e não haver espaços";
    usernameInputErrorDiv.classList.remove("hidden");
    return false;
  } else {
    return true;
  }
}

function checkEmailInput() {
  const email = document.getElementById("emailInput").value;
  const emailInputErrorTxt = document.getElementById("emailErrorTxt");
  const emailInputErrorDiv = document.getElementById("emailErrorMessage");

  const emailRegexPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegexPattern.test(email)) {
    emailInputErrorTxt.textContent =
      "O e-mail informado deve estar em um formato válido";
    emailInputErrorDiv.classList.remove("hidden");
    return false;
  } else {
    emailInputErrorDiv.classList.add("hidden");
    return true;
  }
}

async function checkEmailAvailability(email) {
  email = email.trim();
  const registerError = document.getElementById("registerError");
  try {
    const response = await fetch(
      `/email-disponivel?email=${encodeURIComponent(email)}`,
    );
    if (!response.ok) {
      throw new Error("Erro na requisição!");
    }
    const data = await response.json();
    const email_available = data.available;
    return email_available;
  } catch (error) {
    registerError.textContent = "No momento, estamos com problemas técnicos. Tente novamente mais tarde";
    if (registerError.classList.contains("hidden")) {
      registerError.classList.remove("hidden");
    }
  }
}

function checkPasswordInput() {
  const password = document.getElementById("passwordInput").value;
  const passwordInputErrorTxt = document.getElementById("passwordErrorTxt");
  const passwordInputErrorDiv = document.getElementById("passwordErrorMessage");

  const nonSpecialCharactersRegexPattern = /[^a-zA-Z0-9]/;
  const lowerUpperLettersRegexPattern = /(?=.*\p{Ll})(?=.*\p{Lu})/u;
  if (
    password.length < 8 ||
    !nonSpecialCharactersRegexPattern.test(password) ||
    !lowerUpperLettersRegexPattern.test(password)
  ) {
    passwordInputErrorTxt.textContent =
      "Deve incluir caractere especial, maiúsculas e minúsculas";
    passwordInputErrorDiv.classList.remove("hidden");
    return false;
  } else {
    passwordInputErrorDiv.classList.add("hidden");
    return true;
  }
}

async function checkUserRegisterInput() {
  const username = document.getElementById("usernameInput").value;
  const email = document.getElementById("emailInput").value;

  const isUsernameAvailable = await checkUsernameAvailability(username);
  const isEmailAvailable = await checkEmailAvailability(email);

  const isUsernameValid = checkUsernameInput();
  const isEmailValid = checkEmailInput();
  const isPasswordValid = checkPasswordInput();

  if (
    !isUsernameValid ||
    !isEmailValid ||
    !isPasswordValid ||
    !isUsernameAvailable
  ) {
    if (!registerError.classList.contains("hidden")) {
      registerError.classList.add("hidden");
    }
    return false;
  }

  if (!isEmailAvailable) {
    if (registerError.classList.contains("hidden")) {
      registerError.classList.remove("hidden");
    }
    return false;
  } else {
    registerError.classList.add("hidden");
  }

  document.getElementById("registerForm").requestSubmit();
}
