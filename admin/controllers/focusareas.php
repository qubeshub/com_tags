<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Tags\Admin\Controllers;

use Hubzero\Component\AdminController;
use Components\Tags\Models\FocusArea;

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
}