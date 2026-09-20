<?php
// Local recovery utility for the CONV-RFQ managed option and Page only.
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') throw new RuntimeException('Local CLI only');
$action = $args[0] ?? 'status';
if ($action === 'status') $result = tio2_rfq_migration_status();
elseif ($action === 'migrate') $result = tio2_rfq_migrate();
elseif ($action === 'rollback') $result = tio2_rfq_rollback();
elseif ($action === 'resume') $result = tio2_rfq_resume();
else throw new RuntimeException('Unknown RFQ migration action.');
echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
