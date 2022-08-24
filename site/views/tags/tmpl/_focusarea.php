<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access.
defined('_HZEXEC_') or die();

$html = '';
switch ($this->stage) {
    case 'before':
        if (count($this->fas)) {
            $html .= '<ul class="fa">';
        }
    break;
    case 'during':
        $html .= "<li class='fa'>";
        $html .= "<label style='display: inline;'" . ($this->fa->about ? " title='" . htmlentities($this->fa->about) . "' class='tooltips'" : "") . ">" . $this->fa->label . "</label>";
        $html .= $this->model->render($this->fa->id, $this->rtrn);
        $html .= "</li>";
    break;
    case 'after':
        if (count($this->fas)) {
            $html .= '</ul>';
        }
    break;
}

echo $html;

return;