<?php
if(getenv('NIKXOR_EXPECT_NO_CREATE_VIEW')!=='1')throw new RuntimeException('Requires an isolated account denied CREATE VIEW.');
require __DIR__.'/security.php';security_establish_session($ou);
check($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql','restricted hosting regression uses real PDO MySQL');
$denied=false;try{$pdo->exec('CREATE VIEW nsc_restricted_permission_probe AS SELECT 1 AS value');$pdo->exec('DROP VIEW nsc_restricted_permission_probe');}catch(PDOException $e){$denied=($e->errorInfo[1]??0)===1142;}
check($denied,'database account actually denies CREATE VIEW');
$q=$pdo->query("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('expenses_live','capital_live')");check((int)$q->fetchColumn()===0,'reports run with both legacy views absent');
foreach(['015_live_documents','016_partner_reviews','017_purchase_openings','018_scoped_indexes','019_currency_collations','020_invoice_design','021_invoice_verification'] as $version){$q=$pdo->prepare('SELECT 1 FROM schema_migrations WHERE version=?');$q->execute([$version]);check((bool)$q->fetchColumn(),'restricted hosting completes '.$version);}
foreach(['expenses','capital'] as $entity){$q=$pdo->prepare('SELECT * FROM '.live_documents_source($entity,'d').' WHERE d.shop_id=? LIMIT 1');$q->execute([shop_id()]);check(is_array($q->fetchAll()),'filtered '.$entity.' source supports scoped aliased queries');}
check(is_array(currency_account_summary()),'currency summaries operate without database views');
$caught=false;try{live_documents_source('users');}catch(InvalidArgumentException $e){$caught=true;}check($caught,'live document entity allowlist rejects unrelated tables');
$caught=false;try{live_documents_source('expenses','x; DROP TABLE users');}catch(InvalidArgumentException $e){$caught=true;}check($caught,'live document aliases reject SQL injection');
echo "Restricted hosting scenarios completed.\n";
