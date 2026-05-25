<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = strtolower(trim($_POST['message']));

    $responses = [
        "hello" => "Hi there! Welcome to our computer lab. How can I assist you today?",
        "hi" => "Hello! How can I help you with our computer lab services?",
        "hey" => "Hey there! What can I do for you today?",
        
        "how to reserve" => "To reserve a computer, go to the Reservations tab on our website and select your preferred time slot. You'll need your student ID to complete the reservation.",
        "reserve" => "Computer reservations can be made up to 7 days in advance. Just visit our website's Reservation page or stop by the front desk.",
        "booking" => "You can book a computer workstation through our online portal. Reservations are available in 1-hour blocks, with a maximum of 3 hours per day.",
        
        "hours" => "Blackout Esports Café is open from 9 AM to 10 PM Monday through Friday, and 10 AM to 8 PM on weekends.",
        "opening hours" => "We're open from 9 AM to 10 PM on weekdays and 10 AM to 8 PM on weekends.",
        "when are you open" => "The computer lab is available from 9 AM to 10 PM Monday-Friday and 10 AM to 8 PM on weekends.",
        
        "printing" => "We offer both black & white (10¢/page) and color printing (25¢/page). You can print from any workstation or upload files to our print server remotely.",
        "how to print" => "To print, select Print from any application, choose the lab printer, and collect your documents at the print station. Don't forget to log out when finished!",
        
        "software" => "Our computers have various software including Microsoft Office, Adobe Creative Suite, programming IDEs, and statistical packages. For a complete list, check our website.",
        "available software" => "We provide access to Microsoft Office, Adobe Creative Cloud, MATLAB, Python, R, and many other specialized software packages.",
        
        "wifi" => "Free WiFi is available throughout the lab. Connect to 'ComputerLab_Guest' and use the password posted on the bulletin board.",
        "internet" => "All our workstations have high-speed internet access. For WiFi, connect to 'ComputerLab_Guest' network.",
        
        "help" => "I can answer questions about reservations, hours, printing, available software, and technical support. What would you like to know?",
        "support" => "If you need technical support, please speak with one of our lab assistants at the help desk or call our support line at 555-123-4567.",
        
        "thank you" => "You're welcome! Let me know if you need anything else. 😊",
        "thanks" => "Happy to help! Feel free to ask if you have other questions.",
        
        "bye" => "Goodbye! Have a great day.",
        "goodbye" => "See you later! Don't hesitate to chat again if you have more questions."
    ];

    // Default response if no match is found
    $reply = "I'm not sure I understand. You can ask about reservations, hours, printing, software, WiFi, or technical support.";
    
    // Check for exact matches first
    if (array_key_exists($message, $responses)) {
        $reply = $responses[$message];
    } else {
        // If no exact match, check if message contains any keywords
        foreach ($responses as $key => $response) {
            if (strpos($message, $key) !== false) {
                $reply = $response;
                break;
            }
        }
    }

    echo $reply;
}
?>
