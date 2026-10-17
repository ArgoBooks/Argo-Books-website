document.addEventListener('DOMContentLoaded', function () {
    // Premium Subscriptions Chart initialization
    const subLabels = subscriptionChartData.map(item => item.date);
    // The query returns per-day counts (how many were created that day). Plot a
    // running total instead, so the line shows how many subscriptions exist as
    // of each date and reaches the real total (matching the stat card) rather
    // than sitting at 1 when each day only adds one.
    let runningTotal = 0;
    let runningActive = 0;
    const subTotals = subscriptionChartData.map(item => (runningTotal += parseInt(item.total)));
    const subActive = subscriptionChartData.map(item => (runningActive += parseInt(item.active)));

    const subCtx = document.getElementById('subscriptionsChart').getContext('2d');
    new Chart(subCtx, {
        type: 'line',
        data: {
            labels: subLabels,
            datasets: [
                {
                    label: 'Total Subscriptions',
                    data: subTotals,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)'
                },
                {
                    label: 'Active Subscriptions',
                    data: subActive,
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(16, 185, 129, 1)'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                },
                x: {
                    ticks: {
                        padding: 10
                    }
                }
            },
            plugins: {
                title: {
                    display: false
                },
                legend: {
                    position: 'top',
                }
            },
        }
    });
});

// Restore scroll position after page fully loads
window.addEventListener('load', function () {
    if (sessionStorage.getItem('scrollPosition')) {
        const scrollPos = sessionStorage.getItem('scrollPosition');
        sessionStorage.removeItem('scrollPosition');

        // Use requestAnimationFrame to ensure page is fully rendered
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                window.scrollTo(0, parseInt(scrollPos));
            });
        });
    }
});