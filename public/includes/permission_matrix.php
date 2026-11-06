<?php
/** Shared, complete domain-permission presentation for central account management. */
function ui_permission_group_meta(): array {
    return [
      'dashboard'=>['fa-house-chimney',1],'sales'=>['fa-cart-shopping',2],'purchases'=>['fa-bag-shopping',3],'suppliers'=>['fa-truck-field',4],
      'inventory'=>['fa-boxes-stacked',5],'customers'=>['fa-users',6],'debts'=>['fa-file-invoice',7],'expenses'=>['fa-receipt',8],'losses'=>['fa-house-crack',9],
      'treasury'=>['fa-building-columns',10],'accounting'=>['fa-book-open',11],'capital'=>['fa-vault',12],'reports'=>['fa-chart-pie',13],'closings'=>['fa-calendar-check',14],
      'partners'=>['fa-user-group',15],'mudaraba'=>['fa-handshake-angle',16],'branches'=>['fa-code-branch',17],'staff'=>['fa-user-shield',18],
      'audit'=>['fa-clock-rotate-left',19],'ai'=>['fa-wand-magic-sparkles',20],'settings'=>['fa-gear',21],'backup'=>['fa-database',22],'about'=>['fa-circle-info',23],
    ];
}
function ui_permission_groups(): array {
    $groups=[];foreach(domain_catalog() as $feature=>$label)$groups[explode('.',$feature)[0]][$feature]=$label;
    $meta=ui_permission_group_meta();uksort($groups,fn($a,$b)=>(($meta[$a][1]??99)<=>($meta[$b][1]??99))?:strcmp($a,$b));return $groups;
}
function ui_render_office2_permission_matrix(int $userId): void {
    $catalog=domain_catalog();
    ?>
    <div class="permission-matrix-intro"><div><h4><?= __t('One clear permission map','یک نقشهٔ روشن صلاحیت‌ها','د اجازو یو روښانه نقشه') ?></h4><p><?= __t('These are the same services shown in the user workspace. Open a group, then choose inherit, enabled, locked or hidden for each item.','این‌ها همان خدمات داخل فضای کاری یوزر هستند. گروه را باز کنید و برای هر گزینه ارث‌بری، فعال، قفل یا پنهان را انتخاب کنید.','دا هغه خدمتونه دي چې د کارن په کاري ځای کې دي؛ ګروپ پرانیزئ او حالت وټاکئ.') ?></p></div><span class="permission-total-badge"><?= count($catalog) ?> <?= __t('controls','کنترل','کنټرول') ?></span></div>
    <div class="permission-matrix-toolbar">
      <label class="permission-preset-field"><span><?= __t('Quick setup','تنظیم سریع','چټک تنظیم') ?></span><select class="form-control" name="permission_preset"><option value="custom"><?= __t('Use detailed choices below','استفاده از انتخاب‌های جزئی پایین','لاندې غوره‌توبونه') ?></option><option value="full"><?= __t('Full access within business limits','دسترسی کامل در حدود تجارت','بشپړ لاسرسی') ?></option><option value="read_only"><?= __t('Read and print only','فقط خواندن و چاپ','یوازې لوستل او چاپ') ?></option><option value="inherit"><?= __t('Keep all existing rules','حفظ تمام قواعد موجود','اوسني قواعد وساتئ') ?></option></select></label>
      <label class="permission-search-field"><span><?= __t('Find a permission','جستجوی صلاحیت','د اجازې لټون') ?></span><div><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" data-permission-search placeholder="<?= sanitize(__t('Example: Reports or Dashboard','مثلاً: راپور یا داشبورد','راپور یا ډشبورډ')) ?>" autocomplete="off"></div></label>
    </div>
    <input type="hidden" name="policy_matrix_started" value="1"><input type="hidden" name="policy_matrix_complete" value="<?= count($catalog) ?>">
    <div class="office2-domain-groups" data-permission-groups>
    <?php foreach(ui_permission_groups() as $module=>$features):$meta=ui_permission_group_meta()[$module]??['fa-folder',99]; ?>
      <details class="office2-domain-group" data-permission-group <?= $module==='dashboard'?'open':'' ?>>
        <summary><span class="permission-group-title"><span class="permission-group-icon"><i class="fa-solid <?= sanitize($meta[0]) ?>"></i></span><span><strong><?= sanitize(domain_label($module)) ?></strong><small><?= __t('Open to manage this section','برای مدیریت این بخش باز کنید','برخه پرانیزئ') ?></small></span></span><span class="permission-group-count"><?= count($features) ?> <i class="fa-solid fa-chevron-down"></i></span></summary>
        <div class="permission-feature-list">
        <?php foreach($features as $feature=>$unused):$policy=domain_policy('user',$userId,$feature);$mode=$policy?((int)$policy['enabled']?'enabled':($policy['visibility']==='hidden'?'hidden':'locked')):'inherit';$search=mb_strtolower(domain_label($feature).' '.$feature); ?>
          <div class="office2-domain-feature" data-permission-row data-search="<?= sanitize($search) ?>">
            <div class="permission-feature-copy"><strong><?= sanitize(domain_label($feature)) ?></strong><code><?= sanitize($feature) ?></code></div>
            <label class="permission-mode-field"><span class="sr-only"><?= sanitize(domain_label($feature)) ?></span><select class="form-control" name="policies[<?= sanitize($feature) ?>][mode]" aria-label="<?= sanitize(domain_label($feature)) ?>">
              <option value="inherit" <?= $mode==='inherit'?'selected':'' ?>><?= __t('Inherit','ارث‌بری','میراث') ?></option><option value="enabled" <?= $mode==='enabled'?'selected':'' ?>><?= __t('Enabled','فعال','فعال') ?></option><option value="locked" <?= $mode==='locked'?'selected':'' ?>><?= __t('Locked · visible','قفل · دیده شود','بند خو ښکاري') ?></option><option value="hidden" <?= $mode==='hidden'?'selected':'' ?>><?= __t('Locked · hidden','قفل · پنهان','بند او پټ') ?></option>
            </select></label>
            <details class="permission-limits"><summary><?= __t('Limits','محدودیت‌ها','حدونه') ?></summary><div><label><span><?= __t('Daily','روزانه','ورځنی') ?></span><input class="form-control" type="number" min="0" max="1000000" placeholder="∞" name="policies[<?= sanitize($feature) ?>][daily_limit]" value="<?= sanitize($policy['daily_limit']??'') ?>"></label><label><span><?= __t('Monthly','ماهانه','میاشتنی') ?></span><input class="form-control" type="number" min="0" max="1000000" placeholder="∞" name="policies[<?= sanitize($feature) ?>][monthly_limit]" value="<?= sanitize($policy['monthly_limit']??'') ?>"></label></div></details>
          </div>
        <?php endforeach ?>
        </div>
      </details>
    <?php endforeach ?>
    </div>
    <p class="permission-no-results" data-permission-empty hidden><?= __t('No permission matches this search.','هیچ صلاحیتی با این جستجو مطابقت ندارد.','هېڅ اجازه ونه موندل شوه.') ?></p>
    <script>
    (()=>{const input=document.querySelector('[data-permission-search]');if(!input||input.dataset.ready)return;input.dataset.ready='1';const groups=[...document.querySelectorAll('[data-permission-group]')],empty=document.querySelector('[data-permission-empty]');input.addEventListener('input',()=>{const query=input.value.trim().toLocaleLowerCase();let visible=0;groups.forEach(group=>{let count=0;group.querySelectorAll('[data-permission-row]').forEach(row=>{const match=!query||row.dataset.search.toLocaleLowerCase().includes(query);row.hidden=!match;if(match)count++;});group.hidden=count===0;if(query&&count)group.open=true;visible+=count;});empty.hidden=visible!==0;});})();
    </script>
    <?php
}
