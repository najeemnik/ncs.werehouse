<?php
// Isolated real HTTP tests against the disposable PHP/MariaDB test container.
const TEST_ORIGIN='http://127.0.0.1:8000';
define('TEST_DAY',(new DateTimeImmutable('now',new DateTimeZone('Asia/Kabul')))->format('Y-m-d')); 
class TestClient {
 public string $csrf='';public string $cookie='';
 public function call(string $endpoint,?array $data=null,string $method='GET',array $headers=[]):array {
  $h=['Content-Type: application/json','X-Requested-With: XMLHttpRequest','X-CSRF-Token: '.$this->csrf];if($this->cookie)$h[]='Cookie: '.$this->cookie;foreach($headers as $v)$h[]=$v;
  $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$h),'content'=>$data===null?'':json_encode($data),'ignore_errors'=>true,'timeout'=>10]]);
  $body=file_get_contents(TEST_ORIGIN.'/api/'.$endpoint,false,$ctx);$status=0;
  foreach($http_response_header??[] as $line){if(preg_match('/^HTTP\/\S+ (\d+)/',$line,$m))$status=(int)$m[1];if(preg_match('/^Set-Cookie: (nsc_session=[^;]+)/i',$line,$m))$this->cookie=$m[1];}
  return [$status,json_decode($body,true)];
 }
 public function login(string $username):void { $this->csrf=$this->call('auth.php')[1]['data']['csrf'];$r=$this->call('auth.php?action=login',['username'=>$username,'password'=>'Test-passphrase-2026!'],'POST');expect($r,200,'login '.$username);$this->csrf=$r[1]['data']['csrf']; }
 public function workspace():array {return $this->call('workspace.php')[1]['data'];}
 public function transition(string $collection,string $id,string $status,?int $revision=null):array{return $this->call('workspace.php',['collection'=>$collection,'id'=>$id,'status'=>$status,'revision'=>$revision??$this->workspace()['revision']],'POST');}
 public function save(array $doc):array {return $this->call('workspace.php',['revision'=>$this->workspace()['revision'],'document'=>$doc],'PUT');}
}
$count=0;function expect(array $response,int $status,string $name):void{global $count;if($response[0]!==$status)throw new RuntimeException($name.': expected '.$status.' got '.json_encode($response));$count++;echo 'PASS '.$name."\n";}
function check(bool $ok,string $name):void{global $count;if(!$ok)throw new RuntimeException($name);$count++;echo 'PASS '.$name."\n";}
$master=new TestClient();$master->login('master');$tag='approval-'.bin2hex(random_bytes(5));
$r=$master->call('users.php',['action'=>'create_company','name'=>'Approval QA Owner','username'=>$tag,'email'=>$tag.'@example.test','password'=>'Test-passphrase-2026!','companyName'=>'Disposable approval tests'],'POST');expect($r,200,'isolated company');
$owner=new TestClient();$owner->login($tag);$projects=[['id'=>'p','name'=>'Test project'],['id'=>'q','name'=>'Other project']];expect($owner->save(['projects'=>$projects]),200,'project setup');
function staff(TestClient $owner,string $name,array $modules,string $project='p'):TestClient {
 $r=$owner->call('users.php',['action'=>'create','name'=>$name,'username'=>$name,'email'=>$name.'@example.test','password'=>'Test-passphrase-2026!','role'=>'viewer','permissions'=>['projectGrants'=>[['projectId'=>$project,'modules'=>$modules]]]],'POST');expect($r,200,'create staff');$client=new TestClient();$client->login($name);return $client;
}
$editor=staff($owner,$tag.'-editor',['expenses'=>['view','create','edit','delete'],'payments'=>['view','create','edit']]);
$approver=staff($owner,$tag.'-approver',['expenses'=>['view','approve'],'payments'=>['view','approve']]);
$draft=['id'=>'bill','projectId'=>'p','date'=>TEST_DAY,'amount'=>100,'currency'=>'USD','exchangeRate'=>70,'paidAmount'=>0,'approvalStatus'=>'draft','approvalHistory'=>[]];
$doc=$editor->workspace()['document'];$doc['expenses']=[$draft];expect($editor->save($doc),200,'editor creates draft');
expect($editor->transition('expenses','bill','approved'),409,'cannot skip submission');
$doc=$owner->workspace()['document'];$doc['expenses'][0]['approvalStatus']='posted';expect($owner->save($doc),403,'snapshot cannot forge status even as owner');
$doc=$owner->workspace()['document'];$doc['expenses'][0]['approvalHistory']=[['actorId'=>'fake']];expect($owner->save($doc),403,'snapshot cannot forge history');
expect($editor->transition('expenses','bill','submitted'),200,'editor submits');
expect($editor->transition('expenses','bill','approved'),403,'editor has no approval permission');
expect($approver->transition('expenses','bill','approved'),200,'approver works without edit permission');
expect($editor->transition('expenses','bill','posted'),403,'editor cannot post');
$doc=$owner->workspace()['document'];$doc['expenses'][0]['amount']=200;expect($owner->save($doc),409,'approved amount is locked for owner');
expect($approver->transition('expenses','bill','draft'),200,'approver returns document for correction');
$doc=$editor->workspace()['document'];$doc['expenses'][0]['amount']=110;expect($editor->save($doc),200,'returned draft can be corrected');
expect($editor->transition('expenses','bill','submitted'),200,'corrected draft submits');expect($approver->transition('expenses','bill','approved'),200,'corrected draft approves');
$revision=$owner->workspace()['revision'];expect($approver->transition('expenses','bill','posted'),200,'approver posts');expect($owner->transition('expenses','bill','posted',$revision),409,'stale revision rejects repeated post');
$doc=$owner->workspace()['document'];$history=$doc['expenses'][0]['approvalHistory'];check(count($history)===6,'all six transitions logged');check($history[0]['actorName']===$tag.'-editor'&&$history[5]['actorName']===$tag.'-approver'&&isset($history[5]['at']),'server records authenticated actors and time');
$doc['expenses'][0]['amount']=999;expect($owner->save($doc),409,'posted amount immutable');$doc=$owner->workspace()['document'];$doc['expenses']=[];expect($owner->save($doc),409,'posted document cannot be deleted');
$doc=$owner->workspace()['document'];$draft['id']='other-project';$draft['projectId']='q';$doc['expenses'][]=$draft;expect($owner->save($doc),200,'second project draft');expect($approver->transition('expenses','other-project','submitted'),403,'project grants isolate approval');
// Settlement requires the related invoice to be posted and total must not exceed its bill.
$doc=$owner->workspace()['document'];$draft['id']='unposted-parent';$draft['projectId']='p';$doc['expenses'][]=$draft;$pay=['id'=>'cash','projectId'=>'p','date'=>TEST_DAY,'amount'=>50,'currency'=>'USD','exchangeRate'=>70,'relatedType'=>'expense','relatedId'=>'unposted-parent','approvalStatus'=>'draft','approvalHistory'=>[]];$doc['payments']=[$pay];expect($owner->save($doc),200,'payment draft setup');
expect($owner->transition('payments','cash','submitted'),200,'submit cash');expect($approver->transition('payments','cash','approved'),200,'approve cash');expect($approver->transition('payments','cash','posted'),409,'draft parent prevents cash posting');
foreach(['submitted','approved','posted'] as $status)expect($owner->transition('expenses','unposted-parent',$status),200,'parent '.$status);expect($approver->transition('payments','cash','posted'),200,'cash posts after invoice');
$doc=$owner->workspace()['document'];$pay['id']='overpay';$pay['amount']=60;$doc['payments'][]=$pay;expect($owner->save($doc),200,'overpayment draft');foreach(['submitted','approved'] as $status)expect($owner->transition('payments','overpay',$status),200,'overpayment '.$status);expect($owner->transition('payments','overpay','posted'),409,'posted settlements cannot exceed bill');
$doc=$owner->workspace()['document'];$withheld=$draft;$withheld['id']='withheld';$withheld['partyType']='contractor';$withheld['retentionPercentage']=5;$withheld['taxPercentage']=2;$doc['expenses'][]=$withheld;expect($owner->save($doc),200,'withholding bill draft');
foreach(['submitted','approved','posted'] as $status)expect($owner->transition('expenses','withheld',$status),200,'withheld bill '.$status);
foreach([['net','expense',93,200],['net-over','expense',0.01,409],['release','retention_release',5,200],['release-over','retention_release',0.01,409],['tax','tax_remittance',2,200],['tax-over','tax_remittance',0.01,409]] as [$id,$purpose,$amount,$expected]){
 $doc=$owner->workspace()['document'];$payment=$pay;$payment['id']=$id;$payment['amount']=$amount;$payment['relatedType']=$purpose;$payment['relatedId']='withheld';$doc['payments'][]=$payment;expect($owner->save($doc),200,$purpose.' draft '.$id);
 foreach(['submitted','approved'] as $status)expect($owner->transition('payments',$id,$status),200,$id.' '.$status);expect($owner->transition('payments',$id,'posted'),$expected,$id.' limit');
}
$doc=$owner->workspace()['document'];$bad=$draft;$bad['id']='bad-percent';$bad['partyType']='contractor';$bad['retentionPercentage']=80;$bad['taxPercentage']=30;$doc['expenses'][]=$bad;expect($owner->save($doc),400,'combined withholding above 100 rejected');
$doc=$owner->workspace()['document'];$bad=$draft;$bad['id']='missing-date';unset($bad['date']);$doc['expenses'][]=$bad;expect($owner->save($doc),200,'incomplete draft allowed');expect($owner->transition('expenses','missing-date','submitted'),400,'undated financial document cannot submit');
$doc=$owner->workspace()['document'];$doc['apartments']=[['id'=>'sale-closed','projectId'=>'p','salePrice'=>100,'status'=>'sold','contractDate'=>'2026-10-12','currency'=>'USD','approvalStatus'=>'draft','approvalHistory'=>[]]];expect($owner->save($doc),200,'sale draft before closing');
$doc=$owner->workspace()['document'];$doc['appSettings']['closedThrough']=TEST_DAY;expect($owner->save($doc),200,'close period');expect($owner->transition('expenses','other-project','submitted'),409,'closed period rejects workflow change');expect($owner->transition('apartments','sale-closed','submitted'),409,'closed period protects apartment sale date');
$w=$owner->workspace();expect($owner->call('workspace.php',['collection'=>'expenses','id'=>'other-project','status'=>'submitted','revision'=>$w['revision']],'POST',['X-CSRF-Token: invalid']),403,'approval action requires CSRF');
$other=new TestClient();$other->login('owner-b');expect($other->transition('expenses','bill','approved'),404,'other tenant cannot approve document');
echo 'ALL '.$count." APPROVAL HTTP CHECKS PASSED\n";
