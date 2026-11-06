import {readFile,writeFile} from 'node:fs/promises';
const base=(await readFile('database/nsc_schema.sql')).toString('base64'),studio=(await readFile('database/010-visual-business-studio.sql')).toString('base64');
await writeFile('api/schema-sql.php',"<?php\n// Generated from the checked-in schema. No database credentials or user data.\nif(!defined('NSC_INTERNAL_MIGRATION')){http_response_code(404);exit;}\nreturn ['base'=>base64_decode('"+base+"'),'studio'=>base64_decode('"+studio+"')];\n");
