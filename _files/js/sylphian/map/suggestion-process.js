XF.SylphianMapSuggestionPreview = XF.Element.newHandler({
    options: {},

    init: function() {
        if (!window.L || !L.AwesomeMarkers) {
            console.error('Leaflet or AwesomeMarkers not loaded.');
            return;
        }

        if (!this.$target || !this.$target.length) {
            console.info('Map preview not ready, retrying...');

            setTimeout(() => {
                const mapElement = document.getElementById('suggestionMapPreview');
                if (mapElement) {
                    this.initializeMap(mapElement);
                }
            }, 100);
            return;
        }

        const container = this.$target[0];
        if (!container) return;

        this.initializeMap(container);
    },

    initializeMap: function(container) {
        const mapPreviewContainer = container.closest('.mapPreviewContainer');
        if (!mapPreviewContainer) {
            console.error('Map preview container not found');
            return;
        }

        const lat = parseFloat(mapPreviewContainer.dataset.lat);
        const lng = parseFloat(mapPreviewContainer.dataset.lng);
        const icon = mapPreviewContainer.dataset.icon || 'map-marker-alt';
        const iconVar = mapPreviewContainer.dataset.iconVar || 'solid';
        const iconColor = mapPreviewContainer.dataset.iconColor || 'black';
        const markerColor = mapPreviewContainer.dataset.markerColor || 'blue';

        setTimeout(() => {
            const map = L.map(container.id).setView([lat, lng], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: 'Map data from <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            L.marker([lat, lng], {
                icon: L.AwesomeMarkers.icon({
                    icon: icon,
                    iconVar: iconVar,
                    iconColor: iconColor,
                    markerColor: markerColor
                })
            }).addTo(map).bindPopup(null);

            const tabs = document.querySelectorAll('.tabs-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    setTimeout(function() {
                        map.invalidateSize();
                    }, 10);
                });
            });
        }, 100);
    }
});

XF.Element.register('sylphian-map-preview', 'XF.SylphianMapSuggestionPreview');