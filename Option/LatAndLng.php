<?php

namespace Sylphian\Map\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;

class LatAndLng extends AbstractOption
{
	public static function verifyOption(&$value, Option $option): bool
	{
		if (!is_numeric($value))
		{
			$option->error(\XF::phrase('map_option_error'), $option->option_id);
			return false;
		}

		return true;
	}
}
