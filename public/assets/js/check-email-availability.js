import {
  checkEmailInput,
  debounce,
  setElementHidden,
} from "./auth-form-utils.js";

export async function checkEmailAvailability(email) {
  email = email.trim();
  if (!email) {
    return false;
  }

  try {
    const response = await fetch(
      `/email-disponivel?email=${encodeURIComponent(email)}`,
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

function showEmailFormatError() {
  const messageDiv = document.getElementById("emailAvailabilityMessage");
  const errorDiv = document.getElementById("emailErrorMessage");
  const errorTxt = document.getElementById("emailErrorTxt");

  setElementHidden(messageDiv, true);
  messageDiv?.classList.remove("availabilityMessage");

  if (errorTxt) {
    errorTxt.textContent = "Informe um e-mail em formato válido";
  }
  setElementHidden(errorDiv, false);
}

async function showEmailAvailabilityMessage(email) {
  const messageDiv = document.getElementById("emailAvailabilityMessage");
  const messageTxt = document.getElementById("emailAvailabilityMessageTxt");
  const messageImg = document.getElementById("emailAvailabilityMessageImg");
  const errorDiv = document.getElementById("emailErrorMessage");

  email = email.trim();

  if (!email) {
    messageTxt.textContent = "";
    messageImg.removeAttribute("src");
    setElementHidden(messageDiv, true);
    messageDiv.classList.remove("availabilityMessage");
    setElementHidden(errorDiv, true);
    return;
  }

  if (!checkEmailInput(email)) {
    showEmailFormatError();
    return;
  }

  setElementHidden(errorDiv, true);

  const isAvailable = await checkEmailAvailability(email);

  if (isAvailable === null) {
    messageTxt.textContent =
      "Não foi possível identificar a disponibilidade do e-mail. Tente novamente";
    messageImg.setAttribute("src", "/assets/img/red_x.png");
    messageTxt.classList.remove("field-available-txt", "field-not-available-txt");
    messageTxt.classList.add("field-not-available-txt");
    messageDiv.classList.add("availabilityMessage");
    setElementHidden(messageDiv, false);
    return;
  }

  if (!isAvailable) {
    const strong = document.createElement("strong");
    strong.textContent = "Entrar";

    messageTxt.textContent = "";
    messageTxt.append(
      "O e-mail já está em uso. Clique em ",
      strong,
      " para fazer login.",
    );
    messageTxt.classList.remove("field-available-txt", "field-not-available-txt");
    messageTxt.classList.add("field-not-available-txt");
    messageImg.setAttribute("src", "/assets/img/red_x.png");
    messageDiv.classList.add("availabilityMessage");
    setElementHidden(messageDiv, false);
    return;
  }

  setElementHidden(messageDiv, true);
  messageDiv.classList.remove("availabilityMessage");
}

const debouncedCheckEmailAvailability = debounce((event) => {
  showEmailAvailabilityMessage(event.target.value);
}, 500);

document.addEventListener("DOMContentLoaded", () => {
  const emailInput = document.getElementById("emailInput");
  emailInput?.addEventListener("input", debouncedCheckEmailAvailability);
});
