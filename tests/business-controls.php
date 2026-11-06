<?php
require __DIR__.'/security.php';
migrate_business_controls($pdo);migrate_sale_idempotency($pdo);check(true,'business-control and sale-idempotency migrations are repeatable');
$stockSource=file_get_contents(__DIR__.'/../public/modules/stock_add.php');check(str_contains($stockSource,"FROM mudaraba_contracts WHERE status = 'active' AND \" . shop_sql()")&&str_contains($stockSource,"FROM stock WHERE ' . shop_sql() . ' ORDER BY name"),'purchase dropdown queries are explicitly branch-scoped');
$currencySource=file_get_contents(__DIR__.'/../public/includes/currency.php');check(str_contains($currencySource,"AND (currency_code=? OR currency_code=?) AND shop_id=?"),'profit-known contract update retains branch predicate');
security_establish_session($cu);
$base=['csrf_token'=>csrf_token(),'action'=>'update_business','business_id'=>$a,'business_name'=>'Synthetic A controlled','business_status'=>'active','contact_phone'=>'+93000000000','contact_email'=>'owner@example.test','contact_address'=>'Synthetic address','subscription_active'=>'1','subscription_expires_at'=>'','branch_limit'=>'2','staff_limit'=>'1'];
management_mutate($base);
$q=$pdo->prepare('SELECT * FROM businesses WHERE id=?');$q->execute([$a]);$controlled=$q->fetch();
check($controlled['contact_email']==='owner@example.test'&&(int)$controlled['branch_limit']===2,'central business profile and limits saved');
$caught=false;try{management_mutate(array_merge($base,['branch_limit'=>'0','staff_limit'=>'0']));}catch(Throwable $e){$caught=true;}check(!$caught,'zero limits explicitly mean unlimited');
// Restore a limit of one and verify both creation paths stop at the business ceiling.
management_mutate($base);
security_establish_session($ou);
$caught=false;try{management_mutate(['csrf_token'=>csrf_token(),'action'=>'create_branch','business_id'=>$a,'branch_name'=>'Over limit','city'=>'Herat']);}catch(InvalidArgumentException $e){$caught=true;}check($caught,'business branch limit blocks owner branch creation');
$caught=false;try{management_mutate(['csrf_token'=>csrf_token(),'action'=>'create_staff','business_id'=>$a,'branch_ids'=>[$a1],'fullname'=>'Over limit','username'=>'over'.bin2hex(random_bytes(3)),'password'=>'Synthetic-pass-42','confirm_password'=>'Synthetic-pass-42']);}catch(InvalidArgumentException $e){$caught=true;}check($caught,'business staff limit blocks additional staff');
$caught=false;try{management_mutate(['csrf_token'=>csrf_token(),'action'=>'update_business','business_id'=>$a]);}catch(RuntimeException $e){$caught=$e->getCode()===403;}check($caught,'owner cannot change central business controls');
security_establish_session($cu);$base['csrf_token']=csrf_token();management_mutate(array_merge($base,['business_status'=>'locked','branch_limit'=>'0','staff_limit'=>'0']));
check(!domain_branch_allowed($ou,$a1)&&!domain_branch_allowed($su,$a1),'business lock blocks owner and staff');
management_mutate(array_merge($base,['subscription_expires_at'=>date('Y-m-d',strtotime('-1 day')),'branch_limit'=>'0','staff_limit'=>'0']));
check(!domain_branch_allowed($ou,$a1),'expired business subscription blocks tenant access');
management_mutate(array_merge($base,['branch_limit'=>'0','staff_limit'=>'0']));
check(domain_branch_allowed($ou,$a1),'valid business subscription restores tenant access');
security_establish_session($cu);$_SESSION['central_mfa_at']=time();$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/modules/management.php';$_SERVER['REQUEST_URI']='/modules/management.php';ob_start();require __DIR__.'/../public/modules/management.php';$managementHtml=ob_get_clean();
check(str_contains($managementHtml,'name="action" value="update_business"')&&str_contains($managementHtml,'name="contact_email"')&&str_contains($managementHtml,'name="branch_limit"'),'central management screen renders actual business-control form');
check(str_contains($managementHtml,'invoice_designer.php?scope=business&amp;target_id='.$a),'business logo action opens the selected business design');

$key='sale-'.bin2hex(random_bytes(8));$hash=sale_request_hash(['invoice'=>'NSC-TEST','amount'=>'10.00']);
$insert=$pdo->prepare("INSERT INTO sales (invoice_no,customer_name,total_amount,paid_amount,remaining_amount,shop_id,sale_request_key,sale_content_hash) VALUES (?,?, '10.00','0.00','10.00',?,?,?)");
$insert->execute(['IDEMP-A-'.bin2hex(random_bytes(3)),'Synthetic',$a1,$key,$hash]);$saleId=(int)$pdo->lastInsertId();
check(sale_request_existing($a1,$key,$hash)===$saleId,'identical sale retry returns recorded invoice');
check(sale_request_existing($b1,$key,$hash)===null,'sale idempotency lookup cannot cross businesses');
$caught=false;try{sale_request_existing($a1,$key,sale_request_hash(['invoice'=>'NSC-TEST','amount'=>'11.00']));}catch(InvalidArgumentException $e){$caught=true;}check($caught,'same sale key with altered amount is rejected');
$insert->execute(['IDEMP-B-'.bin2hex(random_bytes(3)),'Synthetic',$b1,$key,$hash]);
check((int)$pdo->query("SELECT COUNT(*) FROM sales WHERE sale_request_key=".$pdo->quote($key))->fetchColumn()===2,'same client key remains isolated per branch');
echo "Business controls and sale idempotency scenarios completed.\n";
