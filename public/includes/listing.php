<?php
/** Server-side list controls. Only caller-owned SQL identifiers can reach ORDER BY. */
function listing_state(array $sorts,string $default='newest',int $defaultSize=25): array {
 $sort=is_string($_GET['sort']??null)?$_GET['sort']:$default;if(!isset($sorts[$sort]))$sort=$default;
 if(!isset($sorts[$sort]))throw new InvalidArgumentException('Unknown default list order.');
 $size=is_scalar($_GET['per_page']??null)?(int)$_GET['per_page']:$defaultSize;if(!in_array($size,[25,50,100],true))$size=$defaultSize;
 $page=is_scalar($_GET['p']??null)?(int)$_GET['p']:1;$page=max(1,min(100000,$page));
 return ['sort'=>$sort,'order'=>$sorts[$sort][0],'sorts'=>$sorts,'size'=>$size,'page'=>$page];
}
function listing_count(array $state,int $count): array {
 $state['count']=max(0,$count);$state['pages']=max(1,(int)ceil($state['count']/$state['size']));$state['page']=min($state['page'],$state['pages']);$state['offset']=($state['page']-1)*$state['size'];return $state;
}
function listing_fields(array $state): void { ?>
<label><?= __t('Sort by','ترتیب لست','د لست ترتیب') ?><select class="form-control" name="sort"><?php foreach($state['sorts'] as $key=>[$sql,$en,$da]):?><option value="<?= sanitize($key) ?>" <?= $key===$state['sort']?'selected':'' ?>><?= __t($en,$da,$en) ?></option><?php endforeach ?></select></label>
<label><?= __t('Rows per page','تعداد در هر صفحه','په هره پاڼه شمېر') ?><select class="form-control" name="per_page"><?php foreach([25,50,100] as $size):?><option value="<?= $size ?>" <?= $size===$state['size']?'selected':'' ?>><?= ui_number($size,0) ?></option><?php endforeach ?></select></label>
<?php }
function listing_link(int $page): string {
 $query=[];foreach($_GET as $key=>$value)if(is_string($key)&&is_scalar($value)&&!in_array($key,['p','edit','id'],true))$query[$key]=(string)$value;$query['p']=$page;return '?'.http_build_query($query);
}
function listing_footer(array $state): void { ?>
<nav class="list-pagination" aria-label="<?= __t('List pages','صفحه‌های لست','د لست پاڼې') ?>"><span><?= __t('Total','مجموع','ټول') ?>: <?= ui_number($state['count'],0) ?> · <?= __t('Page','صفحه','پاڼه') ?> <?= ui_number($state['page'],0) ?> / <?= ui_number($state['pages'],0) ?></span><div><?php if($state['page']>1):?><a class="btn btn-secondary" rel="prev" href="<?= sanitize(listing_link($state['page']-1)) ?>"><?= __t('Previous','قبلی','مخکینۍ') ?></a><?php endif ?><?php if($state['page']<$state['pages']):?><a class="btn btn-secondary" rel="next" href="<?= sanitize(listing_link($state['page']+1)) ?>"><?= __t('Next','بعدی','راتلونکې') ?></a><?php endif ?></div></nav>
<?php }
function listing_query(PDO $db,string $sql,array $params,array $sorts,string $default='newest'): array {
 $list=listing_state($sorts,$default);$q=$db->prepare('SELECT COUNT(*) FROM ('.$sql.') list_count');$q->execute($params);$list=listing_count($list,(int)$q->fetchColumn());$q=$db->prepare($sql.' ORDER BY '.$list['order'].' LIMIT '.$list['size'].' OFFSET '.$list['offset']);$q->execute($params);return [$list,$q->fetchAll()];
}
function listing_controls_form(array $state): void { ?>
<form class="card modern-form form-grid list-filters" method="get"><?php foreach($_GET as $name=>$value)if(is_string($name)&&is_scalar($value)&&!in_array($name,['sort','per_page','p','edit','id'],true)):?><input type="hidden" name="<?= sanitize($name) ?>" value="<?= sanitize((string)$value) ?>"><?php endif ?><?php listing_fields($state) ?><button class="btn btn-primary"><?= __t('Apply list settings','نمایش لست','د لست تنظیمات وښایئ') ?></button></form>
<?php }
