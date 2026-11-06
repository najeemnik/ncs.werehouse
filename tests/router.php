<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('~/(?:config|migrations|sessions|uploads|includes)/~',$path)&&!preg_match('~/includes/(?:change_theme|screen_lock_action)\.php$~',$path)&&!preg_match('~/uploads/branding/[a-zA-Z0-9_.-]+\.(?:png|ico)$~',$path)){http_response_code(404);exit('Not found.');}
if(preg_match('~(?:^|/)\.|\.(?:sqlite|db|key|nxb|sql|log|env)$~i',$path)){http_response_code(404);exit('Not found.');}
return false;
