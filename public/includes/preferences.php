<?php
/** Presentation preferences only. No exchange conversion or accounting rules belong here. */
function ui_options() {
    return [
        'theme'=>['horizon','coreui','adminlte','materio'],
        'palette'=>['crimson_teal','matrix_mint','ultramarine','titanium_gold'],
        'effect'=>['none','glass','clay'], 'mode'=>['light','dark','system'],
        'language'=>['en','da','ps'], 'date_format'=>['Y/m/d','Y-m-d','d/m/Y','m/d/Y'],
        'number_format'=>['locale','western','persian','plain'],
        'currency_display'=>['code','code_symbol'],
        'notifications'=>['on','off'], 'auto_dismiss'=>['on','off']
    ];
}
function ui_defaults() {
    $legacy=get_setting('active_theme','classic');
    $appearance=['theme'=>'horizon','palette'=>'ultramarine','effect'=>'none','mode'=>'light'];
    if($legacy==='dark_gold')$appearance=['theme'=>'materio','palette'=>'titanium_gold','effect'=>'none','mode'=>'dark'];
    elseif($legacy==='emerald')$appearance=['theme'=>'coreui','palette'=>'matrix_mint','effect'=>'none','mode'=>'light'];
    elseif($legacy==='glass')$appearance['effect']='glass';
    return $appearance+['language'=>get_current_lang(),'date_format'=>'Y/m/d','time_zone'=>date_default_timezone_get(),'number_format'=>'locale','currency_display'=>'code','default_currency'=>'','notifications'=>'on','auto_dismiss'=>'off'];
}
function ui_preferences($userId=null) {
    $userId=$userId??(int)($_SESSION['user_id']??0);
    if(isset($GLOBALS['_ui_preferences'][$userId]))return $GLOBALS['_ui_preferences'][$userId];
    $prefs=ui_defaults();
    $cookie=$_COOKIE['nikxor_appearance']??'';if(!is_string($cookie)||strlen($cookie)>512)$cookie='';
    $saved=$userId?json_decode(get_setting('ui.user.'.$userId,'{}'),true):json_decode(base64_decode($cookie,true)?:'{}',true);
    if(is_array($saved)){
        foreach(ui_options() as $key=>$options)if(isset($saved[$key])&&is_string($saved[$key])&&in_array($saved[$key],$options,true))$prefs[$key]=$saved[$key];
        if(isset($saved['time_zone'])&&is_string($saved['time_zone'])&&in_array($saved['time_zone'],DateTimeZone::listIdentifiers(),true))$prefs['time_zone']=$saved['time_zone'];
        if(isset($saved['default_currency'])&&in_array($saved['default_currency'],['','AFN','USD','AED','EUR'],true))$prefs['default_currency']=$saved['default_currency'];
    }
    return $GLOBALS['_ui_preferences'][$userId]=$prefs;
}
function ui_save_preferences(array $post,$userId,array $fields) {
    $prefs=ui_preferences($userId);$allowed=ui_options();
    foreach($fields as $field){
        if(!array_key_exists($field,$post))continue;
        $value=$post[$field];
        if(!is_string($value))throw new InvalidArgumentException(__t('Invalid preference.','تنظیم نامعتبر است.','تنظیم ناسم دی.'));
        if(isset($allowed[$field])&&!in_array($value,$allowed[$field],true))throw new InvalidArgumentException(__t('Unsupported preference.','این گزینه پشتیبانی نمی‌شود.','دا انتخاب نه ملاتړ کېږي.'));
        if($field==='time_zone'&&!in_array($value,DateTimeZone::listIdentifiers(),true))throw new InvalidArgumentException(__t('Invalid time zone.','منطقهٔ زمانی نامعتبر است.','د وخت سیمه ناسمه ده.'));
        if($field==='default_currency'&&$value!=='')currency_context(['currency_code'=>$value]);
        if(!array_key_exists($field,$prefs))throw new InvalidArgumentException('Unsupported preference key.');
        $prefs[$field]=$value;
    }
    if(!update_setting('ui.user.'.(int)$userId,json_encode($prefs,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)))throw new RuntimeException(__t('Settings could not be saved.','تنظیمات ذخیره نشد.','تنظیمات خوندي نه شول.'));
    $GLOBALS['_ui_preferences'][$userId]=$prefs;
    $appearance=array_intersect_key($prefs,array_flip(['theme','palette','effect','mode']));
    app_set_preference_cookie('nikxor_appearance',base64_encode(json_encode($appearance)),time()+86400*180);
    if(in_array('language',$fields,true)){
        $_SESSION['app_lang']=$prefs['language'];
        app_set_preference_cookie('app_lang',$prefs['language'],time()+86400*365);
        unset($_SESSION['app_lang_pending']);
    }
    return $prefs;
}
function ui_is_https(){return app_request_is_https();}
/** Persist an explicit language choice only after the account has been authenticated. */
function ui_apply_language_choice($userId) {
    $lang=$_SESSION['app_lang_pending']??null;
    if(!is_string($lang)||!isset(get_supported_languages()[$lang]))return;
    try{
        $saved=json_decode(get_setting('ui.user.'.(int)$userId,'{}'),true);
        if(!is_array($saved)||($saved['language']??'')!==$lang)ui_save_preferences(['language'=>$lang],$userId,['language']);
        unset($_SESSION['app_lang_pending']);
    }catch(Throwable $e){
        // A preference-write problem must not disable authentication or change the selected language.
    }
}

function ui_root_attributes() {
    $prefs=ui_preferences();$out='';foreach(['theme','palette','effect','mode'] as $key)$out.=' data-'.$key.'="'.sanitize($prefs[$key]).'"';return $out;
}
function ui_persian_digits($text){return strtr((string)$text,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);}
function ui_number($number,$decimals=2,$trim=false) {
    $prefs=ui_preferences();$style=$prefs['number_format'];
    $text=number_format((float)$number,$decimals,'.',$style==='plain'?'':',');
    if($trim&&$decimals>0)$text=rtrim(rtrim($text,'0'),'.');
    if($style==='persian')return strtr(ui_persian_digits($text),[','=>'٬','.'=>'٫']);
    if($style==='locale'&&get_current_lang()!=='en')return to_persian_num($text);
    return $text;
}
function ui_currency_label($code) {
    if(ui_preferences()['currency_display']==='code_symbol')return $code.' '.(['AFN'=>'؋','USD'=>'$','AED'=>'د.إ','EUR'=>'€'][$code]??'');
    return $code;
}
function ui_date($value,$withTime=null) {
    if(!$value)return '—';$value=(string)$value;$prefs=ui_preferences();
    $dateOnly=(bool)preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value);
    try{
        $date=new DateTimeImmutable($value,new DateTimeZone(date_default_timezone_get()));
        if(!$dateOnly)$date=$date->setTimezone(new DateTimeZone($prefs['time_zone']));
        $text=$date->format($prefs['date_format'].(($withTime??!$dateOnly)?' H:i':''));
        return $prefs['number_format']==='persian'||($prefs['number_format']==='locale'&&get_current_lang()!=='en')?ui_persian_digits($text):$text;
    }catch(Exception $e){return sanitize($value);}
}
function ui_logo_url() {
    $logo=get_setting('shop_logo','');
    if(!$logo)return '';
    // Existing uploads use a filename relative to uploads. Never interpret a saved value as an executable URL.
    if(preg_match('#^[\pL\pN _./-]+\.(?:png|jpe?g|webp|gif)$#uiD',$logo)&&!str_contains($logo,'..')&&is_file(BASE_DIR.'/uploads/'.$logo)&&str_starts_with(realpath(BASE_DIR.'/uploads/'.$logo),realpath(BASE_DIR.'/uploads').DIRECTORY_SEPARATOR))return BASE_URL.'uploads/'.implode('/',array_map('rawurlencode',explode('/',$logo)));
    return '';
}
/**
 * URL of a packaged brand asset (stored under uploads/). Falls back to the
 * application mark when a file is missing, so a deployment cannot show a
 * broken image in the header or on the login screen.
 */
function brand_asset_url($path) {
    $path=ltrim(str_replace('\\','/',(string)$path),'/');
    return BASE_URL.'uploads/'.implode('/',array_map('rawurlencode',explode('/',$path)));
}
function brand_mark_url() {
    $custom=ui_logo_url();
    if($custom)return $custom;
    return brand_asset_url(defined('APP_BRAND_MARK')?APP_BRAND_MARK:'branding/nik-smart-count-mark.png');
}
function brand_full_logo_url() {
    return brand_asset_url(defined('APP_BRAND_LOGO')?APP_BRAND_LOGO:'branding/nik-smart-count-logo.png');
}
function brand_signature_url() {
    return brand_asset_url(defined('APP_BRAND_SIGNATURE')?APP_BRAND_SIGNATURE:'branding/nik-signature-ahmad-najeem-nik.png');
}
function brand_name() {
    return defined('APP_BRAND_NAME')?APP_BRAND_NAME:'Nik Smart Count - Warehousing';
}
function brand_short_name() {
    return defined('APP_BRAND_NAME')?APP_NAME:'Nik Smart Count';
}
function ui_save_branding(array $post,array $files) {
    global $pdo;
    $map=['shop_name'=>255,'shop_phone_central'=>150,'shop_phone_secondary'=>150,'shop_phone_whatsapp'=>100,'shop_email'=>254,'shop_address_central'=>1000];$values=[];
    foreach($map as $key=>$max){
        if(!isset($post[$key]))continue;
        if(!is_string($post[$key]))throw new InvalidArgumentException(__t('Invalid business information.','اطلاعات کسب‌وکار نامعتبر است.','د کاروبار معلومات ناسم دي.'));
        $value=trim($post[$key]);if(strlen($value)>$max||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value))throw new InvalidArgumentException(__t('A field is too long or invalid.','یکی از فیلدها طولانی یا نامعتبر است.','یوه خانه اوږده یا ناسمه ده.'));
        $values[$key]=$value;
    }
    if(isset($values['shop_name'])&&$values['shop_name']==='')throw new InvalidArgumentException(__t('Business name is required.','نام کسب‌وکار ضروری است.','د کاروبار نوم اړین دی.'));
    if(!empty($values['shop_email'])&&!filter_var($values['shop_email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException(__t('Enter a valid email address.','ایمیل معتبر وارد کنید.','سم برېښنالیک ولیکئ.'));
    $newFile=null;
    if(!empty($post['remove_logo']))$values['shop_logo']='';
    if(isset($files['business_logo'])&&($files['business_logo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
        $file=$files['business_logo'];
        if(($file['error']??-1)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||($file['size']??0)>ui_upload_limit(3*1024*1024))throw new InvalidArgumentException(__t('Choose a PNG, JPEG or WebP image up to 3 MB.','تصویر PNG، JPEG یا WebP تا ۳ مگابایت انتخاب کنید.','تر ۳ مېګابایټه PNG، JPEG یا WebP انځور وټاکئ.'));
        $info=@getimagesize($file['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!$info||!in_array($mime,['image/png','image/jpeg','image/webp'],true)||$info[0]>4096||$info[1]>4096||$info[0]*$info[1]>12000000)throw new InvalidArgumentException(__t('Unsupported or oversized image.','تصویر نامعتبر یا بیش از حد بزرگ است.','انځور ناسم یا ډېر لوی دی.'));
        // Re-encoding strips metadata and polyglot payloads instead of trusting the uploaded extension.
        if(!function_exists('imagecreatefromstring'))throw new RuntimeException(__t('The server needs the GD image extension to upload logos safely.','برای آپلود امن لوگو، افزونهٔ GD روی سرور لازم است.','د لوګو خوندي پورته کولو لپاره GD اړین دی.'));
        $image=@imagecreatefromstring(file_get_contents($file['tmp_name']));if(!$image)throw new InvalidArgumentException(__t('Image could not be read.','تصویر قابل خواندن نیست.','انځور نه لوستل کېږي.'));
        $dir=BASE_DIR.'/uploads/branding';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException(__t('Logo storage is not writable.','محل ذخیرهٔ لوگو قابل نوشتن نیست.','د لوګو ځای ته لیکل نه کېږي.'));
        ui_protect_logo_directory($dir);
        $filename='logo-'.bin2hex(random_bytes(16)).'.png';$newFile=$dir.'/'.$filename;
        imagealphablending($image,false);imagesavealpha($image,true);$ok=imagepng($image,$newFile,6);imagedestroy($image);
        if(!$ok)throw new RuntimeException(__t('Logo could not be saved.','لوگو ذخیره نشد.','لوګو خوندي نه شو.'));
        chmod($newFile,0644);$values['shop_logo']='branding/'.$filename;
    }
    $ownTransaction=!$pdo->inTransaction();if($ownTransaction)$pdo->beginTransaction();
    try{foreach($values as $key=>$value)if(!update_setting($key,$value))throw new RuntimeException('Settings write failed.');if($ownTransaction)$pdo->commit();}
    catch(Throwable $e){if($ownTransaction&&$pdo->inTransaction())$pdo->rollBack();if($newFile&&is_file($newFile))unlink($newFile);throw new RuntimeException(__t('Business information could not be saved.','اطلاعات کسب‌وکار ذخیره نشد.','د کاروبار معلومات خوندي نه شول.'));}
    // Retain previously uploaded files: old backups may still reference those logos.
}
function ui_informational_rates(){
    $saved=json_decode(get_setting('ui.informational_rates','[]'),true);return is_array($saved)?$saved:[];
}
function ui_save_informational_rate(array $post) {
    $from=$post['rate_from']??'';$to=$post['rate_to']??'';
    if(!is_string($from)||!is_string($to)||!currency_find($from)||!currency_find($to)||$from===$to)throw new InvalidArgumentException(__t('Choose two different supported currencies.','دو ارز متفاوت و معتبر انتخاب کنید.','دوه بېلابېل معتبر اسعار وټاکئ.'));
    $rates=ui_informational_rates();$key=$from.'_'.$to;
    if(!empty($post['remove_rate']))unset($rates[$key]);
    else{
        $rate=dec_round($post['reference_rate']??0,8);
        if(dec_units($rate,8)<=0||dec_units($rate,8)>99999999999999)throw new InvalidArgumentException(__t('Enter a positive reference rate.','نرخ اطلاعاتی مثبت وارد کنید.','مثبت معلوماتي نرخ ولیکئ.'));
        $rates[$key]=['from'=>$from,'to'=>$to,'value'=>$rate,'updated_at'=>gmdate('c')];
    }
    if(!update_setting('ui.informational_rates',json_encode($rates,JSON_THROW_ON_ERROR)))throw new RuntimeException(__t('Reference rate could not be saved.','نرخ اطلاعاتی ذخیره نشد.','معلوماتي نرخ خوندي نه شو.'));
    // This namespace is intentionally never read by currency_to_ledger, reports, purchases or sales.
}

function ui_upload_limit($applicationLimit){
    $parse=function($value){$value=trim((string)$value);if($value===''||(float)$value<=0)return PHP_INT_MAX;$factor=['g'=>1073741824,'m'=>1048576,'k'=>1024][strtolower(substr($value,-1))]??1;return (int)((float)$value*$factor);};
    return max(1,min($applicationLimit,$parse(ini_get('upload_max_filesize')),$parse(ini_get('post_max_size'))-65536));
}

/** Keep uploaded logos inert without Options directives rejected by shared hosts. */
function ui_protect_logo_directory(string $dir): void {
    $rules="<IfModule mod_autoindex.c>\nIndexIgnore *\n</IfModule>\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|html?|svg)$\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n</FilesMatch>\n";
    if(file_put_contents($dir.'/.htaccess',$rules,LOCK_EX)!==strlen($rules))throw new RuntimeException(__t('Logo protection rules could not be saved.','قواعد محافظت لوگو ذخیره نشد.','د لوګو د ساتنې اصول نه شول ساتل کېدای.'));
}
