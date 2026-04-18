const familiarMessageText = document.getElementById("familiarMessageText")

const familiarHomeData = {
  message: "Cuidar a quienes una vez nos cuidaron es uno de los mayores honores."
}

if (familiarMessageText) {
  familiarMessageText.textContent = familiarHomeData.message
}