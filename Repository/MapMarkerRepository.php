<?php

namespace Sylphian\Map\Repository;

use Sylphian\Library\Repository\LogRepository;
use Sylphian\Map\Entity\MapMarker;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

/**
 * Repository for map marker operations
 *
 * This repository provides methods for retrieving, creating, updating, and deleting map markers.
 * It also includes utility methods for processing marker data for display on the map interface.
 */
class MapMarkerRepository extends Repository
{
	/**
	 * Gets all active map markers
	 *
	 * @param string|null $type Optional filter by marker type
	 * @param array|string|null $with Related data to fetch with the entities
	 *
	 * @return AbstractCollection
	 */
	public function getActiveMapMarkers(?string $type = null, array|string|null $with = null): AbstractCollection
	{
		$finder = $this->finder('Sylphian\Map:MapMarker')
			->where('active', true)
			->order('create_date', 'DESC');

		if ($type)
		{
			$finder->where('type', $type);
		}

		if ($with)
		{
			$finder->with($with);
		}

		return $finder->fetch();
	}

	/**
	 * Validates marker input data before creation or update
	 *
	 * This method ensures all required fields are present and properly formatted.
	 * It can be called before creating or updating a marker to ensure data validity.
	 *
	 * @param array $data The marker data to validate
	 *
	 * @return array The validated data, potentially with defaults applied
	 * @throws PrintableException If validation fails
	 */
	public function validateMarkerData(array $data): array
	{
		$errors = [];

		if (empty($data['title']))
		{
			$errors[] = \XF::phrase('please_enter_valid_title');
		}
		else if (strlen($data['title']) > 100)
		{
			$errors[] = \XF::phrase('title_must_be_less_than_x_characters', ['count' => 100]);
		}

		if (!isset($data['lat']) || !is_numeric($data['lat']) || $data['lat'] < -90 || $data['lat'] > 90)
		{
			$errors[] = \XF::phrase('please_enter_valid_latitude');
		}

		if (!isset($data['lng']) || !is_numeric($data['lng']) || $data['lng'] < -180 || $data['lng'] > 180)
		{
			$errors[] = \XF::phrase('please_enter_valid_longitude');
		}

		if (empty($data['icon_var']))
		{
			$data['icon_var'] = 'solid';
		}

		if (empty($data['icon_color']))
		{
			$data['icon_color'] = 'black';
		}

		if (empty($data['marker_color']))
		{
			$data['marker_color'] = 'blue';
		}

		if (count($errors))
		{
			throw new PrintableException(implode("\n", $errors));
		}

		return $data;
	}

	/**
	 * Creates a new map marker
	 *
	 * @param array $data Marker data
	 *
	 * @return MapMarker
	 * @throws PrintableException
	 */
	public function createMapMarker(array $data): MapMarker
	{
		$data = $this->validateMarkerData($data);

		/** @var MapMarker $marker */
		$marker = $this->em->create('Sylphian\Map:MapMarker');
		$marker->bulkSet($data);
		$marker->save();

		/** @var LogRepository $logRepo */
		$logRepo = $this->repository('Sylphian\Library:Log');
		$logRepo->logInfo(
			'Map marker created: ' . $marker->title,
			[
				'marker_id' => $marker->marker_id,
				'lat' => $marker->lat,
				'lng' => $marker->lng,
				'type' => $marker->type ?? 'default',
				'user_id' => $marker->user_id,
				'thread_id' => $marker->thread_id,
			]
		);

		return $marker;
	}

	/**
	 * Gets a map marker by ID, throwing an exception if it doesn't exist
	 *
	 * @param int $id The marker ID
	 * @param array|string|null $with Related data to fetch with the entity
	 *
	 * @return MapMarker
	 * @throws PrintableException If the marker doesn't exist
	 */
	public function getMapMarkerOrFail(int $id, array|string|null $with = null): MapMarker
	{
		/** @var MapMarker $marker */
		$marker = $this->em->find('Sylphian\Map:MapMarker', $id, $with);

		if (!$marker)
		{
			throw new PrintableException(\XF::phrase('requested_map_marker_not_found'));
		}

		return $marker;
	}

	/**
	 * Updates a map marker
	 *
	 * This method validates the input data and updates an existing marker.
	 * It handles both marker entity objects and marker IDs.
	 *
	 * @param int|MapMarker $markerOrId The marker entity or ID to update
	 * @param array $data New marker data to apply
	 *
	 * @return MapMarker|null Returns the updated marker or null if validation or update fails
	 */
	public function updateMapMarker(int|MapMarker $markerOrId, array $data): ?MapMarker
	{
		try
		{
			if ($markerOrId instanceof MapMarker)
			{
				$marker = $markerOrId;
			}
			else
			{
				$marker = $this->getMapMarkerOrFail((int) $markerOrId);
			}

			$oldValues = [
				'title' => $marker->title,
				'lat' => $marker->lat,
				'lng' => $marker->lng,
				'content' => $marker->content,
				'icon' => $marker->icon,
				'type' => $marker->type,
			];

			$data = $this->validateMarkerData($data);

			$marker->bulkSet($data);
			$marker->save();

			/** @var LogRepository $logRepo */
			$logRepo = $this->repository('Sylphian\Library:Log');
			$logRepo->logInfo(
				'Map marker updated: ' . $marker->title,
				[
					'marker_id' => $marker->marker_id,
					'old_values' => $oldValues,
					'new_values' => [
						'title' => $marker->title,
						'lat' => $marker->lat,
						'lng' => $marker->lng,
						'content' => $marker->content,
						'icon' => $marker->icon,
						'type' => $marker->type,
					],
					'user_id' => \XF::visitor()->user_id,
				]
			);

			return $marker;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error updating map marker: ');
			return null;
		}
	}

	/**
	 * Gets all map markers regardless of active status with pagination support
	 *
	 * @param array|string|null $with Related data to fetch with the entities
	 * @param int $page Current page number
	 * @param int $perPage Items per page
	 * @param int|null $total Variable to store the total count (passed by reference)
	 *
	 * @return AbstractCollection
	 */
	public function getAllMapMarkers(array|string|null $with = null, int $page = 1, int $perPage = 20, ?int &$total = null): AbstractCollection
	{
		$finder = $this->finder('Sylphian\Map:MapMarker')
			->order('create_date', 'DESC');

		if ($with)
		{
			$finder->with($with);
		}

		$total = $finder->total();

		return $finder->limitByPage($page, $perPage)->fetch();
	}

	/**
	 * Gets all map markers regardless of active status
	 *
	 * @param array|string|null $with Related data to fetch with the entities
	 *
	 * @return AbstractCollection
	 */
	public function getAllMapMarkersWithoutLimit(array|string|null $with = null): AbstractCollection
	{
		$finder = $this->finder('Sylphian\Map:MapMarker')
			->order('create_date', 'DESC');

		if ($with)
		{
			$finder->with($with);
		}

		return $finder->fetch();
	}

	/**
	 * Gets a map marker by ID or throws an exception if it doesn't exist
	 *
	 * This is a convenience wrapper around getMapMarkerOrFail() that provides consistent
	 * error handling for marker lookup operations.
	 *
	 * @param int $id The marker ID to find
	 * @param array|string|null $with Related data to fetch with the entity
	 *
	 * @return MapMarker The found marker entity
	 * @throws PrintableException If the marker doesn't exist
	 */
	public function getMarkerOrFail(int $id, array|string|null $with = null): MapMarker
	{
		/** @var MapMarkerRepository $markerRepo */
		$markerRepo = $this->repository('Sylphian\Map:MapMarker');
		return $markerRepo->getMapMarkerOrFail($id, $with);
	}

	/**
	 * Process markers' collection and prepare data for display on the map
	 *
	 * This method transforms a collection of map marker entities into a structured array
	 * suitable for display in the map interface. It performs the following operations:
	 * - Extracts relevant marker data for JavaScript consumption
	 * - Compiles a unique list of marker types with their associated icons and colors
	 * - Filters out inactive markers for regular users
	 * - Creates a default marker if no markers exist
	 * - Includes the full marker collection for administrators
	 *
	 * @param AbstractCollection $markersCollection The collection of marker entities to process
	 * @param bool $canManageMarkers Whether the current user has permission to manage markers
	 *
	 * @return array An associative array containing:
	 *               - markers: Array of processed marker data for display
	 *               - markerTypes: Array of unique marker types with their visual properties
	 *               - allMarkers: Complete array of all markers (for administrators only)
	 */
	public function processMarkersForDisplay(AbstractCollection $markersCollection, bool $canManageMarkers): array
	{
		$markers = [];
		$allMarkers = [];
		$markerTypes = [];

		if ($canManageMarkers)
		{
			$allMarkers = $markersCollection->toArray();
		}

		foreach ($markersCollection AS $marker)
		{
			if ($marker->active === false)
			{
				continue;
			}

			$markerData = [
				'lat' => $marker->lat,
				'lng' => $marker->lng,
				'title' => $marker->title,
				'content' => $marker->content,
				'icon' => $marker->icon,
				'iconVar' => $marker->icon_var,
				'iconColor' => $marker->icon_color,
				'markerColor' => $marker->marker_color,
				'type' => $marker->type,
				'thread_id' => $marker->thread_id,
				'thread_url' => \XF::app()->router()->buildLink('threads', ['thread_id' => $marker->thread_id]),
				'start_date' => $marker->start_date,
				'end_date' => $marker->end_date,
			];

			$markers[] = $markerData;

			if (!isset($markerTypes[$marker->type]))
			{
				$markerTypes[$marker->type] = [
					'name' => $marker->type,
					'icon' => $marker->icon,
					'iconVar' => $marker->icon_var,
					'iconColor' => $marker->icon_color,
				];
			}
		}

		if (empty($markers))
		{
			$defaultMarker = [
				'lat' => \XF::options()->map_starting_lat ?: 51.505,
				'lng' => \XF::options()->map_starting_lng ?: -0.09,
				'title' => 'Default Marker',
				'content' => 'No markers currently exist.',
				'icon' => 'frown',
				'iconVar' => 'solid',
				'iconColor' => 'red',
				'markerColor' => 'blue',
				'type' => 'default',
			];

			$markers[] = $defaultMarker;
			$markerTypes['default'] = [
				'name' => 'default',
				'icon' => 'frown',
				'iconVar' => 'solid',
				'iconColor' => 'red',
			];
		}

		return [
			'markers' => $markers,
			'markerTypes' => array_values($markerTypes),
			'allMarkers' => $allMarkers,
		];
	}
}
