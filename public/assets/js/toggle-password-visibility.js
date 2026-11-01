document.addEventListener("DOMContentLoaded", () => {
  const passwordInput = document.getElementById("passwordInput");
  const passwordToggler = document.getElementById("password-visibility-icon");
  passwordToggler.addEventListener("click", () => {
    if (passwordToggler.getAttribute("src").includes("login-eye-open")) {
      passwordToggler.setAttribute("src", "/assets/img/login-eye-closed.png");
      passwordInput.setAttribute("type","text");
    } else {
      passwordToggler.setAttribute("src", "/assets/img/login-eye-open.png");
      passwordInput.setAttribute("type","password");
    }
  });
});
