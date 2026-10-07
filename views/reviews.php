<?php
function reviewList(string $type, int $target): void {
    $table = $type === 'product' ? 'product_reviews' : 'seller_reviews';
    $column = $type === 'product' ? 'product_id' : 'supplier_id';
    $summary = row("SELECT COUNT(*) AS n, AVG(rating) AS average FROM $table WHERE $column=?", [$target]);
    $reviews = rows("SELECT r.*, u.name, p.business_name FROM $table r JOIN users u ON u.id=r.retailer_id JOIN retailers p ON p.user_id=r.retailer_id WHERE r.$column=? ORDER BY r.updated_at DESC,r.id DESC LIMIT 100", [$target]);
    ?>
    <section class="panel review-section"><div class="section-heading"><div><h2><?= $type === 'product' ? 'Product reviews' : 'Seller reviews' ?></h2><p class="muted">From retailers with completed purchases.</p></div><span class="rating-summary"><?= $summary['n'] ? e(number_format((float)$summary['average'],1)) . ' / 5 · ' . (int)$summary['n'] . ' reviews' : 'No ratings yet' ?></span></div>
    <?php if (!$reviews) emptyState('Be the first to review', 'After completing a purchase, open its order details to review the products and seller.'); ?>
    <?php foreach ($reviews as $review): ?><article class="customer-review"><div class="review-meta"><strong><?= e($review['business_name']) ?></strong><span class="stars" aria-label="<?= (int)$review['rating'] ?> out of 5 stars"><?= str_repeat('★',(int)$review['rating']) . str_repeat('☆',5-(int)$review['rating']) ?></span></div><p><?= nl2br(e($review['comment'])) ?></p><small><?= e($review['name']) ?> · Verified purchase · Updated <?= e(date('M j, Y',strtotime($review['updated_at']))) ?></small></article><?php endforeach ?>
    <?php if ((int)$summary['n'] > 100): ?><p class="field-help">Showing the 100 most recently updated reviews.</p><?php endif ?></section>
<?php }
