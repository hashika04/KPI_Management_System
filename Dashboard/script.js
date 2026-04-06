// GLOBAL PLOTLY THEME
const plotlyTheme = {
  paper_bgcolor: 'rgba(0,0,0,0)',
  plot_bgcolor: '#ffffff',
  font: {
    family: 'Sora, sans-serif',
    color: '#3a2948',
    size: 13
  },
  margin: { t: 20, r: 20, b: 50, l: 50 },
  xaxis: {
    gridcolor: '#f3e6ee',
    zerolinecolor: '#f3e6ee',
    tickfont: { color: '#7f6a7b' }
  },
  yaxis: {
    gridcolor: '#f3e6ee',
    zerolinecolor: '#f3e6ee',
    tickfont: { color: '#7f6a7b' }
  },
  legend: {
    orientation: 'h',
    y: -0.2,
    font: { size: 12, color: '#7f6a7b' }
  }
};

function renderSparkline(elementSelector, dataPoints, colorCode) {
    const element = document.querySelector(elementSelector);
    if (!element) return;

    var options = {
        series: [{ name: 'Avg Score', data: dataPoints }],
        chart: {
            type: 'area',
            height: 60,
            sparkline: { enabled: true },
            animations: { enabled: true, easing: 'easeinout', speed: 800 }
        },
        stroke: { curve: 'smooth', width: 3, colors: [colorCode] },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [20, 100]
            }
        },
        tooltip: {
            enabled: true,
            theme: 'light',
            followCursor: true,
            offsetY: -10,
            fixed: { enabled: false },
            x: { show: true },
            marker: { show: true }
        },
        xaxis: {
            categories: ['2022', '2023', '2024', '2025'],
            tooltip: { enabled: false }
        },
        yaxis: { min: 0, max: 100 }
    };

    new ApexCharts(element, options).render();
}

document.addEventListener("DOMContentLoaded", function () {
    const yearCanvas = document.getElementById("kpiYearChart");
    
    // Dashboard/script.js
});
