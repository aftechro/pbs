<?php
// Include .env configuration
require_once('.env.php');

// Establish database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Start session
session_start();

// Handle user login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) == 1) {
        $_SESSION['username'] = $username;
        // Redirect to admin page after successful login
        header("Location: admin.php");
        exit();
    } else {
        $login_error = "Invalid username or password.";
    }
}

// Function to insert available slots with SQL injection protection
function insertAvailableSlots($conn, $dates, $start_time, $end_time) {
    $success_messages = [];
    $failure_messages = [];
    foreach ($dates as $date) {
        $sql = "INSERT INTO available_times (date, start_time, end_time) VALUES ('$date', '$start_time', '$end_time')";
        if (mysqli_query($conn, $sql)) {
            $success_messages[] = "New record created successfully for date: $date";
        } else {
            $failure_messages[] = "Error inserting record for date: $date - " . mysqli_error($conn);
        }
    }
    return array('success' => $success_messages, 'failure' => $failure_messages);
}

// Function to fetch all available slots for the specified month
function fetchAvailableSlotsForMonth($conn, $year, $month) {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $first_day_of_month = date('N', strtotime("$year-$month-01")); // Get the day of the week for the first day of the month
    $available_slots = [];
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = sprintf("%d-%02d-%02d", $year, $month, $day);
        $sql = "SELECT * FROM available_times WHERE DATE(date) = '$date'";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            $slots = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $slots[] = $row;
            }
            $available_slots[$day] = $slots;
        }
    }
    return array('first_day_of_month' => $first_day_of_month, 'available_slots' => $available_slots);
}

// Function to count bookings
function countBookings($conn) {
    $sql = "SELECT COUNT(*) AS bookings_count FROM available_times WHERE is_booked = 1";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['bookings_count'];
}

// Function to count available slots
function countAvailableSlots($conn) {
    $sql = "SELECT COUNT(*) AS available_slots_count FROM available_times WHERE is_booked = 0";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['available_slots_count'];
}

// Function to fetch the next booked slot
function fetchNextBooking($conn) {
    $sql = "SELECT * FROM available_times WHERE is_booked = 1 AND date >= CURDATE() ORDER BY date ASC LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    } else {
        return null;
    }
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['submit'])) {
        $dates_string = $_POST['dates'];
        $dates = explode(',', $dates_string);
        $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
        $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
        $insert_result = insertAvailableSlots($conn, $dates, $start_time, $end_time);
        foreach ($insert_result['success'] as $message) {
            $success_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($message) . '
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>';
        }
        foreach ($insert_result['failure'] as $message) {
            $failure_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($message) . '
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>';
        }
    } elseif (isset($_POST['delete'])) {
        $slot_id = mysqli_real_escape_string($conn, $_POST['slot_id']);
        // Use prepared statement to prevent SQL injection
        $sql = "DELETE FROM available_times WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $slot_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">Record deleted successfully
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>';
        } else {
            $failure_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error deleting record: ' . htmlspecialchars(mysqli_error($conn)) . '
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>';
        }
    }
}

// Get current year and month
$current_year = date('Y');
$current_month = date('m');

// Handle navigation buttons
if (isset($_GET['year']) && isset($_GET['month'])) {
    $current_year = $_GET['year'];
    $current_month = $_GET['month'];
}

// Fetch existing slots for the specified month
$month_data = fetchAvailableSlotsForMonth($conn, $current_year, $current_month);
$first_day_of_month = $month_data['first_day_of_month'];
$available_slots = $month_data['available_slots'];

// Get counts of bookings and available slots
$bookings_count = countBookings($conn);
$available_slots_count = countAvailableSlots($conn);

// Fetch the next booking
$next_booking = fetchNextBooking($conn);

// Close connection
mysqli_close($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <!-- Include Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Include Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Dark Theme CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootswatch/4.5.2/darkly/bootstrap.min.css" rel="stylesheet">
    <!-- Flatpickr CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        .calendar {
            background-color: #343a40;
            color: #ffffff;
        }
        .calendar td {
            text-align: center;
        }
        .booked-date {
            color: green;
        }
        .booked-slot {
            color: green;
        }
        .non-booked {
            color: #6c757d;
        }
        .slot-buttons {
            display: flex;
            justify-content: space-between;
        }
        .flight-time {
            font-family: monospace;
            font-size: 18px;
            color: #ffffff;
            background-color: #343a40;
            padding: 5px 10px;
            border-radius: 5px;
        }

		.slot-info small {
   		 display: block;
    	text-align: left;
		}

    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <!-- Bookings -->
            <a class="navbar-brand" href="#">Bookings</a>

            <!-- Toggler/collapsible Button -->
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <!-- Home -->
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <!-- Set slots -->
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">Set slots</a>
                    </li>
                    <?php if (isset($_SESSION['username'])): ?>
                    <!-- Logged-in user's navigation links -->
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                    <?php endif; ?>


                </ul>
            </div>
        </div>
    </nav>



    <br>
    <?php if (isset($_SESSION['username'])): ?>
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <h5 class="card-header">Set Available Slots</h5>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="dates" class="text-light">Dates:</label>
                                <input type="text" class="form-control" id="dates" name="dates" placeholder="Select dates" required>
                            </div>
                            <div class="form-group row">
                                <div class="col">
                                    <label for="start_time" class="text-light">Start Time:</label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" required>
                                </div>
                                <div class="col">
                                    <label for="end_time" class="text-light">End Time:</label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" required>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col">
                                    <button type="submit" class="btn btn-primary" name="submit">Submit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <hr>
            </div>

            <div class="col-md-6">
                <div class="row">
                    <div class="col">
                        <div class="card bg-primary text-white square-card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-calendar-check"></i> Bookings</h5>
                                <h2 class="card-text"><?= $bookings_count ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card bg-success text-white square-card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-calendar"></i> Available Slots</h5>
                                <h2 class="card-text"><?= $available_slots_count ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <h5 class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-camera"></i> Next shooting</span>
                        <span id="countdown" class="flight-time"></span>
                    </h5>
                    <div class="card-body">
                        <?php if ($next_booking): ?>
                        <p class="card-title"><i class="fa fa-female"></i> With: <strong><a href="https://instagram.com/<?= $next_booking['instagram_link'] ?>" target="_blank"><?= $next_booking['model_name'] ?></a></strong></p>
                        <p class="card-text"><i class="fa fa-calendar"></i> <?= date('l, d F Y', strtotime($next_booking['date'])) ?> / <?= date('H:i', strtotime($next_booking['start_time'])) ?> - <?= date('H:i', strtotime($next_booking['end_time'])) ?></p>
                        <script>
                            // Set the countdown timer
                            var countdownDate = new Date("<?= $next_booking['date'] ?> <?= $next_booking['start_time'] ?>").getTime();
                            var countdownFunction = setInterval(function() {
                                var now = new Date().getTime();
                                var distance = countdownDate - now;
                                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                                var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                                document.getElementById("countdown").innerHTML = days + "d " + hours + "h " + minutes + "m " + seconds + "s ";
                                if (distance < 0) {
                                    clearInterval(countdownFunction);
                                    document.getElementById("countdown").innerHTML = "Booking expired";
                                }
                            }, 1000);
                        </script>
                        <?php else: ?>
                        <p class="card-text">No bookings scheduled.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col">
                <?= isset($success_message) ? $success_message : '' ?>
                <?= isset($failure_message) ? $failure_message : '' ?>
            </div>
        </div>

        <div class="card mt-4">
            <h5 class="card-header">Existing Slots</h5>
            <div class="card-body">
                <div class="col-md-12">
                    <div class="card text-light bg-light mb-3">
                        <div class="card-header">
                            <a href="?year=<?= $current_month == 1 ? $current_year - 1 : $current_year ?>&month=<?= $current_month == 1 ? 12 : $current_month - 1 ?>" class="btn btn-secondary">&lt;</a>
                            <?= date('F Y', strtotime("$current_year-$current_month-01")) ?>
                            <a href="?year=<?= $current_month == 12 ? $current_year + 1 : $current_year ?>&month=<?= $current_month == 12 ? 1 : $current_month + 1 ?>" class="btn btn-secondary">&gt;</a>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered calendar">
                                <thead>
                                    <tr>
                                        <th scope="col">Mon</th>
                                        <th scope="col">Tue</th>
                                        <th scope="col">Wed</th>
                                        <th scope="col">Thu</th>
                                        <th scope="col">Fri</th>
                                        <th scope="col">Sat</th>
                                        <th scope="col">Sun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <?php
                                        // Fill in empty cells for days before the first day of the month
                                        for ($i = 1; $i < $first_day_of_month; $i++) {
                                            echo '<td></td>';
                                        }

                                        $day_counter = $first_day_of_month;
                                        for ($day = 1; $day <= cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year); $day++) {
                                            if ($day_counter == 8) {
                                                echo '</tr><tr>';
                                                $day_counter = 1;
                                            }
                                            $date = sprintf("%d-%02d-%02d", $current_year, $current_month, $day);
                                            echo '<td>';
                                            if (isset($available_slots[$day])) {
                                                echo '<div class="booked-date">' . $day . '</div>';
                                                $slot_count = count($available_slots[$day]);
                                                $slot_index = 0;
                                                foreach ($available_slots[$day] as $slot) {
                                                    $time_display = date('H:i', strtotime($slot['start_time'])) . ' - ' . date('H:i', strtotime($slot['end_time']));
                                                    $slot_info = '<div class="slot-info"><small style="display:block;">' . $time_display . '</small><a href="https://instagram.com/' . htmlspecialchars($slot['instagram_link']) . '" target="_blank">' . htmlspecialchars($slot['model_name']) . '</a></div>';
                                                    $delete_form = '<form method="post" action="" class="float-right">
                                                        <input type="hidden" name="slot_id" value="' . htmlspecialchars($slot['id']) . '">
                                                        <button type="submit" class="btn btn-danger btn-sm" name="delete">&times;</button>
                                                    </form>';
                                                    // Check if it's the last slot
                                                    if ($slot_index < $slot_count - 1) {
                                                        echo '<div class="slot-buttons"><span class="booked-slot">' . $slot_info . '</span>' . $delete_form . '</div>';
                                                        // Add horizontal line if it's not the last slot
                                                        echo '<hr style="border-color: grey;">';
                                                    } else {
                                                        echo '<div class="slot-buttons">' . ($slot['model_name'] ? '<span class="booked-slot">' . $slot_info . '</span>' : '<span class="non-booked">' . $time_display . '</span>') . $delete_form . '</div>';
                                                    }
                                                    $slot_index++;
                                                }
                                            } else {
                                                echo '<div class="non-booked">' . $day . '</div>';
                                            }
                                            echo '</td>';
                                            $day_counter++;
                                        }

                                        // Fill in empty cells for remaining days
                                        while ($day_counter <= 7) {
                                            echo '<td></td>';
                                            $day_counter++;
                                        }
                                        ?>
                                    </tr>
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <br>
    </div>

    <?php else: ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Login to access this page</div>
                    <div class="card-body">
                        <?php if(isset($login_error)): ?>
                        <div class="alert alert-danger"><?= $login_error ?></div>
                        <?php endif; ?>
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password:</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary" name="login">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <!-- Include jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Include Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Include Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        // Initialize Flatpickr for date picker
        flatpickr("#dates", {
            mode: "multiple",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "F j, Y",
            minDate: "today"
        });
    </script>
</body>
</html>

