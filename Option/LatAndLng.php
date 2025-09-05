<?php

namespace Sylphian\Map\Option;

use Sylphian\Library\Logger\Logger;
use XF\Entity\Option;
use XF\Option\AbstractOption;

class LatAndLng extends AbstractOption
{
	public static function verifyOption(&$value, Option $option): bool
	{
		if (!is_numeric($value))
		{
			Logger::error('Map option error', ['value' => $option->getOptionValue()]);
			return false;
		}

		return true;
	}
}
