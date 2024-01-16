<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Tags\Admin\Controllers;

use Hubzero\Component\AdminController;
use Components\Tags\Models\FocusArea;
use Components\Tags\Models\Tag;
use Components\Tags\Models\Objct;
use stdClass;

/**
 * Tags controller class for managing raltionships between tags
 */
class Focusareas extends AdminController
{
    /**
	 * Prelead
	 *
	 * @var string
	 */
	private $preload;

	/**
	 * Execute a task
	 *
	 * @return  void
	 */
	public function execute()
	{
		$this->registerTask('add', 'edit');

		parent::execute();
	}

	/**
	 * Show a form for looking up a tag's relationships
	 *
	 * @return  void
	 */
	public function displayTask()
	{
        // Incoming
        $filters = array(
            'search' => urldecode(Request::getState(
                $this->_option . '.' . $this->_controller . '.search',
                'search',
                ''
            )),
            'by' => strtolower(Request::getState(
                $this->_option . '.' . $this->_controller . '.by',
                'filterby',
                'roots'
            )),
            'sort' => Request::getState(
                $this->_option . '.' . $this->_controller . '.sort',
                'filter_order',
                'id'
            ),
            'sort_Dir' => Request::getState(
                $this->_option . '.' . $this->_controller . '.sortdir',
                'filter_order_Dir',
                'ASC'
            )
        );

        $model = FocusArea::all();

        $fa = $model->getTableName();

        if ($filters['search'])
        {
            $filters['search'] = strtolower((string)$filters['search']);

            $model
                ->whereLike($fa . '.label', $filters['search'], 1)
                ->orWhereLike($fa . '.about', $filters['search'], 1)
                ->resetDepth();
        }

        if ($filters['by'] == 'roots')
        {
            $model->whereIsNull('parent');
        }

		$modelc = $model->copy();
		$modelc
			->select('COUNT(DISTINCT ' . $fa . '.id)', 'count');
		$first = $modelc->rows(false)->first();
		$total = $first ? (int)$first->count : 0;

        $model
            ->select('DISTINCT ' . $fa . '.*');

        $model->pagination = \Hubzero\Database\Pagination::init($model->getModelName(), $total, 'limitstart', 'limit');
        $model->start($model->pagination->start);
        $model->limit($model->pagination->limit);

        // Get records
        $rows = $model
            ->order($fa . '.' . $filters['sort'], $filters['sort_Dir'])
            ->rows();

        // Output the HTML
        $this->view
            ->set('filters', $filters)
            ->set('rows', $rows)
            ->display();
	}

    /**
	 * Edit an entry
	 *
	 * @param   object  $fa  Focus area being edited
	 * @return  void
	 */
	public function editTask($fa=null)
	{
		if (!User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.create', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		Request::setVar('hidemainmenu', 1);

		// Load a focus area object if one doesn't already exist
		if (!is_object($fa))
		{
			// Incoming
			$ids = Request::getArray('id', array(0));

			$fas = array_map(function($id) { return FocusArea::oneOrNew(intval($id)); }, $ids);
		} else {
			// Should be one focus area object - turn into an array
			$fas = array($fa);
		}
		// Only allow editing of multiple focus area as roots
		if ((count($fas) > 1) && !array_reduce($fas, function($carry, $fa) { return $carry && is_null($fa->parent); }, true)) {
			Notify::error("Only focus area roots can be edited together");
			$this->cancelTask();
		}

        $flattree = array_merge(...array_map(function($fa) { return $fa->render('flat'); }, $fas));

		// Output the HTML
		$this->view
			->set('fas', $fas)
            ->set('flattree', $flattree)
			->setLayout('edit')
			->display();
	}

	/**
	 * Save an entry
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken();

		// Incoming
		$old_fas_ids = Request::getString('ids', ''); // Original focus area ids
		$old_ordering = Request::getInt('ordering', null); // Original ordering of first root (if more than one, then null)
		$parent = Request::getInt('parent', null); // Parent of first root (if more than one, then null)
		$flattree	= Request::getString('flattree', ''); // JSON string of updated focus areas

		// Permissions check
		if (!User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.create', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Get original focus areas
		$old_fas_ids = explode(',', $old_fas_ids);
		$old_fas = array_merge(...array_map(function($id) { return FocusArea::oneOrNew(intval($id))->render('flat'); }, $old_fas_ids));

		// Get new focus areas
		$new_fas = json_decode($flattree);
		$new_fas_ids = array_map(function($fa) { return (isset($fa->id) ? $fa->id : '0'); }, $new_fas);

		// Delete 
		$delete = array_filter($old_fas, function($fa) use ($new_fas_ids) {
			return !in_array($fa->id, $new_fas_ids);
		});
		foreach ($delete as $dmw) {
			$fa = FocusArea::oneOrFail(intval($dmw->id));
			$fa->destroy();
		}

		// Add or modify
		$subs = array(); // New id substitutions
		$ordering = array();
		if (!is_null($parent)) { $ordering[$parent] = $old_ordering; }
		$root_ids = array(); // New roots (for ordering adjustment later)
		foreach ($new_fas as $fa) {
			$new_fa = FocusArea::oneOrNew(isset($fa->id) ? intval($fa->id) : 0);
			$id = (string) $new_fa->get('id');
			if (is_null($fa->parent)) { // Root
				$root_ids[] = $id;
				$fa->parent = $parent; // Was set to null for sortable tree to work - set back
			}
			
			// Initialize ordering for this focus area's children
			if (!isset($ordering[$id])) { $ordering[$id] = 1; }
			
			// Save substitution (new focus area which has a negative id)
			if (intval($fa->id) < 0) {
				$subs[$fa->id] = $id;
			}

			// Use substitution to match negative focus area id with correct parent focus area id
			if ($fa->parent && (intval($fa->parent) < 0)) {
				$fa->parent = $subs[$fa->parent];
			}

			// Set focus area properties
			$new_fa->set('label', $fa->name);
			$new_fa->set('about', $fa->subtitle);
			$new_fa->set('parent', $fa->parent);
			$new_fa->set('ordering', $fa->parent ? $ordering[$fa->parent]++ : null);
			if (!$new_fa->save()) {
				Notify::error($new_fa->getError());
				return $this->editTask($new_fa);
			}
		}

		// Update ordering of all unedited root siblings to account for edited siblings at root level
		$order_inc = count($root_ids)-1;
		if (!is_null($parent) && ($order_inc != 0)) { // If not root focus area and there is a change in local tree roots
			$siblings = FocusArea::oneOrFail($parent)->children(); // Get root siblings, including self
			foreach ($siblings as $sibling) {
				if (!in_array($sibling->id, $root_ids) && ($sibling->ordering > $old_ordering)) {
					$sibling->set('ordering', $sibling->ordering + $order_inc);
					if (!$sibling->save()) {
						Notify::error($sibling->getError());
						return $this->editTask($sibling);
					}
				}
			}
		}

		// Realign publications to new focus areas

		// Update solr index

        Notify::success(Lang::txt('COM_TAGS_FOCUS_AREA_SAVED'));

		$this->cancelTask();
	}

	/**
	 * Remove one or more entries
	 *
	 * @return  void
	 */
	public function removeTask()
	{
		// Check for request forgeries
		Request::checkToken();

		// Permissions check
		if (!User::authorise('core.delete', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		// Make sure we have an ID
		if (empty($ids))
		{
			Notify::warning(Lang::txt('COM_TAGS_ERROR_NO_ITEMS_SELECTED'));

			return $this->cancelTask();
		}

		foreach ($ids as $id)
		{
			$id = intval($id);

			// Remove the focus area
			$fa = FocusArea::oneOrFail($id);
			$fa->destroy(true); // In this case, recurse
		}

		Notify::success(Lang::txt('COM_TAGS_FOCUS_AREA_REMOVED'));

		$this->cancelTask();
	}

	/**
	 * Cancel a task and redirect to the main listing
	 *
	 * @return  void
	 */
	public function cancelTask()
	{
		$return = Request::getString('return', 'index.php?option=' . $this->_option . '&controller=focusareas', 'get');

		App::redirect(
			Route::url($return, false)
		);
	}

	/**
	 * Copy all tag associations from one focus area to another
	 *
	 * @return  void
	 */
	public function pierceTask()
	{
		// Permissions check
		if (!User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.manage', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		$step = Request::getInt('step', 1);
		$step = ($step) ? $step : 1;

		// Make sure we have some IDs to work with
		if ($step == 1
		 && (!$ids || count($ids) < 1))
		{
			return $this->cancelTask();
		}

		$idstr = implode(',', $ids);

		switch ($step)
		{
			case 1:
				Request::setVar('hidemainmenu', 1);

				$focusareas = array();

				// Loop through the IDs of the tags we want to merge
				foreach ($ids as $id)
				{
					// Load the tag's info
					$focusareas[] = Focusarea::oneOrFail(intval($id));
				}

				// Get distinct tbl field from jos_tags_object
				$tables = Objct::all()
					->select('DISTINCT tbl')
					->where('tbl', '!=', '')
					->rows()
					->fieldsByKey('tbl');

				// Output the HTML
				$this->view
					->set('step', 2)
					->set('idstr', $idstr)
					->set('focusareas', $focusareas)
					->set('tables', $tables)
					->display();
			break;

			case 2:
				// Check for request forgeries
				Request::checkToken();

				// Get the string of focus area ids to copy associations from
				$from = Request::getVar('ids', '', 'post');
				if (!$from) {
					Notify::error("Did not specify a focus area to copy associations from");
					$this->cancelTask();
				}
				$from_ids = explode(',', $from);

				// Get each focus area
				$from_tags = array_map(function($id) { 
						return FocusArea::oneOrFail($id)->tag;
					}, $from_ids);

				// Get each focus areas ancestors (to filter out later)
				$from_anc = array_merge(...array_map(function($id) { 
					return array_map(function($anc) {
						return $anc->tag_id;
					}, FocusArea::oneOrFail($id)->ancestors(0));
				}, $from_ids));

				// Get the string of focus area ids to copy associations to
				$to = Request::getVar('newfa', '', 'post');
				if (!$to) {
					Notify::error("Did not specify a focus area to copy associations to");
					$this->cancelTask();
				}
				$to_ids = explode(',', $to);

				// Get ancestors of each focus area (if exists)
				// Might need to create new focus area, so do that here
				$to_aids = array_merge(...array_map(function($id) { 
					$fa = FocusArea::oneOrNew($id);
					return $fa->ancestors(1);
				}, $to_ids));

				// Make sure there are no duplicates, filter out from ancestors, and store as tags
				$to_tags = array_reduce($to_aids, function($carry, $item) use ($from_anc) {
					if (!in_array($item->tag_id, $from_anc) && (empty($carry) || !in_array($item->tag_id, array_keys($carry)))) {
						$carry[$item->tag_id] = $item->tag;
					}
					return $carry;
				});

				// Get the table to copy associations from
				$tbl = Request::getVar('table', '', 'post');
				$tbl = $tbl ? $tbl : null;

				// Copy all tag associations from one focus area to another
				foreach ($from_tags as $from_tag) {
					foreach ($to_tags as $to_tag) {
						// echo "Would copy $tbl from " . $from_tag->id . " to " . $to_tag->id . "<br>";
						if (!$from_tag->copyTo($to_tag->id, $tbl)) {
							Notify::error($from_tag->getError());
						}
					}
				}

				if ($this->getError())
				{
					Notify::error($this->getError());
				}
				else
				{
					Notify::success(Lang::txt('COM_TAGS_FOCUS_AREAS_COPIED'));
				}

				$this->cancelTask();
			break;
		}
	}
}