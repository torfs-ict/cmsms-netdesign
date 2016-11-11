<?php

/**
 * CMSMS event handler - Core/SmartyPreCompile.
 *
 * For our {lang} Smarty block function we depend on sprintf for string interpolation.
 * Seeing that PhpStorm gives us an error when we put a modifier on the ending tag we put
 * the sprintf modifier on our starting tag in our code, and have it altered here to the correct format.
 *
 * E.g.
 * <p>{lang|sprintf:$fullName}Welcome, %s!{/lang}</p>
 * in our code would become
 * <p>{lang}Welcome, %s!{/lang|sprintf:$fullName}</p>
 * after running this filter.
 */

if (!isset($gCms)) exit;

/** @var NetDesign $this */
/** @var string $originator */
/** @var string $eventname */
/** @var array $params */

$params['content'] = preg_replace('/({lang(|.*)})(.*)({\/lang})/', '{lang}$3{/lang$2}', $params['content']);