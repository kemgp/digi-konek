<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../app/bootstrap.php';
if (row('SELECT id FROM users LIMIT 1')) { fwrite(STDERR,"Demo seeding requires an empty users table. No changes made.\n"); exit(1); }
$market = new Marketplace();
$market->transaction(function () use ($market) {
    $accounts = [
        ['Alex Rivera','retailer@demo.test','retailer','Circuit Avenue Retail'],
        ['Mika Santos','supplier@demo.test','supplier','NexTech Distribution'],
        ['Jamie Cruz','admin@demo.test','administrator',''],
        ['Luis Reyes','partner@demo.test','supplier','Volt Supply Co.'],
        ['Bea Lim','pending@demo.test','supplier','Pacific Components'],
    ];
    foreach ($accounts as [$name,$email,$role,$business]) {
        execute('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)',[$name,$email,password_hash('DigiKonek!2026',PASSWORD_DEFAULT),$role]);
        $id=(int)db()->lastInsertId();
        if ($role==='administrator') execute('INSERT INTO administrators (user_id) VALUES (?)',[$id]);
        else execute("INSERT INTO {$role}s (user_id,business_name) VALUES (?,?)",[$id,$business]);
        if ($role==='retailer') execute('INSERT INTO carts (user_id) VALUES (?)',[$id]);
        if ($role==='supplier' && $email!=='pending@demo.test') execute("UPDATE suppliers SET verification_status='verified',review_note='Demo supplier; fictional verification for local testing only.' WHERE user_id=?",[$id]);
        $market->audit($id,'demo.created','Fictional local demonstration account');
    }
    // Product specifications, brands, prices, and businesses are illustrative demo data.
    $products = [
        [2,1,1,'ThinkPad E14 Business Laptop','NX-TP14','14-inch Full HD display · Intel Core i5 · 16 GB RAM · 512 GB SSD. A dependable workstation for everyday business. Demo specification.',3890000,3,24],
        [2,2,2,'MK295 Wireless Keyboard Set','NX-MK295','Wireless keyboard and mouse combo · Quiet keys · USB receiver · Full-size layout. Demo specification.',149000,10,180],
        [4,3,3,'Archer AX23 Wi-Fi 6 Router','VT-AX23','Dual-band AX1800 networking · Gigabit ports · Four external antennas. Built for connected workspaces. Demo specification.',249000,5,76],
        [2,4,4,'NV2 1TB NVMe SSD','NX-NV2-1T','1 TB storage · M.2 2280 form factor · PCIe interface. Upgrade inventory for your growing customer base. Demo specification.',329000,5,96],
        [4,5,5,'Tune 520BT Wireless Headphones','VT-520BT','Bluetooth over-ear audio · Foldable design · USB-C charging · Black finish. Demo specification.',219000,10,64],
        [2,6,6,'Core i5 Desktop Processor','NX-I5','Desktop processor · Boxed retail unit · For business PC builds and upgrades. Confirm compatibility before procurement. Demo specification.',1050000,3,38],
        [4,1,8,'Aspire 3 Business Laptop','VT-AS3','15.6-inch display · 8 GB RAM · 512 GB SSD · Slim everyday notebook. Demo specification.',2690000,3,30],
        [4,4,7,'870 EVO 500GB SATA SSD','VT-870','500 GB capacity · 2.5-inch SATA form factor · Solid-state storage for desktop and laptop upgrades. Demo specification.',299000,5,112],
        [2,3,3,'8-Port Gigabit Desktop Switch','NX-SG108','Eight gigabit Ethernet ports · Metal housing · Fanless design. Simple network expansion. Demo specification.',129000,5,85],
    ];
    foreach($products as [$sid,$cat,$brand,$name,$sku,$description,$price,$min,$stock]) {
        execute('INSERT INTO products (supplier_id,category_id,brand_id,name,sku,description,price_cents,minimum_quantity) VALUES (?,?,?,?,?,?,?,?)',[$sid,$cat,$brand,$name,$sku,$description,$price,$min]);
        execute('INSERT INTO inventory (product_id,stock) VALUES (?,?)',[(int)db()->lastInsertId(),$stock]);
    }
    $market->notify(1,'Welcome to Digi-Konek. Explore the catalog and connect with your next supplier.');
    $market->notify(2,'Your demo supplier account is verified. Your products are visible in the marketplace.');
});
echo "Demo accounts created. Password for all demo accounts: DigiKonek!2026\nRetailer: retailer@demo.test\nSupplier: supplier@demo.test\nAdministrator: admin@demo.test\nSecond supplier: partner@demo.test\nUnverified supplier: pending@demo.test\nDo not use demo accounts in a public deployment.\n";
