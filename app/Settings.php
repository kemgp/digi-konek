<?php
declare(strict_types=1);
function preferences(int $id): array {
    $defaults=['theme'=>'light','density'=>'comfortable','notify_orders'=>true,'notify_reviews'=>true,'notify_verification'=>true,'notify_stock'=>true,'catalog_sort'=>'newest','preferred_categories'=>[],'low_stock_threshold'=>10,'default_minimum_quantity'=>1,'company_description'=>'','contact_email'=>'','contact_phone'=>'','business_hours'=>''];
    $record=row('SELECT preferences FROM user_settings WHERE user_id=?',[$id]);
    return array_replace($defaults,$record?json_decode($record['preferences'],true,512,JSON_THROW_ON_ERROR):[]);
}
function requiredDocuments(): array {
    $record=row('SELECT required_documents FROM marketplace_settings WHERE id=1');
    return $record?json_decode($record['required_documents'],true,512,JSON_THROW_ON_ERROR):['Business registration'];
}
trait Settings {
    public function notifyAdministrators(string $message, string $type): void {
        foreach (rows("SELECT id FROM users WHERE role='administrator' AND active=1") as $admin) $this->notify((int)$admin['id'], $message, $type);
    }
    public function savePreferences(array $user,array $input): void {
        $theme=$input['theme']??'';$density=$input['density']??'';
        if(!in_array($theme,['light','dark'],true)||!in_array($density,['comfortable','compact'],true))throw new DomainException('Select a valid theme and layout.');
        $changes=['theme'=>$theme,'density'=>$density];
        foreach(['notify_orders','notify_reviews','notify_verification','notify_stock'] as $key)$changes[$key]=isset($input[$key])&&$input[$key]==='1';
        if($user['role']==='retailer'){
            $sort=$input['catalog_sort']??'';
            if(!in_array($sort,['newest','price-asc','price-desc'],true))throw new DomainException('Choose a valid catalog sort.');
            $categories=$input['preferred_categories']??[];
            if(!is_array($categories)||count($categories)>100)throw new DomainException('Choose valid preferred categories.');
            $ids=[];foreach($categories as $id){$id=positiveInt($id,PHP_INT_MAX);if(!row('SELECT id FROM categories WHERE id=?',[$id]))throw new DomainException('A selected category no longer exists.');$ids[]=$id;}
            $changes['catalog_sort']=$sort;$changes['preferred_categories']=array_values(array_unique($ids));
        }
        if($user['role']==='supplier'){
            $threshold=filter_var($input['low_stock_threshold']??null,FILTER_VALIDATE_INT);
            if($threshold===false||$threshold<0||$threshold>1000000)throw new DomainException('Low-stock threshold must be between 0 and 1,000,000.');
            $changes['low_stock_threshold']=$threshold;
            $changes['default_minimum_quantity']=positiveInt($input['default_minimum_quantity']??0);
            foreach(['company_description'=>2000,'contact_email'=>190,'contact_phone'=>60,'business_hours'=>300] as $key=>$max){
                $value=trim((string)($input[$key]??''));if(mb_strlen($value)>$max)throw new DomainException("$key must be at most $max characters.");$changes[$key]=$value;
            }
            if($changes['contact_email']!==''&&!filter_var($changes['contact_email'],FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid public contact email.');
        }
        $this->transaction(function()use($user,$changes){
            // Serialize settings changes for the same account to avoid lost updates.
            row('SELECT id FROM users WHERE id=? FOR UPDATE',[$user['id']]);
            $old=preferences((int)$user['id']);$next=array_replace($old,$changes);
            execute('INSERT INTO user_settings(user_id,preferences) VALUES (?,?) ON DUPLICATE KEY UPDATE preferences=VALUES(preferences)',[$user['id'],json_encode($next,JSON_THROW_ON_ERROR)]);
            if($user['role']==='supplier'){
                if($old['low_stock_threshold']!==$next['low_stock_threshold']||(!$old['notify_stock']&&$next['notify_stock']))execute('UPDATE inventory i JOIN products p ON p.id=i.product_id SET i.low_stock_notified=0 WHERE p.supplier_id=?',[$user['id']]);
                foreach(rows('SELECT id FROM products WHERE supplier_id=? ORDER BY id',[$user['id']]) as $p)$this->checkLowStock((int)$p['id']);
            }
            $this->audit((int)$user['id'],'settings.updated','Account preferences saved');
        });
    }
    public function secureAccount(array $user,array $input,bool $changePassword): int {
        return $this->transaction(function()use($user,$input,$changePassword){
            $account=row('SELECT * FROM users WHERE id=? AND active=1 FOR UPDATE',[$user['id']]);
            if(!$account||!password_verify((string)($input['current_password']??''),$account['password_hash']))throw new DomainException('Your current password is incorrect.');
            if($changePassword){
                $new=(string)($input['new_password']??'');
                if(strlen($new)<12||strlen($new)>72)throw new DomainException('Use a new password between 12 and 72 bytes.');
                if($new!==($input['password_confirmation']??''))throw new DomainException('New passwords do not match.');
                if(password_verify($new,$account['password_hash']))throw new DomainException('Choose a password different from your current password.');
                execute('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$user['id']]);
            }else execute('UPDATE users SET session_version=session_version+1 WHERE id=?',[$user['id']]);
            $this->audit((int)$user['id'],$changePassword?'security.password_changed':'security.sessions_revoked','Other sign-in sessions invalidated');
            return (int)$account['session_version']+1;
        });
    }
    public function checkLowStock(int $productId): void {
        $item=row('SELECT i.*,p.supplier_id,p.name,p.active FROM inventory i JOIN products p ON p.id=i.product_id WHERE i.product_id=? FOR UPDATE',[$productId]);
        if(!$item)return;
        $prefs=preferences((int)$item['supplier_id']);
        if(!$item['active']||(int)$item['stock']>(int)$prefs['low_stock_threshold']){
            execute('UPDATE inventory SET low_stock_notified=0 WHERE product_id=?',[$productId]);return;
        }
        if(!$item['low_stock_notified']&&$prefs['notify_stock']){
            $this->notify((int)$item['supplier_id'],'Low stock: '.$item['name'].' has '.$item['stock'].' units available.','stock');
            execute('UPDATE inventory SET low_stock_notified=1 WHERE product_id=?',[$productId]);
        }
    }
    public function saveTaxonomy(array $user,array $input): void {
        requireRole($user,'administrator');
        $table=['category'=>'categories','brand'=>'brands'][$input['kind']??'']??null;
        if(!$table)throw new DomainException('Choose category or brand.');
        $id=filter_var($input['id']??0,FILTER_VALIDATE_INT);
        if($id===false||$id<0)throw new DomainException('Invalid record.');
        $delete=($input['operation']??'')==='delete';
        $name=$delete?'':field($input,'name',80);
        try{$this->transaction(function()use($user,$table,$id,$delete,$name){
            if($id&&!row("SELECT id FROM $table WHERE id=? FOR UPDATE",[$id]))throw new DomainException('Record not found.');
            if($delete){if(!$id)throw new DomainException('Select a record to remove.');execute("DELETE FROM $table WHERE id=?",[$id]);}
            elseif($id)execute("UPDATE $table SET name=? WHERE id=?",[$name,$id]);
            else execute("INSERT INTO $table(name) VALUES (?)",[$name]);
            $this->audit((int)$user['id'],'settings.taxonomy',"$table #$id ".($delete?'removed':'saved'));
        });}catch(PDOException $e){if($e->getCode()==='23000')throw new DomainException($delete?'This record is used by products and cannot be removed.':'That name already exists.');throw $e;}
    }
    public function saveDocumentRequirements(array $user,array $input): void {
        requireRole($user,'administrator');
        $text=field($input,'required_documents',1000);
        $names=array_values(array_unique(array_filter(array_map('trim',preg_split('/\R/u',$text)))));
        if(count($names)<1||count($names)>10)throw new DomainException('Provide between 1 and 10 required document types.');
        foreach($names as $name)if(mb_strlen($name)>80)throw new DomainException('Each document type must be at most 80 characters.');
        $this->transaction(function()use($user,$names){
            execute('INSERT INTO marketplace_settings(id,required_documents) VALUES (1,?) ON DUPLICATE KEY UPDATE required_documents=VALUES(required_documents)',[json_encode($names,JSON_THROW_ON_ERROR)]);
            $this->audit((int)$user['id'],'settings.verification','Required document types updated');
        });
    }
}
