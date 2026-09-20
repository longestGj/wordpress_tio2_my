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

function tio2_indexing_authorized(): bool {
    return wp_get_environment_type() === 'production'
        && (bool) get_option('blog_public')
        && defined('TIO2_INDEXING_AUTHORIZED')
        && TIO2_INDEXING_AUTHORIZED === true;
}

add_action('wp_head',static function() {
    $base = tio2_public_base_url();
    $home = is_front_page();
    $products = function_exists('tio2_current_page_id') && tio2_current_page_id() === 'PRODUCT-000';
    $title = $home ? tio2_field('seo.title') : ($products ? tio2_products_field('seo.title') : 'Page not found | TiO₂ Malaysia');
    $indexable = ($home || $products) && tio2_indexing_authorized();
    echo '<title>'.esc_html($title)."</title>\n";
    echo '<meta name="robots" content="'.($indexable?'index, follow':'noindex, nofollow').'">'.PHP_EOL;
    echo '<link rel="icon" type="image/svg+xml" href="'.esc_url(get_template_directory_uri().'/assets/brand/favicon.svg').'">'.PHP_EOL;
    if (!$home && !$products) return;

    $description = $home ? tio2_field('seo.description') : tio2_products_field('seo.description');
    $share_title = $home ? tio2_field('seo.share-title') : tio2_products_field('seo.share-title');
    $share_description = $home ? tio2_field('seo.share-description') : $description;
    $canonical = $home ? $base : $base . 'products/';
    echo '<meta name="description" content="'.esc_attr($description).'">'.PHP_EOL;
    echo '<link rel="canonical" href="'.esc_url($canonical).'">'.PHP_EOL;
    foreach(['og:type'=>'website','og:url'=>$canonical,'og:site_name'=>'TiO₂ Malaysia','og:title'=>$share_title,'og:description'=>$share_description] as $property=>$value) {
        echo '<meta property="'.esc_attr($property).'" content="'.esc_attr($value).'">'.PHP_EOL;
    }

    $ref = static fn($name) => ['@id' => $base . '#' . $name];
    if ($home) {
        $graph = [
            ['@type'=>'WebSite','@id'=>$base.'#website','url'=>$base,'name'=>'TiO₂ Malaysia','inLanguage'=>'en','publisher'=>$ref('organization')],
            ['@type'=>'WebPage','@id'=>$base.'#webpage','url'=>$base,'name'=>tio2_field('hero.heading.1'),'isPartOf'=>$ref('website'),'about'=>[$ref('brand'),$ref('organization'),$ref('titanium-dioxide')],'inLanguage'=>'en'],
            ['@type'=>'Organization','@id'=>$base.'#organization','name'=>tio2_field('company.card-title.1')],
            ['@type'=>'Brand','@id'=>$base.'#brand','name'=>'TiO₂ Malaysia'],
            ['@type'=>'Product','@id'=>$base.'#titanium-dioxide','name'=>'Titanium Dioxide','description'=>'Fourteen titanium dioxide grades organised into four product groups.','brand'=>$ref('brand'),'manufacturer'=>$ref('organization')],
        ];
    } else {
        $content = tio2_products_content();
        $fields = $content['fields'];
        $model = tio2_products_model($content);
        $categories = [];
        foreach ($model['grade_groups'] as $category => $grades) {
            foreach ($grades as $grade) $categories[$grade['route_key']] = $category;
        }
        $items = [];
        foreach ($model['grades'] as $offset => $grade) {
            $item = [
                '@type' => 'Product',
                'name' => $grade['name'],
                'description' => $grade['summary'],
                'category' => $categories[$grade['route_key']],
                'brand' => $ref('brand'),
                'manufacturer' => $ref('organization'),
            ];
            $route = tio2_resolve_route($grade['route_key']);
            if ($route) {
                $item['@id'] = $route['url'] . '#product';
                $item['url'] = $route['url'];
            }
            $items[] = ['@type' => 'ListItem', 'position' => $offset + 1, 'item' => $item];
        }
        $faq = array_map(static fn($entry) => [
            '@type' => 'Question',
            'name' => $entry['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $entry['answer']],
        ], $model['faqs']);
        $graph = [
            ['@type'=>'WebSite','@id'=>$base.'#website','url'=>$base,'name'=>'TiO₂ Malaysia','inLanguage'=>'en','publisher'=>$ref('organization')],
            ['@type'=>'Organization','@id'=>$base.'#organization','name'=>tio2_field('company.card-title.1')],
            ['@type'=>'Brand','@id'=>$base.'#brand','name'=>'TiO₂ Malaysia'],
            ['@type'=>'CollectionPage','@id'=>$canonical.'#webpage','url'=>$canonical,'name'=>$fields['hero.heading'],'description'=>$description,'isPartOf'=>$ref('website'),'inLanguage'=>'en','mainEntity'=>['@id'=>$canonical.'#grade-list']],
            ['@type'=>'BreadcrumbList','@id'=>$canonical.'#breadcrumb','itemListElement'=>[
                ['@type'=>'ListItem','position'=>1,'name'=>$fields['breadcrumb.home-label'],'item'=>$base],
                ['@type'=>'ListItem','position'=>2,'name'=>$fields['breadcrumb.current-label'],'item'=>$canonical],
            ]],
            ['@type'=>'ItemList','@id'=>$canonical.'#grade-list','name'=>$fields['directory.heading'],'numberOfItems'=>14,'itemListOrder'=>'https://schema.org/ItemListOrderAscending','itemListElement'=>$items],
            ['@type'=>'FAQPage','@id'=>$canonical.'#faq','mainEntity'=>$faq],
        ];
    }
    echo '<script type="application/ld+json">'.wp_json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."</script>\n";
},1);
