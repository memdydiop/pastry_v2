import ApexCharts from 'apexcharts';

function initRevenueChart() {
    const el = document.getElementById('revenueChart');
    const dataEl = document.getElementById('revenueChartData');
    if (!el || !dataEl) return;

    if (el.__apexChart) {
        el.__apexChart.destroy();
        delete el.__apexChart;
    }

    let data;
    try {
        data = JSON.parse(dataEl.textContent);
    } catch {
        return;
    }

    el.__apexChart = new ApexCharts(el, {
        series: [
            { name: 'Commandes', type: 'area', data: data.map(d => d.orders) },
            { name: 'Revenus', type: 'bar', data: data.map(d => d.revenue) },
            { name: 'Remboursements', type: 'line', data: data.map(d => d.refunds) }
        ],
        chart: { height: 370, type: 'line', toolbar: { show: false } },
        stroke: { curve: 'straight', dashArray: [0, 0, 8], width: [2, 0, 2.2] },
        fill: { opacity: [0.1, 0.9, 1] },
        markers: { size: [0, 0, 0], strokeWidth: 2, hover: { size: 4 } },
        xaxis: {
            categories: data.map(d => d.label),
            labels: {
                rotate: -45, hideOverlappingLabels: true, style: { fontSize: '10px' }
            },
            axisTicks: { show: false }, axisBorder: { show: false }
        },
        yaxis: [
            {
                opposite: true,
                axisTicks: { show: true },
                axisBorder: { show: true, color: '#6366f1' },
                labels: {
                    formatter: v => Math.round(v)
                },
                min: 0,
                title: { text: 'Commandes', style: { fontSize: '10px' } }
            },
            {
                axisTicks: { show: true },
                axisBorder: { show: true, color: '#10b981' },
                labels: {
                    formatter: v => v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v
                },
                title: { text: 'Montant (FCFA)', style: { fontSize: '10px' } }
            }
        ],
        grid: { show: true, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } }, padding: { top: 0, right: -2, bottom: 15, left: 10 } },
        legend: { show: true, horizontalAlign: 'center', offsetX: 0, offsetY: -5, markers: { width: 9, height: 9, radius: 6 }, itemMargin: { horizontal: 10, vertical: 0 } },
        plotOptions: { bar: { columnWidth: '30%', barHeight: '70%' } },
        colors: ['#6366f1', '#10b981', '#f43f5e'],
        tooltip: {
            shared: true,
            y: [
                { formatter: v => v !== undefined ? v + ' commandes' : v },
                { formatter: v => v !== undefined ? v.toLocaleString() + ' FCFA' : v },
                { formatter: v => v !== undefined ? v.toLocaleString() + ' FCFA' : v }
            ]
        }
    });
    el.__apexChart.render();
}

// Re-init chart after Livewire patches the DOM with new data
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.updated', () => initRevenueChart());
});

document.addEventListener('livewire:navigated', () => initRevenueChart());

// Initialize on first page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRevenueChart);
} else {
    initRevenueChart();
}
