<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Minimal PSR-4 autoloader for the bundled cebe/markdown and this plugin (no Composer needed).

spl_autoload_register(function ($class) {
	$map = array(
		'cebe\\markdown\\' => __DIR__ . '/vendor/cebe/markdown/',
		'PukiWikiMarkdown\\' => __DIR__ . '/',
	);
	foreach ($map as $prefix => $dir) {
		if (strncmp($class, $prefix, strlen($prefix)) === 0) {
			$file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) require $file;
			return;
		}
	}
});
