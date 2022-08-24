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
	 * Retrieves tags of focus areas
	 *
	 * @return  mixed
	 **/
    public function tags()
    {
        return $this->copy()
            ->join('#__tags AS T', '#__focus_areas.tag_id', 'T.id', 'right')
            ->deselect()
            ->select('T.*');
    }

    /**
	 * Get a list of aligned objects
	 *
	 * @return  object
	 */
	public function alignments()
	{
		return $this->oneToMany(__NAMESPACE__ . '\\Alignment', 'faid');
	}

    /**
     * Retrieve focus areas by tag ids
     * 
     * @param   array   $tags   Array of tags
     * @param   bool    $save   Save to selected attribute
     * @return  object  Relational class
     */
    public static function fromTags($tags) {
		$tag_ids = $tags->fieldsByKey('id');
        $fas = self::all()->whereIn('tag_id', $tag_ids);
        return $fas;
	}

    /**
     * Retrieve focus areas by object relationship
     * 
     * @param   integer $oid    Master type id
     * @param   string  $from   Object table
     * @return  object  Relational class
     */
    public static function fromObject($oid, $from='publication_master_types') {
        $fas = self::blank()
            ->select('#__focus_areas.*')
            ->select('O.mandatory_depth AS O_mandatory_depth') // Remove this once old depths are removed from FA table
            ->select('O.multiple_depth AS O_multiple_depth')
            ->select('O.ordering AS O_ordering')
            ->join('#__focus_areas_object O', '#__focus_areas.id', 'O.faid')
            ->whereEquals('O.objectid', $oid)
            ->whereEquals('O.tbl', $from)
            ->order('O.ordering', 'asc')
            ->order('#__focus_areas.label', 'asc');
        return $fas;
    }

    /**
     * Get parents
     * 
     * @param   bool    $null   If true, return null parents (roots of focus area)
     * @return  object  Relational class
     */    
    public function parents($null = false) {
        $parents = $this->copy()
            ->join('#__focus_areas AS P', '#__focus_areas.parent', 'P.id', 'right')
            ->deselect()
            ->select('DISTINCT P.*');
        return ($null ? $parents->whereIsNull('P.parent') : $parents);
    }

    /**
     * Get children
     * 
     * @return  object  Relational class
     */
    public function children() {
        $children = self::all()
            ->select('#__focus_areas.*')
            ->join('#__tags T', '#__focus_areas.tag_id', 'T.id')
            ->whereEquals('#__focus_areas.parent', $this->id)
            ->order('#__focus_areas.ordering', 'ASC');
        return $children;
    }

    /**
     * Return the root focus areas (taxonomies)
     * 
     * @return  Query object
     */
    public static function roots() {
        return self::all()->whereIsNull('parent');
    }

    public function maxdepth($depth = 0) {
        $max_depth = $depth;
        foreach($this->children() as $fa) {
            $max_depth = max($max_depth, $fa->maxdepth($depth+1));
        }
        return $max_depth;
    }

    /**
	 * Recursive method for rendering hierarchical focus areas (tags)
	 *
     * $props:
     *  'selected' => FocusArea class of selected focus areas (note: might be able to have this be array of ids if $rtrn != select)
     * 
     * @param   string  $rtrn       Format to render ('search', 'select', 'view')
     * @param   array   $props      Array of props needed for render
     * @param   int     $depth      Depth of current recursive call
     * 
	 * @return  void
	 */
	public function render($rtrn='view', $props=array(), $depth=1) {
        // Initialize
        if ($rtrn != 'search') {
            if ($rtrn == 'view') {
                $view = new \Hubzero\Component\View(
                    array(
                        'base_path' => dirname(__DIR__) . '/site',
                        'name'      => 'tags',
                        'layout'    => '_focusareas'
                    )
                );
            } elseif ($rtrn == 'select') {
                $view = new \Hubzero\Plugin\View(
                    array(
                        'folder'  => 'projects',
                        'element' => 'publications',
                        'name'    => 'draft',
                        'layout'  => '_focusareas'
                    )
                );
            }
        }

        // Get children
        $children = $this->children();
        if (isset($props['selected']) && ($rtrn != 'select')) {
            $children->whereIn('#__focus_areas.id', $props['selected']->copy()->rows()->fieldsByKey('id'));
        }
        $children = $children->rows();

        // Before recursion
        switch (strtolower($rtrn))
        {
            case 'search':
                $paths = array();
            break;
            case 'view':
                $view->set('stage', 'before')
                    ->set('children', $children);
                $html = $view->loadTemplate();
            break;
            case 'select':
                $html = '';
            break;
        }

        // Recurse step
        foreach ($children as $child) {
            switch (strtolower($rtrn))
            {
                case 'search':
					$paths[] = $child->render('search', $props);
                break;
                case 'view':
                    $view->set('stage', 'during')
                        ->set('child', $child)
                        ->set('props', $props);
                    $html .= $view->loadTemplate();
                break;
                case 'select':
                    $view->set('depth', $depth)
                        ->set('props', $props)
                        ->set('parent', $this->tag->get('tag'))
                        ->set('child', $child);
                    $html .= $view->loadTemplate();
                break;
            }
        }

        // After recursion
        switch (strtolower($rtrn))
        {
            case 'search':
                $tag = $this->tag->get('tag');
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
            case 'view':
                $view->set('stage', 'after')
                    ->set('children', $children);
                $html .= $view->loadTemplate();
                return $html;
            break;
            case 'select':
                return $html;
            break;
        }
    }

    /**
	 * Check status of focus area selection - are tags selected at minimal depths 
     *  across focus areas?
	 *
     * This method should be called from result of $self::fromObject
     * 
     * @param   array   $selected   Result of Cloud render method with $rtrn = 'search'
     * 
	 * @return  void
	 */
    public function checkStatus($selected)
	{
        $fas_tags = $this->tags()->rows()->fieldsByKey('tag');

        // Calculate depth for each focus area
        $depths = array_fill_keys($fas_tags, 0);
        foreach ($selected as $tag) {
            $levels = explode('.', $tag);
            if (isset($depths[$levels[0]])) { // In case deactivated focus area is still stored
                $depths[$levels[0]] = max($depths[$levels[0]], count($levels)-1);
            }
        }

        // Check depths against mandatory depth
        foreach ($this as $fa) {
            $tag = $fa->tag->get('tag');
            if ($depths[$tag] < $fa->O_mandatory_depth) {
                return 0;
            }
        }

        return 1;
	}

    /**
     * Get tags of focus areas from the browser
     * 
     * Call method from result of FocusArea::fromObject($oid)
     * 
     * @return  array   Array of tags
     */
    public function processTags() {
        $tags = array();

        // Get map of ontologies (aren't tagged in database)
        $fas_tags = array_fill_keys($this->tags()->rows()->fieldsByKey('tag'), true);

        foreach ($_POST as $k => $vs) {
            if (!preg_match('/^tagfa/', $k)) {
                continue;
            } else {
                $parent = explode('-', $k)[1];
            }
            $vs = (!is_array($vs) ? array($vs) : $vs);
            foreach ($vs as $v) {
                // Store if parent is ontology (or) parent already stored
                if (isset($fas_tags[$parent]) || isset($tags[$parent])) {
                    $tags[$v] = true;
                } 
            }
        }

        return array_keys($tags);
    }
}