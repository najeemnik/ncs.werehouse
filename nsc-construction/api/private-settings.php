<?php
// Only server-side environment values or a PHP file outside the web root are used.
if(!defined('NSC_INTERNAL_SETTINGS')){http_response_code(404);exit;}
function nscPrivateConfigPath():string{return getenv('NSC_PRIVATE_CONFIG_FILE')?:dirname(__DIR__,2).'/nsc-private-config.php';}
function nscReadPrivateSettings():array{$path=nscPrivateConfigPath();if(!is_file($path))return [];$value=require $path;if(!is_array($value))throw new RuntimeException('Invalid private host configuration');return $value;}
function nscSetting(string $key,string $fallback=''):string{static $settings=null;$v=getenv($key);if($v!==false&&$v!=='')return $v;$settings??=nscReadPrivateSettings();return is_string($settings[$key]??null)?$settings[$key]:$fallback;}
