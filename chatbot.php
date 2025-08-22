<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = strtolower(trim($_POST['message']));

    $responses = [
        "hello" => "Hi there! How can I help you?",
        "how to reserve" => "To reserve a computer, just go to the reservation page and fill in the form.",
        "hours" => "We’re open from 9 AM to 10 PM daily!",
        "thank you" => "You're welcome! 😊",
        "bye" => "Goodbye! See you soon."
    ];

    $reply = "Sorry, I didn't understand that. Try asking something like 'how to reserve'.";
    if (array_key_exists($message, $responses)) {
        $reply = $responses[$message];
    }

    echo $reply;
}
?>
<div class="chatbox">
    <h3>Support Chat</h3>
    <div class="chatlogs" id="chatlogs"></div>
    <div class="chat-form">
        <input type="text" id="userInput" placeholder="Ask something...">
        <button onclick="sendMessage()">Send</button>
    </div>
</div>

<script src="chatbot.js"></script>
