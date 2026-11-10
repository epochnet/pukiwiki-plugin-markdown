<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// markdown.inc.php - Write part of a PukiWiki page in Markdown (GitHub Flavored Markdown)
//
// Usage:
//   #markdown()                 Region form. The lines up to #endmarkdown() are Markdown.
//   # Heading
//   - item
//   #endmarkdown()
//
//   #markdown{{                 Block form (PukiWiki 1.5 multiline plugin).
//   # Heading
//   }}
//
// Raw HTML is not rendered (it is shown as text), and only http/https/mailto/ftp
// links and http/https images are allowed. See README.md.

// ---- settings (define them in pukiwiki.ini.php to override)

// Extra stylesheet URL for .markdown-body (e.g. github-markdown-css). '' = built-in minimal style only.
if (! defined('PLUGIN_MARKDOWN_STYLESHEET_URL')) define('PLUGIN_MARKDOWN_STYLESHEET_URL', '');
// highlight.js for fenced code. '' = no syntax highlighting (no external requests).
// e.g. 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/'
if (! defined('PLUGIN_MARKDOWN_HIGHLIGHTJS_BASE_URL')) define('PLUGIN_MARKDOWN_HIGHLIGHTJS_BASE_URL', '');
// Treat single newlines as line breaks (like PukiWiki). FALSE = GitHub behaviour.
if (! defined('PLUGIN_MARKDOWN_BREAKS')) define('PLUGIN_MARKDOWN_BREAKS', FALSE);

require_once __DIR__ . '/markdown/autoload.php';

function plugin_markdown_convert()
{
	$args = func_get_args();
	$body = count($args) ? $args[count($args) - 1] : '';

	// Block form: #markdown{{ ... }} -- PukiWiki passes the text as the last
	// argument, with its lines separated by "\r"
	if (is_string($body) && strpbrk($body, "\r\n") !== FALSE) {
		return plugin_markdown_assets() .
			'<div class="markdown-body">' . plugin_markdown_render(str_replace(array("\r\n", "\r"), "\n", $body)) . '</div>' . "\n";
	}

	// Region form: #markdown() ... #endmarkdown()
	$lines = plugin_markdown_current_source();
	$regions = plugin_markdown_regions($lines);
	if (! $regions) return '';

	// The plugin is called once per #markdown() line, in order.
	static $cursor = array();
	global $vars;
	$key = (isset($vars['page']) ? $vars['page'] : '') . "\t" . md5(join('', $lines));
	$n = isset($cursor[$key]) ? $cursor[$key] : 0;
	$cursor[$key] = $n + 1;
	$region = $regions[$n % count($regions)];

	$html = plugin_markdown_assets() .
		'<div class="markdown-body">' . plugin_markdown_render(join('', $region['lines'])) . '</div>' . "\n";

	// PukiWiki also renders the region's lines as wiki text. That output is
	// commented out up to #endmarkdown(): a comment hides it even when the wiki
	// markup inside does not nest cleanly (a hidden <div> would not).
	if ($region['closed']) {
		$GLOBALS['plugin_markdown_open'] = (isset($GLOBALS['plugin_markdown_open']) ? $GLOBALS['plugin_markdown_open'] : 0) + 1;
		return $html . '<!-- markdown source' . "\n";
	}
	// No #endmarkdown(): the region runs to the end of the page. An unclosed
	// comment would also hide the page footer, so use an element the browser
	// closes at the end of the page body.
	return $html . '<div class="markdown-source" hidden>';
}

/**
 * Source of the page being converted. #include and #calendar_viewer set
 * $vars['page'] to the page they render, so this follows them. In an edit
 * preview, the text being edited is used instead of the saved page.
 */
function plugin_markdown_current_source()
{
	global $vars;
	$page = isset($vars['page']) ? $vars['page'] : '';
	if (! empty($vars['preview']) && isset($vars['msg']) &&
	    isset($_POST['page']) && $_POST['page'] === $page) {
		// a page included by the preview has its own $vars['page'], so it reads its saved source
		return array_map(function ($l) { return $l . "\n"; }, explode("\n", str_replace("\r", '', $vars['msg'])));
	}
	return $page === '' ? array() : get_source($page);
}

/** Every #markdown() ... #endmarkdown() region in the lines (an unclosed region runs to the end). */
function plugin_markdown_regions($lines)
{
	$regions = array();
	$cur = NULL;
	foreach ($lines as $line) {
		if ($cur === NULL) {
			if (preg_match('/^#markdown\(\)\s*$/', $line)) $cur = array('lines' => array(), 'closed' => FALSE);
			continue;
		}
		if (preg_match('/^#endmarkdown\(\)\s*$/', $line)) {
			$cur['closed'] = TRUE;
			$regions[] = $cur;
			$cur = NULL;
			continue;
		}
		$cur['lines'][] = $line;
	}
	if ($cur !== NULL) $regions[] = $cur;
	return $regions;
}

/** Markdown (in the wiki's encoding) to safe HTML (in the wiki's encoding). */
function plugin_markdown_render($text)
{
	$enc = defined('SOURCE_ENCODING') ? SOURCE_ENCODING : 'UTF-8';
	$utf8 = strcasecmp($enc, 'UTF-8') === 0;
	if (! $utf8) $text = mb_convert_encoding($text, 'UTF-8', $enc);

	// PukiWiki adds fixed anchors " [#abcd1234]" to lines starting with "*" when saving
	$text = preg_replace('/^(\*.*?) \[#[A-Za-z0-9]{8}\]$/m', '$1', str_replace("\r", '', $text));

	$parser = new \PukiWikiMarkdown\SafeMarkdown();
	$parser->enableNewlines = (bool) PLUGIN_MARKDOWN_BREAKS;
	$html = $parser->parse($text);

	return $utf8 ? $html : mb_convert_encoding($html, $enc, 'UTF-8');
}

/** Style (and optional highlight.js), emitted once per request. */
function plugin_markdown_assets()
{
	static $done = FALSE;
	if ($done) return '';
	$done = TRUE;

	$out = '<style>' .
		'.markdown-body{margin:0 0 1em}' .
		'.markdown-body table{border-collapse:collapse;margin:0 0 1em}' .
		'.markdown-body th,.markdown-body td{border:1px solid #d0d7de;padding:4px 10px}' .
		'.markdown-body pre{background:#f6f8fa;padding:10px 12px;overflow:auto}' .
		'.markdown-body code{background:#f6f8fa;padding:1px 4px;border-radius:4px}' .
		'.markdown-body pre code{padding:0;background:none}' .
		'.markdown-body blockquote{margin:0 0 1em;padding:0 1em;color:#59636e;border-left:4px solid #d0d7de}' .
		'.markdown-body img{max-width:100%}' .
		'</style>' . "\n";
	if (PLUGIN_MARKDOWN_STYLESHEET_URL !== '') {
		$out .= '<link rel="stylesheet" href="' . htmlsc(PLUGIN_MARKDOWN_STYLESHEET_URL) . '">' . "\n";
	}
	if (PLUGIN_MARKDOWN_HIGHLIGHTJS_BASE_URL !== '') {
		$base = htmlsc(rtrim(PLUGIN_MARKDOWN_HIGHLIGHTJS_BASE_URL, '/') . '/');
		$out .= '<link rel="stylesheet" href="' . $base . 'styles/default.min.css">' . "\n" .
			'<script src="' . $base . 'highlight.min.js"></script>' . "\n" .
			'<script>document.addEventListener("DOMContentLoaded",function(){if(window.hljs)document.querySelectorAll(".markdown-body pre code").forEach(function(e){hljs.highlightElement(e)})});</script>' . "\n";
	}
	return $out;
}
