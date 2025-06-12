<?php
// process_contact.php
header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input data
    $name = filter_var($_POST['name'] ?? '', FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $subject = filter_var($_POST['subject'] ?? '', FILTER_SANITIZE_STRING);
    $message = filter_var($_POST['message'] ?? '', FILTER_SANITIZE_STRING);

    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    // In a real application, you would:
    // 1. Send an email to the relevant department (e.g., info@yourdomain.com)
    // 2. Store the contact message in a database

    // Example email sending (requires mail server configuration)
    // $to = "the.kenrick.centre@birmingham.gov.uk"; // Official email from the PDF
    // $headers = "From: " . $name . " <" . $email . ">\r\n";
    // $headers .= "Reply-To: " . $email . "\r\n";
    // $email_body = "You have received a new message from your website contact form.\n\n" .
    //               "Here are the details:\n\n" .
    //               "Name: " . $name . "\n" .
    //               "Email: " . $email . "\n" .
    //               "Subject: " . $subject . "\n" .
    //               "Message:\n" . $message;
    //
    // if (mail($to, $subject, $email_body, $headers)) {
    //     echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully!']);
    // } else {
    //     echo json_encode(['success' => false, 'message' => 'Failed to send your message. Please try again later.']);
    // }

    // For now, just a success message (conceptual)
    echo json_encode(['success' => true, 'message' => 'Your message has been received! (This is a demo, actual sending not configured)']);

} else {
    // Not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
