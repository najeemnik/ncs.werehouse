<?php
/** Additive business-level lifecycle, contact and staff/branch limits. */
function migrate_business_controls(PDO $db): void {
    if($db->query("SELECT version FROM schema_migrations WHERE version='022_business_controls'")->fetchColumn())return;
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    $columns=$sqlite?array_column($db->query('PRAGMA table_info(businesses)')->fetchAll(),'name'):array_column($db->query('SHOW COLUMNS FROM businesses')->fetchAll(),'Field');
    $add=[
        'contact_phone'=>"VARCHAR(40) NOT NULL DEFAULT ''",
        'contact_email'=>"VARCHAR(190) NOT NULL DEFAULT ''",
        'contact_address'=>"VARCHAR(1000) NOT NULL DEFAULT ''",
        'subscription_active'=>'INT NOT NULL DEFAULT 1',
        'subscription_expires_at'=>'DATETIME NULL',
        'branch_limit'=>'INT NOT NULL DEFAULT 0',
        'staff_limit'=>'INT NOT NULL DEFAULT 0',
        'updated_at'=>'DATETIME NULL'
    ];
    if($sqlite)$db->beginTransaction();
    try{
        foreach($add as $name=>$definition)if(!in_array($name,$columns,true))$db->exec("ALTER TABLE businesses ADD COLUMN $name $definition");
        $db->exec("INSERT INTO schema_migrations (version) VALUES ('022_business_controls')");
        if($sqlite)$db->commit();
    }catch(Throwable $e){if($sqlite&&$db->inTransaction())$db->rollBack();throw $e;}
}
