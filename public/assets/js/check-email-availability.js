import { debounce } from "./check-username-availability.js";
import { checkEmailInput } from "./check-user-register-input.js";

export async function checkEmailAvailability(email) {
  email = email.trim();
  if (!email) {
    return false;
  } else {
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
      return error;
    }
  }
}

async function showEmailAvailabilityMessage(email) {
  const messageDiv = document.getElementById("emailAvailabilityMessage");
  const messageTxt = document.getElementById("emailAvailabilityMessageTxt");
  const messageImg = document.getElementById("emailAvailabilityMessageImg");
  const emailInputErrorDiv = document.getElementById("emailErrorMessage");

  email = email.trim();

  if (!email || !checkEmailInput(email)) {
    messageTxt.textContent = "";
    messageImg.removeAttribute("src");
    messageDiv.classList.add("hidden");
    messageDiv.classList.remove("availabilityMessage");
    return;
  }

  const isAvailable = await checkEmailAvailability(email);

  if (isAvailable instanceof Error) {
    messageTxt.textContent =
      "Não foi possível identificar a disponibilidade do e-mail. Tente novamente";
    messageImg.setAttribute("src", "/assets/img/red_x.png");
    messageTxt.classList.add("username-not-available-txt");
    messageDiv.classList.add("availabilityMessage");
    messageDiv.classList.remove("hidden");
    return;
  }

  const strong = document.createElement("strong");
  strong.textContent = '"Entrar"';

  if (!isAvailable) {
    messageTxt.textContent = "";
    messageTxt.append(
      "O e-mail já está em uso. Clique em ",
      strong,
      " para fazer login.",
    );

    messageTxt.classList.add("field-not-available-txt");
    messageImg.setAttribute("src", "/assets/img/red_x.png");

    if (!emailInputErrorDiv.classList.contains("hidden")) {
      emailInputErrorDiv.classList.add("hidden");
    }

    messageDiv.classList.remove("hidden");
    messageDiv.classList.add("availabilityMessage");

    return;
  } else {
    if (!messageDiv.classList.contains("hidden")) {
        messageDiv.classList.add("hidden");
    }
  }
}

const debouncedCheckEmailAvailability = debounce((event) => {
  showEmailAvailabilityMessage(event.target.value);
}, 500);

document.addEventListener("DOMContentLoaded", () => {
  const emailInput = document.getElementById("emailInput");
  emailInput.addEventListener("input", debouncedCheckEmailAvailability);
});
