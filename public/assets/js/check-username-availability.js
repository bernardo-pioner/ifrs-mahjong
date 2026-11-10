export function debounce(fn, delay) {
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
  if (!username) {
    return false;
  } else {
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
      return error;
    }
  }
}

async function showUsernameAvailabilityMessage(username) {
  const messageDiv = document.getElementById("usernameAvailabilityMessage");
  const messageTxt = document.getElementById("usernameAvailabilityMessageTxt");
  const messageImg = document.getElementById("usernameAvailabilityMessageImg");
  const usernameInputErrorDiv = document.getElementById("usernameErrorMessage");

  username = username.trim();

  if (!username || username.length < 3) {
    messageTxt.textContent = "";
    messageImg.removeAttribute("src");
    messageDiv.classList.add("hidden");
    messageDiv.classList.remove("availabilityMessage");
    return;
  }

  const isAvailable = await checkUsernameAvailability(username);

  if (isAvailable instanceof Error) {
    messageTxt.textContent =
      "Não foi possível identificar a disponibilidade do apelido. Tente novamente";
    messageImg.setAttribute("src", "/assets/img/red_x.png");
    messageTxt.classList.add("username-not-available-txt");
    messageDiv.classList.add("availabilityMessage");
    messageDiv.classList.remove("hidden");
    return;
  }

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
      ? "field-available-txt"
      : "field-not-available-txt";

    const imageSrc = isAvailable
      ? "/assets/img/green_check.png"
      : "/assets/img/red_x.png";

    messageTxt.classList.remove(
      "field-available-txt",
      "field-not-available-txt",
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
