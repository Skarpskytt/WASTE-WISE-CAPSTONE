<?php ?>


  <div id="linechart"></div>

  <script>
     var options = {
            series: [{
            name: "Ensaymada",
            data: [12, 45, 32, 54, 47, 60, 70, 90, 150]
          }, {
            name: "Muffin",
            data: [15, 40, 30, 50, 48, 65, 68, 92, 140]
          }, {
            name: "Pandesal",
            data: [11, 42, 36, 52, 50, 63, 67, 89, 145]
          }],
          chart: {
          height: 350,
          type: 'line',
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight'
        },
        grid: {
          row: {
            colors: ['#f3f3f3', 'transparent'], // takes an array which will be repeated on columns
            opacity: 0.5
          },
        },
        xaxis: {
          categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
        }
        };

        var chart = new ApexCharts(document.querySelector("#linechart"), options);
        chart.render();
      
      
  </script>