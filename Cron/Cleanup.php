<?php

namespace Sylphian\Map\Cron;

use Sylphian\Map\Repository\MapMarkerSuggestionRepository;

class Cleanup
{
    /**
     * Cleanup old map marker suggestions that have been approved or rejected
     */
    public static function cleanupOldSuggestions(): int
    {
        /** @var MapMarkerSuggestionRepository $suggestionRepo */
        $suggestionRepo = \XF::repository('Sylphian\Map:MapMarkerSuggestion');

        return $suggestionRepo->cleanupOldSuggestions();
    }
}