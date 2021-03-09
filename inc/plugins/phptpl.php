<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');


// the following is a bit of a compatibility fix at the cost of some performance; enabled by default
// set to 0 to disable
define('PHPTPL_TEMPLATE_CACHE_CHECK', 1);

$plugins->add_hook('global_start', 'phptpl_run');
$plugins->add_hook('xmlhttp', 'phptpl_run');

/*
 *  Known issue: in PHP evaluation, "?>" may not match properly if used in strings
 */

function phptpl_info()
{
	return array(
		'name'			=> 'PHP and Template Conditionals',
		'description'	=> 'Allows you to use conditionals and PHP code in templates.',
		'website'		=> 'http://mybbhacks.zingaburga.com/',
		'author'		=> 'ZiNgA BuRgA',
		'authorsite'	=> 'http://zingaburga.com/',
		'version'		=> '2.2',
		'compatibility'	=> '1*',
		'guid'			=> ''
	);
}

function phptpl_run() {
	global $templates;
	if(!defined('IN_ADMINCP') && is_object($templates))
	{
		if(PHPTPL_TEMPLATE_CACHE_CHECK) {
			$code = '
				$r = parent::get($title, $eslashes, $htmlcomments);
				if(!isset($this->parsed_cache[$title]) || $this->parsed_cache[$title][0] != $r)
				{
					$this->parsed_cache[$title] = array($r, $r);
					phptpl_parsetpl($this->parsed_cache[$title][1]);
				}
				return $this->parsed_cache[$title][1];
			';
		} else {
			$code = '
				if(!isset($this->parsed_cache[$title]))
				{
					$this->parsed_cache[$title] = parent::get($title, $eslashes, $htmlcomments);
					phptpl_parsetpl($this->parsed_cache[$title]);
				}
				return $this->parsed_cache[$title];
			';
		}
		// gain control of $templates object
		eval('
			class phptpl_templates extends '.get_class($templates).'
			{
				function phptpl_templates(&$oldtpl)
				{
					foreach(get_object_vars($oldtpl) as $var => $val)
						$this->$var = $val;
					
					$this->parsed_cache = array();
				}
				function get($title, $eslashes=1, $htmlcomments=1)
				{
					// $htmlcomments unnecessary - we\'ll now simply ignore it
					if($eslashes) {'.$code.'}
					else
						return parent::get($title, $eslashes, $htmlcomments);
				}
			}
		');
		$templates = new phptpl_templates($templates);
	}
}


if(function_exists('preg_replace_callback_array')) {
	// PHP >= 7
	// note, this will break parsers from PHP < 5.3
	function phptpl_parsetpl(&$ourtpl)
	{
		$GLOBALS['__phptpl_if'] = array();
		$ourtpl = preg_replace_callback_array(array(
			'#\<template\s+([a-z0-9_ \-+!(),.]+)(\s*/)?\>#i' => function($m) {
				return phptpl_templates_get($m[1]);
			},
			'#\<((?:else)?if\s+(.*?)\s+then|else\s*/?|/if)\>#si' => function($m) {
				return phptpl_if($m[1], phptpl_unescape_string($m[2]));
			},
			'#\<func (htmlspecialchars|htmlspecialchars_uni|intval|floatval|urlencode|rawurlencode|addslashes|stripslashes|trim|crc32|ltrim|rtrim|chop|md5|nl2br|sha1|strrev|strtoupper|strtolower|my_strtoupper|my_strtolower|alt_trow|get_friendly_size|filesize|strlen|my_strlen|my_wordwrap|random_str|unicode_chr|bin2hex|str_rot13|str_shuffle|strip_tags|ucfirst|ucwords|basename|dirname|unhtmlentities)\>#i' => function($m) {
				return '".'.$m[1].'("';
			},
			'#\</func\>#i' => function() {
				return '")."';
			},
			'#\<\?=(.*?)\?\>#s' => function($m) {
				return '".strval('.phptpl_unescape_string($m[1]).')."';
			},
			'#\<setvar\s+([a-z0-9_\-+!(),.]+)\>(.*?)\</setvar\>#i' => function($m) {
				return '".(($GLOBALS["tplvars"][\''.$m[1].'\'] = ('.phptpl_unescape_string($m[2]).'))?"":"")."';
			},
			'#\<\?(?:php|\s).+?(\?\>)#s' => function($m) {
				return phptpl_evalphp(phptpl_unescape_string($m[0]), $m[1], false);
			}
		), $ourtpl);
	}
	// unescapes the slashes added by $templates->get()
	function phptpl_unescape_string($str)
	{
		return strtr($str, array('\\"' => '"', '\\\\' => '\\'));
	}
} else {
	function phptpl_parsetpl(&$ourtpl)
	{
		$GLOBALS['__phptpl_if'] = array();
		$ourtpl = preg_replace(array(
			'#\<template\s+([a-z0-9_ \-+!(),.]+)(\s*/)?\>#ie',
			'#\<((?:else)?if\s+(.*?)\s+then|else\s*/?|/if)\>#sie', // note that this relies on preg_replace working in a forward order
			'#\<func (htmlspecialchars|htmlspecialchars_uni|intval|floatval|urlencode|rawurlencode|addslashes|stripslashes|trim|crc32|ltrim|rtrim|chop|md5|nl2br|sha1|strrev|strtoupper|strtolower|my_strtoupper|my_strtolower|alt_trow|get_friendly_size|filesize|strlen|my_strlen|my_wordwrap|random_str|unicode_chr|bin2hex|str_rot13|str_shuffle|strip_tags|ucfirst|ucwords|basename|dirname|unhtmlentities)\>#i',
			'#\</func\>#i',
			'#\<\?=(.*?)\?\>#se',
			'#\<setvar\s+([a-z0-9_\-+!(),.]+)\>(.*?)\</setvar\>#ie',
			'#\<\?(?:php|\s).+?(\?\>)#se', // '#\<\?.*?(\?\>|$)#se',
		), array(
			'phptpl_templates_get(\'$1\')',
			'phptpl_if(\'$1\', phptpl_unescape_string(\'$2\'))',
			'".$1("',
			'")."',
			'\'".strval(\'.phptpl_unescape_string(\'$1\').\')."\'',
			'\'".(($GLOBALS["tplvars"]["$1"] = \'.phptpl_unescape_string(\'$2\').\')?"":"")."\'',
			'phptpl_evalphp(phptpl_unescape_string(\'$0\'), \'$1\')',
		), $ourtpl);
	}
	// unescapes the slashes added by $templates->get(), plus addslashes() during preg_replace()
	function phptpl_unescape_string($str)
	{
		return strtr($str, array('\\\\"' => '"', '\\\\' => '\\'));
	}
}

function phptpl_if($s, $e)
{
	if($s[0] == '/') {
		// end if tag
		$last = array_pop($GLOBALS['__phptpl_if']);
		$suf = str_repeat(')', (int)substr($last, 1));
		if($last[0] == 'i')
			$suf = ':""'.$suf;
		return '"'.$suf.')."';
	} else {
		$s = strtolower(substr($s, 0, strpos($s, ' ')));
		if($s == 'if') {
			$GLOBALS['__phptpl_if'][] = 'i0';
			return '".(('.$e.')?"';
		} elseif($s == 'elseif') {
			$last = array_pop($GLOBALS['__phptpl_if']);
			$last = 'i'.((int)substr($last, 1) + 1);
			$GLOBALS['__phptpl_if'][] = $last;
			return '":(('.$e.')?"';
		} else {
			$last = array_pop($GLOBALS['__phptpl_if']);
			$last[0] = 'e';
			$GLOBALS['__phptpl_if'][] = $last;
			return '":"';
		}
	}
}


function phptpl_evalphp($str, $end)
{
	return '".eval(\'ob_start(); ?>'
		.strtr($str, array('\'' => '\\\'', '\\' => '\\\\'))
		.($end?'':'?>').'<?php return ob_get_clean();\')."';
}

// compatibility functions with Template Conditionals plugin
function phptpl_eval_expr($__s)
{
	return eval('return ('.$__s.');');
}

function phptpl_eval_text($__s)
{
	// simulate $templates->get()
	$__s = strtr($__s, array('\\' => '\\\\', '"' => '\\"', "\0" => ''));
	phptpl_parsetpl($__s);
	return eval('return "'.$__s.'";');
}

// like $templates->get(), but doesn't evaluate - used for <template> tags
function phptpl_templates_get($title)
{
	global $templates, $db, $theme, $mybb;
	if(!isset($templates->cache[$title]))
	{
		// Only load master and global templates if template is needed in Admin CP
		if(empty($theme['templateset']))
		{
			$query = $db->simple_select("templates", "template", "title='".$db->escape_string($title)."' AND sid IN ('-2','-1')", array('order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1));
		}
		else
		{
			$query = $db->simple_select("templates", "template", "title='".$db->escape_string($title)."' AND sid IN ('-2','-1','".$theme['templateset']."')", array('order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1));
		}

		$gettemplate = $db->fetch_array($query);
		if($mybb->debug_mode)
			$templates->uncached_templates[$title] = $title;

		if(!$gettemplate)
			$gettemplate['template'] = "";

		$templates->cache[$title] = $gettemplate['template'];
	}
	$template = $templates->cache[$title];
	
	// replace nested <template> tags
	$template = preg_replace_callback('#\<template\s+([a-z0-9_ \-+!(),.]+)(\s*/)?\>#i', function($m) {
		return phptpl_templates_get($m[1]);
	}, $template);
	
	$template = strtr($template, array('\\' => '\\\\', '"' => '\\"', "\0" => ''));
	if($mybb->settings['tplhtmlcomments'] == 1) {
		$title = htmlspecialchars_uni($title);
		return "<!-- start: $title -->\n$template\n<!-- end: $title -->";
	} else
		return "\n$template\n";
}

?>
