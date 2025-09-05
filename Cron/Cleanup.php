<?php

namespace Sylphian\Map\Cron;

use Sylphian\Map\Repository\MapMarkerRepository;
use Sylphian\Map\Repository\MapMarkerSuggestionRepository;

class Cleanup
{
	public static function cleanupMapData(): void
	{
		/** @var MapMarkerSuggestionRepository $suggestionRepo */
		$suggestionRepo = \XF::repository('Sylphian\Map:MapMarkerSuggestion');
		$suggestionRepo->cleanupOldSuggestions();

		/** @var MapMarkerRepository $markerRepo */
		$markerRepo = \XF::repository('Sylphian\Map:MapMarker');
		$markerRepo->cleanupPastEvents();
	}
}
