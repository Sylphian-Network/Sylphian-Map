<?php

namespace Sylphian\Map\Option;

use Sylphian\Library\Logger\AddonLogger;
use XF\Entity\Option;
use XF\Option\AbstractOption;

class LatAndLng extends AbstractOption
{
	public static function verifyOption(&$value, Option $option): bool
	{
        $logger = new AddonLogger(\XF::em());

		if (!is_numeric($value))
		{
            $logger->error('Map option error', ['value' => $option->getOptionValue()]);
			return false;
		}

		return true;
	}
}
