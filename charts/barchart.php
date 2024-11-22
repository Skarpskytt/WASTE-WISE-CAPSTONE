<?php ?>


<div id="barchart">

</div>



<script>
   
   var options = {
          series: [{
          data: [400, 430, 448, 470, 540, 580, 690, 1100, 1200, 1380]
        }],
          chart: {
          type: 'bar',
          height: 350
        },
        plotOptions: {
          bar: {
            borderRadius: 4,
            borderRadiusApplication: 'end',
            horizontal: true,
          }
        },
        dataLabels: {
          enabled: false
        },
        xaxis: {
          categories: ['Ingredients', 'Chicken', 'Meat', 'Dough', 'Beef', 'Fish', 'Cereal',
            'Pasta', 'Eggs', 'Fruit'
          ],
        }
        };

        var chart = new ApexCharts(document.querySelector("#barchart"), options);
        chart.render();
      
      
    
</script>