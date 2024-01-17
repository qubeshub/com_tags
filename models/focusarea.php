<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Tags\Models;

use Hubzero\Database\Relational;
use Hubzero\Component\View;
use Components\Tags\Models\Alignment;
use stdClass;

require_once __DIR__ . DS . 'alignment.php';
require_once __DIR__ . DS . 'log.php';

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
            ->select('O.mandatory_depth AS mandatory_depth') // Remove this once old depths are removed from FA table
            ->select('O.multiple_depth AS multiple_depth')
            ->select('O.ordering AS O_ordering')
            ->join('#__focus_areas_object O', '#__focus_areas.id', 'O.faid')
            ->whereEquals('O.objectid', $oid)
            ->whereEquals('O.tbl', $from)
            ->order('O.ordering', 'asc')
            ->order('#__focus_areas.label', 'asc');
        return $fas;
    }

    /**
     * Get parents (if $null is false, only works for one focus area at the moment)
     * 
     * @param   bool    $null   If true, return null parents (roots of focus area)
     * @return  object  Relational class
     */    
    public function parents($null = false) {
        $parents = $this->copy()
            ->join('#__focus_areas AS P', '#__focus_areas.parent', 'P.id', 'right')
            ->deselect()
            ->select('DISTINCT P.*');
        return ($null ? $parents->whereIsNull('P.parent') : $parents->whereEquals('jos_focus_areas.id', $this->id));
    }

    /**
     * Order focus areas by alignment with an object
     *
     * This method is helpful to reduce code duplication, but it is problematic
     * since it needs to know what the table name is for joins. This is currently
     * used after calling FocusArea::parents(), where the table name is 'P'.
     * 
     * @param   int     $objectid   Object id
     * @param   string  $fa_name    Focus area table name (for joins)
     * @param   string  $tbl        Object table
     * @param   string  $dir        Order direction
     * @return  object  Relational class
     */
    public function orderByAlignment($objectid, $fa_name = '#__focus_areas', $tbl='publication_master_types', $dir='asc') {
        return $this->copy()
            ->join('#__focus_areas_object AS O', $fa_name . '.id', 'O.faid', 'right')
			->select('O.ordering')
			->whereEquals('O.tbl', $tbl)
			->whereEquals('O.objectid', $objectid)
			->order('O.ordering', $dir);
    }

    public function ancestors($withme = 0) {
        $fa = $this;
        $ancestors = $withme ? array($fa) : array();
        while ($parent_id = $fa->parent) {
            $ancestors[] = ($fa = FocusArea::oneOrFail($parent_id));
        }
        return $ancestors;
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
	 * Retrieves one row by primary key, returning an initialized row if not found
	 *
	 * @param   mixed  $id  The primary key field value to use to retrieve one row
	 * @return  \Hubzero\Database\Relational|static
	 * @since   2.0.0
	 **/
    public static function oneOrNew($id) {
        $fa = parent::oneOrNew($id);
        
        if ($fa->isNew()) {
            // Save to generate new id
            $fa->set('tag_id', 0);
            if (!$fa->save()) {
                Notify::error($fa->getError());
                return false;
            }

            // Create new tag
            $tag = Tag::oneOrNew(0)->set("raw_tag", "qfa_" . $fa->id);
            $tag->set("tag", $tag->normalize($tag->get("raw_tag")));
            $tag->set("admin", 1);
            if (!$tag->save()) {
                Notify::error($tag->getError());
                return false;
            }

            // Assign tag id
            $fa->set('tag_id', $tag->get('id'));
            if (!$fa->save()) {
                Notify::error($fa->getError());
                return false;
            }
        }

        return $fa;
    }

	/**
	 * Delete entry and associated data
	 *
	 * @return  object
	 */
	public function destroy($recurse = false)
	{
		$fa_id = $this->get('id');

        // Recurse - depth-first in this case
        if ($recurse) {
            foreach ($this->children() as $child) {
                $child->destroy(true);
            }
        }

        // Delete alignments (if root)
        if (is_null($this->get('parent'))) {
		    foreach ($this->alignments()->rows() as $row)
		    {
			    $row->destroy();
		    }
        }

        $this->tag->destroy(); // Delete tag (which also deletes objects)
		parent::destroy(); // Delete focus area
	}

    /**
	 * Recursive method for rendering hierarchical focus areas (tags)
	 *
     * $props:
     *  'selected' => FocusArea class of selected focus areas (note: might be able to have this be array of ids if $rtrn != select)
     * 
     * @param   string  $rtrn       Format to render ('search', 'select', 'view', 'flat', 'filter')
     * @param   array   $props      Array of props needed for render
     * @param   int     $depth      Depth of current recursive call
     * 
	 * @return  void
	 */
	public function render($rtrn='view', $props=array(), $depth=1) {
        // Initialize
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
        } elseif ($rtrn == 'filter') {
            $view = new \Hubzero\Component\View(
                array(
                    'base_path' => Component::path('com_publications') . '/site',
                    'name'      => 'tags',
                    'layout'    => '_filters'
                )
            );
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
            case 'flat':
                $obj = new stdClass();
                $obj->id = ($this->id ? $this->id : 0);
                $obj->name = ($this->label ? $this->label : '');
                $obj->subtitle = ($this->about ? $this->about : '');
                if ($depth++ == 1) {
                    $obj->expanded = true;
                    $obj->parent = null;
                } else {
                    $obj->parent = $this->parent;
                }
                $flattree = array(array($obj));
            break;
            case 'view':
                $view->set('stage', 'before')
                    ->set('children', $children);
                $html = $view->loadTemplate();
            break;
            case 'select':
                $html = '';
            break;
            case 'filter':
                $view->set('stage', 'before')
                    ->set('depth', $depth)
                    ->set('props', $props)
                    ->set('parent', $this)
                    ->set('children', $children);
                $html = $view->loadTemplate();
            break;
        }

        // Recurse step
        foreach ($children as $child) {
            switch (strtolower($rtrn))
            {
                case 'search':
                    $path = $this->tag->get('tag') . '.' . $child->tag->get('tag');
					$paths[] = array($path);
                    // Recurse if child is filtered
                    if (!isset($props['filters']) || in_array($path, $props['filters'])) {
                        $paths[] = $child->render('search', $props);
                    }
                break;
                case 'flat':
                    $flattree[] = $child->render('flat', $props, $depth);
                break;
                case 'view':
                    $view->set('stage', 'during')
                        ->set('child', $child)
                        ->set('parent', $this->tag->get('tag'))
                        ->set('props', $props);
                    $html .= $view->loadTemplate();
                break;
                case 'filter':
                    $view->set('stage', 'during')
                        ->set('child', $child)
                        ->set('parent', $this)
                        ->set('depth', $depth)
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
                if (!count($children)) {
                    return array();
                } else {
                    return array_merge(...$paths);
                }
            break;
            case 'flat':
                if (!count($children)) {
                    return $flattree[0];
                } else {
                    return array_merge(...$flattree);
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
            case 'filter':
                $view->set('stage', 'after')
                    ->set('children', $children);
                $html .= $view->loadTemplate();
                return $html;
            break;
        }
    }

    /**
     * Realigns the focus area (i.e. recursively copies associations from children to parents).
     * NOTE: Be careful - this will delete all object associations for the root focus area.
     *
     * @param int $depth The depth of the realignment.
     * @return void
     */
    public function realign($depth = 0) {
        // Recursively realign children and copy associations to parent
        foreach ($this->children as $child) {
            $child->realign($depth+1);
            if (!is_null($this->parent)) {
                $child->tag->copyTo($this->tag_id);
            }
        }
        // Copy to all ancestors (except root)
        if ($depth == 0) {
            $fa = $this;
            while (!is_null($fa->parent)) {
                $parent = FocusArea::oneOrFail($fa->parent);
                if (!is_null($parent->parent)) { // Don't store associations in root focus areas
                    $fa->tag->copyTo($parent->tag_id);
                }
                $fa = $parent;
            }
            // $fa is at root - remove all associations
            $entries = array();
            foreach ($fa->tag->objects()->rows() as $row)
		    {
			    $row->destroy();
                $entries[] = $row->objectid . ' (' . $row->tbl . ')';
		    }
            if (count($entries)) {
                $data = new stdClass;
			    $data->entries = $entries;

                $log = Log::blank();
                $log->set([
                    'tag_id'   => $fa->tag_id,
                    'action'   => 'objects_removed',
                    'comments' => json_encode($data)
                ]);
                $log->save();
            }
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
        // For my wonky vagrant issues only
        // return 1;
        $fas_tags = $this->tags()->rows()->fieldsByKey('tag');

        // Calculate depth for each focus area
        // Selected tags will be in parent.child form in focus area path order, e.g.
        //  resourcesqubes.teachingmaterial precedes teachingmaterial.instructionalsetting
        $depths = array(); foreach ($fas_tags as $fa) { $depths[$fa] = array($fa => 0); }
        foreach ($selected as $tag) {
            $levels = explode('.', $tag);
            if (isset($depths[$levels[0]])) {
                $fa = $levels[0];
            }
            if (isset($depths[$fa][$levels[0]])) { // Needed in case tagged (i.e. selected) but alignment not set
                $depths[$fa][$levels[1]] = $depths[$fa][$levels[0]] + 1;
            }
        }

        // Check depths against mandatory depth
        foreach ($this as $fa) {
            $tag = $fa->tag->get('tag');
            if (max($depths[$tag]) < $fa->mandatory_depth) {
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

    public static function getAutocomplete() {
        $search = trim(Request::getString('value', ''));

        $tbl = self::blank()->getTableName();

        $rows = self::all()->purgeCache()
            ->select($tbl . '.*')
            ->limit(20)
            ->start(0)
            ->whereLike($tbl . '.label', $search, 1)
            ->orWhereLike($tbl . '.about', $search, 1)
            ->rows();

        // Output search results in JSON format
		$json = array();
		if (count($rows) > 0)
		{
			foreach ($rows as $row)
			{
				$name = str_replace("\n", '', stripslashes(trim($row->get('label')))) . ' (' . $row->get('id') . ')';
				$name = str_replace("\r", '', $name);

				$item = array(
					'id'   => $row->get('id'),
					'name' => $name
				);

				// Push exact matches to the front
				if ($row->get('label') == $search)
				{
					array_unshift($json, $item);
				}
				else
				{
					$json[] = $item;
				}
			}
		}

        return json_encode($json);
    }
}