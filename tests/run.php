<?php
// Tests without PukiWiki: minimal stand-ins for the functions the plugin uses.
// Usage: php tests/run.php            (UTF-8 wiki)
//        php tests/run.php EUC-JP     (EUC-JP wiki)

define('SOURCE_ENCODING', isset($argv[1]) ? $argv[1] : 'UTF-8');
function htmlsc($s) { return htmlspecialchars($s, ENT_QUOTES, SOURCE_ENCODING); }
$GLOBALS['vars'] = array('page' => 'Test');
$GLOBALS['pages'] = array();
function get_source($page) { return isset($GLOBALS['pages'][$page]) ? $GLOBALS['pages'][$page] : array(); }

require __DIR__ . '/../plugin/markdown.inc.php';
require __DIR__ . '/../plugin/endmarkdown.inc.php';

/** Stand-in for PukiWiki's convert_html(): calls the plugins on their lines, in order. */
function convert_html($lines)
{
	if (! is_array($lines)) $lines = explode("\n", $lines);
	$out = '';
	foreach ($lines as $line) {
		$l = rtrim($line, "\n");
		if ($l === '#markdown()') $out .= plugin_markdown_convert();
		elseif ($l === '#endmarkdown()') $out .= plugin_endmarkdown_convert();
		else $out .= '<p>' . htmlsc($l) . '</p>';
	}
	return $out;
}

function enc($s) { return SOURCE_ENCODING === 'UTF-8' ? $s : mb_convert_encoding($s, SOURCE_ENCODING, 'UTF-8'); }
function dec($s) { return SOURCE_ENCODING === 'UTF-8' ? $s : mb_convert_encoding($s, 'UTF-8', SOURCE_ENCODING); }
function md($text) { return dec(plugin_markdown_convert(enc($text) . "\n")); } // block form
function lines($text) { return array_map(function ($l) { return enc($l) . "\n"; }, explode("\n", $text)); }

$fail = 0;
$n = 0;
function check($name, $ok) { global $fail, $n; $n++; if (! $ok) { $fail++; echo "NG  $name\n"; } else echo "ok  $name\n"; }
function has($h, $s) { return strpos($h, $s) !== false; }

// ---- XSS
$h = md("<script>alert(1)</script>\n\n<img src=x onerror=alert(2)>\n\n<div onclick=alert(3)>x</div>\n\ntext <b onmouseover=alert(4)>b</b>");
check('raw <script> is escaped', ! has($h, '<script>alert') && has($h, '&lt;script&gt;'));
check('raw <img onerror> is escaped', ! has($h, '<img src=x'));
check('raw HTML block is escaped', ! has($h, '<div onclick'));
check('inline HTML is escaped', ! has($h, '<b onmouseover'));

$h = md("[a](javascript:alert(1)) [b](JaVaScRiPt:alert(1)) [c](jav&#x61;script:alert(1)) [d]( javascript:alert(1))\n\n<javascript://%0aalert(1)>\n\n[e][r]\n\n[r]: javascript:alert(1)\n\n![i](data:image/svg+xml,<svg onload=alert(1)>) [f](vbscript:x)");
check('javascript: links are dropped', ! preg_match('/href="\s*(j|&#|jav)/i', $h) && ! has(strtolower($h), 'href="javascript'));
check('javascript:// autolink is not a link', ! has($h, 'href="javascript://'));
check('reference-style javascript: is dropped', ! has(strtolower($h), 'javascript:alert(1)"'));
check('data: image is dropped', ! has($h, '<img src="data:'));
check('vbscript: is dropped', ! has($h, 'href="vbscript'));

$h = md("```js\" onmouseover=\"alert(1)\nx\n```");
check('code language cannot inject attributes', ! has($h, 'onmouseover="') && preg_match('~<code class="language-[A-Za-z0-9_+#.\-]+">~', $h) === 1);
$h = md("```python\nprint('<b>')\n```");
check('code language kept', has($h, 'class="language-python"'));
check('code content escaped', has($h, '&lt;b&gt;'));

// ---- ordinary Markdown still works
$h = md("# 見出し\n\n- 項目 **太字**\n\n| a | b |\n|---|---|\n| 1 | 2 |\n\n[リンク](https://example.com/?a=1&b=2) ![画像](https://example.com/a.png) <https://example.com/x> [相対](./?Page) [#](#top) [m](mailto:a@example.com)");
check('heading', has($h, '<h1>見出し</h1>'));
check('list and strong', has($h, '<li>項目 <strong>太字</strong></li>'));
check('table', has($h, '<table>'));
check('https link', has($h, 'href="https://example.com/?a=1&amp;b=2"'));
check('image (lazy)', has($h, '<img loading="lazy" src="https://example.com/a.png"'));
check('autolink', has($h, 'href="https://example.com/x"'));
check('relative and fragment links', has($h, 'href="./?Page"') && has($h, 'href="#top"'));
check('mailto', has($h, 'href="mailto:a@example.com"'));
check('entities kept', has(md('&copy; a & b'), '&copy; a &amp; b'));

// ---- fixed anchors that PukiWiki adds to "*" lines
check('fixed anchor removed from list items', has(md("* item [#abcd1234]\n* two"), '<li>item</li>'));

// ---- region form: each #markdown() gets its own region, the wiki rendering is hidden
$GLOBALS['pages']['Test'] = lines("before\n#markdown()\n# One\n#endmarkdown()\nmiddle\n#markdown()\n# Two\n#endmarkdown()\nafter");
$h = dec(convert_html(get_source('Test')));
check('first region', has($h, '<h1>One</h1>'));
check('second region is its own text', has($h, '<h1>Two</h1>') && substr_count($h, '<h1>One</h1>') === 1);
$visible = preg_replace('/<!--.*?-->/s', '', $h);
check('wiki rendering of the region is commented out', ! has($visible, '<p># One</p>') && has($h, '<p># One</p>'));
check('text after #endmarkdown() is visible', has($visible, '<p>middle</p>') && has($visible, '<p>after</p>'));
check('comments are balanced', substr_count($h, '<!-- markdown source') === 2 && substr_count($h, '-->') === 2);
$GLOBALS['pages']['Open'] = lines("#markdown()\n# Open\nno end");
$GLOBALS['vars']['page'] = 'Open';
$h = dec(convert_html(get_source('Open')));
check('unclosed region does not open a comment', has($h, '<h1>Open</h1>') && ! has($h, '<!--') && has($h, '<div class="markdown-source" hidden>'));
$GLOBALS['vars']['page'] = 'Test';
check('block form with \\r-separated lines (as PukiWiki passes them)', has(dec(plugin_markdown_convert(enc("# CR\r- a\r- b"))), '<h1>CR</h1>'));
check('stray #endmarkdown() outputs nothing', plugin_endmarkdown_convert() === '');

// ---- #include / #calendar_viewer switch $vars['page'] to the page they render
$GLOBALS['pages']['Included'] = lines("#markdown()\n# Included page\n#endmarkdown()");
$GLOBALS['vars']['page'] = 'Included';
$h = dec(convert_html(get_source('Included')));
check('reads the page being rendered', has($h, 'Included page') && ! has($h, '<h1>One</h1>'));
$GLOBALS['vars']['page'] = 'Test';

// ---- edit preview uses the text being edited
$GLOBALS['vars']['preview'] = '1';
$GLOBALS['vars']['msg'] = enc("#markdown()\n# Edited\n#endmarkdown()");
$_POST['page'] = 'Test';
$h = dec(convert_html($GLOBALS['vars']['msg']));
check('preview shows the edited text', has($h, '<h1>Edited</h1>'));
unset($GLOBALS['vars']['preview'], $GLOBALS['vars']['msg'], $_POST['page']);

// ---- assets: nothing external by default, emitted once
check('no external requests by default', ! preg_match('~(src|href)="https?://(?!example\.com)~', convert_html(lines("#markdown()\nx\n#endmarkdown()"))));

echo "\n" . SOURCE_ENCODING . ": $n tests, $fail failed\n";
exit($fail ? 1 : 0);
