<?php
require_once('.env.php');

// Establish database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
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

// Handle form submission
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

// Close connection
mysqli_close($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <!-- Bookings -->
    <a class="navbar-brand" href="#">Bookings</a>

    <!-- Toggler/collapsibe Button -->
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
      </ul>
    </div>
  </div>
</nav>
<br>
    
    <div class="container">
       
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
        <div class="col">
          <?= isset($success_message) ? $success_message : '' ?>
          <?= isset($failure_message) ? $failure_message : '' ?>
        </div>
      </div>
    </form>
  </div>
</div>
<hr>
 <div class="card">
  <h5 class="card-header">Existing Slots</h5>
  <div class="card-body">
   
      <div class="col-md-12">
       
        <div class="card text-dark bg-light mb-3">
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
                          foreach ($available_slots[$day] as $slot) {
                              $time_display = date('H:i', strtotime($slot['start_time'])) . ' - ' . date('H:i', strtotime($slot['end_time']));
                              $slot_info = '<div class="slot-info">' . $time_display . ' <a href="https://instagram.com/' . htmlspecialchars($slot['instagram_link']) . '" target="_blank">' . htmlspecialchars($slot['model_name']) . '</a></div>';
                              $delete_form = '<form method="post" action="" class="float-right">
                                              <input type="hidden" name="slot_id" value="' . htmlspecialchars($slot['id']) . '">
                                              <button type="submit" class="btn btn-danger btn-sm" name="delete">&times;</button>
                                          </form>';
                              echo '<div class="slot-buttons">' . ($slot['model_name'] ? '<span class="booked-slot">' . $slot_info . '</span>' : '<span class="non-booked">' . $time_display . '</span>') . $delete_form . '</div>';
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
