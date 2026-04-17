const solicitarCuentaText = document.getElementById("solicitarCuentaText");
const passwordInput = document.getElementById("passwordInput");
const togglePassword = document.getElementById("togglePassword");

if (solicitarCuentaText) {
  solicitarCuentaText.addEventListener("click", function () {
    window.location.href = "./pages/register.html";
  });
}

if (togglePassword && passwordInput) {
  togglePassword.addEventListener("click", function () {
    const isPassword = passwordInput.type === "password";

    passwordInput.type = isPassword ? "text" : "password";
    togglePassword.classList.toggle("bx-hide");
    togglePassword.classList.toggle("bx-show");
  });
}