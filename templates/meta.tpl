<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>{title} | {sitename}</title>
{if isset($canonical)}<link rel="canonical" href="{$canonical}" />{elseif isset($content_obj)}<link rel="canonical" href="{$content_obj->GetURL()}" />{/if}
{if $css}{cms_stylesheet}{/if}
{metadata}
{cms_selflink dir="start" rellink=1}
{cms_selflink dir="prev" rellink=1}
{cms_selflink dir="next" rellink=1}
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">