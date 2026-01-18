# Sylphian/Map
A simple map addon I designed for my forum. This addon allows your community members to view, and suggest markers that are displayed on a map.


## Requirements
- Xenforo 2.3.7
- PHP 8.3

## Libraries used:
- [Leaflet js](https://leafletjs.com/) - Map library
- [Leaflet awesome markers](https://github.com/lennardv2/Leaflet.awesome-markers) A heavily modified version of the original plugin
- [OpenStreetMap](https://www.openstreetmap.org) - Default map tile provider
- [Nominatim](https://nominatim.org/) - Optional geocoding service

## Creating a custom map
### Required application
https://qgis.org/

### Command to be used:
"C:\Program Files\QGIS 3.40.14\bin\python.exe" -m osgeo_utils.gdal2tiles --tilesize=256 --profile=raster --zoom={minZoom}-{maxZoom} --xyz "C:\path\to\original\world.png" "C:\path\to\tiles\folder"
