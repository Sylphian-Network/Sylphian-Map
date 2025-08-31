<?php

namespace Sylphian\Map\Repository;

use Sylphian\Map\Entity\MapMarker;
use Sylphian\Map\Entity\MapMarkerSuggestion;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class ImportMarkerRepository extends Repository
{
	/**
	 * Process JSON import data
	 *
	 * @param string $content The JSON content to process
	 * @return array Processed import data
	 * @throws \InvalidArgumentException
	 */
	public function processJsonImport(string $content): array
	{
		$data = json_decode($content, true);

		if (json_last_error() !== JSON_ERROR_NONE)
		{
			throw new \InvalidArgumentException(\XF::phrase('invalid_json_file'));
		}

		if (!isset($data['markers']) || !isset($data['suggestions']))
		{
			throw new \InvalidArgumentException(\XF::phrase('invalid_map_import_file_format'));
		}

		return $data;
	}

	/**
	 * Process SQL import data
	 *
	 * @param string $content The SQL content to process
	 * @return array Processed import data
	 */
	public function processSqlImport(string $content): array
	{
		$data = [
			'markers' => [],
			'suggestions' => [],
		];

		$lines = explode("\n", $content);
		$currentTable = null;

		foreach ($lines AS $line)
		{
			$line = trim($line);

			if (empty($line) || str_starts_with($line, '--'))
			{
				if (str_starts_with($line, '-- Markers'))
				{
					$currentTable = 'markers';
				}
				else if (str_starts_with($line, '-- Marker Suggestions'))
				{
					$currentTable = 'suggestions';
				}

				continue;
			}

			if (str_starts_with($line, 'INSERT INTO'))
			{
				if ($currentTable === 'markers' && str_contains($line, 'xf_map_markers'))
				{
					$markerData = $this->extractDataFromSqlInsert($line);
					if ($markerData)
					{
						$data['markers'][] = $markerData;
					}
				}
				else if ($currentTable === 'suggestions' && str_contains($line, 'xf_map_marker_suggestions'))
				{
					$suggestionData = $this->extractDataFromSqlInsert($line);
					if ($suggestionData)
					{
						$data['suggestions'][] = $suggestionData;
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Extract data from SQL INSERT statement
	 *
	 * @param string $insertStatement The SQL INSERT statement
	 * @return array|null Extracted data or null if parsing failed
	 */
	protected function extractDataFromSqlInsert(string $insertStatement): ?array
	{
		if (preg_match('/INSERT INTO [\w_]+ \((.*?)\) VALUES \((.*?)\);/', $insertStatement, $matches))
		{
			$columns = array_map('trim', explode(',', $matches[1]));
			$valuesStr = $matches[2];

			$values = [];
			$valueBuffer = '';
			$inQuote = false;
			$quoteChar = '';

			for ($i = 0; $i < strlen($valuesStr); $i++)
			{
				$char = $valuesStr[$i];

				if (($char === "'" || $char === '"') && ($i === 0 || $valuesStr[$i - 1] !== '\\'))
				{
					if (!$inQuote)
					{
						$inQuote = true;
						$quoteChar = $char;
					}
					else if ($char === $quoteChar)
					{
						$inQuote = false;
					}
					else
					{
						$valueBuffer .= $char;
					}
				}
				else if ($char === ',' && !$inQuote)
				{
					$values[] = $valueBuffer;
					$valueBuffer = '';
				}
				else
				{
					$valueBuffer .= $char;
				}
			}

			$values[] = $valueBuffer;

			foreach ($values AS &$value)
			{
				$value = trim($value);

				if ((str_starts_with($value, "'") && str_ends_with($value, "'")) ||
					(str_starts_with($value, '"') && str_ends_with($value, '"')))
				{
					$value = substr($value, 1, -1);
					$value = stripcslashes($value);
				}
				else if ($value === 'NULL')
				{
					$value = null;
				}
				else if (is_numeric($value))
				{
					$value = str_contains($value, '.') ? (float) $value : (int) $value;
				}
				else if ($value === 'true' || $value === 'false')
				{
					$value = ($value === 'true');
				}
			}

			return array_combine($columns, $values);
		}

		return null;
	}

	/**
	 * Find an existing marker that matches the import data
	 *
	 * @param array $markerData The marker data to check
	 * @return MapMarker|null The existing marker or null if not found
	 */
	protected function findExistingMarker(array $markerData): MapMarker|null
	{
		$finder = $this->finder('Sylphian\Map:MapMarker')
			->where('lat', $markerData['lat'])
			->where('lng', $markerData['lng']);

		if (!empty($markerData['title']))
		{
			$finder->where('title', $markerData['title']);
		}

		/** @var MapMarker|null $result */
		$result = $finder->fetchOne();

		return $result;
	}

	/**
	 * Find an existing suggestion that matches the import data
	 *
	 * @param array $suggestionData The suggestion data to check
	 * @return MapMarkerSuggestion|null The existing suggestion or null if not found
	 */
	protected function findExistingSuggestion(array $suggestionData): MapMarkerSuggestion|null
	{
		$finder = $this->finder('Sylphian\Map:MapMarkerSuggestion')
			->where('lat', $suggestionData['lat'])
			->where('lng', $suggestionData['lng']);

		if (!empty($suggestionData['title']))
		{
			$finder->where('title', $suggestionData['title']);
		}

		/** @var MapMarkerSuggestion|null $result */
		$result = $finder->fetchOne();

		return $result;
	}

	/**
	 * Import data into the database
	 *
	 * @param array $data The data to import
	 * @return array Import statistics
	 * @throws PrintableException
	 */
	public function importData(array $data): array
	{
		$db = \XF::db();
		$db->beginTransaction();

		try
		{
			$markerStats = [
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
			];

			$suggestionStats = [
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
			];

			/** @var MapMarkerRepository $markerRepo */
			$markerRepo = \XF::repository('Sylphian\Map:MapMarker');
			/** @var MapMarkerSuggestionRepository $suggestionRepo */
			$suggestionRepo = \XF::repository('Sylphian\Map:MapMarkerSuggestion');

			foreach ($data['markers'] AS $markerData)
			{
				unset($markerData['marker_id']);

				$validData = $markerRepo->validateMarkerData($markerData);

				$existingMarker = $this->findExistingMarker($validData);

				if ($existingMarker)
				{
					$markerRepo->updateMapMarker($existingMarker, $validData);
					$markerStats['updated']++;
				}
				else
				{
					$markerRepo->createMapMarker($validData);
					$markerStats['created']++;
				}
			}

			foreach ($data['suggestions'] AS $suggestionData)
			{
				unset($suggestionData['suggestion_id']);

				$validData = $suggestionRepo->validateSuggestionData($suggestionData);

				$existingSuggestion = $this->findExistingSuggestion($validData);

				if ($existingSuggestion)
				{
					if ($existingSuggestion->status !== 'pending')
					{
						$suggestionStats['skipped']++;
						continue;
					}

					$existingSuggestion->bulkSet($validData);
					$existingSuggestion->save();
					$suggestionStats['updated']++;
				}
				else
				{
					$suggestionRepo->createSuggestion($validData);
					$suggestionStats['created']++;
				}
			}

			$db->commit();

			return [
				'markerCount' => array_sum($markerStats),
				'markerStats' => $markerStats,
				'suggestionCount' => array_sum($suggestionStats),
				'suggestionStats' => $suggestionStats,
				'count' => array_sum($markerStats) + array_sum($suggestionStats),
			];
		}
		catch (\Exception $e)
		{
			$db->rollback();
			throw $e;
		}
	}
}
