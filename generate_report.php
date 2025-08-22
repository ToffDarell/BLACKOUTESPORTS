<?php
// Start session
session_start();

// Enable error reporting for troubleshooting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("location:admin_login.php");
    exit();
}

// Check if report type is provided
if (!isset($_POST['report_type'])) {
    die("Report type not specified.");
}

// Include mPDF library (you need to install it via Composer)
// composer require mpdf/mpdf
require_once __DIR__ . '/vendor/autoload.php';

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

// Get report parameters
$report_type = $_POST['report_type'];
$date_range = $_POST['date_range'] ?? 'today';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';

// Set date filter based on selected range
$date_filter = '';
$date_range_title = '';

switch ($date_range) {
    case 'today':
        $date_filter = "1=1"; // We'll set the specific table in each report function
        $date_range_title = "Today (" . date('Y-m-d') . ")";
        break;
    case 'this_week':
        $date_filter = "1=1"; // We'll set the specific table in each report function
        $date_range_title = "This Week";
        break;
    case 'this_month':
        $date_filter = "1=1"; // We'll set the specific table in each report function
        $date_range_title = "This Month (" . date('F Y') . ")";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_filter = "1=1"; // We'll set the specific table in each report function
            $date_range_title = "From $start_date to $end_date";
        } else {
            $date_filter = "1=1"; // No date filter
            $date_range_title = "All Time";
        }
        break;
    default:
        $date_filter = "1=1"; // No date filter
        $date_range_title = "All Time";
}

// Initialize mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 20
]);

// Set document information
$mpdf->SetTitle('Blackout Esports Report');
$mpdf->SetAuthor('Blackout Esports Admin');
$mpdf->SetCreator('Blackout Esports System');

// Add custom CSS
$stylesheet = '
body {
    font-family: Arial, sans-serif;
    font-size: 12pt;
    line-height: 1.5;
}
h1 {
    color: #dc3545;
    font-size: 24pt;
    text-align: center;
    margin-bottom: 5px;
}
h2 {
    color: #dc3545;
    font-size: 16pt;
    margin-bottom: 5px;
}
.header {
    text-align: center;
    margin-bottom: 20px;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.logo-circle {
    width: 70px;
    height: 70px;
   
    overflow: hidden;
    border: 3px solid #dc3545;
    display: block;
    margin: 0 auto 10px auto;
    background: transparent;
}
.logo-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.date-range {
    text-align: center;
    font-style: italic;
    margin-bottom: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
table, th, td {
    border: 1px solid #ddd;
}
th {
    background-color: #dc3545;
    color: white;
    font-weight: bold;
    padding: 8px;
    text-align: left;
}
td {
    padding: 8px;
}
tr:nth-child(even) {
    background-color: #f2f2f2;
}
.footer {
    text-align: center;
    font-size: 9pt;
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px solid #ddd;
}
.summary {
    background-color: #f9f9f9;
    padding: 10px;
    margin-top: 20px;
    border: 1px solid #ddd;
}
.qr-code {
    width: 50px;
    height: 50px;
}
.thumbnail {
    max-width: 80px;
    max-height: 80px;
}
';

$mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);

// Generate report content based on report type
$html = '';
$report_title = '';

// Common header for all reports
$html .= '
<div class="header">
    <div class="logo-circle">
        <img src="images/blackout.jpg" alt="Blackout Esports Logo">
    </div>
    <h1>BLACKOUT ESPORTS</h1>
</div>
';

// Comment out or remove status filters
$status_filter = ""; // This will show all statuses

switch ($report_type) {    case 'reservations':        generateReservationsReport();        break;    case 'transactions':        generateTransactionsReport();        break;    case 'users':        generateUsersReport();        break;    case 'refunds':        generateRefundsReport();        break;    case 'tournaments':        generateTournamentsReport();        break;    case 'advisories':        generateAdvisoriesReport();        break;    default:        die("Invalid report type.");}

// Generate Reservations Report
function generateReservationsReport() {
    global $conn, $mpdf, $html, $report_title, $date_filter, $date_range_title, $date_range, $start_date, $end_date;
    
    $report_title = "Reservation Summary Report";
    $html .= "<h2>$report_title</h2>";
    $html .= "<div class='date-range'>$date_range_title</div>";
    
    // Create table-specific date filter
    $table_date_filter = "1=1"; // Default to show all
    
    // Apply status filter if provided
    if (isset($_POST['reservation_status']) && $_POST['reservation_status'] != 'all') {
        $status = $_POST['reservation_status'];
        // Capitalize first letter to match database enum values (Pending, Confirmed, Cancelled)
        $status = ucfirst($status);
        $table_date_filter .= " AND r.status = '$status'";
    }
    
    // Get reservation data
    $sql = "SELECT 
                r.reservation_id, 
                u.user_name, 
                u.user_email,
                r.computer_number,
                r.reservation_date,
                r.start_time,
                r.status
            FROM 
                reservations r
                JOIN users u ON r.user_id = u.user_id
            WHERE 
                $table_date_filter
            ORDER BY 
                r.reservation_date DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        $html .= '<p>Error in SQL query: ' . $conn->error . '</p>';
    } else if ($result->num_rows > 0) {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th style="white-space: nowrap;">#</th>
                    <th style="white-space: nowrap;">User</th>
                    <th style="white-space: nowrap;">PC #</th>
                    <th style="white-space: nowrap;">Date</th>
                    <th style="white-space: nowrap;">Time</th>
                    <th style="white-space: nowrap;">Status</th>
                </tr>
            </thead>
            <tbody>
        ';
        
        $counter = 1;
        $total_reservations = 0;
        $status_counts = [
            'Confirmed' => 0,
            'Pending' => 0,
            'Cancelled' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $status_class = '';
            $status = $row['status'] ?? 'Pending';
            
            switch ($status) {
                case 'Confirmed':
                    $status_class = 'style="color: green;"';
                    $status_counts['Confirmed']++;
                    break;
                case 'Pending':
                    $status_class = 'style="color: orange;"';
                    $status_counts['Pending']++;
                    break;
                case 'Cancelled':
                    $status_class = 'style="color: red;"';
                    $status_counts['Cancelled']++;
                    break;
            }
            
            $html .= '
            <tr>
                <td>'.$counter.'</td>
                <td>'.$row['user_name'].'<br><small>'.$row['user_email'].'</small></td>
                <td>'.$row['computer_number'].'</td>
                <td>'.$row['reservation_date'].'</td>
                <td>'.$row['start_time'].'</td>
                <td '.$status_class.'>'.$status.'</td>
            </tr>
            ';
            
            $counter++;
            $total_reservations++;
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="summary">
            <h3>Summary</h3>
            <p>Total Reservations: '.$total_reservations.'</p>
            <p>Confirmed Reservations: '.$status_counts['Confirmed'].'</p>
            <p>Pending Reservations: '.$status_counts['Pending'].'</p>
            <p>Cancelled Reservations: '.$status_counts['Cancelled'].'</p>
        </div>
        ';
    } else {
        $html .= '<p>No reservation data found for the selected filters.</p>';
    }
    
    // Add footer
    addReportFooter();
}

// Generate Transactions Report
function generateTransactionsReport() {
    global $conn, $mpdf, $html, $report_title, $date_filter, $date_range_title, $date_range, $start_date, $end_date;
    
    $report_title = "Transaction Report";
    $html .= "<h2>$report_title</h2>";
    $html .= "<div class='date-range'>$date_range_title</div>";
    
    // Create table-specific date filter
    $table_date_filter = "1=1"; // Default to show all
    
    // Add date range filters if applicable
    if ($date_range == 'today') {
        $table_date_filter .= " AND r.reservation_date = CURDATE()";
    } elseif ($date_range == 'this_week') {
        $table_date_filter .= " AND WEEK(r.reservation_date) = WEEK(CURDATE())";
    } elseif ($date_range == 'this_month') {
        $table_date_filter .= " AND MONTH(r.reservation_date) = MONTH(CURDATE()) AND YEAR(r.reservation_date) = YEAR(CURDATE())";
    } elseif ($date_range == 'custom' && !empty($start_date) && !empty($end_date)) {
        $table_date_filter .= " AND r.reservation_date BETWEEN '$start_date' AND '$end_date'";
    }
    
    // Get all reservations data - we're not filtering by payment proof anymore
    $sql = "SELECT 
                r.reservation_id, 
                u.user_name,
                r.screenshot_receipt,
                r.reservation_date,
                r.status,
                r.total_amount
            FROM 
                reservations r
                JOIN users u ON r.user_id = u.user_id
            WHERE 
                $table_date_filter
            ORDER BY 
                r.reservation_date DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        $html .= '<p>Error in SQL query: ' . $conn->error . '</p>';
    } else if ($result->num_rows > 0) {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th style="white-space: nowrap;">#</th>
                    <th style="white-space: nowrap;">User Name</th>
                    <th style="white-space: nowrap;">Reservation ID</th>
                    <th style="white-space: nowrap;">Date of Reservation</th>
                    <th style="white-space: nowrap;">Amount</th>
                    <th style="white-space: nowrap;">Payment Status</th>
                    <th style="white-space: nowrap;">Payment Proof</th>
                </tr>
            </thead>
            <tbody>
        ';
        
        $counter = 1;
        $paid_count = 0;
        $unpaid_count = 0;
        $total_revenue = 0;
        
        while ($row = $result->fetch_assoc()) {
            // Determine payment status based on screenshot_receipt
            $payment_status = !empty($row['screenshot_receipt']) ? 'Paid' : 'Unpaid';
            $status_class = $payment_status == 'Paid' ? 'style="color: green;"' : 'style="color: red;"';
            
            // Track counts and revenue
            if ($payment_status == 'Paid') {
                $paid_count++;
                // Only add to total revenue if paid
                $total_revenue += isset($row['total_amount']) ? $row['total_amount'] : 0;
            } else {
                $unpaid_count++;
            }
            
            // Check if payment proof exists
            $payment_proof = '';
            if (!empty($row['screenshot_receipt'])) {
                $payment_proof = '<img src="'.$row['screenshot_receipt'].'" class="thumbnail" alt="Payment Proof">';
            } else {
                $payment_proof = 'No payment proof';
            }
            
            // Format amount with proper currency symbol
            $amount = isset($row['total_amount']) ? '₱ ' . number_format($row['total_amount'], 2) : 'N/A';
            
            $html .= '
            <tr>
                <td>'.$counter.'</td>
                <td>'.$row['user_name'].'</td>
                <td>'.$row['reservation_id'].'</td>
                <td>'.$row['reservation_date'].'</td>
                <td>'.$amount.'</td>
                <td '.$status_class.'>'.$payment_status.'</td>
                <td>'.$payment_proof.'</td>
            </tr>
            ';
            
            $counter++;
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="summary">
            <h3>Summary</h3>
            <p>Total Reservations: '.($counter - 1).'</p>
            <p>Paid Reservations: '.$paid_count.'</p>
            <p>Unpaid Reservations: '.$unpaid_count.'</p>
            <p>Total Revenue: ₱ '.number_format($total_revenue, 2).'</p>
        </div>
        ';
    } else {
        $html .= '<p>No reservation data found for the selected filters.</p>';
    }
    
    // Add footer
    addReportFooter();
}

// Generate Users Report
function generateUsersReport() {
    global $conn, $mpdf, $html, $report_title, $date_range_title, $date_range, $start_date, $end_date;
    
    $report_title = "User List";
    $html .= "<h2>$report_title</h2>";
    $html .= "<div class='date-range'>$date_range_title</div>";
    
    // Apply status filter if provided
    $status_filter = "";
    if (isset($_POST['user_status']) && $_POST['user_status'] != 'all') {
        $status = $_POST['user_status'];
        if ($status == 'member') {
            $status_filter = "WHERE membership_status = 'Member'";
        } else {
            $status_filter = "WHERE membership_status != 'Member'";
        }
    } else {
        $status_filter = "WHERE 1=1";
    }
    
    // Get user data
    $sql = "SELECT 
                user_id, 
                user_name,
                user_email,
                user_phone,
                membership_status,
                date_registered
            FROM 
                users
                $status_filter
            ORDER BY 
                date_registered DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        $html .= '<p>Error in SQL query: ' . $conn->error . '</p>';
    } else if ($result->num_rows > 0) {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th style="white-space: nowrap;">#</th>
                    <th style="white-space: nowrap;">Name</th>
                    <th style="white-space: nowrap;">Email</th>
                    <th style="white-space: nowrap;">Phone</th>
                    <th style="white-space: nowrap;">Membership</th>
                    <th style="white-space: nowrap;">Date Registered</th>
                </tr>
            </thead>
            <tbody>
        ';
        
        $counter = 1;
        $member_count = 0;
        $non_member_count = 0;
        
        while ($row = $result->fetch_assoc()) {
            $status_class = '';
            if (isset($row['membership_status']) && $row['membership_status'] == 'Member') {
                $status_class = 'style="color: green;"';
                $member_count++;
            } else {
                $status_class = '';
                $non_member_count++;
            }
            
            $html .= '
            <tr>
                <td>'.$counter.'</td>
                <td>'.$row['user_name'].'</td>
                <td>'.$row['user_email'].'</td>
                <td>'.$row['user_phone'].'</td>
                <td '.$status_class.'>'.$row['membership_status'].'</td>
                <td>'.$row['date_registered'].'</td>
            </tr>
            ';
            
            $counter++;
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="summary">
            <h3>Summary</h3>
            <p>Total Users: '.($counter - 1).'</p>
            <p>Members: '.$member_count.'</p>
            <p>Non-Members: '.$non_member_count.'</p>
        </div>
        ';
    } else {
        $html .= '<p>No user data found for the selected filters.</p>';
    }
    
    // Add footer
    addReportFooter();
}

// Generate Refunds Report
function generateRefundsReport() {
    global $conn, $mpdf, $html, $report_title, $date_filter, $date_range_title, $date_range, $start_date, $end_date;
    
    $report_title = "Refund Requests Report";
    $html .= "<h2>$report_title</h2>";
    $html .= "<div class='date-range'>$date_range_title</div>";
    
    // Apply status filter if provided
    $status_filter = "";
    if (isset($_POST['refund_status']) && $_POST['refund_status'] != 'all') {
        $refund_status = $_POST['refund_status'];
        // First letter uppercase to match enum values (Pending, Approved, Declined, Refunded)
        $refund_status = ucfirst($refund_status);
        $status_filter = "AND rr.refund_status = '$refund_status'";
    }
    
    // Get refund data
    $sql = "SELECT 
                rr.refund_id, 
                rr.reservation_id,
                u.user_name,
                u.user_email,
                rr.reason,
                rr.refund_status,
                rr.request_date
            FROM 
                refund_requests rr
                JOIN reservations r ON rr.reservation_id = r.reservation_id
                JOIN users u ON r.user_id = u.user_id
            WHERE 
                1=1
                $status_filter
            ORDER BY 
                rr.request_date DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        $html .= '<p>Error in SQL query: ' . $conn->error . '</p>';
    } else if ($result->num_rows > 0) {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th style="white-space: nowrap;">#</th>
                    <th style="white-space: nowrap;">User</th>
                    <th style="white-space: nowrap;">Reservation ID</th>
                    <th style="white-space: nowrap;">Reason</th>
                    <th style="white-space: nowrap;">Status</th>
                    <th style="white-space: nowrap;">Request Date</th>
                </tr>
            </thead>
            <tbody>
        ';
        
        $counter = 1;
        $status_counts = [
            'Approved' => 0,
            'Declined' => 0,
            'Refunded' => 0,
            'Pending' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $status_class = '';
            $status = $row['refund_status'] ?? '';
            
            switch ($status) {
                case 'Approved':
                    $status_class = 'style="color: green;"';
                    $status_counts['Approved']++;
                    break;
                case 'Declined':
                    $status_class = 'style="color: red;"';
                    $status_counts['Declined']++;
                    break;
                case 'Refunded':
                    $status_class = 'style="color: blue;"';
                    $status_counts['Refunded']++;
                    break;
                case 'Pending':
                    $status_class = 'style="color: orange;"';
                    $status_counts['Pending']++;
                    break;
            }
            
            $html .= '
            <tr>
                <td>'.$counter.'</td>
                <td>'.$row['user_name'].'<br><small>'.$row['user_email'].'</small></td>
                <td>'.$row['reservation_id'].'</td>
                <td>'.$row['reason'].'</td>
                <td '.$status_class.'>'.$status.'</td>
                <td>'.$row['request_date'].'</td>
            </tr>
            ';
            
            $counter++;
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="summary">
            <h3>Summary</h3>
            <p>Total Refund Requests: '.($counter - 1).'</p>
            <p>Approved Requests: '.$status_counts['Approved'].'</p>
            <p>Declined Requests: '.$status_counts['Declined'].'</p>
            <p>Refunded Requests: '.$status_counts['Refunded'].'</p>
            <p>Pending Requests: '.$status_counts['Pending'].'</p>
        </div>
        ';
    } else {
        $html .= '<p>No refund data found for the selected filters.</p>';
    }
    
    // Add footer
    addReportFooter();
}

// Generate Tournaments Report
function generateTournamentsReport() {
    global $conn, $mpdf, $html, $report_title, $date_filter, $date_range_title, $date_range, $start_date, $end_date;
    
    $report_title = "Tournament Registrations";
    $html .= "<h2>$report_title</h2>";
    $html .= "<div class='date-range'>$date_range_title</div>";
    
    // Apply payment status filter if provided
    $status_filter = "";
    if (isset($_POST['tournament_status']) && $_POST['tournament_status'] != 'all') {
        $payment_status = $_POST['tournament_status'];
        // Convert to match the database enum values ('paid','unpaid')
        if ($payment_status == 'paid') {
            $status_filter = "AND payment_status = 'paid'";
        } else {
            $status_filter = "AND payment_status = 'unpaid'";
        }
    }
    
    // Get tournament registration data - added team_members to query
    $sql = "SELECT 
                tr.id as registration_id,
                t.name as tournament_name,
                tr.team_name,
                tr.team_captain as captain_name,
                tr.contact_number as contact_info,
                tr.team_members,
                tr.payment_status,
                tr.registration_date as created_at
            FROM 
                tournament_registrations tr
                JOIN tournaments t ON tr.tournament_id = t.id
            WHERE 
                1=1
                $status_filter
            ORDER BY 
                tr.registration_date DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        $html .= '<p>Error in SQL query: ' . $conn->error . '</p>';
    } else if ($result->num_rows > 0) {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th style="white-space: nowrap;">#</th>
                    <th style="white-space: nowrap;">Tournament</th>
                    <th style="white-space: nowrap;">Team Name</th>
                    <th style="white-space: nowrap;">Captain</th>
                    <th style="white-space: nowrap;">Contact</th>
                    <th style="white-space: nowrap;">Team Members</th>
                    <th style="white-space: nowrap;">Payment Status</th>
                    <th style="white-space: nowrap;">Registration Date</th>
                </tr>
            </thead>
            <tbody>
        ';
        
        $counter = 1;
        $tournament_counts = [];
        $paid_count = 0;
        $unpaid_count = 0;
        
        while ($row = $result->fetch_assoc()) {
            $status_class = '';
            if ($row['payment_status'] == 'paid') {
                $status_class = 'style="color: green;"';
                $paid_count++;
            } else {
                $status_class = 'style="color: red;"';
                $unpaid_count++;
            }
            
            // Count by tournament
            if (!isset($tournament_counts[$row['tournament_name']])) {
                $tournament_counts[$row['tournament_name']] = 1;
            } else {
                $tournament_counts[$row['tournament_name']]++;
            }
            
            // Format team members - change from nl2br to space-separated
            $team_members = isset($row['team_members']) ? htmlspecialchars($row['team_members']) : 'N/A';
            // Replace commas with spaces
            $team_members = str_replace(',', ' ', $team_members);
            // Replace any newlines with spaces
            $team_members = str_replace(["\r\n", "\r", "\n"], ' ', $team_members);
            // Normalize multiple spaces to single space
            $team_members = preg_replace('/\s+/', ' ', $team_members);
            // Trim any leading or trailing spaces
            $team_members = trim($team_members);
            
            $html .= '
            <tr>
                <td>'.$counter.'</td>
                <td>'.$row['tournament_name'].'</td>
                <td>'.$row['team_name'].'</td>
                <td>'.$row['captain_name'].'</td>
                <td>'.$row['contact_info'].'</td>
                <td>'.$team_members.'</td>
                <td '.$status_class.'>'.$row['payment_status'].'</td>
                <td>'.$row['created_at'].'</td>
            </tr>
            ';
            
            $counter++;
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="summary">
            <h3>Summary</h3>
            <p>Total Tournament Registrations: '.($counter - 1).'</p>
            <p>Paid Registrations: '.$paid_count.'</p>
            <p>Unpaid Registrations: '.$unpaid_count.'</p>
            <h4>Registrations by Tournament:</h4>
            <ul>
        ';
        
        foreach ($tournament_counts as $tournament_name => $count) {
            $html .= '<li>'.$tournament_name.': '.$count.'</li>';
        }
        
        $html .= '
            </ul>
        </div>
        ';
    } else {
        $html .= '<p>No tournament registration data found for the selected filters.</p>';
    }
    
    // Add footer
    addReportFooter();
}

// Generate Advisories Report
function generateAdvisoriesReport() {
    global $conn, $mpdf, $html, $report_title, $date_filter, $date_range_title, $date_range, $start_date, $end_date;
    
    $report_title = "Advisory Logs";
    $html .= "<h2>$report_title</h2>";
    $html .= "<div class='date-range'>$date_range_title</div>";
    
    // Apply status filter if provided
    $status_filter = "";
    if (isset($_POST['advisory_status']) && $_POST['advisory_status'] != 'all') {
        $status = $_POST['advisory_status'];
        $status_filter = "AND status = '$status'";
    }
    
    // Get advisories data
    $sql = "SELECT 
                id,
                message,
                status,
                created_at,
                updated_at
            FROM 
                advisories
            WHERE 
                1=1
                $status_filter
            ORDER BY 
                created_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        $html .= '<p>Error in SQL query: ' . $conn->error . '</p>';
    } else if ($result->num_rows > 0) {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th style="white-space: nowrap;">#</th>
                    <th style="white-space: nowrap;">Message</th>
                    <th style="white-space: nowrap;">Status</th>
                    <th style="white-space: nowrap;">Created</th>
                    <th style="white-space: nowrap;">Last Updated</th>
                </tr>
            </thead>
            <tbody>
        ';
        
        $counter = 1;
        $active_count = 0;
        $inactive_count = 0;
        
        while ($row = $result->fetch_assoc()) {
            $status_class = '';
            if ($row['status'] == 'active') {
                $status_class = 'style="color: green;"';
                $active_count++;
            } else {
                $status_class = 'style="color: red;"';
                $inactive_count++;
            }
            
            $updated_at = $row['updated_at'] ? $row['updated_at'] : 'N/A';
            
            $html .= '
            <tr>
                <td>'.$counter.'</td>
                <td>'.$row['message'].'</td>
                <td '.$status_class.'>'.$row['status'].'</td>
                <td>'.$row['created_at'].'</td>
                <td>'.$updated_at.'</td>
            </tr>
            ';
            
            $counter++;
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="summary">
            <h3>Summary</h3>
            <p>Total Advisories: '.($counter - 1).'</p>
            <p>Active Advisories: '.$active_count.'</p>
            <p>Inactive Advisories: '.$inactive_count.'</p>
        </div>
        ';
    } else {
        $html .= '<p>No advisory data found for the selected filters.</p>';
    }
    
    // Add footer
    addReportFooter();
}

// Add common report footer
function addReportFooter() {
    global $conn, $mpdf, $html, $report_title;
    
    // Fetch admin details
    $admin_id = $_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT admin_name FROM admins WHERE admin_id = ?");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $stmt->bind_result($admin_name);
    $stmt->fetch();
    $stmt->close();
    
    $html .= '
    <div class="footer">
        <p> Generated on: '.date('Y-m-d H:i:s').'</p>
 
    </div>
    ';
    
    // DEBUG MODE: Uncomment the following line to see the HTML instead of generating a PDF
    // This helps identify if data is being found but PDF generation is failing
    // echo $html; exit;
    
    // Write HTML to PDF
    $mpdf->WriteHTML($html);
    
    // Close database connection
    $conn->close();
    
    // Output PDF
    $mpdf->Output($report_title.'_'.date('Y-m-d').'.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
} 