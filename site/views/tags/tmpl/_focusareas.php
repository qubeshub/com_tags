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
        if (count($this->children)) {
            $html .= '<ul class="fa">';
        }
    break;
    case 'during':
        $html .= "<li class='fa'>";
        $html .= "<label style='display: inline;'" . ($this->child->about ? " title='" . htmlentities($this->child->about) . "' class='tooltips'" : "") . "><a href='" . Route::url('/com_publications/browse?fl=' . $this->child->id) . "'>" . $this->child->label . "</a></label>";
        $html .= $this->child->render('view', $this->props);
        $html .= "</li>";
    break;
    case 'after':
        if (count($this->children)) {
            $html .= '</ul>';
        }
    break;
}

echo $html;

return;