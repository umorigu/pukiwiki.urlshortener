<?php
// s.inc.php
//
// Copyright 2011-2021 umorigu
// PukiWiki URL Shortener plugin
// License: GPLv2

// key-name map directory
define('PLUGIN_S_NAMES_DIR', 'shortener');

// shortener counter map directory
define('PLUGIN_S_NAMES_COUNTER_DIR', 'shortener_counter');

// key string length
define('PLUGIN_S_PAGEID_LENGTH', 10);

// virtual query prefix "/${PLUGIN_S_VIRTUAL_QUERY_PREFIX}91aa88d26e";
define('PLUGIN_S_VIRTUAL_QUERY_PREFIX', '?cmd=s&k=');

// page name minimum length   
define('PLUGIN_S_PAGENAME_MININUM_LENGTH', 20);

// use short url if original url contains percent chars
define('PLUGIN_S_SHORT_URL_ON_PERCENT', FALSE);

function plugin_s_convert_get_short_link()
{
	$shorturl = plugin_s_inline_get_short_url();
	if (! $shorturl) {
		return '';
	}
	$s = htmlsc($shorturl);
	$jsblock = <<<JS_BLOCK_END
<script type="text/javascript">
var __plugin_s_hash = '';
var __plugin_s_prevHash = '';
function __plugin_s_hashmanage_onload() {
	setInterval(__plugin_s_hashmanage, 2000);
}
function __plugin_s_hashmanage() {
	__plugin_s_hash = location.hash;
	if (__plugin_s_hash != __plugin_s_prevHash) {
		var hashSpan = document.getElementById('__plugin_s_hash_span');
		if (hashSpan) {
			if (hashSpan.textContent) {
				hashSpan.textContent = __plugin_s_hash;
			} else {
				hashSpan.innerText = __plugin_s_hash;
			}
			__plugin_s_prevHash = __plugin_s_hash;
		}
	}
};
if (window.addEventListener) {
	window.addEventListener("load", __plugin_s_hashmanage_onload, false);
} else if (window.attachEvent) {
	window.attachEvent("onload", __plugin_s_hashmanage_onload);
}
</script>
JS_BLOCK_END;
	$retval = '<a href="' . $s . '">' . $s . '</a>'
	 . '<span id="__plugin_s_hash_span"></span>' . "\n"
	 . $jsblock;
	return $retval;
}

function plugin_s_inline_get_short_url()
{
	global $vars;
	$page = $vars['page'];
	return plugin_s_get_short_url_from_page($page);
}

/**
 * Get short absolute URL (with http(s):// scheme) for existing page.
 *
 * @return short or original url for existing page
 * @return '' if the page doesn't exists
 */
function plugin_s_get_short_url_from_page($page)
{
	$page_id = plugin_s_get_page_id($page);
	if ($page_id === FALSE) {
		// $page doesn't exists
		return '';
	}
	if (! $page_id) {
		return get_page_uri($page, PKWK_URI_ABSOLUTE);
	}
	$shorturl = get_base_uri(PKWK_URI_ABSOLUTE)
		. PLUGIN_S_VIRTUAL_QUERY_PREFIX . $page_id;
	return $shorturl;
}

/**
 * Get page_id of existing page.
 *
 * @param $page page name
 * @param $is_create_mapping_file if true, create mapping file in shortener/
 * @return FALSE: page doesn't exist
 * @return null: page_id should not be created (page name is enouth short. etc.)
 * @return page_id: 10 hex chars that indicate existing page
 */
function plugin_s_get_page_id($page)
{
	if (! is_page($page)) {
		return FALSE;
	}
	$utf8page = $page;
	if (! defined('PKWK_UTF8_ENABLE')) {
		$utf8page = mb_convert_encoding($page, 'UTF-8', 'CP51932');
	}
	$page_encoded = pagename_urlencode($utf8page);
	if (strlen($page_encoded) <= PLUGIN_S_PAGENAME_MININUM_LENGTH) {
		if (PLUGIN_S_SHORT_URL_ON_PERCENT) {
			if (strpos($page_encoded, '%') === FALSE) {
				return null;
			}
		} else {
			return null;
		}
	}
	$encoded = encode($utf8page);
	$md5 = md5($encoded);
	$page_id = substr($md5, 0, PLUGIN_S_PAGEID_LENGTH);

	// Create pageId to pageName file in shortener/ dir
	$filename = PLUGIN_S_NAMES_DIR . '/' . $page_id . '.txt';
	if (! file_exists($filename)) {
		$fp = fopen($filename, 'w') or die('fopen() failed: ' . htmlsc($filename));
		set_file_buffer($fp, 0);
		flock($fp, LOCK_EX);
		rewind($fp);
		fputs($fp, $utf8page);
		flock($fp, LOCK_UN);
		fclose($fp);
	}
	return $page_id;
}
/**
 * Action-type plugin: ?plugin=s&k={page_id}.
 */
function plugin_s_action()
{
	global $vars;
	$page_id = isset($vars['k']) ? $vars['k'] : '';
	$page = plugin_s_get_page_from_page_id($page_id);
	if (! $page) {
		die_message("Invalid page id: " . htmlsc($page_id));
		exit;
	}
	$url = get_page_uri($page, PKWK_URI_ROOT);
	header("HTTP/1.1 302 Found");
	header("Location: $url");
	exit;
}

/**
 * Get page name from page_id.
 *
 * @param $page_id page id - 1-32 hex chars
 * @return page name
 * @return FALSE if page_id is invalid or map file is not found
 */
function plugin_s_get_page_from_page_id($page_id)
{
	if (! preg_match('#^[a-f0-9]{10,32}$#', $page_id)) {
		return FALSE;
	}
	$filename = PLUGIN_S_NAMES_DIR . '/' . $page_id . '.txt';
	$fp = fopen($filename, 'r');
	if (! $fp) {
		return FALSE;
	}
	$page = trim(fgets($fp));
	if (! defined('PKWK_UTF8_ENABLE')) {
		$page = mb_convert_encoding($page, 'CP51932', 'UTF-8');
	}
	fclose($fp);

	// increment counter
	$cfilename = PLUGIN_S_NAMES_COUNTER_DIR . '/' . $page_id . '.count';
	$fpc = fopen($cfilename, file_exists($cfilename) ? 'r+' : 'w+')
		or die_message('Cannot open: ' . htmlsc($cfilename));
	set_file_buffer($fpc, 0);
	flock($fpc, LOCK_EX);
	$shorter_count = intval(trim(fgets($fpc))) + 1;
	rewind($fpc);
	fputs($fpc, $shorter_count);
	flock($fpc, LOCK_UN);
	fclose($fpc);

	return $page;
}
