<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Tags\Models;

use Hubzero\Database\Relational;
use Components\Tags\Models\FocusArea;
use stdClass;
use Lang;
use Date;
use User;

require_once __DIR__ . DS . 'log.php';

/**
 * Focus area object association (alignments)
 *
 */
class Alignment extends Relational
{
	/**
	 * The table namespace
	 *
	 * @var string
	 */
	protected $namespace = 'focus_areas';

	/**
	 * The table to which the class pertains
	 *
	 * This will default to #__{namespace}_{modelName} unless otherwise
	 * overwritten by a given subclass. Definition of this property likely
	 * indicates some derivation from standard naming conventions.
	 *
	 * @var  string
	 */
	protected $table = '#__focus_areas_object';

	/**
	 * Default order by for model
	 *
	 * @var string
	 */
	public $orderBy = 'ordering';

	/**
	 * Default order direction for select queries
	 *
	 * @var  string
	 */
	public $orderDir = 'asc';

	/**
	 * Fields and their validation criteria
	 *
	 * @var  array
	 */
	protected $rules = array(
		'tbl'      => 'notempty',
		'faid'    => 'positive|nonzero',
		'objectid' => 'positive|nonzero',
		'ordering'	   => 'notempty'
	);

	/**
	 * Retrieves one row loaded by a focus area field
	 *
	 * @param   integer  $scope_id  Object ID (e.g., master type ID)
	 * @param   string   $scope     Object type (ex: master type)
	 * @param   integer  $fa_id     Focus area ID
	 * @return  mixed
	 **/
	public static function oneByScoped($scope_id, $scope, $fa_id)
	{
		$instance = self::all()
			->whereEquals('tbl', $scope)
			->whereEquals('objectid', $scope_id)
			->whereEquals('faid', $fa_id);

		return $instance->row();
	}

    /**
     * 
     */
    public static function saveAlignment($scope_id, $scope, $alignments) {
        $old_faids = self::all()
            ->whereEquals('objectid', $scope_id)
            ->whereEquals('tbl', $scope)
            ->rows()
            ->fieldsByKey('faid');

        $preserve_faids = [];
        $new_faids = array_keys($alignments);
        foreach ($old_faids as $old_faid) {
            if (!in_array($old_faid, $new_faids)) {
                $fa = self::oneByScoped($scope_id, $scope, $old_faid);
		        $fa->destroy();
            } else {
                $preserve_faids[] = $old_faid;
            }
        }

        $new_faids = array_diff($new_faids, $preserve_faids);

        $i = 1;
        foreach ($alignments as $faid => $alignment) {
            if (in_array($faid, $preserve_faids))  {
                // Need row() to update
                self::oneByScoped($scope_id, $scope, $faid)
                    ->set($alignment)
                    ->set('ordering', $i)
                    ->save();
            } elseif (in_array($faid, $new_faids)) {
                // Need original Alignment class for new (i.e. no row()!)
                self::blank()
                    ->set($alignment)
                    ->set('objectid', $scope_id)
                    ->set('tbl', $scope)
                    ->set('ordering', $i)
                    ->save();
            }
            $i++;
        }
    }
}
