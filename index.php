<?php
require_once('.env.php');

// Establish database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Function to fetch available time slots for the selected date
function fetchAvailableTimeSlots($conn, $selectedDate) {
    $selectedDate = mysqli_real_escape_string($conn, $selectedDate);
    $sql = "SELECT * FROM available_times WHERE date = '$selectedDate' AND is_booked = 0 ORDER BY start_time ASC";
    $result = mysqli_query($conn, $sql);
    $slots = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $slots[] = $row;
    }
    return $slots;
}

// Function to handle booking submission
function submitBooking($conn) {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_booking'])) {
        $model_name = mysqli_real_escape_string($conn, $_POST['model_name']);
        $instagram_link = mysqli_real_escape_string($conn, $_POST['instagram_link']);
        $mobile_number = mysqli_real_escape_string($conn, $_POST['mobile_number']);
        $slot_id = mysqli_real_escape_string($conn, $_POST['slot_id']);

        // Update the database with the booking information using prepared statements
        $sql = "UPDATE available_times SET is_booked = 1, model_name = ?, instagram_link = ?, mobile_number = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);

        // Bind parameters
        mysqli_stmt_bind_param($stmt, "sssi", $model_name, $instagram_link, $mobile_number, $slot_id);

        // Execute the statement
        if (mysqli_stmt_execute($stmt)) {
            return "Booking successful";
        } else {
            return "Error updating record: " . mysqli_error($conn);
        }

        // Close statement
        mysqli_stmt_close($stmt);
    }
}

// Handle booking submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_booking'])) {
    // Call the submitBooking function and store its result
    $booking_result = submitBooking($conn);
}

// Fetch available dates with slots
if(isset($_GET['selected_date'])){
    $selected_date = mysqli_real_escape_string($conn, $_GET['selected_date']);
}else{
    // Set a default date if none is selected
    $selected_date = date("Y-m-d");
}

$sql_dates = "SELECT DISTINCT date FROM available_times WHERE is_booked = 0";
$result_dates = mysqli_query($conn, $sql_dates);
$availableDates = [];
while ($row_date = mysqli_fetch_assoc($result_dates)) {
    $availableDates[] = $row_date['date'];
}

// Function to fetch booked time slots for the selected date
function fetchBookedTimeSlots($conn, $selectedDate) {
    $selectedDate = mysqli_real_escape_string($conn, $selectedDate);
    $sql = "SELECT * FROM available_times WHERE date = '$selectedDate' AND is_booked = 1 ORDER BY start_time ASC";
    $result = mysqli_query($conn, $sql);
    $slots = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $slots[] = $row;
    }
    return $slots;
}

// Fetch booked slots for the selected date
$bookedSlots = fetchBookedTimeSlots($conn, $selected_date);

?>


<!DOCTYPE html>
<html>
<head>
    <title>Photography Calendar</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #1e1e1e;
            color: #ffffff;
        }
        .container {
            margin-top: 20px;
        }
        .form-group label {
            color: #ffffff;
        }
        .form-control {
            background-color: #333333;
            color: #ffffff;
        }
        .form-control:focus {
            background-color: #454545;
            color: #ffffff;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .nav-tabs .nav-item .nav-link {
            color: #ffffff;
        }
        .nav-tabs .nav-item .nav-link.active {
            background-color: #333333;
            color: #ffffff;
            border-color: #333333;
        }
        .tab-pane {
            background-color: #333333;
            padding: 20px;
            border-radius: 5px;
        }
        .list-group-item {
            background-color: #454545;
            color: #ffffff;
            border-color: #454545;
        }
        .list-group-item:hover {
            background-color: #333333;
            color: #ffffff;
        }
        .completed {
            color: green;
        }
        .modal-content {
            background-color: #333333;
            color: #ffffff;
            border-color: #333333;
        }
        .modal-content .modal-title {
            color: #ffffff;
        }
        .modal-content .close {
            color: #ffffff;
        }

		#nextShootings table th,
		#nextShootings table td {
    	color: #fff; /* White color */
		}
    </style>
</head>
<body>

<div class="container">
    <h1 class="mt-4 text-center">Photography Calendar</h1>
<form method="get" action="">
    <div class="form-group">
<label for="selected_date">Select your booking date:</label>
<select class="form-control" id="selected_date" name="selected_date">
    <?php 
    // Filter available dates to exclude past dates
    $currentDate = date("Y-m-d");
    $futureDates = array_filter($availableDates, function($date) use ($currentDate) {
        return $date >= $currentDate;
    });

    // Sort future dates in ascending order
    sort($futureDates);

    foreach ($futureDates as $date) : 
        // Format the date to display in "Friday, 19 April 2024" format
        $formattedDate = date("l, j F Y", strtotime($date));
    ?>
        <option value="<?php echo $date; ?>" <?php if ($date === $selected_date) echo 'selected'; ?>><?php echo $formattedDate; ?></option>
    <?php endforeach; ?>
</select>

    </div>
<div class="row">
    <div class="col-md-6">
        <!-- Submit button -->
        <button type="submit" class="btn btn-primary">Show available slots</button>
    </div>
    <div class="col-md-6">
        <!-- Success alert -->
        <?php if (isset($booking_result) && $booking_result === "Booking successful"): ?>
            <div id="successAlert" class="alert alert-success alert-dismissible fade show" role="alert">
                Booking successful!
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <script>
                // Close the success alert after 5 seconds
                setTimeout(function() {
                    $('#successAlert').alert('close');
                }, 5000);
            </script>
        <?php endif; ?>
    </div>
</div>


</form>


<ul class="nav nav-tabs mt-4">
    <li class="nav-item">
        <a class="nav-link <?php echo ($_GET['active_tab'] === 'bookings' || !isset($_GET['active_tab'])) ? 'active' : ''; ?>" id="bookings-tab" data-toggle="tab" href="#bookings" role="tab" aria-controls="bookings" aria-selected="true">Bookings</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($_GET['active_tab'] === 'nextShootings') ? 'active' : ''; ?>" id="nextShootings-tab" data-toggle="tab" href="#nextShootings" role="tab" aria-controls="nextShootings" aria-selected="false">Next Shootings</a>
    </li>
</ul>


        
   <div class="tab-content" id="myTabContent">
    <!-- Bookings Tab Pane -->
    <div class="tab-pane fade <?php echo ($_GET['active_tab'] === 'bookings' || !isset($_GET['active_tab'])) ? 'show active' : ''; ?>" id="bookings" role="tabpanel" aria-labelledby="bookings-tab">
        <div class="row mt-4">
            <div class="col-md-6">
                <h4>Available Slots for <?php echo isset($selected_date) ? $selected_date : ''; ?>:</h4>
                <ul class="list-group">
                    <?php
                    // Fetch available slots for the selected date
                    $slots = fetchAvailableTimeSlots($conn, $selected_date);
                    foreach ($slots as $slot) {
                        // Get the slot date and time
                        $slotDateTime = strtotime($slot['date'] . ' ' . $slot['start_time']);
                        // Check if the slot is in the future
                        if ($slotDateTime > time()) {
                            echo '<li class="list-group-item">' . $slot['start_time'] . ' - ' . $slot['end_time'] . '<button class="btn btn-primary btn-sm float-right book-slot-btn" data-toggle="modal" data-target="#bookingModal" data-slot-id="' . $slot['id'] . '">Book</button></li>';
                        }
                    }
                    ?>
                </ul>
            </div>

            <div class="col-md-6">
                <h4>Booked Slots for <?php echo isset($selected_date) ? $selected_date : ''; ?>:</h4>
                <ul class="list-group" id="bookedSlots">
                    <?php 
                    // Fetch booked slots for the selected date
                    $bookedSlots = fetchBookedTimeSlots($conn, $selected_date);
                    foreach ($bookedSlots as $bookedSlot) : 
                    ?>
                        <li class="list-group-item">
                            <?php echo $bookedSlot['start_time'] . ' - ' . $bookedSlot['end_time']; ?>
                            <br>
                            Model Name: <?php echo $bookedSlot['model_name']; ?>
                            <br>
                            Instagram username: <a href="https://instagram.com/<?php echo $bookedSlot['instagram_link']; ?>" target="_blank"><?php echo $bookedSlot['instagram_link']; ?></a>
                            <br>
                            Mobile Number: <?php echo $bookedSlot['mobile_number']; ?>
                            <br>
                            Countdown: <span id="countdown_<?php echo $bookedSlot['id']; ?>"></span>
                            <script>
                                // Calculate countdown for each booked slot
                                var startTime_<?php echo $bookedSlot['id']; ?> = "<?php echo $bookedSlot['start_time']; ?>";
                                var startDate_<?php echo $bookedSlot['id']; ?> = "<?php echo $bookedSlot['date']; ?>";
                                var slotId_<?php echo $bookedSlot['id']; ?> = "<?php echo $bookedSlot['id']; ?>";
                                var countDownDate_<?php echo $bookedSlot['id']; ?> = new Date(startDate_<?php echo $bookedSlot['id']; ?> + ' ' + startTime_<?php echo $bookedSlot['id']; ?>).getTime();

                                var x_<?php echo $bookedSlot['id']; ?> = setInterval(function() {
                                    var now_<?php echo $bookedSlot['id']; ?> = new Date().getTime();
                                    var distance_<?php echo $bookedSlot['id']; ?> = countDownDate_<?php echo $bookedSlot['id']; ?> - now_<?php echo $bookedSlot['id']; ?>;

                                    var days_<?php echo $bookedSlot['id']; ?> = Math.floor(distance_<?php echo $bookedSlot['id']; ?> / (1000 * 60 * 60 * 24));
                                    var hours_<?php echo $bookedSlot['id']; ?> = Math.floor((distance_<?php echo $bookedSlot['id']; ?> % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                    var minutes_<?php echo $bookedSlot['id']; ?> = Math.floor((distance_<?php echo $bookedSlot['id']; ?> % (1000 * 60 * 60)) / (1000 * 60));
                                    var seconds_<?php echo $bookedSlot['id']; ?> = Math.floor((distance_<?php echo $bookedSlot['id']; ?> % (1000 * 60)) / 1000);

                                    document.getElementById("countdown_<?php echo $bookedSlot['id']; ?>").innerHTML = days_<?php echo $bookedSlot['id']; ?> + "d " + hours_<?php echo $bookedSlot['id']; ?> + "h " + minutes_<?php echo $bookedSlot['id']; ?> + "m " + seconds_<?php echo $bookedSlot['id']; ?> + "s ";

                                    if (distance_<?php echo $bookedSlot['id']; ?> < 0) {
                                        clearInterval(x_<?php echo $bookedSlot['id']; ?>);
                                        document.getElementById("countdown_<?php echo $bookedSlot['id']; ?>").innerHTML = "<span class='done'>Done</span>";
                                    }
                                }, 1000);
                            </script>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Upcoming Shootings Tab Pane -->
    <div class="tab-pane fade <?php echo ($_GET['active_tab'] === 'nextShootings') ? 'show active' : ''; ?>" id="nextShootings" role="tabpanel" aria-labelledby="nextShootings-tab">
        <h2 class="mt-4">Upcoming Shootings</h2>
        <div class="table-responsive" id="nextShootingsContent">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Name</th>
                        <th>Instagram</th>
                        <th>Mobile</th>
                        <th>Countdown</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Pagination
                    $page = isset($_GET['page']) ? $_GET['page'] : 1;
                    $records_per_page = 5;
                    $offset = ($page - 1) * $records_per_page;

                    // Fetch future bookings with pagination
                    $currentDate = date("Y-m-d");
                    $sql_next_bookings = "SELECT * FROM available_times WHERE date >= '$currentDate' AND is_booked = 1 ORDER BY date ASC, start_time ASC LIMIT $offset, $records_per_page";
                    $result_next_bookings = mysqli_query($conn, $sql_next_bookings);
                    $row_color = 0; // Variable to alternate row colors
                    while ($row_booking = mysqli_fetch_assoc($result_next_bookings)) {
                        $row_color_class = ($row_color % 2 == 0) ? "even-row" : "odd-row";
                        echo "<tr class='$row_color_class'>";
                        echo "<td>" . date('l, j F Y', strtotime($row_booking['date'])) . "</td>"; // Display day of the week and formatted date
                        echo "<td>" . $row_booking['start_time'] . " - " . $row_booking['end_time'] . "</td>";
                        echo "<td>" . $row_booking['model_name'] . "</td>";
                        echo "<td><a href='https://instagram.com/" . htmlspecialchars($row_booking['instagram_link']) . "' target='_blank'>" . htmlspecialchars($row_booking['instagram_link']) . "</a></td>";
                        echo "<td>" . $row_booking['mobile_number'] . "</td>";
                        // Calculate countdown for each upcoming booking
                        $startTime = $row_booking['start_time'];
                        $startDate = $row_booking['date'];
                        $countDownDate = strtotime($startDate . ' ' . $startTime);
                        $now = time();
                        $diff = $countDownDate - $now;
                        if ($diff > 0) {
                            $days = floor($diff / (60 * 60 * 24));
                            $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
                            $minutes = floor(($diff % (60 * 60)) / 60);
                            $seconds = $diff % 60;
                            echo "<td id='countdown_" . $row_booking['id'] . "'>{$days}d {$hours}h {$minutes}m {$seconds}s</td>";
                            // JavaScript for countdown
                            echo "<script>
                                var countDownDate_" . $row_booking['id'] . " = new Date('$startDate $startTime').getTime();
                                var x_" . $row_booking['id'] . " = setInterval(function() {
                                    var now = new Date().getTime();
                                    var distance = countDownDate_" . $row_booking['id'] . " - now;
                                    var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                                    var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                    var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                                    var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                                    document.getElementById('countdown_" . $row_booking['id'] . "').innerHTML = days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's ';
                                    if (distance <= 0) {
                                        clearInterval(x_" . $row_booking['id'] . ");
                                        document.getElementById('countdown_" . $row_booking['id'] . "').innerHTML = '<strong class=\"completed\">Completed</strong>';
                                    }
                                }, 1000);
                            </script>";
                        } else {
                            echo "<td><strong class='completed'>Completed</strong></td>";
                        }
                        echo "</tr>";
                        $row_color++;
                    }
                    ?>
                </tbody>
            </table>
            <!-- Pagination links -->
<!-- Pagination links -->
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-end">
        <?php
        $total_pages_sql = "SELECT COUNT(*) as total FROM available_times WHERE date >= '$currentDate' AND is_booked = 1";
        $result = mysqli_query($conn, $total_pages_sql);
        $total_rows = mysqli_fetch_array($result)['total'];
        $total_pages = ceil($total_rows / $records_per_page);

        $prev_page = $page - 1;
        $next_page = $page + 1;

        // Check if 'active_tab' parameter exists in the current URL
        $active_tab_param = isset($_GET['active_tab']) ? '#nextShootings' : '';
        ?>
        <?php if ($page > 1) : ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $prev_page; ?>&active_tab=nextShootings" aria-label="Previous">
                    <span aria-hidden="true">&laquo; Previous</span>
                </a>
            </li>
        <?php endif; ?>
        <?php if ($page < $total_pages) : ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $next_page; ?>&active_tab=nextShootings" aria-label="Next">
                    <span aria-hidden="true">Next &raquo;</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>









        </div>
    </div>
</div>






       

   
        
<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" role="dialog" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookingModalLabel">Book Slot</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
          
<!-- Form to submit booking details -->
<form id="bookingForm" method="post" action="">
    <div class="form-group">
        <label for="model_name">Model Name:</label>
        <input type="text" class="form-control" id="model_name" name="model_name" required>
    </div>
    <div class="form-group">
        <label for="instagram_link">Instagram username:</label>
        <input type="text" class="form-control" id="instagram_link" name="instagram_link" required>
    </div>
    <div class="form-group">
        <label for="mobile_number">Mobile Number:</label>
        <input type="text" class="form-control" id="mobile_number" name="mobile_number" required>
    </div>
    <input type="hidden" name="slot_id" id="slot_id" value="">
    <button type="submit" class="btn btn-primary" name="submit_booking">Submit</button>
</form>

<script>
// JavaScript to update slot_id before form submission
document.addEventListener("DOMContentLoaded", function() {
    // Get all buttons with the class "book-slot-btn"
    var bookButtons = document.querySelectorAll(".book-slot-btn");

    // Add click event listener to each button
    bookButtons.forEach(function(button) {
        button.addEventListener("click", function() {
            // Get the slot ID from the data-slot-id attribute of the clicked button
            var slotId = button.getAttribute("data-slot-id");
            
            // Update the value of the hidden input field "slot_id" in the form
            document.getElementById("slot_id").value = slotId;
        });
    });
});
</script>



            </div>
        </div>
    </div>
</div>

<!-- Include jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Include Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>


