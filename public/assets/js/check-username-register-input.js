document.addEventListener("DOMContentLoaded", () => {
  const submitRegisterButton = document.getElementById("submitRegisterButton");
  submitRegisterButton.addEventListener("click", function (event) {
    event.preventDefault();
    checkUserRegisterInput();
  });
});

let errors = false;

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
    errors = true;
  } else {
    errors = false;
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
    errors = true;
  } else {
    errors = false;
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
    errors = true;
  } else {
    errors = false;
  }
}

function checkUserRegisterInput() {
  checkUsernameInput();
  checkEmailInput();
  checkPasswordInput();

  if (!errors){

  }
}