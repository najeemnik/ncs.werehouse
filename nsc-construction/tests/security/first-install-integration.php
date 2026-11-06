<?php
// Run only against the local, isolated installer server; private credentials arrive on stdin.
$secrets=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
$base='http://127.0.0.1:8002';$checks=0;$cookie=tempnam('/tmp','nsc-first-install-');
function checkInstall(bool $condition,string $label):void{global $checks;if(!$condition)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function installerRequest(string $url,?array $fields=null,?array $json=null,array $headers=[]):array{
 global $cookie;$c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>30]);
 if($fields!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields)]);
 if($json!==null){curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($json)]);$headers[]='Content-Type: application/json';$headers[]='X-Requested-With: XMLHttpRequest';}
 curl_setopt($c,CURLOPT_HTTPHEADER,$headers);$body=curl_exec($c);if($body===false)throw new RuntimeException('Local test request failed');$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return [$status,$body];
}
function setupCsrf(string $html):string{if(!preg_match('/name="csrf" value="([a-f0-9]{64})"/',$html,$m))throw new RuntimeException('Setup token missing');return $m[1];}
try{
 [$status,$body]=installerRequest('http://127.0.0.1:8003/setup/');checkInstall($status===426,'Production installer requires HTTPS');
 foreach(['private-settings.php','schema-migration.php','schema-sql.php','first-install-credentials.php'] as $file){[$status,$body]=installerRequest($base.'/api/'.$file);checkInstall($status===404&&$body==='','Internal bootstrap PHP is not an HTTP endpoint: '.$file);}
 [$status,$html]=installerRequest($base.'/setup/');checkInstall($status===200&&str_contains($html,'name="key"'),'Private key required before database form');$csrf=setupCsrf($html);
 [$status]=installerRequest($base.'/setup/',['csrf'=>'bad','key'=>$secrets['setupKey']]);checkInstall($status===403,'CSRF rejects unauthenticated bootstrap');
 [$status,$body]=installerRequest($base.'/setup/',['csrf'=>$csrf,'key'=>str_repeat('0',64)]);checkInstall($status===403&&!str_contains($body,'name="dbPassword"'),'Wrong key cannot reach installation');
 [$status]=installerRequest($base.'/setup/',['csrf'=>$csrf,'key'=>$secrets['setupKey']],null,['Origin: https://evil.invalid']);checkInstall($status===403,'Cross-origin setup blocked');
 [$status]=installerRequest($base.'/setup/',['csrf'=>$csrf,'key'=>$secrets['setupKey']]);checkInstall($status===303,'Correct key authorizes session without exposing it in a URL');
 [$status,$html]=installerRequest($base.'/setup/');$csrf=setupCsrf($html);checkInstall(str_contains($html,'name="dbPassword"'),'Authorized installer shows connection form');
 $fields=['csrf'=>$csrf,'dbHost'=>getenv('DB_HOST'),'dbUser'=>getenv('DB_USER'),'dbPassword'=>getenv('DB_PASS'),'dbName'=>'nsc_install_occupied_20261014'];
 [$status]=installerRequest($base.'/setup/',$fields);checkInstall($status===400&&!is_file('/tmp/nsc-install-qa-20261014.php'),'Existing database rejected without changing configuration');
 $fields['dbName']='nsc_install_qa_20261014';[$status,$html]=installerRequest($base.'/setup/',$fields);checkInstall($status===200&&str_contains($html,'Primary account created'),'Fresh database migrations and primary account complete');
 checkInstall(!str_contains($html,$secrets['password'])&&!str_contains($html,$secrets['setupKey'])&&!str_contains($html,$fields['dbPassword']),'Success page never prints passwords or setup key');
 [$status]=installerRequest($base.'/setup/');checkInstall($status===410,'Successful setup locks permanently');
 [$status]=installerRequest($base.'/setup/',$fields);checkInstall($status===410,'Repeated setup cannot replace or reset account');
 [$status,$body]=installerRequest($base.'/api/auth.php');$session=json_decode($body,true);checkInstall($status===200&&isset($session['data']['csrf']),'Runtime reads private configuration outside web root');
 [$status,$body]=installerRequest($base.'/api/auth.php',null,['username'=>$secrets['username'],'password'=>$secrets['password']],['X-CSRF-Token: '.$session['data']['csrf']]);$login=json_decode($body,true);checkInstall($status===200&&($login['data']['user']['isMasterSuperAdmin']??false),'Supplied primary credentials authenticate');
 $token=$login['data']['csrf'];checkInstall(preg_match('~^#/office7/[a-f0-9]{64}$~',$login['data']['user']['office7Link']??'')===1,'Primary account receives private Office Seven link');
 [$status,$body]=installerRequest($base.'/api/office7.php');$users=json_decode($body,true);checkInstall($status===200&&count($users['data']['users']??[])===1,'Primary user can access Office Seven');
 [$status,$body]=installerRequest($base.'/api/users.php',null,['action'=>'create_company','name'=>'QA Owner','username'=>'qa-first-owner','email'=>'first-owner@example.invalid','password'=>'QA-owner-strong-passphrase!','companyName'=>'QA Construction'],['X-CSRF-Token: '.$token]);$owner=json_decode($body,true);checkInstall($status===200&&($owner['data']['user']['role']??'')==='admin','Office Seven creates a company owner');
 $db=new PDO('mysql:host='.getenv('DB_HOST').';dbname=nsc_install_qa_20261014;charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 checkInstall((int)$db->query('SELECT COUNT(*) FROM users WHERE is_master=1')->fetchColumn()===1,'Exactly one master remains after repeated installation');
 checkInstall((float)$db->query('SELECT SUM(ABS(current_balance)) FROM chart_of_accounts')->fetchColumn()===0.0,'Fresh accounting balances are zero');
 checkInstall(((int)fileperms('/tmp/nsc-install-qa-20261014.php')&0777)===0600,'Database credentials file has owner-only permissions');
 [$status]=installerRequest($base.'/nsc-private-config.php');checkInstall($status===404,'Private configuration unavailable through website');
 echo "FIRST INSTALL PASSED: $checks checks; isolated database; migrations, primary sign-in, private Office Seven and owner creation.\n";
}finally{unlink($cookie);}
