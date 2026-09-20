<?php
defined('ABSPATH') || exit;

function tio2_normalize_public_url(string $candidate): string {
    $fallback='https://tio2products.com/';
    $parts=wp_parse_url(trim($candidate));
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? ''))!=='https'
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['port'])
        || isset($parts['query'])
        || isset($parts['fragment'])
        || !in_array($parts['path'] ?? '', ['', '/'], true)
    ) return $fallback;
    return 'https://'.strtolower($parts['host']).'/';
}

function tio2_public_base_url(): string {
    $configured=defined('TIO2_PUBLIC_URL') ? (string)TIO2_PUBLIC_URL : (string)getenv('TIO2_PUBLIC_URL');
    return tio2_normalize_public_url($configured);
}

add_action('wp_head',static function() {
    $home=is_front_page();
    $rfq=function_exists('tio2_is_rfq_page') && tio2_is_rfq_page();
    $title=$home?tio2_field('seo.title'):($rfq?tio2_rfq_field('seo.title'):'Page not found | TiO₂ Malaysia');
    $indexable=$home && wp_get_environment_type()==='production' && (bool)get_option('blog_public');
    echo '<title>'.esc_html($title)."</title>\n";
    echo '<meta name="robots" content="'.($indexable?'index, follow':'noindex, nofollow').'">'.PHP_EOL;
    echo '<link rel="icon" type="image/svg+xml" href="'.esc_url(get_template_directory_uri().'/assets/brand/favicon.svg').'">'.PHP_EOL;
    if (!$home && !$rfq) return;
    $base=tio2_public_base_url();
    $canonical=$rfq?$base.'request-a-quote/':$base;
    $description=$rfq?tio2_rfq_field('seo.description'):tio2_field('seo.description');
    $share_title=$rfq?tio2_rfq_field('seo.title'):tio2_field('seo.share-title');
    $share_description=$rfq?$description:tio2_field('seo.share-description');
    echo '<meta name="description" content="'.esc_attr($description).'">'.PHP_EOL;
    echo '<link rel="canonical" href="'.esc_url($canonical).'">'.PHP_EOL;
    foreach(['og:type'=>'website','og:url'=>$canonical,'og:site_name'=>'TiO₂ Malaysia','og:title'=>$share_title,'og:description'=>$share_description] as $property=>$value) echo '<meta property="'.esc_attr($property).'" content="'.esc_attr($value).'">'.PHP_EOL;
    $ref=static fn($name)=>['@id'=>$base.'#'.$name];
    if ($rfq) {
        $breadcrumb_id=$canonical.'#breadcrumb';
        $graph=[
            ['@type'=>'WebPage','@id'=>$canonical.'#webpage','url'=>$canonical,'name'=>tio2_rfq_field('seo.title'),'description'=>$description,'isPartOf'=>$ref('website'),'breadcrumb'=>['@id'=>$breadcrumb_id],'inLanguage'=>'en'],
            ['@type'=>'BreadcrumbList','@id'=>$breadcrumb_id,'itemListElement'=>[
                ['@type'=>'ListItem','position'=>1,'name'=>tio2_rfq_field('breadcrumb.home-label'),'item'=>$base],
                ['@type'=>'ListItem','position'=>2,'name'=>tio2_rfq_field('breadcrumb.current-label'),'item'=>$canonical],
            ]],
        ];
        echo '<script type="application/ld+json">'.wp_json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."</script>\n";
        return;
    }
    $graph=[
        ['@type'=>'WebSite','@id'=>$base.'#website','url'=>$base,'name'=>'TiO₂ Malaysia','inLanguage'=>'en','publisher'=>$ref('organization')],
        ['@type'=>'WebPage','@id'=>$base.'#webpage','url'=>$base,'name'=>tio2_field('hero.heading.1'),'isPartOf'=>$ref('website'),'about'=>[$ref('brand'),$ref('organization'),$ref('titanium-dioxide')],'inLanguage'=>'en'],
        ['@type'=>'Organization','@id'=>$base.'#organization','name'=>tio2_field('company.card-title.1')],
        ['@type'=>'Brand','@id'=>$base.'#brand','name'=>'TiO₂ Malaysia'],
        ['@type'=>'Product','@id'=>$base.'#titanium-dioxide','name'=>'Titanium Dioxide','description'=>'Fourteen titanium dioxide grades organised into four product groups.','brand'=>$ref('brand'),'manufacturer'=>$ref('organization')],
    ];
    echo '<script type="application/ld+json">'.wp_json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."</script>\n";
},1);
