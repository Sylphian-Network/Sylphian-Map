<?php

namespace Sylphian\Map\Option;

use Sylphian\Library\Repository\LogRepository;
use XF\Entity\Option;
use XF\Option\AbstractOption;

class LatAndLng extends AbstractOption
{
	public static function verifyOption(&$value, Option $option): bool
	{
		if (!is_numeric($value))
		{
			/** @var LogRepository $logRepo */
			$logRepo = \XF::repository('Sylphian\Library:Log');
			$logRepo->logError(\XF::phrase('map_option_error'), ['value' => $option->getOptionValue()]);
			return false;
		}

		return true;
	}
}
