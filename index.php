<?php

require_once 'Utility/Config.php';
require_once 'Utility/Nagad.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if 'amount' is set in the POST data
    if (isset($_POST['amount'])) {
        $amount = $_POST['amount'];

        // Validate and sanitize input (consider using more validation)
        $amount = filter_var($amount, FILTER_SANITIZE_NUMBER_FLOAT);

        // Check if the database connection is successful
        if ($connection) {
            try {
                // Use a prepared statement to prevent SQL injection
                $query = "INSERT INTO payments (`amount`) VALUES (?)";
                $stmt = mysqli_prepare($connection, $query);

                // Bind the parameter
                mysqli_stmt_bind_param($stmt, "d", $amount);

                // Execute the statement
                mysqli_stmt_execute($stmt);

                // Get the inserted ID
                $paymentId = mysqli_insert_id($connection);
                $nagad_callback_url .= '/' . $paymentId;

                // Create Nagad instance
                $nagadPayment = new Nagad();

                // Get the payment URL
                $paymentUrl = $nagadPayment->getRedirectUrl($amount);

                // Send JSON response
                echo json_encode([
                    'message' => $paymentUrl,
                    'status' => 200
                ]);
            } catch (\Throwable $th) {
                // If an exception occurs, handle it
                if ($_SESSION['paymentId']) {
                    // Rollback the transaction if there was an exception
                    $query = "DELETE FROM payments WHERE id = '$paymentId'";
                    mysqli_query($connection, $query);
                }

                // Send JSON response for the exception
                echo json_encode([
                    'message' => $th->getMessage(),
                    'status' => 500
                ]);
            } finally {
                // Close the statement
                mysqli_stmt_close($stmt);
            }
        } else {
            // If the database connection fails, send an error response
            echo json_encode([
                'message' => 'Database connection error.',
                'status' => 500
            ]);
        }
    } else {
        // If 'amount' is not set in the POST data, send an error response
        echo json_encode([
            'message' => 'Amount field is required.',
            'status' => 422
        ]);
    }
} else {
    // If the request method is not POST, send a not allowed response
    echo json_encode([
        'message' => 'GET request not allowed.',
        'status' => 405
    ]);
}
