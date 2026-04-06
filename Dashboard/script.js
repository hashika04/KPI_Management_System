// Dashboard/script.js

function renderSparkline(elementSelector, dataPoints, colorCode) {
    const element = document.querySelector(elementSelector);
    if (!element) return; // Exit if the div doesn't exist

    var options = {
        series: [{
            name: 'Avg Score',
            data: dataPoints
        }],
        chart: {
            type: 'area', // Changed to area for the nice gradient fill
            height: 60,
            sparkline: {
                enabled: true
            },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800
            }
        },
        stroke: {
            curve: 'smooth',
            width: 3,
            colors: [colorCode]
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [20, 100]
            },
        },
        tooltip: {
            enabled: true,
            theme: 'light',
            followCursor: true, // This keeps the box near your mouse instead of at the top
            offsetY: -10,
            fixed: {
                enabled: false
            },
            x: {
                show: true
            },
            marker: {
                show: true // Keeps the blue dot
            }
        },

        xaxis: {
            categories: ['2022', '2023', '2024', '2025'], // Labels for the dots
            tooltip: {
                enabled: false // This will show "2022" inside the popup
            }
        },
        yaxis: {
            min: 0,
            max: 100 // Keeps the scale consistent so the "drop" is clear
        }
    };

    var chart = new ApexCharts(element, options);
    chart.render();
}

