<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
function prompt(string $label): string { fwrite(STDOUT,$label); return trim(fgets(STDIN) ?: ''); }
try {
    $name=prompt('Administrator name: '); $email=strtolower(prompt('Email: '));
    fwrite(STDOUT,"Password (12–72 bytes; input hidden): ");
    $tty=function_exists('posix_isatty') && posix_isatty(STDIN);
    if($tty) system('stty -echo');
    try { $password=rtrim(fgets(STDIN) ?: '',"\r\n"); } finally { if($tty) system('stty echo'); fwrite(STDOUT,"\n"); }
    if(!$name || mb_strlen($name)>120 || strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Invalid name, email, or password.');
    (new Marketplace())->transaction(function() use($name,$email,$password) {
        execute("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'administrator')",[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
        $id=(int)db()->lastInsertId(); execute('INSERT INTO administrators (user_id) VALUES (?)',[$id]);
        (new Marketplace())->audit($id,'account.created','Administrator created through local CLI');
    }); echo "Administrator created.\n";
} catch(Throwable $e) { fwrite(STDERR,"Could not create administrator: ".$e->getMessage()."\n");exit(1); }
