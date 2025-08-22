"use strict";

function sendMessage() {
  var userInput = document.getElementById("userInput").value;
  if (!userInput.trim()) return;
  document.getElementById("chatlogs").innerHTML += "<div><b>You:</b> ".concat(userInput, "</div>");
  fetch("responses.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: "message=" + encodeURIComponent(userInput)
  }).then(function (res) {
    return res.text();
  }).then(function (response) {
    document.getElementById("chatlogs").innerHTML += "<div><b>Bot:</b> ".concat(response, "</div>");
    document.getElementById("userInput").value = "";
  });
}