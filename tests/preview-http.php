<?php
$mode=getenv('NSC_PREVIEW_MODE')?:'setup';$statePath=getenv('NSC_PREVIEW_STATE');if(!$statePath)throw new RuntimeException('State path required.');
if($mode==='setup'){
    require __DIR__.'/security.php';security_establish_session($cu);$_SESSION['central_mfa_at']=time();preview_start($staff,$a1);
    $_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/index.php';ob_start();require __DIR__.'/../public/index.php';$html=ob_get_clean();check(str_contains($html,'READ-ONLY USER PREVIEW')&&str_contains($html,'Exit preview'),'real workspace renders the preview identity and exit control');
    file_put_contents($statePath,json_encode(['session'=>session_id(),'csrf'=>csrf_token(),'central'=>$central,'sales'=>(int)$pdo->query('SELECT COUNT(*) FROM sales')->fetchColumn()]));session_write_close();exit;
}
$state=json_decode(file_get_contents($statePath),true,32,JSON_THROW_ON_ERROR);session_id($state['session']);require __DIR__.'/bootstrap.php';
if($mode==='verify'){
    check(!preview_active()&&auth_user()['id']===$state['central'],'preview exit restores actual central identity');
    check((int)$pdo->query('SELECT COUNT(*) FROM sales')->fetchColumn()===$state['sales'],'read-only preview POST saved no sale');echo "Real preview request scenarios completed.\n";exit;
}
if($mode==='stop'){
    $_SERVER['SCRIPT_NAME']='/admin/user_preview.php';$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']='/admin/user_preview.php';$_POST=['action'=>'stop','csrf_token'=>$state['csrf']];require __DIR__.'/../public/admin/user_preview.php';exit;
}
$_SERVER['SCRIPT_NAME']='/modules/sales.php';$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']='/modules/sales.php';$_POST=['action'=>'create_sale','csrf_token'=>$state['csrf']];require __DIR__.'/../public/modules/sales.php';
