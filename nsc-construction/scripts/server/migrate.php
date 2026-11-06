<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../api/db.php';
define('NSC_INTERNAL_MIGRATION',true);require_once __DIR__.'/../../api/schema-migration.php';
nscMigrateDatabase(Database::getConnection());
echo "Migration completed. Existing rows without an assigned tenant remain inaccessible.\n";
