const goToLoginButton = document.getElementById("goToLoginButton");
const passwordInput = document.getElementById("password");
const togglePassword = document.getElementById("togglePassword");

if (goToLoginButton) {
  goToLoginButton.addEventListener("click", function () {
    window.location.href = "../index.html";
  });
}

if (togglePassword && passwordInput) {
  togglePassword.addEventListener("click", function () {
    const icon = togglePassword.querySelector("i");
    const isPassword = passwordInput.type === "password";

    passwordInput.type = isPassword ? "text" : "password";

    if (icon) {
      icon.classList.toggle("bx-hide");
      icon.classList.toggle("bx-show");
    }
  });
}