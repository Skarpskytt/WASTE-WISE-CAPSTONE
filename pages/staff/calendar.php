<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendar</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
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

      // Initialize calendar
      generateCalendar();

      // Event listener for adding events
      $('#addEventForm').on('submit', function(e) {
        e.preventDefault();
        const date = $('#eventDate').val();
        const event = $('#eventDescription').val();
        addEvent(date, event);
        $('#eventModal').hide();
      });
    });

    function generateCalendar() {
      const calendar = $('#calendar');
      const today = new Date();
      const month = today.getMonth();
      const year = today.getFullYear();
      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();

      calendar.empty();

      // Add blank days for the first week
      for (let i = 0; i < firstDay; i++) {
        calendar.append('<div class="p-4 border border-gray-200"></div>');
      }

      // Add days of the month
      for (let i = 1; i <= daysInMonth; i++) {
        const day = $('<div>').addClass('p-4 border border-gray-200 cursor-pointer').text(i);
        if (i === today.getDate()) {
          day.addClass('bg-primarycol text-white');
        }
        day.on('click', function() {
          $('#eventDate').val(`${year}-${month + 1}-${i}`);
          $('#eventModal').show();
        });
        calendar.append(day);
      }
    }

    function addEvent(date, event) {
      const day = $(`#calendar div:contains(${parseInt(date.split('-')[2])})`);
      const eventList = day.find('ul');
      if (eventList.length === 0) {
        const newEventList = $('<ul>').addClass('list-disc pl-5 mt-2');
        day.append(newEventList);
        newEventList.append($('<li>').text(event));
      } else {
        eventList.append($('<li>').text(event));
      }
    }
  </script>
</head>

<body class="flex h-screen font-roboto">
  <?php include '../layout/sidebaruser.php'?>

  <div class="p-8 w-full">
    <h2 class="text-2xl font-semibold mb-10">Calendar</h2>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <button id="prevMonth" class="bg-gray-200 p-2 rounded">Previous</button>
        <h3 id="currentMonth" class="text-xl font-semibold"></h3>
        <button id="nextMonth" class="bg-gray-200 p-2 rounded">Next</button>
      </div>
      <div id="calendar" class="grid grid-cols-7 gap-4"></div>
    </div>
  </div>

  <!-- Event Modal -->
  <div id="eventModal" class="fixed inse  t-0 items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-md w-96">
      <h3 class="text-xl font-semibold mb-4">Add Event</h3>
      <form id="addEventForm">
        <div class="mb-4">
          <label for="eventDate" class="block text-sm font-medium text-gray-700">Date</label>
          <input type="text" id="eventDate" name="eventDate" class="mt-1 p-2 w-full border rounded-md" readonly>
        </div>
        <div class="mb-4">
          <label for="eventDescription" class="block text-sm font-medium text-gray-700">Event Description</label>
          <input type="text" id="eventDescription" name="eventDescription" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <button type="submit" class="bg-blue-500 text-white p-2 rounded-md w-full">Add Event</button>
      </form>
      <button class="mt-4 bg-red-500 text-white p-2 rounded-md w-full" onclick="$('#eventModal').hide()">Close</button>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const today = new Date();
      let currentMonth = today.getMonth();
      let currentYear = today.getFullYear();

      function updateCalendar() {
        const calendar = $('#calendar');
        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        $('#currentMonth').text(`${monthNames[currentMonth]} ${currentYear}`);
        calendar.empty();

        // Add day headers
        const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        dayHeaders.forEach(day => {
          calendar.append(`<div class="p-4 font-semibold text-center">${day}</div>`);
        });

        // Add blank days for the first week
        for (let i = 0; i < firstDay; i++) {
          calendar.append('<div class="p-4 border border-gray-200"></div>');
        }

        // Add days of the month
        for (let i = 1; i <= daysInMonth; i++) {
          const day = $('<div>').addClass('p-4 border border-gray-200 cursor-pointer').text(i);
          if (i === today.getDate() && currentMonth === today.getMonth() && currentYear === today.getFullYear()) {
            day.addClass('bg-primarycol text-white');
          }
          day.on('click', function() {
            $('#eventDate').val(`${currentYear}-${currentMonth + 1}-${i}`);
            $('#eventModal').show();
          });
          calendar.append(day);
        }
      }

      $('#prevMonth').on('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
          currentMonth = 11;
          currentYear--;
        }
        updateCalendar();
      });

      $('#nextMonth').on('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
          currentMonth = 0;
          currentYear++;
        }
        updateCalendar();
      });

      updateCalendar();
    });
  </script>
</body>
</html>