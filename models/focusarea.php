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
     * @param   bool    $save   Save to selected attribute
     * @return  object  Relational class
     */
    public function fromTags($tags, $save=true) {
		$tag_ids = $tags->fieldsByKey('id');
        $fas = self::all()->whereIn('tag_id', $tag_ids);
        if ($save) {
            $this->set('selected', $fas->copy());
        }
        return $fas;
	}

    /**
     * Retrieve focus areas by tag ids
     * 
     * @param   array   $tags   Array of tags
     * @param   bool    $null   If true, return null parents (roots of focus area)
     * @return  object  Relational class
     */    
    public function parents($fas, $null = false) {
        $parents = $fas->join('#__focus_areas AS P', '#__focus_areas.parent', 'P.id', 'right')
                       ->deselect()
                       ->select('DISTINCT P.*');
        return ($null ? $parents->whereIsNull('P.parent') : $parents);
    }

    /**
     * Return the root focus areas (taxonomies)
     * 
     * @return  Query object
     */
    public function roots() {
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
     * @param   integer $fa         Focus area to render
     * @param   string  $rtrn       Format to render ('search', 'select', 'view')
	 * @return  void
	 */
	public function render($fa, $rtrn='html') {
        // Initialize
        $selected = ($this->hasAttribute('selected') ? $this->get('selected') : null);
        $view = ($this->hasAttribute('view') 
            ? $this->get('view') 
            : new View(array(
                    'base_path' => dirname(__DIR__) . '/site',
                    'name'      => 'tags',
                    'layout'    => '_focusareas'
                ))
            );

        // Get children
        $tbl = $this->getTableName();
        $children = self::all()
            ->select($tbl . '.*')
            ->join('#__tags T', $tbl . '.tag_id', 'T.id')
            ->whereEquals($tbl . '.parent', $fa->id)
            ->order($tbl . '.ordering', 'ASC');
        
        if (!is_null($selected)) {
            $children->whereIn($tbl . '.id', $selected->copy()->rows()->fieldsByKey('id'));
        }
        $children = $children->rows();

        // Before recursion
        switch (strtolower($rtrn))
        {
            case 'search':
                $paths = array();
            break;
            case 'html':
                $view->set('stage', 'before')
                    ->set('fas', $children);
                $html = $view->loadTemplate();
            break;
        }

        // Recurse step
        foreach ($children as $child) {
            switch (strtolower($rtrn))
            {
                case 'search':
					$paths[] = $this->render($child, 'search');
                break;
                case 'html':
                    $view->set('stage', 'during')
                        ->set('model', $this)
                        ->set('fa', $child)
                        ->set('rtrn', $rtrn);
                    $html .= $view->loadTemplate();
                break;
            }
        }

        // After recursion
        switch (strtolower($rtrn))
        {
            case 'search':
                $tag = $fa->tag->get('tag');
                if (!count($children)) {
                    return array($tag);
                } else {
                    return array_map(
                        function($v) use ($tag) {
                            return $tag . '.' . $v;
                        },
                        array_merge(...$paths)
                    );
                }
            break;
            case 'html':
                $view->set('stage', 'after')
                    ->set('fas', $children);
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
    public function setSelectSettings($id, $oid, $from='publication_master_types') {
        $tbl = $this->getTableName();
        $settings = self::blank()
            ->select('O.mandatory_depth')
            ->select('O.multiple_depth')
            ->join('#__focus_areas_object O', $tbl . '.id', 'O.faid')
            ->whereEquals('O.objectid', $oid)
            ->whereEquals('O.faid', $id)
            ->whereEquals('O.tbl', $from)
            ->row();

        if ($settings) {
            $this->set('mandatory_depth', $settings->mandatory_depth);
            $this->set('multiple_depth', $settings->multiple_depth);
            return array(
                'mandatory_depth' => $settings->mandatory_depth,
                'multiple_depth' => $settings->multiple_depth
            );
        } else {
            return array(
                'mandatory_depth' => null,
                'multiple_depth' => null
            );
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