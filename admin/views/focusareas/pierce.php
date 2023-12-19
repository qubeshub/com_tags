<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access.
defined('_HZEXEC_') or die();

$canDo = Components\Tags\Helpers\Permissions::getActions();

Toolbar::title(Lang::txt('COM_TAGS_FOCUS_AREAS') . ': ' . Lang::txt('COM_TAGS_PIERCE'), 'focusareas');
if ($canDo->get('core.edit'))
{
	Toolbar::save('pierce');
}
Toolbar::cancel();

use Components\Tags\Models\Focusarea;

?>

<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller); ?>" method="post" name="adminForm" class="editform" id="item-form">
	<p class="warning"><?php echo Lang::txt('COM_FOCUSAREAS_PIERCED_EXPLANATION'); ?></p>

	<div class="grid">
		<div class="col span4">
			<fieldset class="adminform">
				<legend><span><?php echo Lang::txt('COM_TAGS_PIERCING'); ?></span></legend>

				<div class="input-wrap">
					<ul>
						<?php
						foreach ($this->focusareas as $focusarea)
						{
                            $parent_label = ($focusarea->get('parent') ? Focusarea::oneOrFail($focusarea->get('parent'))->get('label') : 'None');
							echo '<li>' . $this->escape(stripslashes($focusarea->get('label'))) . ' (Parent: ' . $this->escape($parent_label) . ')</li>' . "\n";
						}
						?>
					</ul>
				</div>
			</fieldset>
		</div>
        <div class="col span4">
			<fieldset class="adminform">
				<legend><span><?php echo Lang::txt('COM_TAGS_PIERCING_TABLE'); ?></span></legend>

				<div class="input-wrap">
					<!-- Create select input for $this->tables array of strings -->
                    <select name="table" id="table">
                        <option value="">-- All associations --</option>
                        <?php
                        foreach ($this->tables as $table)
                        {
                            echo '<option value="' . $table . '">' . $table . '</option>' . "\n";
                        }
                        ?>
                    </select>
				</div>
			</fieldset>
		</div>
		<div class="col span4">
			<fieldset class="adminform">
				<legend><span><?php echo Lang::txt('COM_TAGS_PIERCE_TO'); ?></span></legend>

				<div class="input-wrap">
					<?php
					$tf = Event::trigger(
						'hubzero.onGetMultiEntry',
						array(
							array('tags', 'newfa', 'newfa', '', '', '30px', '', 'focusareas')
						)
					);
					echo (count($tf)) ? implode("\n", $tf) : '<input type="text" name="newfa" id="newfa" size="25" value="" />';
					?>
				</div>
			</fieldset>
		</div>
	</div>

	<input type="hidden" name="ids" value="<?php echo $this->idstr; ?>" />
	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>" />
	<input type="hidden" name="step" value="<?php echo $this->step; ?>" />
	<input type="hidden" name="task" value="pierce" />

	<?php echo Html::input('token'); ?>
</form>