<?php
if(getenv('NIKXOR_TEST_MODE')!=='1')throw new RuntimeException('Tests require an isolated test environment.');
if(!getenv('NIKXOR_RUNTIME_DIR'))putenv('NIKXOR_RUNTIME_DIR=/data/runtime-test-'.substr(hash('sha256',(getenv('DB_NAME')?:'').'|'.(getenv('NIKXOR_SQLITE_PATH')?:'')),0,12));
$_SERVER['DOCUMENT_ROOT']=is_dir('/app/public')?'/app/public':dirname(__DIR__).'/public';$_SERVER['SCRIPT_NAME']='/tests.php';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/tests.php';
require __DIR__.'/../public/includes/management_service.php';
function check($ok,$label){if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";}
