<?php

namespace Sylphian\Map\Repository;

use Exception;
use Sylphian\Map\Entity\MapMarker;
use Sylphian\Map\MarkerStatus;
use XF;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Service\Thread\CreatorService;

class ThreadMarkerRepository extends Repository
{
    /**
     * Gets a thread associated with a marker or returns null
     *
     * @param MapMarker $marker The marker to get the thread for
     * @return Thread|null The associated thread or null
     */
    protected function getThreadFromMarker(MapMarker $marker): ?Thread
    {
        if (!$marker->thread_id) {
            return null;
        }

        /** @var Thread $thread */
        $thread = XF::em()->find('XF:Thread', $marker->thread_id);
        return $thread;
    }

    /**
     * Extracts the base title from a thread title by removing the status prefix
     *
     * @param string $threadTitle The current thread title
     * @return string The base title without a status prefix
     */
    protected function extractBaseTitle(string $threadTitle): string
    {
        $pattern = MarkerStatus::getRegexPattern();

        if (preg_match('/^\[(' . $pattern . ')\] (.+)$/i', $threadTitle, $matches)) {
            return $matches[2];
        }

        return $threadTitle;
    }

    /**
     * Formats a thread title with the appropriate status prefix
     *
     * @param string $baseTitle The base title without status
     * @param MarkerStatus $status The status to prefix
     * @return string The formatted title
     */
    protected function formatThreadTitle(string $baseTitle, MarkerStatus $status): string
    {
        return "[{$status->value}] {$baseTitle}";
    }

    /**
     * Creates a thread for a map marker
     *
     * @param MapMarker $marker The marker to create a thread for
     * @param string|null $customTitle Optional custom title for the thread
     * @return bool Whether the thread was successfully created
     *
     * @throws PrintableException
     * @throws Exception
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

        $threadUser = null;
        if (XF::options()->use_specific_account_for_threads) {
            $threadUser = XF::em()->find('XF:User', XF::options()->specific_account_for_thread);
        } else if ($marker->user_id) {
            $threadUser = XF::em()->find('XF:User', $marker->user_id);
        }

        if (!($threadUser instanceof User)) {
            $threadUser = XF::em()->find('XF:User', 1);

            if (!($threadUser instanceof User)) {
                XF::logError('Thread creation failed: Could not find a valid user for thread creation');
                return false;
            }
        }

        return XF::asVisitor($threadUser, function() use ($marker, $customTitle, $forum) {
            $status = MarkerStatus::fromMarker($marker->active);

            $baseTitle = $customTitle ?: $marker->title;
            $formattedTitle = $this->formatThreadTitle($baseTitle, $status);

            /** @var CreatorService $creator */
            $creator = XF::service('XF:Thread\CreatorService', $forum);

            $creator->setContent(
                $formattedTitle,
                $this->getThreadMessageFromMarker($marker)
            );

            $errors = [];
            if ($creator->validate($errors)) {
                $thread = $creator->save();

                if ($marker->thread_lock) {
                    $thread->discussion_open = false;
                    $thread->save();
                }

                $marker->thread_id = $thread->thread_id;
                $marker->save();

                return true;
            } else {
                XF::logError('Thread creation validation failed: ' . implode(', ', $errors));
                return false;
            }
        });
    }

    /**
     * Updates a thread associated with a marker
     *
     * @param MapMarker $marker The marker with an associated thread
     * @param bool $updateContent Whether to update the content
     * @param bool $updateTitle Whether to update the title
     * @return bool Whether the update was successful
     * @throws PrintableException
     */
    public function updateThread(MapMarker $marker, bool $updateContent = true, bool $updateTitle = true): bool
    {
        $thread = $this->getThreadFromMarker($marker);
        if (!$thread) {
            return false;
        }

        $success = true;

        if ($updateContent) {
            $firstPost = $thread->FirstPost;
            if ($firstPost) {
                $firstPost->message = $this->getThreadMessageFromMarker($marker);
                $firstPost->save();
            } else {
                $success = false;
            }
        }

        $thread->discussion_open = !$marker->thread_lock;
        $thread->save();

        if ($updateTitle) {
            $status = MarkerStatus::fromMarker($marker->active);
            $thread->title = $this->formatThreadTitle($marker->title, $status);
            $thread->save();
        }

        return $success;
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
        $thread = $this->getThreadFromMarker($marker);
        if (!$thread) {
            return false;
        }

        $baseTitle = $this->extractBaseTitle($thread->title);
        $thread->title = "[" . MarkerStatus::DELETED->value . "] {$baseTitle}";
        $thread->save();

        return true;
    }

    /**
     * Handles all marker thread updates in one call
     *
     * @param MapMarker $marker The updated marker
     * @return bool Whether updates were successful
     *
     * @throws PrintableException
     */
    public function handleMarkerThreadUpdates(MapMarker $marker): bool
    {
        if ($marker->create_thread && !$marker->thread_id) {
            return $this->createThreadForMarker($marker);
        }
        else if ($marker->thread_id) {
            return $this->updateThread($marker);
        }

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
        $status = MarkerStatus::fromMarker($marker->active);
        $mapUrl = XF::app()->router()->buildLink('full:map');

        return "[B]Title:[/B] {$marker->title}\n" .
            "[B]Description:[/B] {$marker->content}\n" .
            "[B]Location:[/B] {$marker->lat}, {$marker->lng}\n" .
            "[B]Status:[/B] {$status->value}\n" .
            "[B]Type:[/B] {$marker->type}\n\n" .
            "[URL={$mapUrl}]View on Map[/URL]\n\n" .
            "[CENTER][B]This thread is associated with a map marker[/B][/CENTER]\n" .
            "[CENTER][SIZE=1]Last updated: " . XF::language()->dateTime(XF::$time) . "[/SIZE][/CENTER]";
    }
}