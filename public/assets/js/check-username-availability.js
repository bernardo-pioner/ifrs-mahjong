import {
  checkUsernameInput,
  debounce,
  setElementHidden,
} from "./auth-form-utils.js";

export async function checkUsernameAvailability(username) {
  username = username.trim();
  if (!username) {
    return false;
  }

  try {
    const response = await fetch(
      `/username-disponivel?username=${encodeURIComponent(username)}`,
    );
    if (!response.ok) {
      throw new Error("Erro na requisição!");
    }
    const data = await response.json();
    return data.available === true;
  } catch {
    return null;
  }
}

function showUsernameFormatError() {
  const messageDiv = document.getElementById("usernameAvailabilityMessage");
  const errorDiv = document.getElementById("usernameErrorMessage");
  const errorTxt = document.getElementById("usernameErrorTxt");

  setElementHidden(messageDiv, true);
  messageDiv?.classList.remove("availabilityMessage");

  if (errorTxt) {
    errorTxt.textContent =
      "O apelido deve ter de 3 a 20 caracteres, apenas letras e números";
  }
  setElementHidden(errorDiv, false);
}

async function showUsernameAvailabilityMessage(username) {
  const messageDiv = document.getElementById("usernameAvailabilityMessage");
  const messageTxt = document.getElementById("usernameAvailabilityMessageTxt");
  const messageImg = document.getElementById("usernameAvailabilityMessageImg");
  const errorDiv = document.getElementById("usernameErrorMessage");

  username = username.trim();

  if (!username) {
    messageTxt.textContent = "";
    messageImg.removeAttribute("src");
    setElementHidden(messageDiv, true);
    messageDiv.classList.remove("availabilityMessage");
    setElementHidden(errorDiv, true);
    return;
  }

  if (!checkUsernameInput(username)) {
    showUsernameFormatError();
    return;
  }

  setElementHidden(errorDiv, true);

  const isAvailable = await checkUsernameAvailability(username);

  if (isAvailable === null) {
    messageTxt.textContent =
      "Não foi possível identificar a disponibilidade do apelido. Tente novamente";
    messageImg.setAttribute("src", "/assets/img/red_x.png");
    messageTxt.classList.remove("field-available-txt", "field-not-available-txt");
    messageTxt.classList.add("field-not-available-txt");
    messageDiv.classList.add("availabilityMessage");
    setElementHidden(messageDiv, false);
    return;
  }

  messageTxt.textContent = "";
  messageTxt.append('O apelido "');

  const strong = document.createElement("strong");
  strong.textContent = username;
  messageTxt.append(
    strong,
    `" ${isAvailable ? "está disponível" : "não está disponível"}`,
  );

  const textClass = isAvailable
    ? "field-available-txt"
    : "field-not-available-txt";
  const imageSrc = isAvailable
    ? "/assets/img/green_check.png"
    : "/assets/img/red_x.png";

  messageTxt.classList.remove("field-available-txt", "field-not-available-txt");
  messageTxt.classList.add(textClass);
  messageImg.setAttribute("src", imageSrc);
  messageDiv.classList.add("availabilityMessage");
  setElementHidden(messageDiv, false);
}

const debouncedCheckUsernameAvailability = debounce((event) => {
  showUsernameAvailabilityMessage(event.target.value);
}, 500);

document.addEventListener("DOMContentLoaded", () => {
  const usernameInput = document.getElementById("usernameInput");
  usernameInput?.addEventListener("input", debouncedCheckUsernameAvailability);
});
