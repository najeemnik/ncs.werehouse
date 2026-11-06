<?php
require_once __DIR__.'/../includes/functions.php';require_once __DIR__.'/../includes/listing.php';
$pageTitle=__t('Purchase History','تاریخچهٔ خرید','د پېرود تاریخچه');
require_once __DIR__.'/../includes/header.php';
$id=(int)($_GET['id']??0); $purchase=null;
if($id) { $s=$pdo->prepare('SELECT * FROM inventory_purchases WHERE id=? AND '.shop_sql());$s->execute([$id]);$purchase=$s->fetch(); }
?>
<div class="card">
 <div class="card-header"><h3 class="card-title"><?= __t('Purchase History','تاریخچهٔ خرید','د پېرود تاریخچه') ?></h3><a class="btn btn-primary btn-sm" href="stock_add.php"><?= __t('New purchase','خرید جدید','نوی پېرود') ?></a></div>
 <?php if($id && !$purchase): ?><div class="alert alert-warning"><?= __t('Purchase not found.','خرید یافت نشد.','پېرود ونه موندل شو.') ?></div><?php endif; ?>
 <?php if($purchase): $s=$pdo->prepare('SELECT * FROM inventory_purchase_items WHERE purchase_id=? AND '.shop_sql().' ORDER BY id');$s->execute([$id]);$items=$s->fetchAll(); ?>
 <h3><?= sanitize($purchase['reference']) ?></h3>
 <p><?= sanitize($purchase['supplier']) ?> · <?= sanitize($purchase['purchase_date']) ?> · <?= $purchase['mode']==='lot'?__t('Lot purchase','خرید لات','د ټولګې پېرود'):__t('Individual purchase','خرید تکی','انفرادي پېرود') ?></p>
 <p><?= __t('Original purchase + expenses = total','خرید اصلی + مصارف = مجموع','اصلي پېرود + مصارف = ټول') ?>:
 <?= currency_format($purchase['purchase_amount'],$purchase['currency_code']) ?> + <?= currency_format($purchase['expense_amount'],$purchase['currency_code']) ?> = <strong><?= currency_format($purchase['total_amount'],$purchase['currency_code']) ?></strong></p>
 <p><?= __t('Allocation','تقسیم هزینه','د لګښت وېش') ?>: <?= $purchase['allocation_method']==='value'?__t('proportional purchase value','به تناسب ارزش خرید','د پېرود ارزښت په تناسب'):__t('proportional quantity','به تناسب تعداد','د مقدار په تناسب') ?></p>
 <div class="table-responsive"><table class="table"><thead><tr>
 <?php foreach([__t('Product / category','جنس / کتگوری','توکی / کټګوري'),'SKU / Serial',__t('Quantity / unit','مقدار / واحد','مقدار / واحد'),__t('Purchase','خرید','پېرود'),__t('Expenses','مصارف','مصارف'),__t('Allocated unit cost','قیمت تمام‌شده فی واحد','د واحد ټول لګښت'),__t('Selling price','قیمت فروش','د پلور بیه')] as $h): ?><th><?= sanitize($h) ?></th><?php endforeach; ?>
 </tr></thead><tbody><?php foreach($items as $line): ?><tr><td><?= sanitize($line['product_name']) ?><small style="display:block"><?= sanitize($line['category_name']??'') ?></small><?php if($line['notes']): ?><small style="display:block"><?= sanitize($line['notes']) ?></small><?php endif; ?></td><td><?= sanitize(($line['sku']??'').' / '.($line['serial_number']??'')) ?></td><td><?= format_qty($line['quantity']).' '.sanitize($line['unit']) ?></td><td><?= currency_format($line['original_purchase_amount'],$purchase['currency_code']) ?></td><td><?= currency_format($line['original_expense_amount'],$purchase['currency_code']) ?></td><td><?= currency_format(dec_div(dec_add($line['original_purchase_amount'],$line['original_expense_amount']),$line['quantity'],6),$purchase['currency_code'],6) ?></td><td><?= currency_format($line['selling_price_original'],$line['selling_currency_code']??$purchase['currency_code'],6) ?></td></tr><?php endforeach; ?></tbody></table></div>
 <p style="white-space:pre-wrap"><?= sanitize($purchase['notes']??'') ?></p>
 <a href="purchases.php" class="btn btn-secondary btn-sm"><?= __t('All purchases','تمام خریدها','ټول پېرودونه') ?></a>
 <?php else:
 $search=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';$where=currency_sql().' AND '.shop_sql();$params=[];if($search!==''){$where.=' AND (reference LIKE ? OR supplier LIKE ?)';$params=['%'.$search.'%','%'.$search.'%'];}$list=listing_state(['newest'=>['id DESC','Newest first','تازه‌ترین اول'],'oldest'=>['id ASC','Oldest first','قدیمی‌ترین اول'],'date'=>['purchase_date DESC,id DESC','Purchase date','تاریخ خرید'],'amount_high'=>['total_amount DESC,id DESC','Amount: high to low','مبلغ: زیاد به کم'],'amount_low'=>['total_amount ASC,id ASC','Amount: low to high','مبلغ: کم به زیاد']]);$count=$pdo->prepare('SELECT COUNT(*) FROM inventory_purchases WHERE '.$where);$count->execute($params);$list=listing_count($list,(int)$count->fetchColumn());$q=$pdo->prepare('SELECT * FROM inventory_purchases WHERE '.$where.' ORDER BY '.$list['order'].' LIMIT '.$list['size'].' OFFSET '.$list['offset']);$q->execute($params);$rows=$q->fetchAll();
 ?>
 <p style="color:var(--text-muted)"><?= __t('New purchases are listed here with immutable currency and cost snapshots. Existing inventory and historical records have not been replaced.','خریدهای جدید با ارز و هزینهٔ اصلی ثبت می‌شود. موجودی و سوابق قبلی جایگزین نشده است.','نوي پېرودونه د اصلي اسعارو او لګښتونو سره ثبتېږي. پخواني معلومات نه دي بدل شوي.') ?></p>
 <form class="modern-form form-grid" method="get"><label><?= __t('Reference or supplier','مرجع یا عرضه‌کننده','شمېره یا عرضه کوونکی') ?><input class="form-control" name="q" value="<?= sanitize($search) ?>"></label><?php listing_fields($list) ?><button class="btn btn-primary"><?= __t('Search','جستجو','لټون') ?></button></form>
 <div class="table-responsive"><table class="table"><thead><tr><th><?= __t('Reference','مرجع','شمېره') ?></th><th><?= __t('Date','تاریخ','نېټه') ?></th><th><?= __t('Supplier','تأمین‌کننده','عرضه کوونکی') ?></th><th><?= __t('Original total','مجموع اصلی','اصلي ټول') ?></th><th><?= __t('Purchase type','نوع خرید','د پېرود ډول') ?></th></tr></thead><tbody>
 <?php foreach($rows as $r): ?><tr><td><a href="?id=<?= $r['id'] ?>"><?= sanitize($r['reference']) ?></a></td><td><?= format_date($r['purchase_date']) ?></td><td><?= sanitize($r['supplier']) ?></td><td><?= currency_format($r['total_amount'],$r['currency_code']) ?></td><td><?= $r['mode']==='lot'?__t('Lot','لات','ټولګه'):__t('Individual','تکی','انفرادي') ?></td></tr><?php endforeach; ?>
 <?php if(!$rows): ?><tr><td colspan="5"><?= __t('No new purchases recorded yet.','هنوز خرید جدیدی ثبت نشده است.','تر اوسه نوی پېرود نه دی ثبت شوی.') ?></td></tr><?php endif; ?>
 </tbody></table></div>
 <?php listing_footer($list); ?>
 <?php endif; ?>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
