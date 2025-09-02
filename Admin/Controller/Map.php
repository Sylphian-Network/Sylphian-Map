<?php

namespace Sylphian\Map\Admin\Controller;

use Sylphian\Library\Entity\AddonLog;
use Sylphian\Library\Repository\LogRepository;
use Sylphian\Map\Repository\ImportMarkerRepository;
use Sylphian\Map\Repository\MapMarkerRepository;
use Sylphian\Map\Repository\MapMarkerSuggestionRepository;
use XF;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

class Map extends AbstractController
{
	public function actionExport(): View|Error
	{
		if ($this->request->isGet())
		{
			return $this->view('Sylphian\Map:Export', 'sylphian_map_export');
		}

		$format = $this->filter('format', 'str', 'json');

		/** @var MapMarkerRepository $markerRepo */
		$markerRepo = $this->repository('Sylphian\Map:MapMarker');
		/** @var MapMarkerSuggestionRepository $suggestionRepo */
		$suggestionRepo = $this->repository('Sylphian\Map:MapMarkerSuggestion');

		$markers = $markerRepo->getAllMapMarkersWithoutLimit();
		$suggestions = $suggestionRepo->getPendingSuggestions(null, 1, 1000);

		$viewParams = [
			'format' => $format,
			'markers' => $markers,
			'suggestions' => $suggestions,
		];

		$this->setResponseType('raw');

		return $this->view('Sylphian\Map:Export', 'sylphian_map_export', $viewParams);
	}

	public function actionImport(): View|AddonLog|Redirect|XF\Mvc\Reply\Message
	{
		if (!$this->request->isPost())
		{
			return $this->view('Sylphian\Map:Import', 'sylphian_map_import');
		}

		/** @var LogRepository $logRepo */
		$logRepo = \XF::repository('Sylphian\Library:Log');

		$upload = $this->request->getFile('import_file');
		if (!$upload)
		{
			return $logRepo->logError(\XF::phrase('please_upload_valid_file'));
		}

		$fileName = $upload->getFileName();
		$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

		if (!in_array($extension, ['json', 'sql']))
		{
			return $logRepo->logError(\XF::phrase('please_upload_valid_file_with_extensions', [
				'extensions' => 'json, sql',
			]));
		}

		$fileWrapper = $upload->getFileWrapper();
		$tempFile = $fileWrapper->getFilePath();

		if (!$tempFile || !file_exists($tempFile))
		{
			return $logRepo->logError(\XF::phrase('uploaded_file_failed_not_found'));
		}

		try
		{
			$fileContent = file_get_contents($tempFile);
			$importData = [];

			/** @var ImportMarkerRepository $importRepo */
			$importRepo = $this->repository('Sylphian\Map:ImportMarker');

			if ($extension === 'json')
			{
				$importData = $importRepo->processJsonImport($fileContent);
			}
			else if ($extension === 'sql')
			{
				$importData = $importRepo->processSqlImport($fileContent);
			}

			$result = $importRepo->importData($importData);

			return $this->message(\XF::phrase('import_completed_successfully_detailed', [
				'total' => $result['count'],
				'markers_created' => $result['markerStats']['created'],
				'markers_updated' => $result['markerStats']['updated'],
				'markers_skipped' => $result['markerStats']['skipped'],
				'suggestions_created' => $result['suggestionStats']['created'],
				'suggestions_updated' => $result['suggestionStats']['updated'],
				'suggestions_skipped' => $result['suggestionStats']['skipped'],
			]));
		}
		catch (\Exception $e)
		{
			return $logRepo->logError('Import failed error:', (array) $e);
		}
	}
}
