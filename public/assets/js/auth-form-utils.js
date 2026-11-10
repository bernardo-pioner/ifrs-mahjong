export function debounce(fn, delay) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => {
      fn.apply(this, args);
    }, delay);
  };
}

export function checkUsernameInput(username) {
  return /^[a-zA-Z0-9]{3,20}$/.test(username.trim());
}

export function checkEmailInput(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

export function checkPasswordInput(password) {
  return password.length >= 8 && /\d/.test(password);
}

export function setElementHidden(element, hidden) {
  if (!element) return;
  element.classList.toggle("hidden", hidden);
}
