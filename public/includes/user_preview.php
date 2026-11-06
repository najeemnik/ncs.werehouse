<?php
/** Optional central-only, read-only view of a tenant account. The original admin session never changes. */
function preview_original_user(): ?array {
    global $pdo;if(empty($_SESSION['authenticated'])||empty($_SESSION['user_id'])||!totp_session_valid())return null;
    $q=$pdo->prepare('SELECT id,username,fullname,role,status,shop_id FROM users WHERE id=?');$q->execute([(int)$_SESSION['user_id']]);$u=$q->fetch();
    return $u&&domain_is_central($u)&&$u['status']==='active'?$u:null;
}
function preview_state(): ?array {
    if(array_key_exists('_user_preview_state',$GLOBALS))return $GLOBALS['_user_preview_state'];
    $state=$_SESSION['central_user_preview']??null;if(!is_array($state))return $GLOBALS['_user_preview_state']=null;
    $original=preview_original_user();$uid=(int)($state['user_id']??0);$branch=(int)($state['branch_id']??0);
    global $pdo;$q=$pdo->prepare('SELECT id,username,fullname,role,status,shop_id FROM users WHERE id=?');$q->execute([$uid]);$target=$q->fetch();
    if(!$original||!$target||domain_is_central($target)||!access_account_available($target)||$branch<1||!domain_branch_allowed($target,$branch)){
        unset($_SESSION['central_user_preview']);return $GLOBALS['_user_preview_state']=null;
    }
    return $GLOBALS['_user_preview_state']=['original'=>$original,'user'=>$target,'branch_id'=>$branch,'started_at'=>(int)($state['started_at']??0)];
}
function preview_active(): bool {return preview_state()!==null;}
function preview_start(int $user,int $branch): void {
    global $pdo;if(!preview_original_user())throw new RuntimeException('Central preview authorization required.',403);
    $q=$pdo->prepare('SELECT id,username,fullname,role,status,shop_id FROM users WHERE id=?');$q->execute([$user]);$target=$q->fetch();
    if(!$target||domain_is_central($target)||!access_account_available($target)||!domain_branch_allowed($target,$branch))throw new InvalidArgumentException(__t('Choose an active business user and an assigned branch.','یک یوزر فعال تجارت و شعبهٔ تعیین‌شدهٔ او را انتخاب کنید.','د سوداګرۍ فعال کارن او ټاکل شوې څانګه وټاکئ.'));
    $_SESSION['central_user_preview']=['user_id'=>$user,'branch_id'=>$branch,'started_at'=>time()];unset($GLOBALS['_user_preview_state'],$GLOBALS['_auth_user'],$GLOBALS['_route_enforced']);
}
function preview_stop(): void {unset($_SESSION['central_user_preview'],$GLOBALS['_user_preview_state'],$GLOBALS['_auth_user'],$GLOBALS['_route_enforced']);}
