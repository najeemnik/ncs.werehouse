<?php
/** Signed links refer to an existing invoice; the public response exposes no customer data. */
function invoice_public_origin(?string $value=null): ?string {
 $url=rtrim($value??runtime_value('NIKXOR_PUBLIC_URL'),'/');if(strlen($url)>200||preg_match('/[\x00-\x20\\\\]/',$url)||!filter_var($url,FILTER_VALIDATE_URL))return null;$p=parse_url($url);
 if(!$p||($p['scheme']??'')!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass'])||isset($p['query'])||isset($p['fragment'])||str_contains(rawurldecode($p['path']??''),'..'))return null;return $url;
}
function invoice_verification_signature(array $sale): string {
 $payload='invoice-verify-v1|'.$sale['id'].'|'.$sale['shop_id'].'|'.$sale['verification_nonce'];return rtrim(strtr(base64_encode(hash_hmac('sha256',$payload,runtime_master_key(),true)),'+/','-_'),'=');
}
function invoice_verification_token(array $sale): string {
 global $pdo;if(!isset($sale['id'],$sale['shop_id'])||(int)$sale['id']<1||(int)$sale['shop_id']<1)throw new InvalidArgumentException('A recorded invoice is required.');
 // Always load the authoritative row. No caller can sign an invented invoice.
 $q=$pdo->prepare('SELECT id,shop_id,verification_nonce FROM sales WHERE id=? AND shop_id=?');$q->execute([$sale['id'],$sale['shop_id']]);$stored=$q->fetch();if(!$stored)throw new InvalidArgumentException('Invoice not found.');
 if(empty($stored['verification_nonce'])){$nonce=bin2hex(random_bytes(16));$pdo->prepare('UPDATE sales SET verification_nonce=? WHERE id=? AND shop_id=? AND verification_nonce IS NULL')->execute([$nonce,$stored['id'],$stored['shop_id']]);$q->execute([$sale['id'],$sale['shop_id']]);$stored=$q->fetch();}
 if(!preg_match('/^[a-f0-9]{32}$/D',$stored['verification_nonce']??''))throw new RuntimeException('Invalid verification nonce.');return $stored['id'].'.'.invoice_verification_signature($stored);
}
function invoice_verification_url(array $sale): ?string {$base=invoice_public_origin();return $base?$base.'/verify_invoice.php?'.http_build_query(['t'=>invoice_verification_token($sale),'lang'=>get_current_lang()]):null;}
function invoice_verification_find($token): ?array {
 global $pdo;if(!is_string($token)||strlen($token)>80||!preg_match('/^([1-9][0-9]{0,18})\.([A-Za-z0-9_-]{43})$/D',$token,$parts))return null;
 $id=filter_var($parts[1],FILTER_VALIDATE_INT);if(!$id)return null;$q=$pdo->prepare('SELECT * FROM sales WHERE id=?');$q->execute([$id]);$sale=$q->fetch();if(!$sale||!preg_match('/^[a-f0-9]{32}$/D',$sale['verification_nonce']??'')||!hash_equals(invoice_verification_signature($sale),$parts[2]))return null;return $sale;
}
function invoice_verification_details(array $sale): array {
 global $pdo;$brand=[];if(!empty($sale['invoice_snapshot'])){try{$snapshot=json_decode($sale['invoice_snapshot'],true,32,JSON_THROW_ON_ERROR);$brand=$snapshot['brand']??[];}catch(Throwable $e){}}
 if(!is_array($brand)||!isset($brand['shop_name']))$brand=invoice_effective_brand((int)$sale['shop_id']);
 $q=$pdo->prepare("SELECT currency_code,original_amounts,ledger_amounts FROM currency_transactions WHERE entity_type='sales' AND entity_id=? AND shop_id=? AND event_type='create' ORDER BY id LIMIT 1");$q->execute([$sale['id'],$sale['shop_id']]);$context=$q->fetch();$total=$sale['total_amount'];$code=$sale['currency_code'];if($context){$original=json_decode($context['original_amounts'],true);$ledger=json_decode($context['ledger_amounts'],true);if(isset($original['total_amount'],$ledger['total_amount'])&&dec_round($ledger['total_amount'])===dec_round($total))$total=$original['total_amount'];$code=$context['currency_code'];}
 $q=$pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS amount,COUNT(*) AS documents FROM sales_returns WHERE sale_id=? AND shop_id=? AND status IN ('posted','partial')");$q->execute([$sale['id'],$sale['shop_id']]);$returns=$q->fetch();
 // A stored invoice is proof of its registration, not a claim of independent bank settlement.
 return ['business_name'=>(string)$brand['shop_name'],'invoice_no'=>(string)$sale['invoice_no'],'issued_at'=>(string)$sale['created_at'],'currency'=>(string)$code,'total'=>dec_round($total),'remaining'=>dec_round($sale['remaining_amount']),'returned'=>dec_round($returns['amount']),'return_documents'=>(int)$returns['documents'],'fully_returned'=>dec_units($sale['total_amount'])>0&&dec_units($returns['amount'])>=dec_units($sale['total_amount'])];
}
