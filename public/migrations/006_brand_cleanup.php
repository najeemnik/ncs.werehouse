<?php
/**
 * Migration 006 — remove the legacy "NiKXor" brand from stored text.
 *
 * Additive and idempotent: it only rewrites stored *branding* values
 * (settings, per-shop settings, custom-feature titles and shop names).
 * User-authored business text (invoices, notes, customer records) is never touched.
 * A marker row in schema_migrations prevents a second run.
 */
function migrate_brand_cleanup(PDO $db): void
{
    try {
        if ($db->query("SELECT version FROM schema_migrations WHERE version='006_brand_cleanup'")->fetchColumn()) return;
    } catch (Throwable $e) {
        return; // no migration registry yet: nothing to do
    }

    $replacements = [
        'NiKXor'   => 'Nik Smart Count - Warehousing',
        'نیک‌زور'  => 'نیک اسمارت کانت',
        'نیکزور'   => 'نیک اسمارت کانت',
    ];
    $targets = [
        ['settings', 'setting_value'],
        ['shop_settings', 'setting_value'],
        ['custom_features', 'title_fa'],
        ['custom_features', 'title_en'],
        ['custom_features', 'description_fa'],
        ['custom_features', 'description_en'],
        ['shops', 'name'],
    ];

    $changed = 0;
    foreach ($targets as [$table, $column]) {
        foreach ($replacements as $from => $to) {
            try {
                $stmt = $db->prepare("UPDATE {$table} SET {$column} = REPLACE({$column}, :from, :to) WHERE {$column} LIKE :like");
                $stmt->execute([':from' => $from, ':to' => $to, ':like' => '%' . $from . '%']);
                $changed += $stmt->rowCount();
            } catch (Throwable $e) {
                // Table or column absent on this installation: skip silently.
            }
        }
    }

    if ($changed > 0) {
        error_log('Nik Smart Count brand cleanup: ' . $changed . ' stored values rewritten.');
    }
    $db->exec("INSERT INTO schema_migrations (version) VALUES ('006_brand_cleanup')");
}
