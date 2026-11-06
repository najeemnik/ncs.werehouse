<?php
require_once __DIR__.'/decimal.php';
/** Independent currency accounts. New amounts are never converted or combined across currencies. */
function currency_list($enabledOnly=false) {
    global $pdo;
    if(!isset($GLOBALS['_currency_rows']))$GLOBALS['_currency_rows']=$pdo->query("SELECT * FROM currencies ORDER BY CASE code WHEN 'AFN' THEN 0 WHEN 'USD' THEN 1 WHEN 'AED' THEN 2 ELSE 3 END")->fetchAll(PDO::FETCH_ASSOC);
    return array_values(array_filter($GLOBALS['_currency_rows'],fn($r)=>!$enabledOnly||(int)$r['enabled']===1));
}
function currency_find($code){foreach(currency_list() as $r)if($r['code']===$code)return $r;return null;}
// Compatibility name: this is now the selected account, NOT a conversion/reporting base.
function currency_base_code() {
    if(isset($GLOBALS['_currency_display']))return $GLOBALS['_currency_display']['code'];
    if(strpos($_SERVER['SCRIPT_NAME']??'','/admin/')!==false)return 'AFN';
    if(isset($GLOBALS['_currency_account']))return $GLOBALS['_currency_account'];
    if(isset($_GET['report_currency'])&&is_string($_GET['report_currency'])&&currency_find($_GET['report_currency']))$_SESSION['report_currency']=$_GET['report_currency'];
    $code=$_SESSION['report_currency']??'AFN';
    return $GLOBALS['_currency_account']=currency_find($code)?$code:'AFN';
}
function currency_default_entry(){ $preferred=function_exists('ui_preferences')?ui_preferences()['default_currency']:'';if($preferred){$saved=currency_find($preferred);if($saved&&$saved['enabled'])return $preferred;} $c=currency_find(currency_base_code());return $c&&$c['enabled']?$c['code']:(currency_list(true)[0]['code']??'AFN'); }
// Retained for API compatibility only. These helpers perform no exchange conversion.
function currency_rate($code){return '1.00000000';}
function currency_display_rate(){return '1.00000000';}
function currency_to_ledger($amount,$unusedRate=null,$scale=2){$value=dec_round($amount,$scale);if(abs(dec_units($value,$scale))>99999999999999)throw new InvalidArgumentException('Amount exceeds the supported transaction limit.');return $value;}
function currency_from_ledger($amount,$unusedRate=null,$scale=2){return dec_round($amount,$scale);}
// Archived documents may need their old stored denomination reconstructed. Never used for new entries.
function currency_display_override($code,$archivedRate='1',$mode='native'){$GLOBALS['_currency_display']=['code'=>$code,'archived_rate'=>$archivedRate,'mode'=>$mode];}
function currency_document_amount($amount,$scale=2){$c=$GLOBALS['_currency_display']??[];return ($c['mode']??'native')==='legacy_afn'?dec_div($amount,$c['archived_rate'],$scale):dec_round($amount,$scale);}
function currency_format($amount,$code,$decimals=2){$value=dec_round($amount,$decimals);return ui_number($value,$decimals).' '.ui_currency_label($code);}
function currency_profit_html($amount,$known=true,$code=null){return $known?currency_format($amount,$code??currency_base_code()):'<span class="badge badge-secondary" title="'.sanitize(__t('Different currencies: no automatic profit calculation.','ارزهای متفاوت: سود خودکار محاسبه نمی‌شود.','بېلابېل اسعار: ګټه په اتومات ډول نه حسابېږي.')).'">'.__t('Not calculated','محاسبه‌نشده','نه دی حساب شوی').'</span>';}
function currency_sql($alias='',$code=null,$column='currency_code'){
    global $pdo;$code=$code??currency_base_code();if(!currency_find($code))throw new InvalidArgumentException('Invalid account currency.');
    if(!preg_match('/^[a-z_]*$/iD',$alias)||!in_array($column,['currency_code','cost_currency_code','inventory_currency_code'],true))throw new InvalidArgumentException('Invalid currency query.');
    $where=($alias?$alias.'.':'').$column.'='.$pdo->quote($code);
    return $where;
}
function currency_entity_code($entity,$id){global $pdo;$tables=['stock','sales','sale_items','expenses','debts','installments','capital','partners','mudaraba_contracts','losses'];if(!in_array($entity,$tables,true))throw new InvalidArgumentException('Invalid currency entity.');$s=$pdo->prepare("SELECT currency_code FROM {$entity} WHERE id=? AND ".shop_sql());$s->execute([(int)$id]);return $s->fetchColumn()?:'AFN';}
function currency_assert_record($entity,$id,$code){if(currency_entity_code($entity,$id)!==$code)throw new InvalidArgumentException(__t('The currency of an existing account cannot be relabelled. Use a new transaction for another currency.','ارز حساب قبلی قابل تغییر نام نیست. برای ارز دیگر معاملهٔ جدید ثبت کنید.','د پخواني حساب اسعار مه بدلوئ؛ د بل اسعارو لپاره نوې معامله ثبت کړئ.'));}
function currency_context(array $post){
    $legacy=!isset($post['currency_code']);$code=$legacy?'AFN':strtoupper(trim((string)$post['currency_code']));$row=currency_find($code);
    if(!$row||!(int)$row['enabled'])throw new InvalidArgumentException(__t('This currency is disabled or unsupported.','این ارز غیرفعال یا نامعتبر است.','دا اسعار غیر فعال یا ناسم دي.'));
    return ['code'=>$code,'rate'=>'1.00000000','original'=>[],'legacy'=>$legacy,'mode'=>'native'];
}
function currency_prepare_request(array $actions,$defaultAction='',$atomic=false){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return null;$action=$_POST['action']??$defaultAction;
    if(!isset($actions[$action]))return null;if(isset($GLOBALS['_currency_request']))return $GLOBALS['_currency_request'];
    try{
        if(!csrf_verify($_POST['csrf_token']??''))throw new InvalidArgumentException(__t('Security session error. Refresh the form.','خطای نشست؛ فرم را دوباره باز کنید.','د ناستې تېروتنه؛ فورمه تازه کړئ.'));
        $ctx=currency_context($_POST);$ctx['raw_post']=$_POST;$ctx['action']=$action;
        $edits=['edit_stock'=>['stock',(int)($_GET['id']??0)],'edit_partner'=>['partners',(int)($_POST['partner_id']??0)],'edit_invoice'=>['sales',(int)($_POST['invoice_id']??0)],'edit_debt'=>['debts',(int)($_POST['debt_id']??0)],'edit_contract'=>['mudaraba_contracts',(int)($_POST['contract_id']??0)]];
        if(isset($edits[$action])&&$edits[$action][1]>0)currency_assert_record($edits[$action][0],$edits[$action][1],$ctx['code']);
        $links=['pay_installment'=>['debts','debt_id'],'update_progress'=>['mudaraba_contracts','contract_id'],'payout_partner'=>['mudaraba_contracts','contract_id'],'add_contract_item'=>['mudaraba_contracts','contract_id']];
        if(isset($links[$action])){global $pdo;[$table,$field]=$links[$action];$q=$pdo->prepare("SELECT id FROM {$table} WHERE id=? AND ".shop_sql());$q->execute([(int)($_POST[$field]??0)]);if(!$q->fetchColumn())throw new InvalidArgumentException(__t('The linked account does not exist.','حساب مربوط یافت نشد.','اړوند حساب ونه موندل شو.'));}

        foreach($actions[$action] as $name=>$scale){if(is_int($name)){$name=$scale;$scale=2;}if(!isset($_POST[$name])||$_POST[$name]==='')continue;$value=currency_to_ledger($_POST[$name],null,$scale);if(dec_units($value,$scale)<0&&!in_array($name,['profit_amount','add_profit','total_profit_amount'],true))throw new InvalidArgumentException(__t('Amounts cannot be negative.','مبلغ نمی‌تواند منفی باشد.','مبلغ منفي نه شي کېدای.'));$ctx['original'][$name]=$value;$_POST[$name]=$value;}
        if($atomic){global $pdo;$pdo->beginTransaction();$GLOBALS['_currency_auto_transaction']=true;register_shutdown_function(function()use($pdo){if(!empty($GLOBALS['_currency_auto_transaction'])&&$pdo->inTransaction())$pdo->rollBack();});}
        return $GLOBALS['_currency_request']=$ctx;
    }catch(InvalidArgumentException $e){set_flash($e->getMessage(),'danger');redirect($_SERVER['SCRIPT_NAME']??BASE_URL.'index.php');exit;}
}
function currency_request_commit(){global $pdo;if(!empty($GLOBALS['_currency_auto_transaction'])){$pdo->commit();$GLOBALS['_currency_auto_transaction']=false;}}
function currency_request_rollback(){global $pdo;if(!empty($GLOBALS['_currency_auto_transaction'])&&$pdo->inTransaction())$pdo->rollBack();$GLOBALS['_currency_auto_transaction']=false;}
function currency_record($entity,$id,$context,array $amounts,$event='create',array $originals=[]){
    global $pdo;$context=$context??['code'=>'AFN','original'=>[]];$ledger=[];
    foreach($amounts as $field=>$value){$scale=in_array($field,['purchase_price','sale_price','expenses','unit_sale_price','unit_purchase_price','unit_expense'],true)?6:2;$ledger[$field]=dec_round($value,$scale);}
    $entities=['stock','sales','sale_items','expenses','debts','installments','capital','partners','mudaraba_contracts','losses'];
    if(in_array($entity,$entities,true)){
        if($event==='create'){
            $pdo->prepare("UPDATE {$entity} SET currency_code=? WHERE id=? AND ".shop_sql())->execute([$context['code'],(int)$id]);
            if(in_array($entity,['sales','sale_items','debts'],true))$pdo->prepare("UPDATE {$entity} SET profit_known=? WHERE id=? AND ".shop_sql())->execute([($context['profit_known']??true)?1:0,(int)$id]);
        }
        elseif(in_array($event,['edit','adjustment','payment_edit'],true))currency_assert_record($entity,$id,$context['code']);
    }
    if($entity==='mudaraba_contracts'&&!($context['profit_known']??true))$pdo->prepare('UPDATE mudaraba_contracts SET profit_known=0 WHERE id=? AND (currency_code=? OR currency_code=?)')->execute([(int)$id,$context['code'],$context['cost_currency']??$context['code']]);
    $_SESSION['report_currency']=$context['code'];
    $pdo->prepare('INSERT INTO currency_transactions (entity_type,entity_id,event_type,currency_code,exchange_rate,ledger_currency,original_amounts,ledger_amounts,created_by,accounting_mode,profit_known,related_currency,shop_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'.(function_exists('shop_id')?(int)shop_id():1).')')->execute([$entity,(int)$id,$event,$context['code'],'1.00000000',$context['code'],json_encode($ledger,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($ledger,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$_SESSION['user_id']??null,'native',($context['profit_known']??true)?1:0,$context['cost_currency']??null]);
    if(function_exists('finance_currency_event'))finance_currency_event($entity,(int)$id,$event);
}
function currency_saved_context($entity,$id){global $pdo;$s=$pdo->prepare("SELECT * FROM currency_transactions WHERE entity_type=? AND entity_id=? AND event_type='create' AND ".shop_sql()." ORDER BY id LIMIT 1");$s->execute([$entity,(int)$id]);$r=$s->fetch();return $r?['code'=>$r['currency_code'],'rate'=>$r['exchange_rate'],'mode'=>$r['accounting_mode'],'original'=>json_decode($r['original_amounts'],true),'ledger'=>json_decode($r['ledger_amounts'],true)]:['code'=>in_array($entity,['stock','sales','sale_items','expenses','debts','installments','capital','partners','mudaraba_contracts','losses'],true)?currency_entity_code($entity,$id):'AFN','rate'=>'1.00000000','mode'=>'native','original'=>[]];}
function currency_historical_format($entity,$id,$field,$amount,$scale=2,$showCurrency=true){$ctx=currency_saved_context($entity,$id);$value=isset($ctx['ledger'][$field],$ctx['original'][$field])&&dec_round($ctx['ledger'][$field],$scale)===dec_round($amount,$scale)?dec_round($ctx['original'][$field],$scale):currency_document_amount($amount,$scale);$text=number_format((float)$value,$scale,'.',',');if(get_current_lang()!=='en')$text=to_persian_num($text);return $text.($showCurrency?' '.currency_base_code():'');}
function currency_badge($entity,$id){return '<span class="badge badge-secondary">'.sanitize(currency_entity_code($entity,$id)).'</span>';}
function currency_select($name,$code=null,$extra=''){
    $code=$code??currency_default_entry();$out='<select name="'.sanitize($name).'" class="form-control" required '.$extra.'>';
    $row=currency_find($code);if(!$row||!$row['enabled'])$out.='<option value="" selected>'.__t('Enable the saved currency in Settings','ارز ثبت‌شده را در تنظیمات فعال کنید','ثبت شوي اسعار په تنظیماتو کې فعال کړئ').' ('.sanitize($code).')</option>';
    foreach(currency_list(true) as $c)$out.='<option value="'.$c['code'].'"'.($c['code']===$code?' selected':'').'>'.sanitize($c['code'].' — '.$c['name']).'</option>';
    return $out.'</select>';
}
function currency_fields(array $fields,$initial='ledger',$code=null){
    $ctx=$GLOBALS['_currency_request']??null;$code=$code??($ctx['code']??$_POST['currency_code']??currency_default_entry());$map=[];foreach($fields as $name=>$scale){if(is_int($name)){$name=$scale;$scale=2;}$map[$name]=$scale;}
    echo '<div class="currency-fields" data-money-fields="'.sanitize(json_encode($map)).'" style="display:flex;gap:12px;flex-wrap:wrap;padding:12px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;margin:12px 0;grid-column:1/-1"><label class="form-label" style="flex:1;min-width:140px">'.__t('Transaction currency','ارز معامله','د معاملې اسعار').currency_select('currency_code',$code).'</label></div>';
}
function currency_settings_save(array $post,$userId){
    global $pdo;$enabled=$post['enabled']??[];$supported=['AFN','USD','AED','EUR'];
    if(!is_array($enabled)||!$enabled||array_diff($enabled,$supported))throw new InvalidArgumentException(__t('Enable at least one supported currency.','حداقل یک ارز معتبر را فعال کنید.','لږ تر لږه یو معتبر اسعار فعال کړئ.'));
    $pdo->beginTransaction();try{foreach($supported as $code)$pdo->prepare('UPDATE currencies SET enabled=?,updated_at=CURRENT_TIMESTAMP WHERE code=?')->execute([in_array($code,$enabled,true)?1:0,$code]);$pdo->commit();unset($GLOBALS['_currency_rows']);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function currency_account_filter(){
    $out='<div class="header-currency"><label for="headerReportCurrency">'.__t('View account','نمایش حساب','حساب وګورئ').'</label><select id="headerReportCurrency" name="report_currency" class="form-control">';
    foreach(currency_list() as $c)$out.='<option value="'.$c['code'].'"'.($c['code']===currency_base_code()?' selected':'').'>'.$c['code'].'</option>';
    return $out.'</select></div>';
}
function currency_unknown_profit_count($code=null,$start=null,$end=null){
    global $pdo;$code=$code??currency_base_code();$args=[$code,$code];$dates='';
    if($start&&$end){$dates=' AND s.created_at BETWEEN ? AND ?';$args[]=$start;$args[]=$end;}
    $s=$pdo->prepare('SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE NOT EXISTS(SELECT 1 FROM sales_returns sr WHERE sr.sale_id=s.id AND sr.shop_id=s.shop_id AND sr.status=\'posted\') AND si.profit_known=0 AND (si.currency_code=? OR si.cost_currency_code=?) AND '.shop_sql('s').$dates);$s->execute($args);$count=(int)$s->fetchColumn();
    $args=[$code,$code];$dates='';if($start&&$end){$dates=' AND COALESCE(expense_date,created_at) BETWEEN ? AND ?';$args[]=substr($start,0,10);$args[]=$end;}
    $s=$pdo->prepare("SELECT COUNT(*) FROM ".live_documents_source('expenses')." WHERE expense_type='ضرر' AND inventory_cost IS NOT NULL AND inventory_currency_code<>currency_code AND (currency_code=? OR inventory_currency_code=?) AND ".shop_sql().$dates);$s->execute($args);return $count+(int)$s->fetchColumn();
}
/** Report totals in their saved currencies, including disabled currencies with history. */
function currency_account_summary($start=null,$end=null){
    global $pdo;$rows=[];
    foreach(currency_list() as $currency){
        $code=$currency['code'];$row=['code'=>$code];
        $queries=[
            'sales'=>['sales','total_amount','created_at','1=1'],
            'paid'=>['sales','paid_amount','created_at','1=1'],
            'profit'=>['sales','profit','created_at','1=1'],
            'expenses'=>[live_documents_source('expenses'),'amount','COALESCE(expense_date,created_at)',"(expense_type<>'ضرر' OR expense_type IS NULL)"],
            'losses'=>[live_documents_source('expenses'),'amount','COALESCE(expense_date,created_at)',"expense_type='ضرر'"],
            'receipts'=>['installments','amount','payment_date',"debt_id IN (SELECT id FROM debts WHERE debt_type='receivable' OR debt_type IS NULL)"],
            'debt_payments'=>['installments','amount','payment_date',"debt_id IN (SELECT id FROM debts WHERE debt_type='payable')"],
            'unapplied'=>['installments','amount','payment_date',"applied_to_balance=0 AND debt_id IN (SELECT id FROM debts WHERE debt_type='receivable' OR debt_type IS NULL)"],
            'unapplied_out'=>['installments','amount','payment_date',"applied_to_balance=0 AND debt_id IN (SELECT id FROM debts WHERE debt_type='payable')"],
            'purchases'=>['inventory_purchases','total_amount','purchase_date','1=1'],
            'receivables'=>['debts','remaining_amount',null,"(debt_type='receivable' OR debt_type IS NULL)"],
            'payables'=>['debts','remaining_amount',null,"debt_type='payable'"],
            'inventory'=>['stock','COALESCE(inventory_value,quantity*(purchase_price+expenses))',null,'quantity>0'],
            'capital_in'=>[live_documents_source('capital'),'amount','COALESCE(transaction_date,created_at)',"transaction_type<>'برداشت'"],
            'capital_out'=>[live_documents_source('capital'),'amount','COALESCE(transaction_date,created_at)',"transaction_type='برداشت'"]
        ];
        foreach($queries as $key=>[$table,$field,$date,$where]){
            if($table==='inventory_purchases')$field="(inventory_purchases.total_amount-COALESCE((SELECT SUM(r.total_amount) FROM purchase_returns r WHERE r.purchase_id=inventory_purchases.id AND r.shop_id=inventory_purchases.shop_id),0))";
            if($table==='sales'&&in_array($field,['total_amount','profit'],true))$field=sale_net_sql($field);
            if($table==='sales')$where.=' AND NOT EXISTS(SELECT 1 FROM sales_returns sr WHERE sr.sale_id=sales.id AND sr.shop_id=sales.shop_id AND sr.status=\'posted\')';
            $sql="SELECT COALESCE(SUM({$field}),0) FROM {$table} WHERE currency_code=? AND ({$where}) AND ".shop_sql();$args=[$code];
            if($date&&$start&&$end){$sql.=" AND {$date} BETWEEN ? AND ?";$args[]=substr($start,0,10);$args[]=$end;}
            $q=$pdo->prepare($sql);$q->execute($args);$row[$key]=dec_round($q->fetchColumn());
        }
        $row['profit_known']=currency_unknown_profit_count($code,$start,$end)===0;
        $row['net_profit']=dec_sub(dec_sub($row['profit'],$row['expenses']),$row['losses']);$rows[]=$row;
    }
    return $rows;
}
function currency_render_summary($start=null,$end=null){
    $heads=['code'=>__t('Currency','ارز','اسعار'),'sales'=>__t('Sales','فروش','پلور'),'purchases'=>__t('Purchases','خرید','پېرود'),'expenses'=>__t('Expenses','مصارف','مصارف'),'losses'=>__t('Losses','ضرر','زیان'),'receipts'=>__t('Receipts','وصول اقساط','رسیدونه'),'debt_payments'=>__t('Debt payments','پرداخت بدهی','د پور تادیات'),'unapplied_out'=>__t('Unapplied payments','پرداخت تسویه‌نشده','نا تصفیه تادیات'),'unapplied'=>__t('Unapplied receipts','دریافت تسویه‌نشده','نا تصفیه رسیدونه'),'receivables'=>__t('Receivables balance','ماندهٔ طلب','پاتې طلب'),'payables'=>__t('Payables balance','ماندهٔ بدهی','پاتې پور'),'inventory'=>__t('Inventory balance','ارزش موجودی','د موجودۍ ارزښت'),'capital_in'=>__t('Capital in','ورود سرمایه','ورود پانګه'),'capital_out'=>__t('Capital out','خروج سرمایه','وتلې پانګه'),'net_profit'=>__t('Net profit','سود خالص','خالصه ګټه')];
    if(is_feature_locked('liquidity_report'))foreach(['receipts','debt_payments','unapplied','unapplied_out','receivables','payables'] as $key)unset($heads[$key]);
    if(is_feature_locked('capital_turnover'))foreach(['capital_in','capital_out'] as $key)unset($heads[$key]);
    echo '<div class="card" id="currencyAccounts"><h3 class="card-title">'.__t('Separate currency accounts','حساب‌های جداگانهٔ هر ارز','د هر اسعارو جلا حسابونه').'</h3><p>'.__t('No combined total. Different currencies are never added or converted. Foreign receipts remain unapplied to the original debt. Mixed-currency profit is not calculated. Balances are current; activity follows the selected dates.','مجموع مشترک وجود ندارد؛ ارزهای متفاوت جمع یا تبدیل نمی‌شوند. دریافت به ارز دیگر، ماندهٔ قرضهٔ اصلی را کم نمی‌کند. سود چندارزی محاسبه نمی‌شود. مانده‌ها فعلی و فعالیت‌ها مربوط به تاریخ انتخابی‌اند.','ګډ مجموع نشته؛ بېلابېل اسعار نه جمع او نه بدلېږي. د بل اسعارو رسید اصلي پور نه کموي. د څو اسعارو ګټه نه حسابېږي.').'</p><div class="table-responsive"><table class="table"><thead><tr>';
    foreach($heads as $h)echo '<th>'.sanitize($h).'</th>';echo '</tr></thead><tbody>';
    foreach(currency_account_summary($start,$end) as $row){echo '<tr data-currency="'.$row['code'].'">';foreach($heads as $key=>$h)echo '<td data-metric="'.$key.'">'.($key==='code'?sanitize($row[$key]):($key==='net_profit'?currency_profit_html($row[$key],$row['profit_known'],$row['code']):currency_format($row[$key],$row['code']))).'</td>';echo '</tr>';}
    echo '</tbody></table></div></div>';
}
/** Native contract fields remain untouched; other currency accounts are read from immutable events. */
function currency_contract_view(array $contract,$code=null){
    global $pdo;$code=$code??currency_base_code();$view=$contract;$view['_native']=$contract;$view['_has_account']=$contract['currency_code']===$code;
    if($view['_has_account'])return $view;
    $fields=['capital_amount','total_sales_amount','total_profit_amount','paid_profit_to_partner','returned_capital'];
    foreach($fields as $field)$view[$field]='0.00';$view['currency_code']=$code;$view['profit_known']=1;
    $s=$pdo->prepare("SELECT * FROM currency_transactions WHERE entity_type='mudaraba_contracts' AND entity_id=? AND accounting_mode='native' AND ".shop_sql()." ORDER BY id");$s->execute([$contract['id']]);
    foreach($s as $event){
        if(!$event['profit_known']&&($event['currency_code']===$code||$event['related_currency']===$code)){$view['profit_known']=0;$view['_has_account']=true;}
        if($event['currency_code']!==$code)continue;$view['_has_account']=true;$amounts=json_decode($event['ledger_amounts'],true);
        $map=['purchase'=>['capital_amount'=>'capital_amount'],'sale'=>['add_sales'=>'total_sales_amount','add_profit'=>'total_profit_amount'],'return'=>['add_sales'=>'total_sales_amount','add_profit'=>'total_profit_amount'],'progress'=>['add_sales'=>'total_sales_amount','add_profit'=>'total_profit_amount'],'payout'=>['payout_profit'=>'paid_profit_to_partner','return_capital'=>'returned_capital']][$event['event_type']]??[];
        foreach($map as $from=>$to)$view[$to]=dec_add($view[$to],$amounts[$from]??0);
    }
    return $view;
}
function currency_contract_views(array $contracts,$code=null){$rows=[];foreach($contracts as $c){$v=currency_contract_view($c,$code);if($v['_has_account'])$rows[]=$v;}return $rows;}
function currency_contract_accounts_html(array $contract){
    $html='<div class="table-responsive"><table class="table"><thead><tr><th>'.__t('Currency','ارز','اسعار').'</th><th>'.__t('Capital','سرمایه','پانګه').'</th><th>'.__t('Sales','فروش','پلور').'</th><th>'.__t('Profit','سود','ګټه').'</th><th>'.__t('Paid profit','سود پرداخت‌شده','ورکړل شوې ګټه').'</th><th>'.__t('Returned capital','سرمایهٔ مسترد','بېرته ورکړل شوې پانګه').'</th></tr></thead><tbody>';
    foreach(currency_list() as $cur){$c=currency_contract_view($contract,$cur['code']);if(!$c['_has_account'])continue;$html.='<tr><td>'.$cur['code'].'</td>';foreach(['capital_amount','total_sales_amount','total_profit_amount','paid_profit_to_partner','returned_capital'] as $field)$html.='<td>'.($field==='total_profit_amount'?currency_profit_html($c[$field],$c['profit_known'],$cur['code']):currency_format($c[$field],$cur['code'])).'</td>';$html.='</tr>';}
    return $html.'</tbody></table></div>';
}
function currency_unapplied_receipts_html($saleId){
    global $pdo;$s=$pdo->prepare('SELECT i.* FROM installments i JOIN debts d ON d.id=i.debt_id WHERE d.sale_id=? AND i.applied_to_balance=0 AND '.shop_sql('i').' ORDER BY i.id');$s->execute([(int)$saleId]);$rows=$s->fetchAll();if(!$rows)return '';
    $html='<div class="unapplied-receipts"><p>'.__t('Received separately; not deducted from the invoice balance.','دریافت‌های جداگانه؛ از ماندهٔ این بل کم نشده‌اند.','جلا رسیدونه؛ د دې بل له پاتې حساب نه دي کم شوي.').'</p><ul>';
    foreach($rows as $row)$html.='<li>'.sanitize($row['payment_date']).' — '.currency_format($row['amount'],$row['currency_code']).'</li>';
    return $html.'</ul></div>';
}
