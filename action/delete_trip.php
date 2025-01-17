<?php
require '../settings/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['preference_id'])) {
    $preference_id = $_GET['preference_id'];

    // SQL statement to delete the trip
    $stmt = $conn->prepare("DELETE FROM Travel_Preferences WHERE preference_id = :preference_id");
    $stmt->bindParam(':preference_id', $preference_id, PDO::PARAM_INT);

    try {
        if ($stmt->execute()) {
            echo 'success'; // Ensure no extra output
        } else {
            echo 'error';
        }
    } catch (PDOException $e) {
        echo 'error';
    }
} else {
    echo 'error';
}
?>
