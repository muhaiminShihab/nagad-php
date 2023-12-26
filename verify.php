<?php

require_once 'Utility/Config.php';
require_once 'Utility/Nagad.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Define the regex pattern to match the number between the slashes
    $pattern = '~verify\.php/(\d+)/~';
    $url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    // Perform the regex match
    if (preg_match($pattern, $url, $matches)) {
        $paymentId = $matches[1];
    } else {
        $paymentId = null;
    }

    $nagadPayment = new Nagad();
    $response = (object) $nagadPayment->verify();

    // Check if paymentId is set
    if ($paymentId) {
        $paymentId = $paymentId;
        $paymentResponse = json_encode($response);

        // Check if the payment ID already exists in the table
        $query = "SELECT * FROM payment_responses WHERE payment_id = '$paymentId'";
        $result = mysqli_query($connection, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            // Send JSON response if paymentId is not set
            echo json_encode([
                'message' => 'Invalid Payment ID.',
                'status' => 500
            ]);

            return false;
        }

        // Use prepared statement to prevent SQL injection
        $query = "INSERT INTO payment_responses (`payment_id`, `payment_status`, `payment_response`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($connection, $query);

        // Bind parameters
        mysqli_stmt_bind_param($stmt, "iss", $paymentId, $paymentStatus, $paymentResponse);

        // Determine payment status based on the response
        $paymentStatus = ($response->status === 'Success') ? 1 : 0;

        // Execute the statement
        mysqli_stmt_execute($stmt);

        // Close the statement
        mysqli_stmt_close($stmt);

        // Send JSON response based on payment status
        if ($response->status === 'Success') {
            echo json_encode([
                'message' => 'Payment successful.',
                'status' => 200
            ]);
        } else {
            echo json_encode([
                'message' => 'Payment failed.',
                'status' => 402
            ]);
        }
    } else {
        // Send JSON response if paymentId is not set
        echo json_encode([
            'message' => 'Payment ID not found.',
            'status' => 500
        ]);
    }
} catch (\Throwable $th) {
    // Send JSON response for exceptions
    echo json_encode([
        'message' => $th->getMessage(),
        'status' => 500
    ]);
}
