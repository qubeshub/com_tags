<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access.
defined('_HZEXEC_') or die();

$canDo = Components\Tags\Helpers\Permissions::getActions();
$text = ($this->task == 'edit' ? Lang::txt('JACTION_EDIT') : Lang::txt('JACTION_CREATE'));

Toolbar::title(Lang::txt('COM_TAGS') . ': FOCUS AREAS: ' . $text, 'focusareas');
if ($canDo->get('core.edit'))
{
	Toolbar::save();
}
Toolbar::cancel();

Html::behavior('framework');
Html::behavior('formvalidation');
Html::behavior('keepalive');

$this->css('sortable_tree_bundle')
    ->css('sortable_tree');
$this->js()
	->js('api')
    ->js('sortable_tree_bundle');
?>

<div id="st-focusarea"></div>

<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller); ?>" method="post" name="adminForm" id="item-form" class="editform form-validate" data-invalid-msg="<?php echo $this->escape(Lang::txt('JGLOBAL_VALIDATION_FORM_FAILED'));?>">

    <input type="hidden" name="flattree" value='<?php echo htmlspecialchars(json_encode($this->flattree), ENT_QUOTES); ?>' />
    <input type="hidden" name="ids" value="<?php echo implode(',', array_map(function($fa) { return $fa->id; }, $this->fas)); ?>" />
	<input type="hidden" name="parent" value="<?php echo $this->fas[0]->parent; ?>" />
	<input type="hidden" name="ordering" value="<?php echo $this->fas[0]->ordering; ?>" />
	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>" />
	<input type="hidden" name="task" value="save" />

	<?php echo Html::input('token'); ?>
</form>

<script>
    var App = ST.ReactContentRenderer.render({flattree: JSON.parse($('input[name="flattree"]').val())}, document.getElementById('st-focusarea'));
</script>
