<?php
require_once('.env.php');

// Establish database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Function to insert available slots with SQL injection protection
function insertAvailableSlot($conn, $date, $start_time, $end_time) {
    // Prepare the SQL statement using prepared statement
    $sql = "INSERT INTO available_times (date, start_time, end_time) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    // Bind parameters
    mysqli_stmt_bind_param($stmt, "sss", $date, $start_time, $end_time);
    // Execute the statement
    if (mysqli_stmt_execute($stmt)) {
        return "New record created successfully";
    } else {
        return "Error: " . mysqli_error($conn);
    }
}

// Function to fetch all available slots
function fetchAvailableSlots($conn) {
    $sql = "SELECT * FROM available_times ORDER BY date, start_time";
    $result = mysqli_query($conn, $sql);
    $slots = [];
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $slots[] = $row;
        }
    }
    return $slots;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['submit'])) {
        $date = mysqli_real_escape_string($conn, $_POST['date']);
        $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
        $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
        $message = insertAvailableSlot($conn, $date, $start_time, $end_time);
        echo htmlspecialchars($message); // XSS protection
    } elseif (isset($_POST['delete'])) {
        $slot_id = mysqli_real_escape_string($conn, $_POST['slot_id']);
        $sql = "DELETE FROM available_times WHERE id = '$slot_id'"; // SQL injection protection
        if (mysqli_query($conn, $sql)) {
            echo htmlspecialchars("Record deleted successfully"); // XSS protection
        } else {
            echo htmlspecialchars("Error deleting record: " . mysqli_error($conn)); // XSS protection
        }
    }
}

// Fetch existing slots
$slots = fetchAvailableSlots($conn);

// Close connection
mysqli_close($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1 class="mt-4 text-center">Admin Panel</h1>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="mb-4">Set Available Slots</h2>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="date">Date:</label>
                        <input type="date" class="form-control" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="start_time">Start Time:</label>
                        <input type="time" class="form-control" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="end_time">End Time:</label>
                        <input type="time" class="form-control" name="end_time" required>
                    </div>
                    <button type="submit" class="btn btn-primary" name="submit">Submit</button>
                </form>
            </div>
        </div>
        <div class="row justify-content-center mt-4">
            <div class="col-md-8">
                <h2 class="mb-4">Existing Slots</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($slot['date']); ?></td>
                                <td><?php echo htmlspecialchars($slot['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($slot['end_time']); ?></td>
                                <td>
                                    <form method="post" action="">
                                        <input type="hidden" name="slot_id" value="<?php echo htmlspecialchars($slot['id']); ?>">
                                        <button type="submit" class="btn btn-danger" name="delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
