(function () {
    function initDailySmsChart() {
        var ctx = document.getElementById('dailySmsChart');
        if (!ctx || !window.dailySmsData || !window.Chart) {
            return;
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: window.dailySmsData.labels || [],
                datasets: [{
                    label: 'SMS',
                    data: window.dailySmsData.values || [],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function initRevenueByCountryChart() {
        var ctx = document.getElementById('revenueByCountryChart');
        if (!ctx || !window.revenueByCountryData || !window.Chart) {
            return;
        }

        var labels = [];
        var values = [];
        window.revenueByCountryData.forEach(function (row) {
            labels.push(row.country || 'Unknown');
            values.push(row.revenue || 0);
        });

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997']
                }]
            },
            options: {
                responsive: true
            }
        });
    }

    function initTopRangesChart() {
        var ctx = document.getElementById('topRangesChart');
        if (!ctx || !window.topRangesData || !window.Chart) {
            return;
        }

        var labels = [];
        var values = [];
        window.topRangesData.forEach(function (row) {
            labels.push(row.range_name || 'Range');
            values.push(row.sms_total || 0);
        });

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'SMS',
                    data: values,
                    backgroundColor: '#198754'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function autoRefreshStats() {
        if (!window.autoStatsEndpoint || !window.jQuery) {
            return;
        }

        setInterval(function () {
            window.jQuery.getJSON(window.autoStatsEndpoint, function (data) {
                if (!data || data.error) {
                    return;
                }
                if (typeof data.total_ranges !== 'undefined') {
                    var el = document.getElementById('totalRanges');
                    if (el) el.textContent = data.total_ranges;
                }
                if (typeof data.active_users !== 'undefined') {
                    var el2 = document.getElementById('activeUsers');
                    if (el2) el2.textContent = data.active_users;
                }
                if (typeof data.total_revenue !== 'undefined') {
                    var el3 = document.getElementById('totalRevenue');
                    if (el3) el3.textContent = '$' + Number(data.total_revenue).toFixed(2);
                }
                if (typeof data.monthly_sms !== 'undefined') {
                    var el4 = document.getElementById('monthlySms');
                    if (el4) el4.textContent = data.monthly_sms;
                }
            });
        }, 30000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDailySmsChart();
        initRevenueByCountryChart();
        initTopRangesChart();
        autoRefreshStats();
    });
})();