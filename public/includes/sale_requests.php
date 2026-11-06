<?php
/** POS request identity: scoped to a branch and bound to the exact submitted content. */
function sale_request_key($value): string {
    if(!is_string($value)||!preg_match('/^[a-zA-Z0-9_-]{16,64}$/D',$value))throw new InvalidArgumentException(__t('Invalid sale request. Refresh the form.','درخواست فروش نامعتبر است؛ فرم را تازه کنید.','د پلور غوښتنه ناسمه ده؛ فورمه تازه کړئ.'));
    return $value;
}
function sale_request_hash(array $content): string {
    return hash('sha256',json_encode($content,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR));
}
/** Return the recorded invoice only when both key and content match. */
function sale_request_existing(int $branch,string $key,string $contentHash,bool $lock=false): ?int {
    global $pdo;
    $sql='SELECT id,sale_content_hash FROM sales WHERE shop_id=? AND sale_request_key=?'.($lock&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');
    $q=$pdo->prepare($sql);$q->execute([$branch,$key]);$row=$q->fetch();if(!$row)return null;
    if(!is_string($row['sale_content_hash'])||!hash_equals($row['sale_content_hash'],$contentHash))throw new InvalidArgumentException(__t('This sale request key was already used with different values. Open a new sale form.','این کلید درخواست قبلاً با مقادیر متفاوت استفاده شده است؛ فورم فروش تازه باز کنید.','دا غوښتنځای مخکې له بېلو ارزښتونو سره کارول شوی؛ نوې فورمه پرانیزئ.'));
    return (int)$row['id'];
}
