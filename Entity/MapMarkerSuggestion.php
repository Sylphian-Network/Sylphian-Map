<?php

namespace Sylphian\Map\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $suggestion_id
 * @property float $lat
 * @property float $lng
 * @property string $title
 * @property string|null $content
 * @property string|null $icon
 * @property string|null $icon_var
 * @property string|null $icon_color
 * @property string|null $marker_color
 * @property string|null $type
 * @property int|null $user_id
 * @property int $create_date
 * @property string $status
 * @property boolean $create_thread
 * @property bool $thread_lock
 * @property int|null $start_date
 * @property int|null $end_date
 *
 * RELATIONS
 * @property-read User|null $User
 */
class MapMarkerSuggestion extends Entity
{
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_map_marker_suggestions';
		$structure->shortName = 'Sylphian:MapMarkerSuggestion';
		$structure->primaryKey = 'suggestion_id';
		$structure->columns = [
			'suggestion_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'lat' => ['type' => self::FLOAT, 'required' => true],
			'lng' => ['type' => self::FLOAT, 'required' => true],
			'title' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'content' => ['type' => self::STR, 'nullable' => true],
			'icon' => ['type' => self::STR, 'maxLength' => 50, 'nullable' => true],
			'icon_var' => ['type' => self::STR, 'maxLength' => 20, 'default' => 'solid'],
			'icon_color' => ['type' => self::STR, 'maxLength' => 30, 'default' => 'black'],
			'marker_color' => ['type' => self::STR, 'maxLength' => 30, 'default' => 'blue'],
			'type' => ['type' => self::STR, 'maxLength' => 50, 'nullable' => true],
			'user_id' => ['type' => self::UINT, 'nullable' => true],
			'create_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'status' => ['type' => self::STR, 'default' => 'pending', 'allowedValues' => ['pending', 'approved', 'rejected']],
			'create_thread' => ['type' => self::BOOL, 'default' => false],
			'thread_lock' => ['type' => self::BOOL, 'default' => false],
			'start_date' => ['type' => self::UINT, 'nullable' => true],
			'end_date' => ['type' => self::UINT, 'nullable' => true],
		];

		$structure->getters = [];

		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}
