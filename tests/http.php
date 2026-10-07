<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$testName='digikonek_http_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
$dsn=getenv('DB_SOCKET')?'mysql:unix_socket='.getenv('DB_SOCKET'):'mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306');
$server=new PDO($dsn,getenv('DB_USER')?:'root',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$testName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");putenv('DB_NAME='.$testName);
$port=random_int(19000,29000);$origin='http://127.0.0.1:'.$port;
$dir=ROOT.'/storage/'.$testName;mkdir($dir,0700);$proc=null;$checks=0;$storedFiles=[];
function expectHttp(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";}
function request(string $path,string $jar,?array $post=null):array {
    global $origin,$dir;
    $c=curl_init($origin.$path);$headers='';
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_COOKIEFILE=>$dir.'/'.$jar,CURLOPT_COOKIEJAR=>$dir.'/'.$jar,CURLOPT_TIMEOUT=>15,CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){$headers.=$line;return strlen($line);}]);
    if($post!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$post);}
    $body=curl_exec($c);if($body===false)throw new RuntimeException(curl_error($c));$code=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
    return ['body'=>$body,'status'=>$code,'headers'=>$headers];
}
function token(array $response):string {if(!preg_match('/name="csrf" value="([a-f0-9]+)"/',$response['body'],$m))throw new RuntimeException('Missing CSRF token');return $m[1];}
function loginAs(string $email,string $jar):array {$page=request('/?page=login',$jar);return request('/?page=login',$jar,['csrf'=>token($page),'action'=>'login','email'=>$email,'password'=>'DigiKonek!2026']);}
try {
    db()->exec(file_get_contents(ROOT.'/database/schema.sql'));
    foreach(['Computing','Peripherals','Networking','Storage','Audio','Components'] as $name)execute('INSERT INTO categories(name) VALUES (?)',[$name]);
    foreach(['Lenovo','Logitech','TP-Link','Kingston','JBL','Intel','Samsung','Acer'] as $name)execute('INSERT INTO brands(name) VALUES (?)',[$name]);
    require ROOT.'/bin/seed.php';
    $pipes=[];$proc=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',ROOT.'/public',ROOT.'/public/router.php'],[0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']],$pipes,ROOT);fclose($pipes[0]);
    for($i=0;$i<40;$i++){try{$guest=request('/','guest');break;}catch(RuntimeException $e){usleep(50000);}}
    expectHttp(isset($guest)&&str_contains($guest['body'],'Welcome back.'),'guest redirected to sign in');
    $bad=request('/?page=login','guest',['csrf'=>'invalid','action'=>'login','email'=>'retailer@demo.test','password'=>'DigiKonek!2026']);expectHttp($bad['status']===419,'CSRF violation returns 419');
    $retail=loginAs('retailer@demo.test','retailer');expectHttp(str_contains($retail['body'],'Hello, Alex'),'retailer HTTP sign in');
    expectHttp(str_contains(strtolower($retail['headers']),'httponly')&&str_contains(strtolower($retail['headers']),'samesite=lax'),'session cookies protect script access and cross-site requests');
    expectHttp(str_contains($retail['headers'],'Content-Security-Policy:'),'CSP response header present');
    foreach(['dashboard','catalog','product&id=1','compare&ids[]=1&ids[]=2','cart','orders','notifications'] as $page){$r=request('/?page='.$page,'retailer');expectHttp($r['status']===200&&!str_contains($r['body'],'could not be loaded'),'retailer page '.$page);}
    foreach(['users','suppliers','reports','audit','products','verification'] as $page)expectHttp(request('/?page='.$page,'retailer')['status']===403,'retailer cannot access '.$page);
    $filtered=request('/?page=catalog&q=MK295','retailer');expectHttp(str_contains($filtered['body'],'MK295 Wireless Keyboard Set')&&!str_contains($filtered['body'],'ThinkPad E14 Business Laptop'),'catalog search filters results');
    $t=token($retail);$cart=request('/?page=product&id=1','retailer',['csrf'=>$t,'action'=>'add-cart','product_id'=>1,'quantity'=>3]);expectHttp(str_contains($cart['body'],'3 units'),'add to cart through HTTP');
    preg_match('/name="quote" value="([a-f0-9]+)"/',$cart['body'],$quote);
    $placed=request('/?page=cart','retailer',['csrf'=>token($cart),'action'=>'checkout','quote'=>$quote[1]]);expectHttp(str_contains($placed['body'],'order(s) submitted'),'checkout through HTTP');
    $orderId=(int)row('SELECT id FROM orders ORDER BY id DESC LIMIT 1')['id'];
    $supplier=loginAs('supplier@demo.test','supplier');
    foreach(['dashboard','products','product-edit','product-edit&id=1','orders','order&id='.$orderId,'verification','reports','notifications'] as $page){$r=request('/?page='.$page,'supplier');expectHttp($r['status']===200&&!str_contains($r['body'],'could not be loaded'),'supplier page '.$page);}
    $accepted=request('/?page=order&id='.$orderId,'supplier',['csrf'=>token($supplier),'action'=>'order-status','order_id'=>$orderId,'status'=>'accepted']);expectHttp(str_contains($accepted['body'],'Mark as completed'),'supplier acceptance through HTTP');
    $complete=request('/?page=order&id='.$orderId,'supplier',['csrf'=>token($accepted),'action'=>'order-status','order_id'=>$orderId,'status'=>'completed']);expectHttp(str_contains($complete['body'],'badge-completed'),'supplier completion through HTTP');
    $other=loginAs('partner@demo.test','other');expectHttp(request('/?page=order&id='.$orderId,'other')['status']===404,'other supplier cannot read order detail');
    $saved=request('/?page=product-edit','supplier',['csrf'=>token($supplier),'action'=>'save-product','name'=>'HTTP test product','sku'=>'HTTP-001','description'=>'Integration test product','price'=>'25.50','minimum_quantity'=>2,'stock'=>20,'category_id'=>6,'brand_id'=>6]);expectHttp(str_contains($saved['body'],'Product and available inventory saved'),'supplier product creation form');
    $pending=loginAs('pending@demo.test','pending');
    file_put_contents($dir.'/fixture.pdf',"%PDF-1.4\n% Digi-Konek fictional test document\n%%EOF\n");
    $upload=request('/?page=verification','pending',['csrf'=>token($pending),'action'=>'upload-document','document'=>new CURLFile($dir.'/fixture.pdf','application/pdf','business-test.pdf')]);expectHttp(str_contains($upload['body'],'Document submitted for administrator review'),'business document upload');
    $doc=row('SELECT * FROM supplier_documents ORDER BY id DESC LIMIT 1');$storedFiles[]=ROOT.'/storage/documents/'.$doc['stored_name'];
    expectHttp(request('/?page=verification&document='.$doc['id'],'retailer')['status']===404,'retailer cannot download supplier documents');
    $ownDoc=request('/?page=verification&document='.$doc['id'],'pending');expectHttp(str_starts_with($ownDoc['body'],'%PDF-1.4'),'supplier can download own private document');
    file_put_contents($dir.'/blocked.php','<?php echo "no";');
    $blocked=request('/?page=verification','pending',['csrf'=>token($pending),'action'=>'upload-document','document'=>new CURLFile($dir.'/blocked.php','application/x-httpd-php','blocked.php')]);expectHttp(str_contains($blocked['body'],'Only PDF, JPEG, and PNG'),'executable upload rejected');
    $admin=loginAs('admin@demo.test','admin');
    foreach(['dashboard','suppliers','users','products','orders','reports','audit','notifications'] as $page){$r=request('/?page='.$page,'admin');expectHttp($r['status']===200&&!str_contains($r['body'],'could not be loaded'),'administrator page '.$page);}
    expectHttp(request('/?page=suppliers&document='.$doc['id'],'admin')['status']===200,'administrator can review private supplier document');
    $verified=request('/?page=suppliers','admin',['csrf'=>token($admin),'action'=>'verify-supplier','supplier_id'=>5,'status'=>'verified','review_note'=>'Approved test fixture']);expectHttp(str_contains($verified['body'],'Supplier review saved'),'supplier approval form');
    $deactivated=request('/?page=users','admin',['csrf'=>token($admin),'action'=>'toggle-user','user_id'=>5]);expectHttp(str_contains($deactivated['body'],'Account status updated'),'administrator account status form');
    expectHttp(str_contains(request('/?page=dashboard','pending')['body'],'Welcome back.'),'deactivation invalidates existing supplier session');
    require __DIR__.'/profile-reviews-http.php';
    $logout=request('/?page=dashboard','retailer',['csrf'=>token($retail),'action'=>'logout']);expectHttp(str_contains($logout['body'],'Welcome back.'),'logout ends authenticated access');
    $registration=request('/?page=register','new');$registered=request('/?page=register','new',['csrf'=>token($registration),'action'=>'register','name'=>'HTTP New Retailer','email'=>'new@demo.test','business_name'=>'New Test Shop','role'=>'retailer','password'=>'LongPassword!123','password_confirmation'=>'LongPassword!123']);expectHttp(str_contains($registered['body'],'Your account is ready'),'registration form');
    expectHttp(!str_contains(request('/.env','guest')['body'],'DB_SOCKET='),'configuration is not publicly served');
    require __DIR__.'/settings-http.php';
    echo "\n$checks HTTP checks passed.\n";
} finally {
    if($proc){proc_terminate($proc);proc_close($proc);}
    foreach($storedFiles as $file)if(is_file($file))unlink($file);
    $server->exec("DROP DATABASE `$testName`");
    foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);
}
