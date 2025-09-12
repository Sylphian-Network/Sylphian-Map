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

XF.SylphianGeocodeButton = XF.Element.newHandler({
    options: {
        inputSelectors: {
            address: '[name="address"]',
            lat: '[name="lat"]',
            lng: '[name="lng"]'
        },
        statusId: 'geocode-status'
    },

    init: function()
    {
        this.$inputs = {
            address: document.querySelector(this.options.inputSelectors.address),
            lat: document.querySelector(this.options.inputSelectors.lat),
            lng: document.querySelector(this.options.inputSelectors.lng)
        };
        this.$status = document.getElementById(this.options.statusId);

        if (!this.validateElements()) {
            console.error('Required elements not found for geocoding');
            return;
        }

        this.target.addEventListener('click', this.geocodeAddress.bind(this));
    },

    validateElements: function()
    {
        return this.$inputs.address && this.$inputs.lat &&
            this.$inputs.lng && this.$status;
    },

    setLoading: function(loading)
    {
        this.$status.style.display = loading ? 'inline' : 'none';
        this.target.disabled = loading;
    },

    updateCoordinates: function(lat, lng)
    {
        this.$inputs.lat.value = parseFloat(lat).toFixed(6);
        this.$inputs.lng.value = parseFloat(lng).toFixed(6);
    },

    geocodeAddress: function()
    {
        const address = this.$inputs.address.value.trim();

        if (!address) {
            XF.alert('Please enter an address before performing a search.');
            return;
        }

        this.setLoading(true);

        XF.ajax('post', XF.canonicalizeUrl('index.php?map/geocode'), {
            address: address
        }).then((response) => {
            if (response.data?.lat && response.data?.lng) {
                this.updateCoordinates(response.data.lat, response.data.lng);
                XF.flashMessage('The address was successfully converted to coordinates.', 3000);
            } else {
                console.error('Unexpected geocoding response:', response);
            }
        }).catch((error) => {
            XF.alert(error.errors?.[0] || 'Could not convert this address to coordinates. Please check it and try again.');
        }).finally(() => {
            this.setLoading(false);
        });
    }
});

XF.Element.register('sylphian-geocode-button', 'XF.SylphianGeocodeButton');
XF.Element.register('sylphian-location-button', 'XF.SylphianLocationButton');