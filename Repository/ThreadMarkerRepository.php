<?php

namespace Sylphian\Map\Repository;

use Sylphian\Map\Entity\MapMarker;
use Sylphian\Map\MarkerStatus;
use XF;
use XF\Entity\Thread;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Service\Thread\CreatorService;

class ThreadMarkerRepository extends Repository
{
    /**
     * Creates a thread for a map marker
     *
     * @param MapMarker $marker The marker to create a thread for
     * @param string|null $customTitle Optional custom title for the thread
     * @return bool Whether the thread was successfully created
     * @throws PrintableException
     */
    public function createThreadForMarker(MapMarker $marker, ?string $customTitle = null): bool
    {
        if (!XF::options()->enableThreadCreation) {
            return false;
        }

        $threadCreationLocation = XF::options()->threadCreationLocation;
        if (!$threadCreationLocation) {
            return false;
        }

        $forum = XF::em()->find('XF:Forum', $threadCreationLocation);
        if (!$forum) {
            XF::logError('Thread creation failed: Forum not found with ID ' . $threadCreationLocation);
            return false;
        }

        /** @var CreatorService $creator */
        $creator = XF::service('XF:Thread\CreatorService', $forum);

        //TODO: To be changed at some point.
        // Placeholder until I figure out how to create prefixes on addon instillation.
        $status = MarkerStatus::fromMarker($marker->active, $marker->create_thread);

        $baseTitle = $customTitle ?: $marker->title;
        $formattedTitle = "[{$status->value}] {$baseTitle}";

        $creator->setContent(
            $formattedTitle,
            $this->getThreadMessageFromMarker($marker)
        );

        $errors = [];
        if ($creator->validate($errors)) {
            $thread = $creator->save();

            $marker->thread_id = $thread->thread_id;
            $marker->save();

            return true;
        } else {
            XF::logError('Thread creation validation failed: ' . implode(', ', $errors));
            return false;
        }
    }

    /**
     * Updates the title of a thread associated with a marker
     *
     * @param MapMarker $marker The marker with an associated thread
     * @return bool Whether the update was successful
     * @throws PrintableException
     */
    public function updateThreadTitle(MapMarker $marker): bool
    {
        if (!$marker->thread_id) {
            return false;
        }

        /** @var Thread $thread */
        $thread = XF::em()->find('XF:Thread', $marker->thread_id);
        if (!$thread) {
            return false;
        }

        $status = MarkerStatus::fromMarker($marker->active, $marker->create_thread);

        $pattern = MarkerStatus::getRegexPattern();
        $currentTitle = $thread->title;

        if (preg_match('/^\[(' . $pattern . ')\] (.+)$/i', $currentTitle, $matches)) {
            $baseTitle = $matches[2];
        } else {
            $baseTitle = $currentTitle;
        }

        $thread->title = "[{$status->value}] {$baseTitle}";
        $thread->save();

        return true;
    }

    /**
     * Marks a thread as deleted without physically removing it
     *
     * @param MapMarker $marker The marker with an associated thread
     * @return bool Whether the update was successful
     * @throws PrintableException
     */
    public function markThreadAsDeleted(MapMarker $marker): bool
    {
        if (!$marker->thread_id) {
            return false;
        }

        /** @var Thread $thread */
        $thread = XF::em()->find('XF:Thread', $marker->thread_id);
        if (!$thread) {
            return false;
        }

        $pattern = MarkerStatus::getRegexPattern();
        $currentTitle = $thread->title;

        if (preg_match('/^\[(' . $pattern . ')\] (.+)$/i', $currentTitle, $matches)) {
            $baseTitle = $matches[2];
        } else {
            $baseTitle = $currentTitle;
        }

        $thread->title = "[" . MarkerStatus::DELETED->value . "] {$baseTitle}";
        $thread->save();

        return true;
    }

    /**
     * Generates thread message content from a map marker
     *
     * @param MapMarker $marker
     * @return string
     */
    protected function getThreadMessageFromMarker(MapMarker $marker): string
    {
        return "This thread is associated with a map marker:\n\n" .
            "Title: {$marker->title}\n" .
            "Description: {$marker->content}\n" .
            "Location: {$marker->lat}, {$marker->lng}";
    }
}