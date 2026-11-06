<?php
/** Shared cookie policy for direct PHP, reverse proxies and embedded HTTPS previews. */
function app_request_is_https() {
    $https=strtolower((string)($_SERVER['HTTPS']??''));
    if(($https!==''&&$https!=='off'&&$https!=='0')||(int)($_SERVER['SERVER_PORT']??0)===443)return true;
    // Trust forwarded headers only when the host explicitly opts in for its proxy.
    return getenv('NIKXOR_TRUST_PROXY')==='1' && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https';
}
function app_set_preference_cookie($name,$value,$expires) {
    if(headers_sent())return false;
    $secure=app_request_is_https();
    return setcookie($name,$value,['expires'=>$expires,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
}
function app_prepare_cookie_headers() { /* Cookie attributes are defined once at creation. */ }
