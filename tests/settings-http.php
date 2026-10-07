<?php
$retailSettings=loginAs('retailer@demo.test','settings-retailer');
$oldSession=loginAs('retailer@demo.test','settings-other-session');
$rSettings=request('/?page=settings','settings-retailer');expectHttp(str_contains($rSettings['body'],'Catalog preferences'),'retailer settings page');
$input=['csrf'=>token($rSettings),'action'=>'save-settings','theme'=>'dark','density'=>'compact','catalog_sort'=>'price-asc','preferred_categories'=>[1],'notify_orders'=>'1','notify_reviews'=>'1','notify_verification'=>'1'];
// curl multipart does not encode nested arrays; use PHP-style field names.
unset($input['preferred_categories']);$input['preferred_categories[0]']=1;
$saved=request('/?page=settings','settings-retailer',$input);
expectHttp(str_contains($saved['body'],'Settings saved.')&&str_contains($saved['body'],'theme-dark density-compact'),'appearance form applies dark compact layout');
$catalog=request('/?page=catalog','settings-retailer');expectHttp(str_contains($catalog['body'],'ThinkPad E14')&&!str_contains($catalog['body'],'MK295 Wireless Keyboard'),'preferred categories filter initial catalog');
expectHttp(strpos($catalog['body'],'Aspire 3 Business Laptop')<strpos($catalog['body'],'ThinkPad E14 Business Laptop'),'default price sorting applies');
$allProducts=request('/?page=catalog&category=0','settings-retailer');expectHttp(str_contains($allProducts['body'],'MK295 Wireless Keyboard'),'retailer can override preferred categories');
$deniedAdmin=request('/?page=settings','settings-retailer',['csrf'=>token($saved),'action'=>'save-taxonomy','kind'=>'brand','name'=>'Unauthorized brand']);expectHttp(!row("SELECT id FROM brands WHERE name='Unauthorized brand'"),'taxonomy POST rejects nonadministrator');
$supplierSettings=request('/?page=settings','supplier');expectHttp(str_contains($supplierSettings['body'],'Inventory defaults'),'supplier settings page');
$sInput=['csrf'=>token($supplierSettings),'action'=>'save-settings','theme'=>'light','density'=>'comfortable','notify_orders'=>'1','notify_reviews'=>'1','notify_verification'=>'1','notify_stock'=>'1','low_stock_threshold'=>7,'default_minimum_quantity'=>9,'company_description'=>'Our public company description','contact_email'=>'sales@example.test','contact_phone'=>'123456789','business_hours'=>'Weekdays, 9–5'];
$sSaved=request('/?page=settings','supplier',$sInput);expectHttp(str_contains($sSaved['body'],'Settings saved.'),'supplier preferences form');
$editor=request('/?page=product-edit','supplier');expectHttp((bool)preg_match('/name="minimum_quantity"[^>]*value="9"/',$editor['body']),'new product uses supplier minimum quantity default');
$sellerPublic=request('/?page=seller&id=2','settings-retailer');expectHttp(str_contains($sellerPublic['body'],'Our public company description')&&str_contains($sellerPublic['body'],'sales@example.test')&&str_contains($sellerPublic['body'],'Weekdays, 9–5'),'public seller profile shows saved business details');
$aSettings=request('/?page=settings','admin');expectHttp(str_contains($aSettings['body'],'Supplier document requirements'),'administrator settings page');
$created=request('/?page=settings','admin',['csrf'=>token($aSettings),'action'=>'save-taxonomy','kind'=>'brand','name'=>'Settings HTTP Brand']);expectHttp(str_contains($created['body'],'Catalog configuration updated.')&&(bool)row("SELECT id FROM brands WHERE name='Settings HTTP Brand'"),'administrator taxonomy form adds brand');
$requirements=request('/?page=settings','admin',['csrf'=>token($created),'action'=>'save-document-requirements','required_documents'=>"Business registration\nTax certificate"]);expectHttp(str_contains($requirements['body'],'Document requirements saved.'),'administrator document requirements form');
$verifyPage=request('/?page=verification','supplier');expectHttp(str_contains($verifyPage['body'],'value="Tax certificate"'),'supplier upload offers configured document types');
$revoked=request('/?page=settings','settings-retailer',['csrf'=>token($saved),'action'=>'revoke-sessions','current_password'=>'DigiKonek!2026']);expectHttp(str_contains($revoked['body'],'Other sessions have been signed out.'),'revoke sessions form keeps caller signed in');
expectHttp(str_contains(request('/?page=dashboard','settings-other-session')['body'],'Welcome back.'),'another browser session is actually signed out');
$passwordChanged=request('/?page=settings','settings-retailer',['csrf'=>token($revoked),'action'=>'change-password','current_password'=>'DigiKonek!2026','new_password'=>'NewHttpPassword!2026','password_confirmation'=>'NewHttpPassword!2026']);expectHttp(str_contains($passwordChanged['body'],'Password changed.'),'password change form');
$oldLogin=loginAs('retailer@demo.test','settings-old-login');expectHttp(str_contains($oldLogin['body'],'The email or password is incorrect'),'old password rejected after change');
$newLoginPage=request('/?page=login','settings-new-login');$newLogin=request('/?page=login','settings-new-login',['csrf'=>token($newLoginPage),'action'=>'login','email'=>'retailer@demo.test','password'=>'NewHttpPassword!2026']);expectHttp(str_contains($newLogin['body'],'Hello, Alex'),'new password signs in successfully');
