import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

// Make Alpine.js available globally
window.Alpine = Alpine;
Alpine.start();

// Make Chart.js available globally for Blade templates
window.Chart = Chart;
