# phptpl

PHP in Templates / Complex Templates

#### Information

- Plugin Version: 2.2
- Last Updated: 03-01-2021, 08:29 PM

This is a relatively small plugin, but seems that a number of people wanted such a thing, so... here it is.

This plugin will allow you to use:
PHP in templates, using <?php ... ?> tags
Shortcut template conditionals, using <if ... then>...<elseif ... then>...<else>...</if>
Some shortcut string functions (see below), eg <func htmlspecialchars>...</func>
Call another template, using <template ...>, eg <template header>

For those that do not wish to allow arbitrary PHP code execution that this plugin offers, take a look at the Template Conditionals plugin.

Here's an example of some of the functions that this can be used for - for example, you may use this code in your postbit:

```html
{$post['user_details']}

<if $post['fid5'] then>
Your game tag is <func htmlspecialchars_uni>{$post['fid5']}</func>
<elseif $post['fid6'] and $mybb->user['cancp'] then>
This user's lucky number is <func intval>{$post['fid6']}</func>
<else />Some other profile field: {$post['fid7']}</if>

<?php echo "Hi from PHP"; ?>
```

Some notes:

* PHP tags must be closed with ?>
* PHP runs slower than conditionals, so unless you need to use PHP, I recommend the conditionals
* PHP inside the <?php ... ?> tags must be well formed, ie the following will not work:

```php
<?php if(true) { ?> some stuff <?php } ?>
```

I'm thinking about whether to add support for the above, but there are performance reasons for me choosing not to.

* The template insertion function is really basic - it performs no caching, so you should not call a lot of templates this way (there really isn't any nice way to get around this) however should be fine for small things.  Also, as it is basic, ensure that you don't stuff it up with recursive calls, that is, it's possible to try to get a template to call itself, but that will obviously cause MyBB to stuff up
* As of v1.5, I've included the ability to perform additional cache checks, and enabled this option by default.  If you wish to disable this feature, it can be done by modifying a define in the .php file, but this is not necessary for most people (see post below this one for more info).
* As of v2.1, PHP 5.3 or later is required

The functions available, with the shortcut, are:
htmlspecialchars, htmlspecialchars_uni, intval, file_get_contents, floatval, urlencode, rawurlencode, addslashes, stripslashes, trim, crc32, ltrim, rtrim, chop, md5, nl2br, strrev, strtoupper, strtolower, my_strtoupper, my_strtolower, alt_trow, get_friendly_size, filesize, strlen, my_strlen, my_wordwrap, random_str, unicode_chr, bin2hex, str_rot13, str_shuffle, strip_tags, ucfirst, ucwords, basename, dirname, unhtmlentities
