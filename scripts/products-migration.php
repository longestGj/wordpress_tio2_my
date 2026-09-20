<?php
// Local recovery utility for the PRODUCT-000 managed option and Page only.
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') throw new RuntimeException('Local CLI only');
$action = $args[0] ?? 'status';
if ($action === 'status') $result = tio2_products_migration_status();
elseif ($action === 'migrate') $result = tio2_products_migrate();
elseif ($action === 'rollback') $result = tio2_products_rollback();
elseif ($action === 'resume') $result = tio2_products_resume();
else throw new RuntimeException('Unknown Products migration action.');
echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
