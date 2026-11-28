document.addEventListener("DOMContentLoaded", () => {
  bindPasswordToggle("passwordInput", "password-visibility-icon");
  bindPasswordToggle("passwordConfirmInput", "password-confirm-visibility-icon");
});

function bindPasswordToggle(inputId, iconId) {
  const passwordInput = document.getElementById(inputId);
  const passwordToggler = document.getElementById(iconId);

  if (!passwordInput || !passwordToggler) return;

  const showPassword = () => {
    passwordToggler.setAttribute("src", "/assets/img/login-eye-closed.png");
    passwordToggler.setAttribute("alt", "Ocultar senha");
    passwordToggler.setAttribute("aria-label", "Ocultar senha");
    passwordInput.setAttribute("type", "text");
  };

  const hidePassword = () => {
    passwordToggler.setAttribute("src", "/assets/img/login-eye-open.png");
    passwordToggler.setAttribute("alt", "Mostrar senha");
    passwordToggler.setAttribute("aria-label", "Mostrar senha");
    passwordInput.setAttribute("type", "password");
  };

  passwordToggler.setAttribute("role", "button");
  passwordToggler.setAttribute("tabindex", "0");
  hidePassword();

  const togglePasswordVisibility = () => {
    if (passwordInput.getAttribute("type") === "password") {
      showPassword();
    } else {
      hidePassword();
    }
  };

  // O ícone fica dentro do <label>. Sem impedir o mousedown, o navegador
  // foca o campo e engole o clique — sobretudo na confirmação da senha.
  passwordToggler.addEventListener("mousedown", (event) => {
    event.preventDefault();
  });
  passwordToggler.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();
    togglePasswordVisibility();
  });
  passwordToggler.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      togglePasswordVisibility();
    }
  });
}
