<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../api/db.php';$db=Database::getConnection();$o=getopt('',['owner:','apply']);
if(empty($o['owner']))throw new RuntimeException('Specify --owner=<company owner id>. Run without --apply first.');
$s=$db->prepare('SELECT id FROM users WHERE id=:id AND owner_admin_id IS NULL');$s->execute(['id'=>$o['owner']]);if(!$s->fetch())throw new RuntimeException('Invalid company owner');
$tables=['company_profiles','fiscal_periods','projects','project_budgets','treasury_accounts','suppliers','subcontractors','clients','apartment_units','payment_vouchers','material_procurements','labor_payroll','revenue_entries','treasury_transactions','petty_cash_expenses','assets_machinery','project_transfers','journal_entries','journal_lines','steel_procurements','concrete_castings','project_partners','partner_investments','project_documents','system_audit_logs'];
$db->beginTransaction();try{foreach($tables as $t){$count=$db->query("SELECT COUNT(*) FROM `$t` WHERE tenant_id IS NULL")->fetchColumn();echo "$t: $count unassigned rows\n";if(isset($o['apply'])){$s=$db->prepare("UPDATE `$t` SET tenant_id=:tid WHERE tenant_id IS NULL");$s->execute(['tid'=>$o['owner']]);}}if(isset($o['apply']))$db->commit();else $db->rollBack();}catch(Throwable $e){$db->rollBack();throw $e;}
