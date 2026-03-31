document.addEventListener("DOMContentLoaded", function () {
    const yearCanvas = document.getElementById("kpiYearChart");

    if (yearCanvas) {
        const ctx = yearCanvas.getContext("2d");

        const gradient = ctx.createLinearGradient(0, 0, 0, 220);
        gradient.addColorStop(0, "rgba(75, 21, 53, 0.18)");
        gradient.addColorStop(1, "rgba(75, 21, 53, 0.02)");

        new Chart(ctx, {
            type: "line",
            data: {
                labels: kpiYears,
                datasets: [{
                    label: "Average KPI",
                    data: kpiYearPercentages,
                    borderColor: "#4B1535",
                    backgroundColor: gradient,
                    tension: 0.42,
                    fill: true,
                    borderWidth: 4,

                    pointRadius: 0,
                    pointHoverRadius: 7,
                    pointHitRadius: 18,
                    pointBackgroundColor: "#4B1535",
                    pointHoverBackgroundColor: "#4B1535",
                    pointBorderColor: "#FFFFFF",
                    pointHoverBorderColor: "#FFFFFF",
                    pointBorderWidth: 0,
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: "nearest",
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: "#4B1535",
                        titleColor: "#FFFFFF",
                        bodyColor: "#FFFFFF",
                        displayColors: false,
                        padding: 10,
                        cornerRadius: 10,
                        caretSize: 6,
                        callbacks: {
                            title: function (context) {
                                return "Year " + context[0].label;
                            },
                            label: function (context) {
                                return context.raw + "%";
                            }
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 6,
                        right: 10,
                        bottom: 0,
                        left: 4
                    }
                },
                scales: {
                    y: {
                        min: 60,
                        max: 80,
                        ticks: {
                            stepSize: 2,
                            callback: function (value) {
                                return value + "%";
                            },
                            font: {
                                size: 10
                            },
                            color: "#6f6a6d"
                        },
                        title: {
                            display: false
                        },
                        grid: {
                            color: "rgba(0,0,0,0.06)",
                            drawBorder: false
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            },
                            color: "#6f6a6d"
                        },
                        title: {
                            display: false
                        },
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});