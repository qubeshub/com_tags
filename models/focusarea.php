<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Tags\Models;

use Hubzero\Database\Relational;
use Hubzero\Component\View;

/**
 * Model class for publication version author
 */
class FocusArea extends Relational
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
	 **/
    protected $table = '#__focus_areas';

	/**
	 * Default order by for model
	 *
	 * @var string
	 */
	public $orderBy = 'id';

	/**
	 * Default order direction for select queries
	 *
	 * @var  string
	 */
	public $orderDir = 'asc';

    /**
	 * Retrieves one row loaded by a tag field
	 *
	 * @param   string  $tag  The tag to load by
	 * @return  mixed
	 **/
	public function tag()
	{
        return $this->oneToOne('Components\Tags\Models\Tag', 'id', 'tag_id');
    }

    /**
     * Retrieve focus areas by tag ids
     * 
     * @param   array   $tags   Array of tags
     * @return  object  Relational class
     */
    public static function fromTags($tags) {
		$tag_ids = $tags->fieldsByKey('id');
        return self::all()->whereIn('tag_id', $tag_ids);
	}

    /**
     * Retrieve focus areas by tag ids
     * 
     * @param   array   $tags   Array of tags
     * @param   bool    $null   If true, return null parents (roots of focus area)
     * @return  object  Relational class
     */    
    public static function parents($fas, $null = false) {
        $parents = $fas->join('#__focus_areas AS P', '#__focus_areas.parent', 'P.id', 'right');
        return ($null ? $parents->whereIsNull('P.parent') : $parents);
    }

    /**
     * Return the root focus areas (taxonomies)
     * 
     * @return  Query object
     */
    public static function roots() {
        return self::all()->whereIsNull('parent');
    }

    /**
     * NOTE: MAY BE MADE REDUNDANT BY RENDER METHOD BELOW
     * 
	 * Recursive method for loading hierarchical focus areas (tags)
	 *
	 * @param   integer  $parent        Id of parent
	 * @param	array	 $subset		Existing focus area ids
	 * @return  void
	 */
	public function loadFocusArea($parent, $subset = null) {
        $db = App::get('db'); 
        $db->setQuery(
            'SELECT FA.id, FA.label, FA.about, FA.tag_id, T.tag, FA.ordering, FA.other FROM `#__focus_areas` FA
            INNER JOIN jos_tags T
            ON T.id = FA.tag_id
            WHERE FA.parent = ' . $parent . '
            ORDER BY FA.ordering ASC'
        );
        $fas = $db->loadAssocList('id');
		if (!is_null($subset)) {
			$fas = array_filter($fas, function($k) use ($subset) {
				return in_array(strtolower($k), $subset);
			}, ARRAY_FILTER_USE_KEY);
		}
		foreach ($fas as &$fa) {
			$fa['children'] = $this->loadFocusArea($fa['id'], $subset);
		}
		return $fas;
    }

    /**
	 * Recursive method for rendering hierarchical focus areas (tags)
	 *
     * @param   integer $id         Id of parent
     * @param   string  $rtrn       Format to render ('array', 'search', 'select', 'view')
	 * @param	array	$subset		Existing focus area ids
	 * @return  void
	 */
	public function render($id, $rtrn='array') {
        $subset = ($this->hasAttribute('subset') ? $this->get('subset') : null);
        $view = ($this->hasAttribute('view') 
            ? $this->get('view') 
            : new View(array(
                    'base_path' => dirname(__DIR__) . '/site',
                    'name'      => 'tags',
                    'layout'    => '_focusarea'
                ))
            );

        $tbl = $this->getTableName();
        $fas = self::all()
            ->select($tbl . '.*')
            ->select('T.tag')
            ->join('#__tags T', $tbl . '.tag_id', 'T.id')
            ->whereEquals($tbl . '.parent', $id)
            ->order($tbl . '.ordering', 'ASC');
        
        if (!is_null($subset)) {
            $fas->whereIn($tbl . '.id', $subset);
        }
        $fas = $fas->rows();

        // Before recursion
        switch (strtolower($rtrn))
        {
            case 'html':
                $view->set('stage', 'before')
                    ->set('fas', $fas);
                $html = $view->loadTemplate();
            break;
        }

        // Recurse step
        foreach ($fas as $fa) {
            switch (strtolower($rtrn))
            {
                case 'array':
                    $fa->children = $this->render($fa->id, $rtrn);
                break;
                case 'html':
                    $view->set('stage', 'during')
                        ->set('model', $this)
                        ->set('fa', $fa)
                        ->set('rtrn', $rtrn);
                    $html .= $view->loadTemplate();
                break;
            }
        }

        // After recursion
        switch (strtolower($rtrn))
        {
            case 'array':
                return $fas;
            break;
            case 'html':
                $view->set('stage', 'after')
                    ->set('fas', $fas);
                $html .= $view->loadTemplate();
                return $html;
            break;
        }
    }

    /**
     * Return focus area by object relationship settings (checkbox/radio, depth)
     *  given master type id and focus area
     * 
     * @param   integer $id     Focus area id
     * @param   integer $oid    Master type id
     * @return  array
     */
    public function setSelectSettings($id, $oid) {
        $tbl = $this->getTableName();
        $settings = self::blank()
            ->select('FP.mandatory_depth')
            ->select('FP.multiple_depth')
            ->join('#__focus_area_publication_master_type_rel FP', $tbl . '.id', 'FP.focus_area_id')
            ->whereEquals('FP.focus_area_id', $id)
            ->whereEquals('FP.master_type_id', $oid)
            ->row();

        if ($settings) {
            $this->set('mandatory_depth', $settings->mandatory_depth);
            $this->set('multiple_depth', $settings->multiple_depth);
            return true;
        } else {
            return false;
        }
    }

    /**
	 * Recursive method for flattening paths
	 *
	 * @param   integer  $array         Tree structure from loadFocusArea
	 * @return  void
	 */
    public function flatten_paths(&$array, $search = false) {
        $stack = array(); $paths = array();
        return $this->_flatten_paths($array, $stack, $paths, $search);
	}

    private function _flatten_paths(&$array, &$stack = array(), &$paths = array(), $search = false) {
        if (!array_key_exists('children', $array)) {
            // Root case
            array_walk($array, function($v) use (&$stack, &$paths, $search) {
                $this->_flatten_paths($v, $stack, $paths, $search);
            });
            return $paths;
        } else {
            // Add to stack
            $stack[] = array($array['id'] => ($search ? 'qfa' . $array['id'] : $array['label']));
            if (count($array['children']) == 0) {
                // Store stack (if exists)
                if ($stack) {
                    $paths[] = array_merge(...$stack);
                }
            } else {
                // Call children
                array_walk($array['children'], function($v) use (&$stack, &$paths, $search) {
                    $this->_flatten_paths($v, $stack, $paths, $search);
                });
            }
        }
        array_pop($stack);
    }
}