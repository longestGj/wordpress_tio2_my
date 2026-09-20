<?php
defined('ABSPATH') || exit;
/** Verification-only identity computed from the files actually loaded, not approval records. */
function tio2_runtime_artifact(): string {
    $files=[];
    $roots=[
        'wp-content/plugins/tio2-content'=>dirname(__DIR__),
        'wp-content/themes/tio2-malaysia'=>get_template_directory(),
    ];
    foreach($roots as $prefix=>$root) {
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file) {
            if(!$file->isFile()) continue;
            $relative=$prefix.'/'.str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($root)+1));
            $files[$relative]=hash_file('sha256',$file->getPathname());
        }
    }
    ksort($files,SORT_STRING);
    $identity='';
    foreach($files as $path=>$hash) $identity.=$path."\0".$hash."\n";
    return 'wp-'.hash('sha256',$identity);
}

function tio2_normalize_release(string $release): string {
    return preg_match('/\A[0-9a-f]{40}\z/',$release)===1 ? $release : '';
}

function tio2_release_sha(): string {
    return tio2_normalize_release(defined('TIO2_RELEASE') ? (string)TIO2_RELEASE : '');
}

add_action('send_headers',static function() {
    if(!tio2_site_ready()) return;
    header('X-Site-Scope: '.(defined('TIO2_SITE_SCOPE') ? TIO2_SITE_SCOPE : 'tio2-my'));
    if($release=tio2_release_sha()) header('X-Tio2-Release: '.$release);
});
add_action('wp_head',static function() {
    if(wp_get_environment_type()!=='local') return;
    echo '<meta name="tio2-artifact" content="'.esc_attr(tio2_runtime_artifact()).'">'.PHP_EOL;
    echo '<meta name="tio2-content-sha256" content="'.hash('sha256',wp_json_encode(tio2_content(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'">'.PHP_EOL;
    if (function_exists('tio2_current_page_id') && tio2_current_page_id() === 'PRODUCT-000') {
        echo '<meta name="tio2-products-content-sha256" content="'.hash('sha256',wp_json_encode(tio2_products_content(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'">'.PHP_EOL;
    }
    if (function_exists('tio2_is_rfq_page') && tio2_is_rfq_page()) {
        echo '<meta name="tio2-rfq-content-sha256" content="'.hash('sha256',wp_json_encode(tio2_rfq_content(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'">'.PHP_EOL;
    }
},2);
