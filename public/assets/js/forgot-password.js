import { checkPasswordInput, setElementHidden } from "./auth-form-utils.js";

document.addEventListener("DOMContentLoaded", () => {
  setupPinBoxes();
  setupResendTimer();
  setupPasswordSubmit();
});

function setupPinBoxes() {
  const boxes = Array.from(document.querySelectorAll(".pin-digit"));
  if (boxes.length === 0) return;

  const focusAt = (index) => {
    const box = boxes[index];
    if (!box) return;
    box.focus();
    box.select();
  };

  boxes.forEach((box, index) => {
    box.addEventListener("input", () => {
      const digit = box.value.replace(/\D/g, "");
      box.value = digit.slice(-1);
      if (box.value !== "" && index < boxes.length - 1) {
        focusAt(index + 1);
      }
    });

    box.addEventListener("keydown", (event) => {
      if (event.key === "Backspace" && box.value === "" && index > 0) {
        event.preventDefault();
        boxes[index - 1].value = "";
        focusAt(index - 1);
      }
      if (event.key === "ArrowLeft" && index > 0) {
        event.preventDefault();
        focusAt(index - 1);
      }
      if (event.key === "ArrowRight" && index < boxes.length - 1) {
        event.preventDefault();
        focusAt(index + 1);
      }
    });

    box.addEventListener("paste", (event) => {
      event.preventDefault();
      const text = (event.clipboardData?.getData("text") || "").replace(/\D/g, "");
      if (text === "") return;
      text.split("").slice(0, boxes.length - index).forEach((digit, offset) => {
        boxes[index + offset].value = digit;
      });
      const next = Math.min(index + text.length, boxes.length - 1);
      focusAt(next);
    });
  });

  const form = document.getElementById("pinForm");
  form?.addEventListener("submit", (event) => {
    const pin = boxes.map((box) => box.value).join("");
    if (!/^\d{6}$/.test(pin)) {
      event.preventDefault();
    }
  });
}

function setupResendTimer() {
  const button = document.getElementById("resendEmailButton");
  if (!button) return;

  let wait = Number(button.dataset.wait || 0);
  if (!Number.isFinite(wait) || wait <= 0) {
    button.disabled = false;
    button.textContent = "Reenviar o e-mail";
    return;
  }

  const tick = () => {
    if (wait <= 0) {
      button.disabled = false;
      button.textContent = "Reenviar o e-mail";
      return;
    }
    button.disabled = true;
    button.textContent = `Reenviar o e-mail (${wait}s)`;
    wait -= 1;
    window.setTimeout(tick, 1000);
  };

  tick();
}

function setupPasswordSubmit() {
  const button = document.getElementById("submitResetPasswordButton");
  const form = document.getElementById("resetPasswordForm");
  if (!button || !form) return;

  button.addEventListener("click", (event) => {
    event.preventDefault();
    if (button.disabled) return;

    const password = document.getElementById("passwordInput").value;
    const passwordConfirm = document.getElementById("passwordConfirmInput").value;
    const passwordErrorTxt = document.getElementById("passwordErrorTxt");
    const passwordErrorDiv = document.getElementById("passwordErrorMessage");
    const passwordConfirmErrorTxt = document.getElementById("passwordConfirmErrorTxt");
    const passwordConfirmErrorDiv = document.getElementById("passwordConfirmErrorMessage");

    const isPasswordValid = checkPasswordInput(password);
    const passwordsMatch = password === passwordConfirm;

    if (!isPasswordValid) {
      passwordErrorTxt.textContent =
        "A senha deve ter no mínimo 8 caracteres e conter pelo menos um número";
      setElementHidden(passwordErrorDiv, false);
    } else {
      setElementHidden(passwordErrorDiv, true);
    }

    if (!passwordsMatch) {
      passwordConfirmErrorTxt.textContent = "As senhas não combinam.";
      setElementHidden(passwordConfirmErrorDiv, false);
    } else {
      setElementHidden(passwordConfirmErrorDiv, true);
    }

    if (!isPasswordValid || !passwordsMatch) return;

    button.disabled = true;
    form.requestSubmit();
  });
}
