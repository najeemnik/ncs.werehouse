<?php
/** Correction-aware read sources; no CREATE VIEW privilege or copied financial rows. */
function live_documents_select(string $entity): string {
    if($entity==='expenses')return "SELECT d.* FROM expenses d WHERE NOT EXISTS(SELECT 1 FROM financial_corrections c WHERE c.shop_id=d.shop_id AND c.entity_id=d.id AND c.entity_type IN ('expense','loss'))";
    if($entity==='capital')return "SELECT d.* FROM capital d WHERE NOT EXISTS(SELECT 1 FROM financial_corrections c WHERE c.shop_id=d.shop_id AND c.entity_id=d.id AND c.entity_type='capital')";
    throw new InvalidArgumentException('Unknown live document source.');
}
function live_documents_source(string $entity,?string $alias=null): string {
    $sql=live_documents_select($entity);$alias??=$entity.'_live';
    if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D',$alias))throw new InvalidArgumentException('Invalid live document alias.');
    return '('.$sql.') AS '.$alias;
}
