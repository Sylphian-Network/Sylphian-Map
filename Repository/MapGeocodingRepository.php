<?php

namespace Sylphian\Map\Repository;

use GuzzleHttp\Exception\GuzzleException;
use Sylphian\Library\Logger\Logger;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class MapGeocodingRepository extends Repository
{
	/**
	 * Geocodes an address to latitude and longitude using Nominatim API
	 *
	 * @param string $address The address to geocode
	 * @return array|null Returns [lat, lng] if successful, null if not found
	 * @throws PrintableException If the request fails for technical reasons
	 */
	public function geocodeAddress(string $address): ?array
	{
		if (empty(trim($address)))
		{
			return null;
		}

		$url = 'https://nominatim.openstreetmap.org/search';
		$params = [
			'q' => $address,
			'format' => 'json',
			'limit' => 1,
			'addressdetails' => 0,
		];

		try
		{
			$client = \XF::app()->http()->client();

			$boardTitle = \XF::options()->boardTitle;
			$boardUrl = \XF::options()->boardUrl;
			$addonVersion = \XF::app()->container('addon.cache')['Sylphian/Map'] ?: 'unknown';

			$response = $client->get($url, [
				'query' => $params,
				'headers' => [
					'User-Agent' => "{$boardTitle} Geocoder/{$addonVersion} ({$boardUrl}/contact)",
				],
			]);

			$responseBody = $response->getBody()->getContents();
			$data = json_decode($responseBody, true);

			if (empty($data) || !isset($data[0]['lat']) || !isset($data[0]['lon']))
			{
				return null;
			}

			return [
				'lat' => (float) $data[0]['lat'],
				'lng' => (float) $data[0]['lon'],
			];
		}
		catch (GuzzleException $e)
		{
			Logger::error('Geocoding request failed (GuzzleException): ' . $e->getMessage());
			throw new PrintableException(\XF::phrase('sylphian_map_geocoding_failed') . ': ' . $e->getMessage());
		}
	}
}
