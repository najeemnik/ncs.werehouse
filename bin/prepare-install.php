<?php
if(PHP_SAPI!=='cli')exit(1);require __DIR__.'/../public/includes/runtime_settings.php';$path=runtime_directory().'/install-token.key';
if(file_exists($path))throw new RuntimeException('Installation token already exists; it was not replaced.');$h=fopen($path,'x');if(!$h)throw new RuntimeException('Cannot create installation token.');chmod($path,0600);fwrite($h,bin2hex(random_bytes(32)));fclose($h);echo "Installation token created privately. Retrieve it using your host file manager; it is not printed.\n";
