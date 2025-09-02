<?php

namespace Sylphian\Map\Repository;

use Exception;
use Sylphian\Library\Repository\LogRepository;
use Sylphian\Map\Entity\MapMarkerSuggestion;
use XF;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

/**
 * Repository for map marker suggestion operations
 *
 * This repository handles user-submitted marker suggestions including creation,
 * approval/rejection workflow, and retrieval operations. Suggestions allow users
 * to propose new map markers that administrators can review before adding them
 * to the public map.
 */
class MapMarkerSuggestionRepository extends Repository
{
	/**
	 * Gets pending map marker suggestions with pagination support
	 *
	 * @param array|string|null $with Related data to fetch with the entities
	 * @param int $page Current page number
	 * @param int $perPage Items per page
	 * @param int|null $total Variable to store the total count (passed by reference)
	 *
	 * @return XF\Mvc\Entity\AbstractCollection
	 */
	public function getPendingSuggestions(array|string|null $with = null, int $page = 1, int $perPage = 20, ?int &$total = null): XF\Mvc\Entity\AbstractCollection
	{
		$finder = $this->finder('Sylphian\Map:MapMarkerSuggestion')
			->where('status', 'pending')
			->order('create_date', 'DESC');

		if ($with)
		{
			$finder->with($with);
		}

		$total = $finder->total();

		return $finder->limitByPage($page, $perPage)->fetch();
	}

	/**
	 * Validates suggestion input data
	 *
	 * This method ensures all required fields are present and properly formatted
	 * for a marker suggestion. It can be called before creating a suggestion to
	 * ensure data validity.
	 *
	 * @param array $data The suggestion data to validate
	 *
	 * @return array The validated data, potentially with defaults applied
	 * @throws PrintableException If validation fails
	 */
	public function validateSuggestionData(array $data): array
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

		if (empty($data['status']))
		{
			$data['status'] = 'pending';
		}

		if (count($errors))
		{
			throw new PrintableException(implode("\n", $errors));
		}

		return $data;
	}

	/**
	 * Creates a new map marker suggestion
	 *
	 * @param array $data Suggestion data
	 *
	 * @return MapMarkerSuggestion
	 * @throws PrintableException
	 */
	public function createSuggestion(array $data): MapMarkerSuggestion
	{
		$data = $this->validateSuggestionData($data);

		/** @var MapMarkerSuggestion $suggestion */
		$suggestion = $this->em->create('Sylphian\Map:MapMarkerSuggestion');
		$suggestion->bulkSet($data);
		$suggestion->save();

        /** @var LogRepository $logRepo */
        $logRepo = $this->repository('Sylphian\Library:Log');
        $logRepo->logInfo(
            'Map marker suggestion created: ' . $suggestion->title,
            [
                'suggestion_id' => $suggestion->suggestion_id,
                'lat' => $suggestion->lat,
                'lng' => $suggestion->lng,
                'type' => $suggestion->type ?? 'default',
                'user_id' => $suggestion->user_id,
            ]
        );

		return $suggestion;
	}

	/**
	 * Gets a suggestion by ID, throwing an exception if it doesn't exist
	 *
	 * @param int $id The suggestion ID
	 * @param array|string|null $with Related data to fetch with the entity
	 *
	 * @return MapMarkerSuggestion
	 * @throws PrintableException If the suggestion doesn't exist
	 */
	public function getSuggestionOrFail(int $id, array|string|null $with = null): MapMarkerSuggestion
	{
		/** @var MapMarkerSuggestion $suggestion */
		$suggestion = $this->em->find('Sylphian\Map:MapMarkerSuggestion', $id, $with);

		if (!$suggestion)
		{
			throw new PrintableException(\XF::phrase('requested_map_marker_suggestion_not_found'));
		}

		return $suggestion;
	}

	/**
	 * Approves a suggestion and creates a permanent map marker from it
	 *
	 * This method performs the following operations:
	 * - Retrieves the suggestion by ID
	 * - Creates a new map marker using the suggestion data
	 * - Updates the suggestion status to 'approved'
	 * - Handles error conditions with appropriate logging
	 *
	 * @param int $id The suggestion ID to approve
	 *
	 * @return bool True if the suggestion was successfully approved and the marker is created, false otherwise
	 */
	public function approveSuggestion(int $id): bool
	{
		try
		{
			$suggestion = $this->getSuggestionOrFail($id);

			/** @var MapMarkerRepository $markerRepo */
			$markerRepo = $this->repository('Sylphian\Map:MapMarker');

			$markerData = [
				'lat' => $suggestion->lat,
				'lng' => $suggestion->lng,
				'title' => $suggestion->title,
				'content' => $suggestion->content,
				'icon' => $suggestion->icon,
				'icon_var' => $suggestion->icon_var,
				'icon_color' => $suggestion->icon_color,
				'marker_color' => $suggestion->marker_color,
				'type' => $suggestion->type,
				'user_id' => $suggestion->user_id,
				'active' => true,
				'create_thread' => $suggestion->create_thread,
				'thread_lock' => $suggestion->thread_lock,
			];

			$marker = $markerRepo->createMapMarker($markerData);

			if ($suggestion->create_thread)
			{
				/** @var ThreadMarkerRepository $threadMarkerRepo */
				$threadMarkerRepo = $this->repository('Sylphian\Map:ThreadMarkerRepository');
				$threadMarkerRepo->createThreadForMarker($marker);
			}

			$suggestion->status = 'approved';
			$suggestion->save();

            /** @var LogRepository $logRepo */
            $logRepo = $this->repository('Sylphian\Library:Log');
            $logRepo->logInfo(
                'Map marker suggestion approved: ' . $suggestion->title,
                [
                    'suggestion_id' => $suggestion->suggestion_id,
                    'marker_id' => $marker->marker_id,
                    'lat' => $suggestion->lat,
                    'lng' => $suggestion->lng,
                    'type' => $suggestion->type ?? 'default',
                    'user_id' => $suggestion->user_id,
                    'approved_by' => \XF::visitor()->user_id
                ]
            );

			return true;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error approving map marker suggestion: ');
			return false;
		}
	}

	/**
	 * Rejects a suggestion
	 *
	 * @param int $id The suggestion ID
	 *
	 * @return bool
	 */
	public function rejectSuggestion(int $id): bool
	{
		try
		{
			$suggestion = $this->getSuggestionOrFail($id);
			$suggestion->status = 'rejected';
			$suggestion->save();

            /** @var LogRepository $logRepo */
            $logRepo = $this->repository('Sylphian\Library:Log');
            $logRepo->logInfo(
                'Map marker suggestion rejected: ' . $suggestion->title,
                [
                    'suggestion_id' => $suggestion->suggestion_id,
                    'lat' => $suggestion->lat,
                    'lng' => $suggestion->lng,
                    'type' => $suggestion->type ?? 'default',
                    'user_id' => $suggestion->user_id,
                    'rejected_by' => \XF::visitor()->user_id
                ]
            );

			return true;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error rejecting map marker suggestion: ');
			return false;
		}
	}

	/**
	 * Cleans up old approved and rejected marker suggestions
	 *
	 * This method removes suggestions that:
	 * - Have a status of either 'approved' or 'rejected'
	 * - Are older than the specified number of days (default: 30)
	 *
	 * @param int $olderThanDays Number of days to consider a suggestion "old"
	 *
	 * @return int Number of suggestions deleted
	 */
	public function cleanupOldSuggestions(int $olderThanDays = 30): int
	{
		$cutoffTime = \XF::$time - ($olderThanDays * 86400);

		$suggestions = $this->finder('Sylphian\Map:MapMarkerSuggestion')
			->where('status', ['approved', 'rejected'])
			->where('create_date', '<', $cutoffTime)
			->fetch();

		$deleteCount = 0;
        $deletedSuggestions = [];

		foreach ($suggestions AS $suggestion)
		{
            $deletedSuggestions[] = [
                'suggestion_id' => $suggestion->suggestion_id,
                'title' => $suggestion->title,
                'status' => $suggestion->status,
                'create_date' => $suggestion->create_date
            ];

			$suggestion->delete();
			$deleteCount++;
		}

		if ($deleteCount > 0)
		{
            /** @var LogRepository $logRepo */
            $logRepo = $this->repository('Sylphian\Library:LogRepository');
            $logRepo->logInfo(
                "Map marker suggestion cleanup: deleted {$deleteCount} old approved/rejected suggestions.",
                [
                    'count' => $deleteCount,
                    'cutoff_days' => $olderThanDays,
                    'cutoff_time' => $cutoffTime,
                    'deleted_suggestions' => $deletedSuggestions
                ]
            );
		}

		return $deleteCount;
	}
}
