<?php

namespace Sylphian\Map\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $marker_id
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
 * @property bool $create_thread
 * @property int|null $thread_id
 * @property bool $thread_lock
 * @property int $create_date
 * @property int $update_date
 * @property bool $active
 * @property int|null $start_date
 * @property int|null $end_date
 *
 * RELATIONS
 * @property-read User|null $User
 */
class MapMarker extends Entity
{
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_map_markers';
		$structure->shortName = 'Sylphian:MapMarker';
		$structure->primaryKey = 'marker_id';
		$structure->columns = [
			'marker_id' => ['type' => self::UINT, 'autoIncrement' => true],
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
			'create_thread' => ['type' => self::BOOL, 'default' => false],
			'thread_id' => ['type' => self::UINT, 'nullable' => true],
			'thread_lock' => ['type' => self::BOOL, 'default' => false],
			'create_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'update_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'active' => ['type' => self::BOOL, 'default' => true],
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
			'Thread' => [
				'entity' => 'XF:Thread',
				'type' => self::TO_ONE,
				'conditions' => 'thread_id',
				'primary' => true,
			],
		];

		return $structure;
	}

	protected function _preSave(): void
	{
		if ($this->isUpdate())
		{
			$this->update_date = \XF::$time;
		}
	}
}
