<?php
$targets = [['type'=>'seller','id'=>(int)$order['supplier_id'],'name'=>$order['supplier']]];
foreach (rows('SELECT DISTINCT product_id,product_name FROM order_items WHERE order_id=?',[$order['id']]) as $item) $targets[]=['type'=>'product','id'=>(int)$item['product_id'],'name'=>$item['product_name']];
?>
<section class="panel editor"><h2>Review your purchase</h2><p class="muted">Share your experience with the products and seller. You can update your reviews here.</p>
<?php foreach ($targets as $target):
$table=$target['type']==='product'?'product_reviews':'seller_reviews';$column=$target['type']==='product'?'product_id':'supplier_id';
$review=row("SELECT * FROM $table WHERE retailer_id=? AND $column=?",[$uid,$target['id']]);
$failed=$error && ($_POST['action']??'')==='save-review' && ($_POST['review_type']??'')===$target['type'] && ($target['type']==='seller' || (int)($_POST['product_id']??0)===$target['id']);
$values=$failed?$_POST:($review??[]);
?><details class="review-editor" <?= $failed?'open':'' ?>><summary><?= $review?'Edit':'Write' ?> <?= e($target['type']) ?> review · <?= e($target['name']) ?></summary>
<form method="post" class="form-stack"><?= csrf() ?><input type="hidden" name="action" value="save-review"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><input type="hidden" name="review_type" value="<?= e($target['type']) ?>"><input type="hidden" name="product_id" value="<?= $target['type']==='product'?$target['id']:0 ?>">
<label>Rating for <?= e($target['name']) ?><select name="rating" required><option value="">Choose a rating</option><?php for($rating=5;$rating>=1;$rating--): ?><option value="<?= $rating ?>" <?= (int)($values['rating']??0)===$rating?'selected':'' ?>><?= $rating ?> / 5 — <?= ['','Poor','Fair','Good','Very good','Excellent'][$rating] ?></option><?php endfor ?></select></label>
<label>Your <?= e($target['type']) ?> review<textarea name="comment" rows="3" maxlength="2000" required placeholder="What should other retailers know?"><?= e($values['comment']??'') ?></textarea></label><p class="field-help">Published with your name and company. One review per product or seller; saving again updates your existing review.</p><button class="button">Save <?= e($target['type']) ?> review</button></form></details>
<?php endforeach ?></section>
