<?php
/** Existing theme endpoint, now session-authenticated, CSRF-protected and per-user. */
require_once __DIR__.'/functions.php';
require_once __DIR__.'/auth.php';
auth_require();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
    // Old bookmarked theme links still lead to a working appearance screen, but GET never mutates preferences.
    redirect(BASE_URL.'modules/settings.php?tab=appearance');
}
header('Content-Type: application/json; charset=utf-8');
try{
    if(!csrf_verify($_POST['csrf_token']??'')){http_response_code(403);echo json_encode(['success'=>false,'message'=>__t('Refresh and try again.','صفحه را تازه و دوباره تلاش کنید.','پاڼه تازه او بیا هڅه وکړئ.')]);exit;}
    $post=$_POST;
    $legacy=['classic'=>['theme'=>'horizon'],'dark_gold'=>['theme'=>'materio','palette'=>'titanium_gold','mode'=>'dark'],'emerald'=>['theme'=>'coreui','palette'=>'matrix_mint'],'glass'=>['theme'=>'horizon','effect'=>'glass']];
    if(is_string($post['theme']??null)&&isset($legacy[$post['theme']]))$post=array_merge($post,$legacy[$post['theme']]);
    $prefs=ui_save_preferences($post,auth_user()['id'],['theme','palette','effect','mode']);
    echo json_encode(['success'=>true,'theme'=>$prefs['theme'],'preferences'=>$prefs],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);
}catch(Throwable $e){http_response_code(422);echo json_encode(['success'=>false,'message'=>__t('Invalid appearance preference.','تنظیم ظاهر نامعتبر است.','د بڼې تنظیم ناسم دی.')]);}
