<?php

namespace Sylphian\Map;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1(): bool
	{
		try
		{
			$this->schemaManager()->createTable('xf_map_markers', function (Create $table)
			{
				$table->addColumn('marker_id', 'int')->autoIncrement();
				$table->addColumn('lat', 'decimal', '10,6')->unsigned(false);
				$table->addColumn('lng', 'decimal', '10,6')->unsigned(false);
				$table->addColumn('title', 'varchar', 100);
				$table->addColumn('content', 'text')->nullable();
				$table->addColumn('icon', 'varchar', 50)->nullable();
				$table->addColumn('icon_var', 'varchar', 20)->nullable()->setDefault('solid');
				$table->addColumn('icon_color', 'varchar', 30)->nullable()->setDefault('black');
				$table->addColumn('marker_color', 'varchar', 30)->nullable()->setDefault('blue');
				$table->addColumn('type', 'varchar', 50)->nullable();
				$table->addColumn('user_id', 'int')->nullable()->setDefault(null);
				$table->addColumn('create_thread', 'tinyint', 1)->setDefault(0);
				$table->addColumn('thread_id', 'int')->nullable()->setDefault(null);
				$table->addColumn('thread_lock', 'tinyint', 1)->setDefault(0);
				$table->addColumn('create_date', 'int')->setDefault(\XF::$time);
				$table->addColumn('update_date', 'int')->setDefault(\XF::$time);
				$table->addColumn('active', 'tinyint', 1)->setDefault(1);
				$table->addColumn('start_date', 'int')->nullable()->setDefault(null);
				$table->addColumn('end_date', 'int')->nullable()->setDefault(null);

				$table->addPrimaryKey('marker_id');
				$table->addKey(['lat', 'lng']);
				$table->addKey('type');
				$table->addKey('user_id');
				$table->addKey('thread_id');
				$table->addKey('active');
			});

			return true;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error creating map markers table: ');
			return false;
		}
	}

	public function installStep2(): bool
	{
		try
		{
			$this->schemaManager()->createTable('xf_map_marker_suggestions', function (Create $table)
			{
				$table->addColumn('suggestion_id', 'int')->autoIncrement();
				$table->addColumn('lat', 'decimal', '10,6')->unsigned(false);
				$table->addColumn('lng', 'decimal', '10,6')->unsigned(false);
				$table->addColumn('title', 'varchar', 100);
				$table->addColumn('content', 'text')->nullable();
				$table->addColumn('icon', 'varchar', 50)->nullable();
				$table->addColumn('icon_var', 'varchar', 20)->nullable()->setDefault('solid');
				$table->addColumn('icon_color', 'varchar', 30)->nullable()->setDefault('black');
				$table->addColumn('marker_color', 'varchar', 30)->nullable()->setDefault('blue');
				$table->addColumn('type', 'varchar', 50)->nullable();
				$table->addColumn('user_id', 'int')->nullable()->setDefault(null);
				$table->addColumn('create_date', 'int')->setDefault(\XF::$time);
				$table->addColumn('status', 'varchar', 20)->setDefault('pending');
				$table->addColumn('create_thread', 'tinyint', 1)->setDefault(0);
				$table->addColumn('thread_lock', 'tinyint', 1)->setDefault(0);
				$table->addColumn('start_date', 'int')->nullable()->setDefault(null);
				$table->addColumn('end_date', 'int')->nullable()->setDefault(null);

				$table->addPrimaryKey('suggestion_id');
				$table->addKey(['lat', 'lng']);
				$table->addKey('type');
				$table->addKey('user_id');
				$table->addKey('status');
			});

			return true;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error creating map marker suggestions table: ');
			return false;
		}
	}

	public function uninstallStep1()
	{
		$this->schemaManager()->dropTable('xf_map_markers');
		return true;
	}

	public function uninstallStep2()
	{
		$this->schemaManager()->dropTable('xf_map_marker_suggestions');
		return true;
	}

	public function upgrade1000020Step1(): bool
	{
		try
		{
			$this->schemaManager()->createTable('xf_map_marker_suggestions', function (Create $table)
			{
				$table->addColumn('suggestion_id', 'int')->autoIncrement();
				$table->addColumn('lat', 'decimal', '10,6')->unsigned(false);
				$table->addColumn('lng', 'decimal', '10,6')->unsigned(false);
				$table->addColumn('title', 'varchar', 100);
				$table->addColumn('content', 'text')->nullable();
				$table->addColumn('icon', 'varchar', 50)->nullable();
				$table->addColumn('icon_var', 'varchar', 20)->nullable()->setDefault('solid');
				$table->addColumn('icon_color', 'varchar', 30)->nullable()->setDefault('black');
				$table->addColumn('marker_color', 'varchar', 30)->nullable()->setDefault('blue');
				$table->addColumn('type', 'varchar', 50)->nullable();
				$table->addColumn('user_id', 'int')->nullable()->setDefault(null);
				$table->addColumn('create_date', 'int')->setDefault(\XF::$time);
				$table->addColumn('status', 'varchar', 20)->setDefault('pending');

				$table->addPrimaryKey('suggestion_id');
				$table->addKey(['lat', 'lng']);
				$table->addKey('type');
				$table->addKey('user_id');
				$table->addKey('status');
			});

			return true;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error creating map marker suggestions table during upgrade: ');

			if (str_contains($e->getMessage(), 'already exists'))
			{
				return true;
			}

			return false;
		}
	}

	public function upgrade1000510Step1(): bool
	{
		try
		{
			$this->schemaManager()->alterTable('xf_map_markers', function (Alter $table)
			{
				$table->addColumn('thread_id', 'int')->nullable()->setDefault(null);
				$table->addKey('thread_id');
				$table->addColumn('create_thread', 'tinyint', 1)->setDefault(0);
				$table->addColumn('thread_lock', 'tinyint', 1)->setDefault(0);
			});

			return true;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error updating database tables for Map addon: ' . $e->getMessage());

			if (str_contains($e->getMessage(), 'Duplicate column name'))
			{
				return true;
			}

			return false;
		}
	}

	public function upgrade1000510Step2(): bool
	{
		try
		{
			$this->schemaManager()->alterTable('xf_map_marker_suggestions', function (Alter $table)
			{
				$table->addColumn('create_thread', 'tinyint', 1)->setDefault(0);
				$table->addColumn('thread_lock', 'tinyint', 1)->setDefault(0);
			});

			return true;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error updating database tables for Map addon: ' . $e->getMessage());

			if (str_contains($e->getMessage(), 'Duplicate column name'))
			{
				return true;
			}

			return false;
		}
	}

	public function upgrade1000710Step1(): bool
	{
		try
		{
			$this->schemaManager()->alterTable('xf_map_markers', function (Alter $table)
			{
				$table->addColumn('start_date', 'int')->nullable()->setDefault(null);
				$table->addColumn('end_date', 'int')->nullable()->setDefault(null);
			});

			return true;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error adding time-based columns to map markers table: ');

			if (str_contains($e->getMessage(), 'Duplicate column name'))
			{
				return true;
			}

			return false;
		}
	}

	public function upgrade1000710Step2(): bool
	{
		try
		{
			$this->schemaManager()->alterTable('xf_map_marker_suggestions', function (Alter $table)
			{
				$table->addColumn('start_date', 'int')->nullable()->setDefault(null);
				$table->addColumn('end_date', 'int')->nullable()->setDefault(null);
			});

			return true;
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error adding time-based columns to map markers table: ');

			if (str_contains($e->getMessage(), 'Duplicate column name'))
			{
				return true;
			}

			return false;
		}
	}
}
