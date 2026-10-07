<?php
$seller=row('SELECT s.*,u.active FROM suppliers s JOIN users u ON u.id=s.user_id WHERE s.user_id=?',[(int)($_GET['id']??0)]);
if (!$seller) throw new DomainException('Seller not found.');
pageHeading('MEET YOUR BUSINESS PARTNER',$seller['business_name'],'Seller profile and retailer feedback.');
?>
<div class="notice"><div><strong>Company name: <?= e($seller['business_name']) ?></strong><p>Business verification: <?= e($seller['verification_status']) ?><?= !$seller['active']?' · Account inactive':'' ?></p></div><a class="button secondary" href="<?= e(url('orders',['status'=>'completed'])) ?>">Review a completed purchase →</a></div>
<?php $public=preferences((int)$seller['user_id']); ?>
<section class="panel editor"><h2>About the company</h2><p class="description"><?= nl2br(e($public['company_description'] ?: 'No company description added yet.')) ?></p><dl class="business-details"><dt>Contact email</dt><dd><?= e($public['contact_email'] ?: 'Not provided') ?></dd><dt>Contact phone</dt><dd><?= e($public['contact_phone'] ?: 'Not provided') ?></dd><dt>Business hours</dt><dd><?= nl2br(e($public['business_hours'] ?: 'Not provided')) ?></dd></dl></section>
<?php reviewList('seller',(int)$seller['user_id']); ?>
