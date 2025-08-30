<?php

namespace Sylphian\Map\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;
use XF\Repository\NodeRepository;

class ThreadCreationLocation extends AbstractOption
{
	public static function renderOption(Option $option, array $htmlParams): string
	{
		$data = static::getSelectData($option, $htmlParams);

		return static::getTemplater()->formSelectRow(
			$data['controlOptions'],
			$data['choices'],
			$data['rowOptions']
		);
	}

	protected static function getSelectData(Option $option, array $htmlParams): array
	{
		/** @var NodeRepository $nodeRepo */
		$nodeRepo = \XF::repository('XF:Node');

		$choices = $nodeRepo->getNodeOptionsData(true, 'Forum', 'option');

		return [
			'choices' => $choices,
			'controlOptions' => static::getControlOptions($option, $htmlParams),
			'rowOptions' => static::getRowOptions($option, $htmlParams),
		];
	}

	public static function verifyOption(&$value, Option $option): bool
	{
		if ($value && !is_numeric($value))
		{
			$option->error(\XF::phrase('please_select_valid_forum'), $option->option_id);
			return false;
		}

		if ($value && !\XF::em()->find('XF:Forum', $value))
		{
			$option->error(\XF::phrase('please_select_valid_forum'), $option->option_id);
			return false;
		}

		return true;
	}
}
