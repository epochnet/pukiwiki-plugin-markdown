<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// endmarkdown.inc.php - Closes a #markdown() region. See markdown.inc.php.

function plugin_endmarkdown_convert()
{
	// close only what #markdown() opened, so a stray #endmarkdown() cannot break the layout
	if (empty($GLOBALS['plugin_markdown_open'])) return '';
	$GLOBALS['plugin_markdown_open']--;
	return '-->' . "\n";
}
