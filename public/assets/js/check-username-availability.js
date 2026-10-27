import { checkUsernameInput } from "./check-username-register-input.js";

function debounce(fn, delay) {
  let timer;
  return function (...args) {
    clearTimeout(timer); // Cancela o timer anterior
    timer = setTimeout(() => {
      fn.apply(this, args); // Executa a função após o tempo de espera
    }, delay);
  };
}

export async function checkUsernameAvailability(username) {
  username = username.trim();
  try {
    const response = await fetch(
      `/username-disponivel?username=${encodeURIComponent(username)}`,
    );
    if (!response.ok) {
      throw new Error("Erro na requisição!");
    }
    const data = await response.json();
    const username_available = data.available;
    return username_available;
  } catch (error) {
    console.error("Erro ao buscar dados:", error); // verificar que retorno é correto de exibir aqui
  }
}

function showUsernameAvailabilityMessage(username) {
  const messageDiv = document.getElementById("availabilityMessage");
  const messageTxt = document.getElementById("availabilityMessageTxt");
  const messageImg = document.getElementById("availabilityMessageImg");
  const usernameInputErrorDiv = document.getElementById("usernameErrorMessage");

  if (!username.trim() || username.length < 3) {
    messageTxt.textContent = "";
    messageImg.removeAttribute("src");
    messageDiv.classList.add("hidden");
    messageDiv.classList.remove("availabilityMessage");
    return;
  }

  const isAvailable = checkUsernameAvailability(username);

  username = username.trim();
  if (username.length >= 3) {
    if (username.includes(" ")) {
      checkUsernameInput();
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
      ? "username-available-txt"
      : "username-not-available-txt";

    const imageSrc = isAvailable
      ? "/assets/img/green_check.png"
      : "/assets/img/red_x.png";

    messageTxt.classList.remove(
      "username-available-txt",
      "username-not-available-txt",
    );
    messageTxt.classList.add(textClass);

    messageImg.setAttribute("src", imageSrc);

    if (!usernameInputErrorDiv.classList.contains("hidden")) {
      usernameInputErrorDiv.classList.add("hidden");
    }

    messageDiv.classList.remove("hidden");
    messageDiv.classList.add("availabilityMessage");
  }
}

const debouncedCheckUsernameAvailability = debounce((event) => {
  showUsernameAvailabilityMessage(event.target.value);
}, 500);

document.addEventListener("DOMContentLoaded", () => {
  const usernameInput = document.getElementById("usernameInput");
  usernameInput.addEventListener("input", debouncedCheckUsernameAvailability);
});