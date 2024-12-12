<?php
// Include the database connection
include('../../config/db_connect.php');

// Fetch events from the database
$eventsQuery = "SELECT id, title, start, end FROM events";
$eventsStmt = $pdo->prepare($eventsQuery);
$eventsStmt->execute();
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendar</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primarycol: '#47663B',
            sec: '#E8ECD7',
            third: '#EED3B1',
            fourth: '#1F4529',
          }
        }
      }
    }

    $(document).ready(function() {
      $('#toggleSidebar').on('click', function() {
        $('#sidebar').toggleClass('-translate-x-full');
      });

      $('#closeSidebar').on('click', function() {
        $('#sidebar').addClass('-translate-x-full');
      });

      // Initialize FullCalendar
      var calendarEl = document.getElementById('calendar');
      var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: <?= json_encode($events); ?>,
        dateClick: function(info) {
          $('#eventDate').val(info.dateStr);
          $('#eventModal').removeClass('hidden');
        }
      });
      calendar.render();

      // Event listener for adding events
      $('#addEventForm').on('submit', function(e) {
        e.preventDefault();
        const date = $('#eventDate').val();
        const title = $('#eventTitle').val();
        addEvent(date, title);
        $('#eventModal').addClass('hidden');
      });

      // Function to add event
      function addEvent(date, title) {
        $.ajax({
          url: 'add_event.php',
          type: 'POST',
          data: { date: date, title: title },
          success: function(response) {
            calendar.addEvent({
              title: title,
              start: date
            });
          },
          error: function() {
            alert('There was an error adding the event.');
          }
        });
      }

      // Close modal when clicking outside of it
      $(document).on('click', function(event) {
        if (!$(event.target).closest('#eventModal, .fc-daygrid-day').length) {
          $('#eventModal').addClass('hidden');
        }
      });
    });
  </script>
</head>
<body class="flex h-screen bg-gray-100">
<?php include '../layout/sidebaruser.php'?>

<div class="p-6 w-full">
  <div class="bg-white rounded-lg shadow p-4">
    <h3 class="text-lg font-semibold text-gray-700 mb-4">Calendar</h3>
    <div id="calendar" class="w-full max-w-4xl mx-auto"></div>
  </div>
</div>

<!-- Event Modal -->
<div id="eventModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
  <div class="bg-white rounded-lg shadow p-6 w-1/3">
    <h3 class="text-lg font-semibold text-gray-700 mb-4">Add Event</h3>
    <form id="addEventForm">
      <div class="mb-4">
        <label for="eventDate" class="block text-sm font-medium text-gray-700">Date</label>
        <input type="text" id="eventDate" name="eventDate" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-primarycol focus:border-primarycol" readonly>
      </div>
      <div class="mb-4">
        <label for="eventTitle" class="block text-sm font-medium text-gray-700">Event Title</label>
        <input type="text" id="eventTitle" name="eventTitle" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-primarycol focus:border-primarycol" required>
      </div>
      <button type="submit" class="w-full bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition-colors">Add Event</button>
    </form>
  </div>
</div>
</body>
</html>