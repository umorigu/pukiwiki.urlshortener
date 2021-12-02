<?php
// s.inc.php
//
// Copyright 2011-2021 umorigu
// URL Shortener plugin
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
define('PLUGIN_S_ALWAYS_SHORT_URL_ON_PERCENT', FALSE);

function plugin_s_convert_get_short_link()
{
	$shorturl = plugin_s_inline_get_short_url();
	if ($shorturl === '')
	{
		return '';
	}
	$s = htmlspecialchars($shorturl);
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
	$url = plugin_s_get_short_url_from_page($page);
	return $url;
}

function plugin_s_get_short_url_from_page($page)
{
	if (! is_page($page)) {
		return '';
	}
	$shortid = plugin_s_get_page_id($page);
	if (!$shortid) {
		return get_page_uri($page, PKWK_URI_ABSOLUTE);
	}
	$utf8page = $page;
	if (! defined('PKWK_UTF8_ENABLE')) {
		$utf8page = mb_convert_encoding($page, 'UTF-8', 'CP51932');
	}
	$filename = PLUGIN_S_NAMES_DIR . '/' . $shortid . '.txt';
	if (!file_exists($filename)) {
		$fp = fopen($filename, 'w') or die('fopen() failed: ' . htmlsc($filename));
		set_file_buffer($fp, 0);
		flock($fp, LOCK_EX);
		rewind($fp);
		fputs($fp, $utf8page);
		flock($fp, LOCK_UN);
		fclose($fp);
	}
	$shorturl = get_base_uri(PKWK_URI_ABSOLUTE)
		. PLUGIN_S_VIRTUAL_QUERY_PREFIX . $shortid;
	return $shorturl;
}

function plugin_s_get_page_id($page)
{
	$utf8page = $page;
	if (! defined('PKWK_UTF8_ENABLE')) {
		$utf8page = mb_convert_encoding($page, 'UTF-8', 'CP51932');
	}
	$page_encoded = pagename_urlencode($utf8page);
	if (PLUGIN_S_PAGENAME_MININUM_LENGTH < strlen($page_encoded)
		|| (PLUGIN_S_ALWAYS_SHORT_URL_ON_PERCENT && strpos($page_encoded, '%') >= 0)) {
		$encoded = encode($utf8page);
		$md5 = md5($encoded);
		$shortid = substr($md5, 0, PLUGIN_S_PAGEID_LENGTH);
		return $shortid;
	}
	return null;
}

// Action-type plugin: ?plugin=s&k=key
function plugin_s_action()
{
	global $vars;
	$pageid = isset($vars['k']) ? $vars['k'] : '';
	$filename = PLUGIN_S_NAMES_DIR . '/' . $pageid . '.txt';
	$fp = fopen($filename, 'r') or die_message('Cannnot open ' . htmlsc($filename));
	$str = fgets($fp);
	$str2 = trim($str);
	fclose($fp);

	// increment counter
	$cfilename = PLUGIN_S_NAMES_COUNTER_DIR . '/' . $pageid . '.count';
	$fpc = fopen($cfilename, file_exists($cfilename) ? 'r+' : 'w+')
		or die_message('Cannot open: ' . htmlsc($cfilename));
	set_file_buffer($fpc, 0);
	flock($fpc, LOCK_EX);
	$shorter_count = intval(trim(fgets($fpc))) + 1;
	rewind($fpc);
	fputs($fpc, $shorter_count);
	flock($fpc, LOCK_UN);
	fclose($fpc);

	if (!defined('PKWK_UTF8_ENABLE')) {
		$str2 = mb_convert_encoding($str2, 'CP51932', 'UTF-8');
	}
	$url = get_page_uri($str2, PKWK_URI_ROOT);
	header("HTTP/1.1 302 Found");
	header("Location: $url");
	exit;
}
