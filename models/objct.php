<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Tags\Models;

use Hubzero\Database\Relational;
use stdClass;
use Lang;
use Date;
use User;

require_once __DIR__ . DS . 'log.php';

/**
 * Tag object association
 *
 * NOTE: If following naming conventions, this should be "Object"
 * but that's a reserved word in PHP 7. So, we drop the 'e'.
 */
class Objct extends Relational
{
	/**
	 * The table namespace
	 *
	 * @var string
	 */
	protected $namespace = 'tags';

	/**
	 * The table to which the class pertains
	 *
	 * This will default to #__{namespace}_{modelName} unless otherwise
	 * overwritten by a given subclass. Definition of this property likely
	 * indicates some derivation from standard naming conventions.
	 *
	 * @var  string
	 */
	protected $table = '#__tags_object';

	/**
	 * Default order by for model
	 *
	 * @var string
	 */
	public $orderBy = 'taggedon';

	/**
	 * Default order direction for select queries
	 *
	 * @var  string
	 */
	public $orderDir = 'desc';

	/**
	 * Fields and their validation criteria
	 *
	 * @var  array
	 */
	protected $rules = array(
		'tbl'      => 'notempty',
		'tagid'    => 'positive|nonzero',
		'objectid' => 'positive|nonzero'
	);

	/**
	 * Automatic fields to populate every time a row is created
	 *
	 * @var  array
	 */
	public $initiate = array(
		'taggedon',
		'taggerid'
	);

	/**
	 * Generates automatic taggedon field value
	 *
	 * @param   array   $data  the data being saved
	 * @return  string
	 */
	public function automaticTaggedon($data)
	{
		if (!isset($data['taggedon']) || !$data['taggedon'])
		{
			$data['taggedon'] = Date::toSql();
		}
		return $data['taggedon'];
	}

	/**
	 * Generates automatic taggerid field value
	 *
	 * @param   array   $data  the data being saved
	 * @return  integer
	 */
	public function automaticTaggerid($data)
	{
		if (!isset($data['taggerid']) || !$data['taggerid'])
		{
			$data['taggerid'] = User::get('id');
		}
		return $data['taggerid'];
	}

	/**
	 * Get parent tag
	 *
	 * @return  object
	 */
	public function tag()
	{
		return $this->belongsToOne('Tag', 'tagid');
	}

	/**
	 * Creator profile
	 *
	 * @return  object
	 */
	public function creator()
	{
		return $this->belongsToOne('Hubzero\User\User', 'taggerid');
	}

	/**
	 * Return a formatted timestamp
	 *
	 * @param   string  $as  What data to return
	 * @return  string
	 */
	public function created($as='')
	{
		$as = strtolower($as);
		$dt = $this->get('taggedon');

		if ($as == 'date')
		{
			$dt = Date::of($dt)->toLocal(Lang::txt('TIME_FORMAT_HZ1'));
		}

		if ($as == 'time')
		{
			$dt = Date::of($dt)->toLocal(Lang::txt('DATE_FORMAT_HZ1'));
		}

		return $dt;
	}

	/**
	 * Retrieves one row loaded by a tag field
	 *
	 * @param   string   $scope     Object type (ex: resource, ticket)
	 * @param   integer  $scope_id  Object ID (e.g., resource ID, ticket ID)
	 * @param   integer  $tag_id    Tag ID
	 * @param   integer  $tagger    User ID of person adding tag
	 * @param	string	 $label		Label specifying relationship for tagging
	 * @return  mixed
	 **/
	public static function oneByScoped($scope, $scope_id, $tag_id, $tagger=0, $label='')
	{
		$instance = self::blank()
			->whereEquals('tbl', $scope)
			->whereEquals('objectid', $scope_id)
			->whereEquals('tagid', $tag_id)
			->whereEquals('label', $label);

		if ($tagger)
		{
			$instance->whereEquals('taggerid', $tagger);
		}

		return $instance->row();
	}

	/**
	 * Move all references to one tag to another tag
	 *
	 * @param   integer  $oldtagid  ID of tag to be moved
	 * @param   integer  $newtagid  ID of tag to move to
	 * @return  boolean  True if records changed
	 */
	public static function moveTo($oldtagid=null, $newtagid=null)
	{
		if (!$oldtagid || !$newtagid)
		{
			return false;
		}

		$items = self::all()
			->whereEquals('tagid', $oldtagid)
			->rows();

		$entries = array();

		foreach ($items as $item)
		{
			// Find if there is already a database row with the same object, tag, and label. Delete the item then.
			$duplicates = self::all()
				->whereEquals('tbl', $item->get('tbl'))
				->whereEquals('tagid', $newtagid)
				->whereEquals('objectid', $item->get('objectid'))
				->whereEquals('label', $item->get('label'))
				->rows();
			if ($duplicates->count())
			{
				$item->destroy();
			}
			else
			{
				$item->set('tagid', $newtagid);
				$item->save();
				$entries[] = $item->toArray();
			}
		}

		$data = new stdClass;
		$data->old_id  = $oldtagid;
		$data->new_id  = $newtagid;
		$data->entries = $entries;

		$log = Log::blank();
		$log->set([
			'tag_id'   => $newtagid,
			'action'   => 'objects_moved',
			'comments' => json_encode($data)
		]);
		$log->save();

		return true;
	}

	/**
	 * Copy all tags on an object to another object
	 *
	 * @param   integer  $oldtagid  ID of tag to be copied
	 * @param   integer  $newtagid  ID of tag to copy to
	 * @return  boolean  True if records copied
	 */
	public static function copyTo($oldtagid=null, $newtagid=null, $tbl=null)
	{
		if (!$oldtagid || !$newtagid)
		{
			return false;
		}

		// Get all objects associated with the old tag but NOT the new tag
		// This is a left outer join with exclusion (https://stevestedman.com/2015/03/mysql-join-types-poster/)
		// This is a workaround for the fact that Hubzero's database abstraction layer doesn't support exclusion joins
		// Refactors previous code that checked database for each individual object (this is more efficient)
		$scope = ($tbl ? ' AND O.tbl = "' . $tbl . '"' : '');
		$db = App::get('db');
		$db->setQuery('SELECT old.id
			FROM (SELECT *
				FROM `#__tags_object` O
				WHERE O.tagid = ' . $oldtagid . $scope . ') old
			LEFT JOIN
				(SELECT *
				FROM `#__tags_object` O
				WHERE O.tagid = ' . $newtagid . $scope . ') new
			ON old.objectid = new.objectid
			WHERE new.id is null');
		$ids = $db->query()->loadColumn();

		if ($ids)
		{
			$entries = array();

			foreach ($ids as $id)
			{
				$obj = self::oneOrFail($id);
				$obj->set('id', 0)
					->set('tagid', $newtagid);

				if (!$obj->save()) {
					Notify::error($obj->getError());
					continue;
				}
				$entries[] = $id;
			}

			$data = new stdClass;
			$data->old_id  = $oldtagid;
			$data->new_id  = $newtagid;
			$data->entries = $entries;

			$log = Log::blank();
			$log->set([
				'tag_id'   => $newtagid,
				'action'   => 'objects_copied',
				'comments' => json_encode($data)
			]);
			$log->save();
		}

		return true;
	}
}
