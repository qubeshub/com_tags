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
			$id = Request::getArray('id', array(0));
			if (is_array($id) && !empty($id))
			{
				$id = $id[0];
			}

			$fa = FocusArea::oneOrNew(intval($id));
		}
        $flattree = $fa->render('flat');

		// Output the HTML
		$this->view
			->set('fa', $fa)
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
		$parent     = Request::getInt('parent', null);
		$flattree	= Request::getString('flattree', '');

		// Permissions check
		if (!User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.create', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		$new_fas = json_decode($flattree);
		$new_fas[0]->parent = $parent; // Was set to null for sortable tree to work - set back
		$new_fas_ids = array_map(function($fa) { return (isset($fa->id) ? $fa->id : '0'); }, $new_fas);

		// Get original focus areas
		$old_fa = FocusArea::oneOrNew(intval($new_fas[0]->id)); // 0th should be same
		$old_fas = $old_fa->render('flat');

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
		foreach ($new_fas as $fa) {
			$new_fa = FocusArea::oneOrNew(isset($fa->id) ? intval($fa->id) : 0);
			$id = (string) $new_fa->get('id');
			if (!isset($ordering[$id])) { $ordering[$id] = 0; }
			// Save substitution (new focus area)
			if (intval($fa->id) < 0) {
				$subs[$fa->id] = $id;
			}
			// Use substitution
			if ($fa->parent && (intval($fa->parent) < 0)) {
				$fa->parent = $subs[$fa->parent];
			}
			$new_fa->set('label', $fa->name);
			$new_fa->set('about', $fa->subtitle);
			$new_fa->set('parent', $fa->parent);
			if (is_null($fa->parent) || isset($ordering[$fa->parent])) {
				$new_fa->set('ordering', $fa->parent ? ++$ordering[$fa->parent] : null);
			}
			if (!$new_fa->save()) {
				Notify::error($new_fa->getError());
				return $this->editTask($new_fa);
			}
		}

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
}