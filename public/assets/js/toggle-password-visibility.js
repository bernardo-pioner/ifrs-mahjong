document.addEventListener("DOMContentLoaded", () => {
  const passwordInput = document.getElementById("passwordInput");
  const passwordToggler = document.getElementById("password-visibility-icon");

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

  passwordToggler.addEventListener("click", togglePasswordVisibility);
  passwordToggler.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      togglePasswordVisibility();
    }
  });
});
