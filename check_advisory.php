<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the active advisory
$query = "SELECT id, message, status, created_at FROM advisories WHERE status = 'active' ORDER BY id DESC LIMIT 1";
$result = $conn->query($query);

$activeAdvisory = null;

if ($result && $result->num_rows > 0) {
    $activeAdvisory = $result->fetch_assoc();
}

$conn->close();

// Set flag for active advisory
$hasActiveAdvisory = !empty($activeAdvisory);
?>

<?php if ($hasActiveAdvisory): ?>
<!-- Advisory CSS -->
<style>
    .advisory-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(34, 34, 34, 0.97);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        color: white;
        font-family: Arial, sans-serif;
    }
    
    .advisory-content {
        max-width: 600px;
        text-align: center;
        padding: 2rem;
    }
    
    .advisory-message {
        font-size: 2.5rem;
        margin-bottom: 2rem;
        color: #dc3545;
        font-weight: bold;
    }
    
    .advisory-time {
        font-size: 0.9rem;
        color: #aaa;
        margin-top: 2rem;
    }
    
    .advisory-icon {
        font-size: 4rem;
        color: #dc3545;
        margin-bottom: 2rem;
    }
    
    .advisory-button {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        border-radius: 5px;
        cursor: pointer;
        margin-top: 2rem;
        transition: background-color 0.3s;
    }
    
    .advisory-button:hover {
        background-color: #bd2130;
    }
</style>

<!-- Advisory HTML -->
<div class="advisory-overlay" id="advisoryOverlay">
    <div class="advisory-content">
        <div class="advisory-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="advisory-message">
            <?php echo htmlspecialchars($activeAdvisory['message']); ?>
        </div>
        <p>Thank you for understanding. We will be back soon.</p>
        <button class="advisory-button" id="closeAdvisoryBtn">I Understand</button>
        <div class="advisory-time">
            Posted: <?php echo date('F j, Y, g:i a', strtotime($activeAdvisory['created_at'])); ?>
        </div>
    </div>
</div>

<!-- Advisory JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Close button event
        document.getElementById('closeAdvisoryBtn').addEventListener('click', function() {
            // Check if user is admin (using a URL parameter or session check)
            if (window.location.href.includes('admin')) {
                // For admin users, just hide the advisory overlay
                document.getElementById('advisoryOverlay').style.display = 'none';
            } else {
                // For regular users, log them out
                window.location.href = 'logout.php';
            }
        });
        
        // Prevent back navigation after showing advisory
        window.history.pushState(null, '', window.location.href);
        window.addEventListener('popstate', function() {
            window.history.pushState(null, '', window.location.href);
        });
    });
</script>
<?php endif; ?>