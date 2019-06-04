<?php
// s.inc.php
//
// URL Shortener plugin
// License: The same as PukiWiki

// key-name map directory
define('PLUGIN_S_NAMES_DIR', 'shortener');

// shortener counter map directory
define('PLUGIN_S_NAMES_COUNTER_DIR', 'shortener_counter');

// key string length
define('PLUGIN_S_PAGEID_LENGTH', 10);

// command string "/?${PLUGIN_S_COMMAND_STR}91aa88d26e";  
define('PLUGIN_S_COMMAND_STR', 'cmd=s&k=');

// page name minimum length   
define('PLUGIN_S_PAGENAME_MININUM_LENGTH', 20);

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
	if (is_page($page) &&
		PLUGIN_S_PAGENAME_MININUM_LENGTH < strlen(plugin_s_pagename_urlencode($page)))
	{
		$utf8page = $page;
		if (! defined('PKWK_UTF8_ENABLE'))
		{
			$utf8page = mb_convert_encoding($page, 'UTF-8', 'CP51932');
		}
		$encoded = encode($utf8page);
		$md5 = md5($encoded);
		$shortid = substr($md5, 0, PLUGIN_S_PAGEID_LENGTH);
		$shorturl = get_script_uri() . '?' . PLUGIN_S_COMMAND_STR . $shortid;
		$filename = PLUGIN_S_NAMES_DIR . '/' . $shortid . '.txt';
		if (!file_exists($filename))
		{
			$fp = fopen($filename, 'w') or die('fopen() failed: ' . $filename);
			set_file_buffer($fp, 0);
			flock($fp, LOCK_EX);
			rewind($fp);
			fputs($fp, $utf8page);
			flock($fp, LOCK_UN);
			fclose($fp);
		}
		return $shorturl;
	}
	return '';
}

// Action-type plugin: ?plugin=s&k=key
function plugin_s_action()
{
	global $vars;
	$pageid = isset($vars['k']) ? $vars['k'] : '';
	$filename = PLUGIN_S_NAMES_DIR . '/' . $pageid . '.txt';
	$fp = fopen($filename, 'r') or die_message('Cannnot open ' . plugin_s_htmlsc($filename));
	$str = fgets($fp);
	$str2 = trim($str);
	fclose($fp);
	
	// increment counter
	$cfilename = PLUGIN_S_NAMES_COUNTER_DIR . '/' . $pageid . '.count';
	$fpc = fopen($cfilename, file_exists($cfilename) ? 'r+' : 'w+')
		or die_message('Cannot open: ' . plugin_s_htmlsc($cfilename));
	set_file_buffer($fpc, 0);
	flock($fpc, LOCK_EX);
	$shorter_count = trim(fgets($fpc));
	$shorter_count = intval($shorter_count) + 1;
	rewind($fpc);
	fputs($fpc, $shorter_count);
	flock($fpc, LOCK_UN);
	fclose($fpc);
	
	if (!defined('PKWK_UTF8_ENABLE'))
	{
		$str2 = mb_convert_encoding($str2, 'CP51932', 'UTF-8');
	}
	if (function_exists('get_page_uri') && defined('PKWK_URI_ROOT'))
	{
		$url = get_page_uri($str2, PKWK_URI_ROOT);
	}
	else
	{
		$url = get_script_uri() . '?' . plugin_s_pagename_urlencode($str2);
	}
	header("HTTP/1.1 302 Found");
	header("Location: $url");
	exit;
}

/**
 * Compatible htmlsc()
 */
function plugin_s_htmlsc($s)
{
	if (function_exists('htmlsc'))
	{
		return htmlsc($s);
	}
	return htmlspecialchars($s);
}

/**
 * Compatible pagename_urlencode()
 */
function plugin_s_pagename_urlencode($page)
{
	static $simple_url = null;
	if (is_null($simple_url))
	{
		$simple_url = function_exists('pagename_urlencode');
	}
	if ($simple_url)
	{
		return pagename_urlencode($page);
	}
	return rawurlencode($page);
}
