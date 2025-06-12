<?php
// process_application.php
header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize all form data
    // This is a simplified example; in production, validate every field rigorously
    $data = [];
    foreach ($_POST as $key => $value) {
        // Basic sanitization, adjust as needed for specific data types
        $data[$key] = filter_var($value, FILTER_SANITIZE_STRING);
    }

    // Basic validation for required fields (example)
    if (empty($data['fullName']) || empty($data['email']) || empty($data['applicationLetter'])) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (Full Name, Email, Application Letter).']);
        exit;
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    // In a real application, you would:
    // 1. Store the application data in a secure database (e.g., MySQL, PostgreSQL)
    // 2. Send a confirmation email to the applicant
    // 3. Send a notification email to the HR department

    // Example: Prepare email body
    $email_body = "New Job Application Received for TRE Geriatric Care.\n\n";
    $email_body .= "Applicant Details:\n";
    foreach ($data as $key => $value) {
        $email_body .= ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $key)) . ": " . $value . "\n";
    }

    // Example email sending (requires mail server configuration)
    // $to = "the.kenrick.centre@birmingham.gov.uk"; // Official email from the PDF
    // $subject = "New Job Application from " . $data['fullName'];
    // $headers = "From: " . $data['fullName'] . " <" . $data['email'] . ">\r\n";
    // $headers .= "Reply-To: " . $data['email'] . "\r\n";
    //
    // if (mail($to, $subject, $email_body, $headers)) {
    //     echo json_encode(['success' => true, 'message' => 'Your application has been submitted successfully!']);
    // } else {
    //     echo json_encode(['success' => false, 'message' => 'Failed to submit your application. Please try again later.']);
    // }

    // For now, just a success message (conceptual)
    echo json_encode(['success' => true, 'message' => 'Your application has been received! (This is a demo, actual submission not configured)']);

} else {
    // Not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
