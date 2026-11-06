<?php
// Copy to first-install-credentials.php outside source control, or supply server variables.
if(!defined('NSC_INTERNAL_FIRST_INSTALL')){http_response_code(404);exit;}
return ['keyHash'=>getenv('NSC_SETUP_KEY_HASH')?:'', 'passwordHash'=>getenv('NSC_SETUP_PASSWORD_HASH')?:'', 'username'=>getenv('NSC_SETUP_USERNAME')?:'owner', 'name'=>getenv('NSC_SETUP_NAME')?:'Primary administrator', 'email'=>getenv('NSC_SETUP_EMAIL')?:'admin@example.invalid'];
