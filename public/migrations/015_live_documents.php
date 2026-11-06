<?php
require_once __DIR__.'/../includes/live_documents.php';
function migrate_live_documents(PDO $db): void {
 if($db->query("SELECT version FROM schema_migrations WHERE version='015_live_documents'")->fetchColumn())return;
 // Validate both filtered sources before marking this step complete. Existing views remain untouched.
 foreach(['expenses','capital'] as $entity)$db->query('SELECT 1 FROM '.live_documents_source($entity).' LIMIT 1')->closeCursor();
 $db->exec("INSERT INTO schema_migrations (version) VALUES ('015_live_documents')");
}
