<?php

namespace Sylphian\Map;

enum MarkerStatus: string
{
    case ACTIVE = 'Active';
    case INACTIVE = 'Inactive';
    case DELETED = 'Deleted';

    /**
     * Determine the status of a marker based on its properties
     *
     * @param bool $active Whether the marker is active
     * @param bool $isDeleted Whether the marker is marked for deletion
     * @return self The appropriate status enum value
     */
    public static function fromMarker(bool $active, bool $isDeleted = false): self
    {
        if ($isDeleted) {
            return self::DELETED;
        }

        if (!$active) {
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