<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
[$script,$uid,$start]=$argv;
$market=new Marketplace();$user=row('SELECT * FROM users WHERE id=?',[(int)$uid]);
$quote=$market->quote($market->cart((int)$uid));
while(microtime(true)<(float)$start) usleep(1000);
try {$market->checkout($user,$quote);echo 'success';} catch(DomainException $e) {echo 'rejected';} catch(Throwable $e){fwrite(STDERR,(string)$e);exit(2);}
