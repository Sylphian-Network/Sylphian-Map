XF.SylphianLocationButton = XF.Element.newHandler({
    options: {},

    init: function()
    {
        this.target.addEventListener('click', this.getLocation.bind(this));
    },

    getLocation: function()
    {
        var latInput = document.querySelector('[name="lat"]');
        var lngInput = document.querySelector('[name="lng"]');
        var statusElement = document.getElementById('location-status');
        var button = this.target;

        if (!window.isSecureContext) {
            console.warn("Geolocation API requires a secure context (HTTPS)");
            XF.alert("Geolocation API requires a secure context (HTTPS)");
            return;
        }

        if (!navigator.geolocation) {
            console.warn("This browser does not support GeoLocation, consider switching to a different browser.");
            XF.alert("This browser does not support GeoLocation, consider switching to a different browser.");
            return;
        }

        statusElement.style.display = 'inline';
        button.disabled = true;

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                latInput.value = pos.coords.latitude.toFixed(6);
                lngInput.value = pos.coords.longitude.toFixed(6);
                statusElement.style.display = 'none';
                button.disabled = false;
                XF.flashMessage("Successfully fetched your location.", 3000);
                console.info(pos.coords);
            },
            function(err) {
                statusElement.style.display = 'none';
                button.disabled = false;

                var errorMessage;
                switch(err.code) {
                    case err.PERMISSION_DENIED:
                        errorMessage = "You denied permission to access your location.";
                        break;
                    case err.POSITION_UNAVAILABLE:
                        errorMessage = "Your location information is unavailable.";
                        break;
                    case err.TIMEOUT:
                        errorMessage = "The request to get your location timed out.";
                        break;
                    default:
                        errorMessage = err.message;
                }

                XF.alert("Could not fetch your geolocation. Error: " + errorMessage);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
});

XF.Element.register('sylphian-location-button', 'XF.SylphianLocationButton');