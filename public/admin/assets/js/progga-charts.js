/**
 * Progga RMS — Charts
 * progga-charts.js  (requires Chart.js)
 */

(function () {
  'use strict';

  const PRIMARY    = '#21352a';
  const SECONDARY  = '#d5aa65';
  const SUCCESS    = '#2e7d52';
  const WARNING    = '#c67c28';
  const DANGER     = '#b93a3a';
  const INFO       = '#1a6b8a';
  const MUTED      = '#9aada5';
  const BG         = '#f2e2ce';

  /* ─── Default Chart.js Overrides ─── */
  function applyDefaults() {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family   = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.font.size     = 12;
    Chart.defaults.color         = '#6b7c74';
    Chart.defaults.plugins.legend.labels.boxWidth  = 12;
    Chart.defaults.plugins.legend.labels.borderRadius = 3;
    Chart.defaults.plugins.tooltip.backgroundColor = PRIMARY;
    Chart.defaults.plugins.tooltip.titleColor      = SECONDARY;
    Chart.defaults.plugins.tooltip.bodyColor       = '#fff';
    Chart.defaults.plugins.tooltip.padding         = 10;
    Chart.defaults.plugins.tooltip.cornerRadius    = 6;
    Chart.defaults.plugins.tooltip.displayColors   = false;
  }

  /* ─── Revenue Line Chart ─── */
  function initRevenueChart() {
    const canvas = document.getElementById('revenueChart');
    if (!canvas) return;

    const data7 = {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      values: [12400, 14800, 11200, 17600, 19800, 22400, 18600]
    };
    const data30 = {
      labels: Array.from({ length: 30 }, function (_, i) { return 'D' + (i + 1); }),
      values: Array.from({ length: 30 }, function () { return Math.floor(Math.random() * 20000) + 8000; })
    };

    let currentPeriod = '7';
    let chart;

    function buildChart(d) {
      if (chart) chart.destroy();
      chart = new Chart(canvas, {
        type: 'line',
        data: {
          labels: d.labels,
          datasets: [{
            label: 'Revenue (৳)',
            data: d.values,
            borderColor: SECONDARY,
            backgroundColor: function (ctx) {
              const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 240);
              gradient.addColorStop(0, 'rgba(213,170,101,0.22)');
              gradient.addColorStop(1, 'rgba(213,170,101,0)');
              return gradient;
            },
            borderWidth: 2.5,
            pointBackgroundColor: SECONDARY,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: MUTED }
            },
            y: {
              grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
              ticks: {
                color: MUTED,
                callback: function (v) { return '৳' + (v / 1000).toFixed(0) + 'k'; }
              },
              border: { dash: [3, 3] }
            }
          },
          interaction: { intersect: false, mode: 'index' },
          plugins: {
            tooltip: {
              callbacks: {
                label: function (ctx) { return '৳' + ctx.parsed.y.toLocaleString(); }
              }
            }
          }
        }
      });
    }

    buildChart(data7);

    document.querySelectorAll('.progga-chart-toggle-btn[data-revenue-period]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.progga-chart-toggle-btn[data-revenue-period]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentPeriod = btn.dataset.revenuePeriod;
        buildChart(currentPeriod === '7' ? data7 : data30);
      });
    });
  }

  /* ─── Top Items Bar Chart ─── */
  function initTopItemsChart() {
    const canvas = document.getElementById('topItemsChart');
    if (!canvas) return;

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: ['Kacchi Biryani', 'Chicken Roast', 'Beef Rezala', 'Prawn Bhuna', 'Mutton Korma'],
        datasets: [{
          label: 'Orders',
          data: [142, 118, 96, 87, 74],
          backgroundColor: [PRIMARY, '#2e4a3c', SECONDARY, '#e0bf85', SUCCESS],
          borderRadius: 6,
          borderSkipped: false
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: {
            grid: { color: 'rgba(0,0,0,0.04)' },
            ticks: { color: MUTED },
            border: { dash: [3, 3] }
          },
          y: {
            grid: { display: false },
            ticks: { color: PRIMARY, font: { weight: '600' } }
          }
        }
      }
    });
  }

  /* ─── Order Status Donut ─── */
  function initOrderStatusChart() {
    const canvas = document.getElementById('orderStatusChart');
    if (!canvas) return;

    new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: ['Pending', 'Cooking', 'Ready', 'Delivered', 'Cancelled'],
        datasets: [{
          data: [18, 24, 12, 86, 5],
          backgroundColor: [WARNING, INFO, SUCCESS, PRIMARY, DANGER],
          borderColor: BG,
          borderWidth: 3,
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: { padding: 16, usePointStyle: true, pointStyleWidth: 10 }
          }
        }
      }
    });
  }

  /* ─── Monthly Sales Bar (Reports) ─── */
  function initMonthlySalesChart() {
    const canvas = document.getElementById('monthlySalesChart');
    if (!canvas) return;

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const sales  = [185000, 204000, 198000, 221000, 245000, 267000, 232000, 288000, 271000, 256000, 310000, 295000];

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: months,
        datasets: [{
          label: 'Revenue (৳)',
          data: sales,
          backgroundColor: PRIMARY,
          borderRadius: 6,
          borderSkipped: false
        }, {
          label: 'Orders',
          data: [620, 680, 660, 740, 810, 890, 770, 950, 920, 870, 1020, 990],
          backgroundColor: SECONDARY,
          borderRadius: 6,
          borderSkipped: false,
          yAxisID: 'y1'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', align: 'end' }
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            grid: { color: 'rgba(0,0,0,0.04)' },
            ticks: { callback: function (v) { return '৳' + (v / 1000) + 'k'; } },
            border: { dash: [3, 3] }
          },
          y1: {
            position: 'right',
            grid: { display: false },
            ticks: { color: MUTED }
          }
        }
      }
    });
  }

  /* ─── Daily Sales Line (Reports) ─── */
  function initDailySalesChart() {
    const canvas = document.getElementById('dailySalesChart');
    if (!canvas) return;

    const hours = ['6am', '8am', '10am', '12pm', '2pm', '4pm', '6pm', '8pm', '10pm'];
    new Chart(canvas, {
      type: 'line',
      data: {
        labels: hours,
        datasets: [{
          label: 'Today',
          data: [1200, 3400, 5600, 12800, 9400, 7200, 18600, 14200, 6800],
          borderColor: PRIMARY,
          backgroundColor: 'rgba(33,53,42,0.06)',
          fill: true,
          tension: 0.4,
          borderWidth: 2,
          pointRadius: 3
        }, {
          label: 'Yesterday',
          data: [800, 2800, 4900, 11200, 8600, 6400, 16800, 12400, 5800],
          borderColor: MUTED,
          backgroundColor: 'transparent',
          borderDash: [4, 4],
          tension: 0.4,
          borderWidth: 1.5,
          pointRadius: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top', align: 'end' } },
        scales: {
          x: { grid: { display: false } },
          y: {
            grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
            ticks: { callback: function (v) { return '৳' + (v / 1000) + 'k'; } }
          }
        }
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    applyDefaults();
    initRevenueChart();
    initTopItemsChart();
    initOrderStatusChart();
    initMonthlySalesChart();
    initDailySalesChart();
  });

})();
