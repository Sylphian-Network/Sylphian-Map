<?php

namespace Sylphian\Map;

enum MarkerStatus: string
{
    case ACTIVE = 'Active';
    case INACTIVE = 'Inactive';
    case DISABLED = 'Disabled';

    /**
     * Determine the status of a marker based on its properties
     *
     * @param bool $active Whether the marker is active
     * @param bool $createThread Whether the marker has create_thread enabled
     * @return self The appropriate status enum value
     */
    public static function fromMarker(bool $active, bool $createThread): self
    {
        if (!$active) {
            return self::DISABLED;
        } elseif (!$createThread) {
            return self::INACTIVE;
        } else {
            return self::ACTIVE;
        }
    }

    /**
     * Generates a regex pattern by combining the values of all cases in the enum.
     *
     * @return string A string containing the regex pattern formed by joining the case values.
     */
    public static function getRegexPattern(): string
    {
        return implode('|', array_column(self::cases(), 'value'));
    }
}