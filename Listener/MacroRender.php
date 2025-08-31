<?php

namespace Sylphian\Map\Listener;

use XF\Template\Templater;

class MacroRender
{
	public static function preRender(Templater $templater, &$type, &$template, &$name, array &$arguments, array &$globalVars): void
	{
		if (!empty($arguments['group']) && $arguments['group']->group_id == 'sylphian_map')
		{
			$template = 'sylphian_map_option_macros';
		}
	}
}
