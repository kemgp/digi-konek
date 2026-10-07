<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
// Create a uniquely named disposable schema. The configured application database is never modified.
$testName='digikonek_test_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
$dsn=getenv('DB_SOCKET')?'mysql:unix_socket='.getenv('DB_SOCKET'):'mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306');
$server=new PDO($dsn,getenv('DB_USER')?:'root',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$testName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
putenv('DB_NAME='.$testName);
$checks=0;
function check(bool $condition,string $message):void {global $checks;if(!$condition)throw new RuntimeException('FAIL: '.$message);$checks++;echo "PASS $message\n";}
function denied(callable $action,string $message):void {try{$action();}catch(DomainException $e){check(true,$message);return;}throw new RuntimeException('FAIL: '.$message.' (not rejected)');}
try {
    db()->exec(file_get_contents(ROOT.'/database/schema.sql'));
    foreach(['Computing','Peripherals','Networking','Storage','Audio','Components'] as $name)execute('INSERT INTO categories(name) VALUES (?)',[$name]);
    foreach(['Lenovo','Logitech','TP-Link','Kingston','JBL','Intel','Samsung','Acer'] as $name)execute('INSERT INTO brands(name) VALUES (?)',[$name]);
    require ROOT.'/bin/seed.php';
    $market=new Marketplace();
    $retailer=row('SELECT * FROM users WHERE id=1');$supplier=row('SELECT * FROM users WHERE id=2');$admin=row('SELECT * FROM users WHERE id=3');$other=row('SELECT * FROM users WHERE id=4');$pending=row('SELECT * FROM users WHERE id=5');
    check($market->login('retailer@demo.test','DigiKonek!2026','test')===1,'password login');
    denied(fn()=>$market->login('retailer@demo.test','wrong','test'),'wrong password rejected');
    $base=['name'=>'Test Retailer','email'=>'test@demo.test','business_name'=>'Test Shop','role'=>'retailer','password'=>'TestPassword!123','password_confirmation'=>'TestPassword!123'];
    denied(fn()=>$market->register(array_replace($base,['role'=>'administrator'])),'public administrator registration rejected');
    denied(fn()=>$market->register(array_replace($base,['password_confirmation'=>'wrong'])),'password mismatch rejected');
    $rid=$market->register($base);$r2=row('SELECT * FROM users WHERE id=?',[$rid]);
    check((bool)row('SELECT user_id FROM retailers WHERE user_id=?',[$rid]),'retailer shares User primary key');
    check((bool)row('SELECT user_id FROM carts WHERE user_id=?',[$rid]),'registration creates cart');
    denied(fn()=>$market->register($base),'duplicate email rejected');
    $product=['name'=>'Test component','sku'=>'TEST-001','description'=>'Test stock item','price'=>'100.25','minimum_quantity'=>1,'stock'=>10,'category_id'=>6,'brand_id'=>6];
    denied(fn()=>$market->saveProduct($pending,$product),'unverified supplier cannot publish');
    denied(fn()=>$market->saveProduct($retailer,$product),'retailer cannot publish');
    $market->saveProduct($supplier,$product);$pid=(int)db()->lastInsertId();
    $pid=(int)row("SELECT id FROM products WHERE sku='TEST-001'")['id'];
    check((int)row('SELECT price_cents FROM products WHERE id=?',[$pid])['price_cents']===10025,'prices stored as integer centavos');
    denied(fn()=>$market->saveProduct($other,array_replace($product,['id'=>$pid])),'supplier cannot edit another supplier product');
    denied(fn()=>$market->saveProduct($supplier,array_replace($product,['sku'=>'BAD','price'=>'1.001'])),'fractional centavo price rejected');
    denied(fn()=>$market->addCart($supplier,$pid,1),'supplier cannot use retailer cart');
    denied(fn()=>$market->addCart($retailer,$pid,11),'quantity above stock rejected');
    denied(fn()=>$market->addCart($retailer,1,1),'minimum order quantity enforced');
    $market->addCart($retailer,$pid,3);
    $stale=$market->quote($market->cart(1));
    execute('UPDATE products SET price_cents=11025 WHERE id=?',[$pid]);
    denied(fn()=>$market->checkout($retailer,$stale),'stale price quote rejected');
    check((int)row('SELECT stock FROM inventory WHERE product_id=?',[$pid])['stock']===10,'failed checkout leaves stock unchanged');
    $orders=$market->checkout($retailer,$market->quote($market->cart(1)));$id=$orders[0];
    check((int)row('SELECT stock FROM inventory WHERE product_id=?',[$pid])['stock']===7,'checkout reserves stock');
    check((int)row('SELECT total_cents FROM orders WHERE id=?',[$id])['total_cents']===33075,'server calculates exact total');
    check(count($market->cart(1))===0,'checkout clears cart');
    denied(fn()=>$market->checkout($retailer,$stale),'duplicate checkout does not create duplicate order');
    denied(fn()=>$market->transition($other,$id,'accepted'),'other supplier cannot accept order');
    denied(fn()=>$market->transition($r2,$id,'cancelled'),'other retailer cannot cancel order');
    denied(fn()=>$market->transition($admin,$id,'completed'),'administrator cannot bypass order workflow');
    $market->transition($retailer,$id,'cancelled');
    check((int)row('SELECT stock FROM inventory WHERE product_id=?',[$pid])['stock']===10,'cancellation restores stock');
    denied(fn()=>$market->transition($retailer,$id,'cancelled'),'repeated cancellation rejected');
    check((int)row('SELECT stock FROM inventory WHERE product_id=?',[$pid])['stock']===10,'repeated cancellation cannot inflate stock');
    $market->addCart($retailer,$pid,2);$market->addCart($retailer,3,5);
    $grouped=$market->checkout($retailer,$market->quote($market->cart(1)));
    check(count($grouped)===2,'checkout splits orders by supplier');
    $own=(int)row('SELECT id FROM orders WHERE supplier_id=2 AND status="pending" ORDER BY id DESC')['id'];
    $market->transition($supplier,$own,'accepted');
    denied(fn()=>$market->transition($retailer,$own,'cancelled'),'accepted orders cannot be cancelled');
    $market->transition($supplier,$own,'completed');
    check(row('SELECT status FROM orders WHERE id=?',[$own])['status']==='completed','supplier can complete accepted order');
    check((int)row('SELECT stock FROM inventory WHERE product_id=?',[$pid])['stock']===8,'acceptance and completion do not double deduct stock');
    $otherOrder=(int)row('SELECT id FROM orders WHERE supplier_id=4 AND status="pending" ORDER BY id DESC')['id'];
    $market->transition($other,$otherOrder,'rejected');
    check((int)row('SELECT stock FROM inventory WHERE product_id=3')['stock']===76,'rejection restores reserved inventory');
    denied(fn()=>$market->verifySupplier($supplier,5,'verified',''),'supplier cannot approve verification');
    denied(fn()=>$market->verifySupplier($admin,5,'verified',''),'approval requires submitted document');
    denied(fn()=>$market->verifySupplier($admin,5,'rejected',''),'rejection requires explanation');
    execute('INSERT INTO supplier_documents(supplier_id,original_name,stored_name,mime_type) VALUES (5,?,?,?)',['fixture.pdf','test-document-metadata','application/pdf']);
    $market->verifySupplier($admin,5,'verified','Reviewed test fixture');
    check(row('SELECT verification_status FROM suppliers WHERE user_id=5')['verification_status']==='verified','administrator can approve documented supplier');
    execute('UPDATE users SET active=0 WHERE id=5');
    denied(fn()=>$market->login('pending@demo.test','DigiKonek!2026','test'),'inactive account cannot sign in');
    $market->addCart($retailer,$pid,1);execute('UPDATE products SET active=0 WHERE id=?',[$pid]);
    denied(fn()=>$market->checkout($retailer,$market->quote($market->cart(1))),'archived product cannot be ordered from stale cart');
    $market->removeCart($retailer,$pid);execute('UPDATE products SET active=1 WHERE id=?',[$pid]);
    $market->addCart($retailer,$pid,1);execute('UPDATE users SET active=0 WHERE id=2');
    denied(fn()=>$market->checkout($retailer,$market->quote($market->cart(1))),'inactive supplier cannot receive checkout');
    execute('UPDATE users SET active=1 WHERE id=2');$market->removeCart($retailer,$pid);
    check((int)row('SELECT COUNT(*) AS n FROM audit_logs WHERE action LIKE "order.%"')['n']>=5,'order actions produce audit records');
    check((int)row('SELECT COUNT(*) AS n FROM notifications WHERE user_id=1')['n']>1,'order actions notify retailer');
    check(e('<script>')==='&lt;script&gt;','HTML output escapes untrusted content');
    for($i=0;$i<8;$i++){try{$market->login('rate@demo.test','wrong','test-rate');}catch(DomainException $e){}}
    try{$market->login('rate@demo.test','wrong','test-rate');throw new RuntimeException('Rate limiting absent');}catch(DomainException $e){check(str_contains($e->getMessage(),'15 minutes'),'repeated failed login is rate limited');}
    // Competing checkouts run in separate PHP processes with independent connections.
    execute('UPDATE inventory SET stock=5 WHERE product_id=?',[$pid]);
    $market->addCart($retailer,$pid,5);$market->addCart($r2,$pid,5);
    $start=(string)(microtime(true)+1.0);$processes=[];
    foreach([1,$rid] as $who){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/checkout-worker.php',(string)$who,$start],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$proc,$pipes];}
    $results=[];
    foreach($processes as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($exit!==0)throw new RuntimeException('Concurrency worker failed: '.$err);}
    sort($results);check($results===['rejected','success'],'concurrent buyers cannot oversell last stock');
    check((int)row('SELECT stock FROM inventory WHERE product_id=?',[$pid])['stock']===0,'concurrent checkout leaves exact zero available stock');
    require __DIR__.'/profile-reviews.php';
    require __DIR__.'/settings.php';
    echo "\n$checks checks passed against MySQL/MariaDB.\n";
} finally {
    $server->exec("DROP DATABASE `$testName`");
}
