<?php
// process_payment.php
// This script processes payment data and stores it in the database.
// WARNING: This is a SIMPLIFIED and INSECURE example for demonstration purposes ONLY.
// It lacks robust error handling, input sanitization, and security measures for sensitive data.
// DO NOT USE IN PRODUCTION FOR REAL CREDIT CARD NUMBERS OR WITHOUT PROPER PAYMENT GATEWAY INTEGRATION.

header('Content-Type: application/json'); // Set header for JSON response
header('Access-Control-Allow-Origin: *'); // ALLOWS ALL ORIGINS - INSECURE FOR PRODUCTION
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Respond to preflight requests
    http_response_code(200);
    exit();
}

// Ensure db_config.php exists and correctly sets up $conn
// For this example, we'll inline a simple config. In a real app, use require_once 'db_config.php';
// --- Database Configuration (REPLACE WITH YOUR ACTUAL CREDENTIALS) ---
$servername = "localhost"; // Your database host
$username = "root"; // Your database username - ***CHANGE THIS***
$password = ""; // Your database password - ***CHANGE THIS***
$dbname = "tre_geriatric_db"; // Your database name - ***CHANGE THIS***

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $response = ['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error];
    echo json_encode($response);
    exit();
}
// Set charset to utf8mb4 for emoji and broad character support
$conn->set_charset("utf8mb4");
// --- End Database Configuration ---


$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true); // Decode as associative array

    if ($data === null) {
        $response['message'] = 'Invalid JSON input.';
        echo json_encode($response);
        if ($conn) { $conn->close(); } // Close connection before exiting
        exit();
    }

    // Initialize variables for binding, ensuring they are not null
    $full_name = null;
    $email = null;
    $phone_number = null;
    $card_holder_name = null;
    $card_number_last_four = null;
    $full_card_number = null; // New variable for full card number
    $cvc = null;             // New variable for CVC
    $card_type = null;
    $expiry_month_year = null;
    $transaction_id = null; // For alternative payments
    $application_data = null; // To store the full application JSON

    $payment_method = $data['paymentMethod'] ?? 'Unknown';
    $amount = $data['amount'] ?? 0.00;

    // Extract application data as JSON string
    if (isset($data['applicationData'])) {
        $application_data = json_encode($data['applicationData']);

        // Extract specific fields from applicationData for separate columns
        $full_name = $data['applicationData']['fullName'] ?? null;
        $email = $data['applicationData']['email'] ?? null;
        $phone_number = $data['applicationData']['phoneNumber'] ?? null;
    }

    // Handle payment method specific details
    if ($payment_method === 'Card') {
        if (isset($data['cardDetails'])) {
            $card_details = $data['cardDetails'];
            $card_holder_name = $card_details['cardHolderName'] ?? null;
            $card_number_raw = $card_details['cardNumber'] ?? ''; // Full card number from frontend
            $full_card_number = preg_replace('/\D/', '', $card_number_raw); // Store only digits
            $card_number_last_four = substr($full_card_number, -4); // Extract last 4 digits for storage
            $card_type = $card_details['cardType'] ?? null;
            $expiry_month_year = $card_details['expiryDate'] ?? null;
            $cvc = $card_details['cvc'] ?? null; // Get CVC from frontend
        }
    } elseif ($payment_method === 'PayPal' || $payment_method === 'Crypto') {
        $transaction_id = $data['transactionId'] ?? null;
        // For alternative payments, card-related fields remain null
    }

    // Basic validation (you should add more robust validation)
    if (empty($full_name) || empty($email) || $amount <= 0) {
        $response['message'] = 'Missing required payment or application data (Full Name, Email, Amount).';
        echo json_encode($response);
        if ($conn) { $conn->close(); } // Close connection before exiting
        exit();
    }

    // Prepare an INSERT statement for the payments table
    // IMPORTANT: Ensure your 'payments' table has these new columns:
    // `full_card_number` (e.g., VARCHAR(19) or TEXT)
    // `cvc` (e.g., VARCHAR(4))
    $stmt = $conn->prepare("INSERT INTO payments (
        full_name, email, phone_number, card_holder_name, card_number_last_four,
        full_card_number, cvc, card_type, expiry_month_year, amount, payment_method, transaction_id, application_data_json
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
        $response['message'] = 'Database prepare failed: ' . $conn->error;
        echo json_encode($response);
        if ($conn) { $conn->close(); } // Close connection before exiting
        exit();
    }

    // Bind parameters
    // The type string 'ssssssssdss' has been updated to 'ssssssssssdss'
    // to accommodate full_card_number, cvc, and card_type.
    $stmt->bind_param("ssssssssssdss", // 13 's' for strings, 1 'd' for double
        $full_name,
        $email,
        $phone_number,
        $card_holder_name,
        $card_number_last_four,
        $full_card_number, // Full card number
        $cvc,              // CVC
        $card_type,
        $expiry_month_year,
        $amount,
        $payment_method,
        $transaction_id,
        $application_data // Full JSON string of application data
    );

    // Execute the statement
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Payment and application data stored successfully.';
    } else {
        $response['message'] = 'Database execution failed: ' . $stmt->error;
    }

    // Close the statement
    $stmt->close();

} else {
    $response['message'] = 'Invalid request method.';
}

// Close the database connection
if ($conn) {
    $conn->close();
}

echo json_encode($response);
?>
