<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access.
defined('_HZEXEC_') or die();

$this->css();

$this->tagstring = str_replace(array('%20', ' ', '+'), ',', $this->tagstring);

$name  = Lang::txt('COM_TAGS_ALL_CATEGORIES');
$total = $this->total;
$here  = 'index.php?option=' . $this->option . '&tag=' . $this->tagstring . ($this->filters['sort'] ? '&sort=' . $this->filters['sort'] : '');

// Add the "all" category
$all = array(
	'name'    => '',
	'title'   => Lang::txt('COM_TAGS_ALL_CATEGORIES'),
	'total'   => $this->total,
	'results' => null,
	'sql'     => ''
);
$cats = $this->categories;
array_unshift($cats, $all);

// An array for storing all the links we make
$links = array();

// Loop through each category
foreach ($cats as $cat)
{
	// Only show categories that have returned search results
	if (!$cat['total'] > 0)
	{
		continue;
	}

	// If we have a specific category, prepend it to the search term
	$blob = '';
	if ($cat['name'])
	{
		$blob = $cat['name'];
	}

	$sef = Route::url($here . ($blob ? '&area=' . stripslashes($blob) : ''));

	// Is this the active category?
	$a = '';
	
	if ($cat['name'] == $this->active && !$this->parent)
	{
		$a = ' class="active"';

		$name  = $cat['title'];
		$total = $cat['total'];

		Pathway::append($cat['title'], $here . '&area=' . stripslashes($blob));
	}

	// Build the HTML
	$l = "\t".'<li><a' . $a . ' href="' . $sef . '">' . $this->escape(stripslashes($cat['title'])) . ' <span class="item-count">' . $cat['total'] . '</span></a>';

	// Are there sub-categories?
	if (isset($cat['children']) && is_array($cat['children']))
	{
		// An array for storing the HTML we make
		$k = array();
		// Loop through each sub-category
		foreach ($cat['children'] as $subcat)
		{
			// Only show sub-categories that returned search results
			if ($subcat['total'] > 0)
			{
				// If we have a specific category, prepend it to the search term
				$blob = ($subcat['name'] ? $subcat['name'] : '');
				
				$parent = $cat['name'];

				// Is this the active category?
				$a = '';
				if ($subcat['name'] == $this->active && isset($this->parent) && $this->parent == $parent)
				{
					$a = ' class="active"';

					$name  = $subcat['title'];
					$total = $subcat['total'];

					Pathway::append($subcat['title'], $here . '&area=' . stripslashes($blob));
				}

				// Build the HTML
				$k[] = "\t\t\t".'<li><a' . $a . ' href="' . Route::url($here . '&parent=' . $parent . '&area='. stripslashes($blob)) . '">' . $this->escape(stripslashes($subcat['title'])) . ' <span class="item-count">' . $subcat['total'] . '</span></a></li>';
			}
		}
		// Do we actually have any links?
		// NOTE: this method prevents returning empty list tags "<ul></ul>"
		if (count($k) > 0)
		{
			$l .= "\t\t".'<ul>'."\n";
			$l .= implode("\n", $k);
			$l .= "\t\t".'</ul>'."\n";
		}
	}
	$l .= '</li>';

	$links[] = $l;
}
?>
<header id="content-header">
	<h2><?php echo $this->title; ?></h2>

	<div id="content-header-extra">
		<p>
			<a class="icon-tag btn" href="<?php echo Route::url('index.php?option=' . $this->option); ?>">
				<?php echo Lang::txt('COM_TAGS_MORE_TAGS'); ?>
			</a>
		</p>
	</div><!-- / #content-header-extra -->
</header><!-- / #content-header -->

<section class="main section">
	<form class="section-inner hz-layout-with-aside" action="<?php echo Route::url('index.php?option=' . $this->option); ?>" method="get">
		<div class="subject">
			<div class="container data-entry">
				<input type="hidden" name="task" value="view" />
				<input class="entry-search-submit" type="submit" value="<?php echo Lang::txt('COM_TAGS_SEARCH'); ?>" />
				<fieldset class="entry-search">
					<label for="actags"><?php echo Lang::txt('COM_TAGS_SEARCH_LABEL'); ?></label>
					<?php echo $this->autocompleter('tags', 'tag', $this->escape($this->search), 'actags'); ?>
				</fieldset>
			</div><!-- / .container -->

			<?php foreach ($this->tags as $tagobj) { ?>
				<?php if ($tagobj->get('description') != '') { ?>
					<div class="container">
						<div class="container-block">
							<h3><?php echo Lang::txt('COM_TAGS_DESCRIPTION'); ?></h3>
							<div class="tag-description">
								<?php echo stripslashes($tagobj->get('description')); ?>
							</div>
						</div>
					</div><!-- / .container -->
				<?php } ?>
			<?php } ?>

			<div class="container">
				<nav class="entries-filters">
					<ul class="entries-menu order-options">
						<li>
							<a<?php echo ($this->filters['sort'] == 'title') ? ' class="active"' : ''; ?> href="<?php echo Route::url('index.php?option=' . $this->option . '&tag=' . $this->tagstring . '&area=' . $this->active . '&sort=title&limit=' . $this->filters['limit'] . '&start=' . $this->filters['start']); ?>" title="<?php echo Lang::txt('COM_TAGS_OPT_SORT_BY_TITLE'); ?>">
								<?php echo Lang::txt('COM_TAGS_OPT_TITLE'); ?>
							</a>
						</li>
						<li>
							<a<?php echo ($this->filters['sort'] == 'date' || $this->filters['sort'] == '') ? ' class="active"' : ''; ?> href="<?php echo Route::url('index.php?option=' . $this->option . '&tag=' . $this->tagstring . '&area=' . $this->active . '&sort=date&limit=' . $this->filters['limit'] . '&start=' . $this->filters['start']); ?>" title="<?php echo Lang::txt('COM_TAGS_OPT_SORT_BY_DATE'); ?>">
								<?php echo Lang::txt('COM_TAGS_OPT_DATE'); ?>
							</a>
						</li>
					</ul>
				</nav>

				<div class="container-block">
					<?php
						$ttl = ($total > ($this->filters['limit'] + $this->filters['start'])) ? ($this->filters['limit'] + $this->filters['start']) : $total;
						if ($total && !$ttl)
						{
							$ttl = $total;
						}

						$base = rtrim(Request::base(), '/');

						$html  = '<h3>' . $this->escape(stripslashes($name)) . ' <span>(' . Lang::txt('COM_TAGS_RESULTS_THROUGH_OF', ($this->filters['start'] + 1), $ttl, $total) . ')</span></h3>'."\n";

						if ($this->results)
						{
							$html .= '<ol class="results">' . "\n";
							foreach ($this->results as $row)
							{
								$obj = 'plgTags' . ucfirst($row->section == null ? '' : $row->section);

								if (method_exists($obj, 'out'))
								{
									$html .= call_user_func(array($obj, 'out'), $row);
								}
								else
								{
									// @todo accommodate scope (aka) group citations
									if (strstr($row->href, 'index.php'))
									{
										$row->href = Route::url($row->href);
									}

									$html .= "\t" . '<li>' . "\n";
									$html .= "\t\t" . '<p class="title"><a href="' . $row->href . '">' . \Hubzero\Utility\Sanitize::clean($row->title) . '</a></p>' . "\n";
									if($row->section) 
									{
										$html .= "\t\t" . '<p class="details"><strong class="type">' . ucfirst($row->section) . "</strong></p>\n";
									}
									if ($row->ftext)
									{
										$html .= "\t\t" . '<p>' . \Hubzero\Utility\Str::truncate(strip_tags($row->ftext), 200) . "</p>\n";
									}
									$html .= "\t\t" . '<p class="href">' . $base . $row->href . '</p>' . "\n";
									$html .= "\t" . '</li>' . "\n";
								}
							}
							$html .= '</ol>' . "\n";
						}
						else
						{
							$html = '<p class="warning">' . Lang::txt('COM_TAGS_NO_RESULTS') . '</p>';
						}
						echo $html;
					?>
				</div><!-- / .container-block -->
				<?php
					$pageNav = $this->pagination(
						$total,
						$this->filters['start'],
						$this->filters['limit']
					);
					$pageNav->setAdditionalUrlParam('task', '');
					$pageNav->setAdditionalUrlParam('tag', $this->tagstring);
					$pageNav->setAdditionalUrlParam('active', $this->active);
					$pageNav->setAdditionalUrlParam('sort', $this->filters['sort']);

					echo $pageNav->render() . '<div class="clearfix"></div>';
				?>
			</div><!-- / .container -->
		</div><!-- / .subject -->
		<aside class="aside">
			<div class="container">
				<h3><?php echo Lang::txt('COM_TAGS_CATEGORIES'); ?></h3>
				<?php
				// Do we actually have any links?
				// NOTE: this method prevents returning empty list tags "<ul></ul>"
				if (count($links) > 0)
				{
					// Yes - output the necessary HTML
					$html  = '<ul>'."\n";
					$html .= implode("\n", $links);
					$html .= '</ul>'."\n";
				}
				else
				{
					// No - nothing to output
					$html = '';
				}
				$html .= "\t" . '<input type="hidden" name="parent" value="' . $this->escape($this->parent) . '" />' . "\n";
				$html .= "\t" . '<input type="hidden" name="area" value="' . $this->escape($this->active) . '" />' . "\n";
				$html .= "\t" . '<input type="hidden" name="sort" value="' . $this->escape($this->filters['sort']) . '" />' . "\n";

				echo $html;
				?>
				<p class="info">
					<?php echo Lang::txt('COM_TAGS_RESULTS_NOTE'); ?>
				</p>
			</div>
		</aside><!-- / .aside -->
	</form>
</section><!-- / .main section -->
