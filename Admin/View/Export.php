<?php

namespace Sylphian\Map\Admin\View;

use XF\Http\ResponseFile;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\View;
use XF\Util\File;

class Export extends View
{
	public function renderRaw(): ResponseFile
	{
		$markers = $this->params['markers'] ?? [];
		$suggestions = $this->params['suggestions'] ?? [];
		$format = $this->params['format'] ?? 'json';

		if ($markers instanceof ArrayCollection)
		{
			$markers = $markers->toArray();
		}
		if ($suggestions instanceof ArrayCollection)
		{
			$suggestions = $suggestions->toArray();
		}

		switch ($format)
		{
			case 'sql':
				$contentType = 'text/plain';
				$extension = 'sql';
				$data = $this->generateSqlExport($markers, $suggestions);
				break;
			default:
				$contentType = 'application/json';
				$extension = 'json';
				$data = $this->generateJsonExport($markers, $suggestions);
				break;
		}

		$tempFile = File::getTempFile();
		file_put_contents($tempFile, $data);

		$this->response->setDownloadFileName('sylphian_map_export_' . date('Y-m-d') . '.' . $extension)
			->contentType($contentType, 'utf-8');

		return $this->response->responseFile($tempFile);
	}

	protected function generateSqlExport(array $markers, array $suggestions): string
	{
		$sql = "-- Map Markers Export " . date('Y-m-d') . "\n\n";

		$sql .= "-- Markers\n";
		foreach ($markers AS $marker)
		{
			$data = $marker->toArray();
			$columns = implode(', ', array_keys($data));
			$values = implode(', ', array_map(function ($value)
			{
				return is_string($value) ? "'" . addslashes($value) . "'" : $value;
			}, $data));

			$sql .= "INSERT INTO xf_map_markers ({$columns}) VALUES ({$values});\n";
		}

		$sql .= "\n-- Marker Suggestions\n";
		foreach ($suggestions AS $suggestion)
		{
			$data = $suggestion->toArray();
			$columns = implode(', ', array_keys($data));
			$values = implode(', ', array_map(function ($value)
			{
				return is_string($value) ? "'" . addslashes($value) . "'" : $value;
			}, $data));

			$sql .= "INSERT INTO xf_map_marker_suggestions ({$columns}) VALUES ({$values});\n";
		}

		return $sql;
	}

	protected function generateJsonExport(array $markers, array $suggestions): string
	{
		$data = [
			'markers' => [],
			'suggestions' => [],
		];

		foreach ($markers AS $marker)
		{
			$data['markers'][] = $marker->toArray();
		}

		foreach ($suggestions AS $suggestion)
		{
			$data['suggestions'][] = $suggestion->toArray();
		}

		return json_encode($data, JSON_PRETTY_PRINT);
	}
}
