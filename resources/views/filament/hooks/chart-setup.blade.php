{{-- Chart.js dari CDN --}}
<script src="{{ asset('js/chart.min.js') }}"></script>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('skyChart', (config) => ({
        chart: null,

        init() {
            this.$nextTick(() => this.createChart());
        },

        destroy() {
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
        },

        createChart() {
            if (typeof window.Chart === 'undefined') {
                setTimeout(() => this.createChart(), 100);
                return;
            }

            const ctx = this.$refs.canvas.getContext('2d');

            this.chart = new window.Chart(ctx, {
                type: config.type,
                data: config.data,
                options: {
                    ...config.options,
                    responsive: true,
                    maintainAspectRatio: false,
                },
            });
        },
    }));
});
</script>
