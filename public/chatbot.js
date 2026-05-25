function sendMessage() {
    let userInput = document.getElementById("userInput").value;
    if (!userInput.trim()) return;
  
    // Clear input field immediately for better UX
    document.getElementById("userInput").value = "";
    
    // Add user message with new styling
    const userMessageDiv = document.createElement("div");
    userMessageDiv.className = "message user-message";
    userMessageDiv.textContent = userInput;
    document.getElementById("chatlogs").appendChild(userMessageDiv);
    
    // Auto scroll to bottom
    const chatLogs = document.getElementById("chatlogs");
    chatLogs.scrollTop = chatLogs.scrollHeight;
    
    // Show typing indicator
    const typingIndicator = document.createElement("div");
    typingIndicator.className = "message bot-message typing";
    typingIndicator.textContent = "";
    document.getElementById("chatlogs").appendChild(typingIndicator);
    chatLogs.scrollTop = chatLogs.scrollHeight;
  
    fetch("responses.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "message=" + encodeURIComponent(userInput)
    })
      .then(res => res.text())
      .then(response => {
        // Remove typing indicator
        document.getElementById("chatlogs").removeChild(typingIndicator);
        
        // Add bot message with new styling
        const botMessageDiv = document.createElement("div");
        botMessageDiv.className = "message bot-message";
        botMessageDiv.textContent = response;
        document.getElementById("chatlogs").appendChild(botMessageDiv);
        
        // Auto scroll to bottom again
        chatLogs.scrollTop = chatLogs.scrollHeight;
      });
}

// Allow submitting with Enter key
document.getElementById("userInput").addEventListener("keypress", function(event) {
    if (event.key === "Enter") {
        sendMessage();
    }
});

// Add initial greeting when page loads
window.onload = function() {
    setTimeout(function() {
        const welcomeMessage = document.createElement("div");
        welcomeMessage.className = "message bot-message";
        welcomeMessage.textContent = "Hello! How can I help you today? You can ask about reservations, hours, printing, or other lab services.";
        document.getElementById("chatlogs").appendChild(welcomeMessage);
    }, 500);
}
  