<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// GitHub Flavored Markdown without raw HTML and with URL filtering.
// Wiki pages can be edited by anyone who can edit the wiki, so nothing in the
// Markdown text may produce markup other than what Markdown itself defines.

namespace PukiWikiMarkdown;

use cebe\markdown\GithubMarkdown;

class SafeMarkdown extends GithubMarkdown
{
	/** URL schemes allowed in links. Anything else (javascript:, data:, vbscript: ...) is dropped. */
	public $linkSchemes = ['http', 'https', 'mailto', 'ftp'];
	/** URL schemes allowed in images. */
	public $imageSchemes = ['http', 'https'];

	// ---- raw HTML is shown as text

	protected function identifyHtml($line, $lines, $current)
	{
		return false; // HTML blocks become ordinary paragraphs (and are escaped below)
	}

	/**
	 * `<` starts an autolink (<https://...>, <user@example.com>) or inline HTML.
	 * Autolinks are kept; inline HTML becomes text. (Markdown's own parseInlineHtml
	 * is private, so it is filtered here.) The marker annotation is required:
	 * the parser finds marker methods by their doc comments.
	 * @marker <
	 */
	protected function parseLt($text)
	{
		list($block, $offset) = parent::parseLt($text);
		if (isset($block[0]) && $block[0] === 'inlineHtml') {
			return [['text', '&lt;'], 1];
		}
		return [$block, $offset];
	}

	protected function renderHtml($block)
	{
		return htmlspecialchars($block['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
	}

	// ---- URLs

	/** Returns the URL if its scheme is allowed (or it is relative), otherwise null. */
	public static function filterUrl($url, array $schemes)
	{
		$url = (string) $url;
		// browsers ignore control characters and whitespace inside the scheme
		$probe = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
		if (preg_match('/^([a-z][a-z0-9+.\-]*):/', $probe, $m)) {
			return in_array($m[1], $schemes, true) ? $url : null;
		}
		if (strpos($probe, '//') === 0) {
			return in_array('https', $schemes, true) ? $url : null; // protocol-relative
		}
		return $url; // relative path, ?query or #fragment
	}

	protected function lookupReference($key)
	{
		$ref = parent::lookupReference($key);
		if ($ref !== false && isset($ref['url'])) {
			$ref['url'] = (string) self::filterUrl($ref['url'], $this->linkSchemes);
		}
		return $ref;
	}

	protected function renderLink($block)
	{
		if (isset($block['url'])) {
			$safe = self::filterUrl($block['url'], $this->linkSchemes);
			if ($safe === null) {
				return $this->renderAbsy($block['text']); // keep the text, drop the link
			}
			$block['url'] = $safe;
		}
		return parent::renderLink($block);
	}

	protected function renderImage($block)
	{
		if (isset($block['url'])) {
			$safe = self::filterUrl($block['url'], $this->imageSchemes);
			if ($safe === null) {
				return htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			}
			$block['url'] = $safe;
		}
		$html = parent::renderImage($block);
		return $html === '' ? $html : preg_replace('/^<img /', '<img loading="lazy" ', $html);
	}

	protected function renderUrl($block)
	{
		if (self::filterUrl($block[1], $this->linkSchemes) === null) {
			return htmlspecialchars($block[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
		return parent::renderUrl($block);
	}

	protected function renderAutoUrl($block)
	{
		if (self::filterUrl($block[1], $this->linkSchemes) === null) {
			return htmlspecialchars($block[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
		return parent::renderAutoUrl($block);
	}

	// ---- fenced code: the info string ends up in a class attribute

	protected function renderCode($block)
	{
		if (isset($block['language'])) {
			$lang = preg_replace('/[^A-Za-z0-9_+#.\-]/', '', (string) $block['language']);
			if ($lang === '') {
				unset($block['language']);
			} else {
				$block['language'] = $lang;
			}
		}
		return parent::renderCode($block);
	}
}
