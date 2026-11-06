<?php
if(PHP_SAPI!=='cli'||getenv('NIKXOR_TEST_MODE')!=='1')throw new RuntimeException('Isolated CLI tests only.');
require __DIR__.'/../public/includes/installer_security.php';
function installCheck($ok,$label){if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";}
$base='/data/installer-key-unit-'.bin2hex(random_bytes(6));mkdir($base,0700,true);putenv('NIKXOR_RUNTIME_DIR='.$base);putenv('NIKXOR_HOST_PROFILE='.$base.'/absent.php');
$key=bin2hex(random_bytes(32));$file=$base.'/install-token.key';file_put_contents($file,$key);chmod($file,0600);
installCheck(installer_key_matches($key),'exact private installation key accepted');
installCheck(installer_key_matches(" \t".$key."\r\n "),'surrounding copied whitespace accepted consistently with host preparation');
installCheck(!installer_key_matches(substr($key,0,32).' '.substr($key,32)),'internal key changes rejected');
installCheck(!installer_key_matches(str_repeat('0',64)),'wrong 64-character key rejected');
installCheck(!installer_key_matches([$key])&&!installer_key_matches(str_repeat('a',129)),'structured and oversized keys rejected');
$_SESSION=[];installCheck(!installer_session_authorized(),'account step denied without verified key session');
$_SESSION=['install_key_verified_at'=>time(),'install_key_binding'=>installer_authorization_binding()];installCheck(installer_session_authorized(),'verified session authorizes account step');
$_SESSION['install_key_verified_at']=time()-900;installCheck(!installer_session_authorized(),'authorization expires at 15 minutes');
$_SESSION['install_key_verified_at']=time()+60;installCheck(!installer_session_authorized(),'future authorization timestamp rejected');
$_SESSION['install_key_verified_at']=time();$_SESSION['install_key_binding']='forged';installCheck(!installer_session_authorized(),'forged authorization binding rejected');
$_SESSION['install_key_binding']=installer_authorization_binding();file_put_contents($file,bin2hex(random_bytes(32)));installCheck(!installer_session_authorized(),'changing the private key revokes prior session authorization');file_put_contents($file,$key);
$second=$base.'/second';mkdir($second);file_put_contents($second.'/install-token.key',$key);putenv('NIKXOR_RUNTIME_DIR='.$second);installCheck(!installer_session_authorized(),'same key in a different runtime directory does not reuse authorization');putenv('NIKXOR_RUNTIME_DIR='.$base);
unlink($file);installCheck(!installer_key_matches($key)&&!installer_session_authorized(),'missing key file fails closed');file_put_contents($base.'/target',$key);symlink($base.'/target',$file);installCheck(!installer_key_matches($key),'symlink token file rejected');unlink($file);
mkdir($base.'/public/config',0700,true);$profile=['version'=>1,'mode'=>'protected','directory'=>'nsc-private-'.bin2hex(random_bytes(12)),'setup_token_hash'=>hash('sha256',$key)];$profileFile=$base.'/public/config/hosting.php';file_put_contents($profileFile,'<?php return '.var_export($profile,true).';');putenv('NIKXOR_HOST_PROFILE='.$profileFile);
installCheck(installer_key_matches("\n".$key." "),'protected-host profile accepts the same trimmed key');
$_SESSION=['install_key_verified_at'=>time(),'install_key_binding'=>installer_authorization_binding()];$profile['setup_token_hash']=hash('sha256',bin2hex(random_bytes(32)));file_put_contents($profileFile,'<?php return '.var_export($profile,true).';');installCheck(!installer_session_authorized(),'profile key rotation revokes account-step authorization');
echo "Installer key/session scenarios completed.\n";
