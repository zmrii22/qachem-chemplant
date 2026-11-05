<?php
require_once __DIR__.'/bootstrap.php';

final class ReferenceStore {
    public static function ready(PDO $pdo): bool {return table_exists($pdo,'t_qc_product_spec');}
    public static function install(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS t_qc_product (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_name VARCHAR(200) NOT NULL, product_type VARCHAR(100) NOT NULL, batch_code VARCHAR(100) NOT NULL, appearance TEXT NULL, shelf_life_value DECIMAL(10,2) NULL, shelf_life_unit VARCHAR(20) NOT NULL DEFAULT 'MONTH', active TINYINT NOT NULL DEFAULT 1, updated_by VARCHAR(160) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS t_qc_product_spec (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id INT UNSIGNED NOT NULL, spec_type VARCHAR(10) NOT NULL, factory_scope VARCHAR(200) NOT NULL DEFAULT 'ALL', profile_json LONGTEXT NOT NULL, rules_json LONGTEXT NOT NULL, active TINYINT NOT NULL DEFAULT 1, updated_by VARCHAR(160) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY(product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS t_qc_coa_remark (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, remark_key VARCHAR(80) NOT NULL UNIQUE, label VARCHAR(160) NOT NULL, remark_text TEXT NOT NULL, compulsory TINYINT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, updated_by VARCHAR(160) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS t_qc_product_coa_remark (product_id INT UNSIGNED NOT NULL, remark_id INT UNSIGNED NOT NULL, sort_order INT NOT NULL DEFAULT 0, PRIMARY KEY(product_id,remark_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $master=json_decode(file_get_contents(__DIR__.'/../data/qachem_master.json'),true,512,JSON_THROW_ON_ERROR);
        $reference=json_decode(file_get_contents(__DIR__.'/../data/tracking_reference.json'),true,512,JSON_THROW_ON_ERROR);
        $pdo->beginTransaction();
        try {
            $query=$pdo->prepare('INSERT IGNORE INTO t_qc_product (id,product_name,product_type,batch_code,appearance,shelf_life_value,shelf_life_unit,active) VALUES (?,?,?,?,?,?,?,?)');
            foreach($master['products'] as $p)$query->execute([$p['id'],$p['product_name'],$p['product_type'],$p['batch_code'],$p['appearance'],$p['shelf_life_value'],$p['shelf_life_unit'],$p['active']]);
            $query=$pdo->prepare('INSERT IGNORE INTO t_qc_product_spec (id,product_id,spec_type,factory_scope,profile_json,rules_json,active) VALUES (?,?,?,?,?,?,?)');
            foreach($master['spec_profiles'] as $p){if($p['entity_type']!=='PRODUCT')continue;$rules=array_values(array_filter($master['spec_rules'],fn($r)=>(int)$r['profile_id']===(int)$p['id']));$query->execute([$p['id'],$p['entity_id'],$p['spec_type'],$p['factory_scope']?:'ALL',json_encode($p,JSON_UNESCAPED_UNICODE),json_encode($rules,JSON_UNESCAPED_UNICODE),$p['active']]);}
            $query=$pdo->prepare('INSERT IGNORE INTO t_qc_coa_remark (remark_key,label,remark_text,compulsory) VALUES (?,?,?,?)');
            foreach($reference['remarks'] as $r)$query->execute([$r['key'],$r['label'],$r['text'],(int)$r['compulsory']]);
            $remarkIds=array_column($pdo->query('SELECT id,remark_key FROM t_qc_coa_remark')->fetchAll(),'id','remark_key');
            $query=$pdo->prepare('INSERT IGNORE INTO t_qc_product_coa_remark (product_id,remark_id,sort_order) VALUES (?,?,?)');
            foreach($master['products'] as $p){$keys=['base_compliance','base_returns'];$code=strtoupper($p['batch_code']);if(in_array($code,['INS','TS','MP']))$keys=array_merge($keys,['arp_storage','arp_ph']);elseif($code==='SH')$keys[]='hypo';elseif($code==='PTC')$keys[]='ptc';elseif(stripos($p['product_type'],'cleaning')!==false&&$code!=='KZ')$keys[]='cleaning_chlorine';foreach($keys as $n=>$key)$query->execute([$p['id'],$remarkIds[$key],$n]);}
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        $next=max(array_column($master['spec_profiles'],'id'))+1;$pdo->exec('ALTER TABLE t_qc_product_spec AUTO_INCREMENT='.$next);
    }
    public static function overlay(PDO $pdo,array $master): array {
        $master['products']=$pdo->query('SELECT * FROM t_qc_product ORDER BY id')->fetchAll();
        $productProfiles=array_column(array_filter($master['spec_profiles'],fn($p)=>$p['entity_type']==='PRODUCT'),'id');
        $master['spec_profiles']=array_values(array_filter($master['spec_profiles'],fn($p)=>$p['entity_type']!=='PRODUCT'));
        $master['spec_rules']=array_values(array_filter($master['spec_rules'],fn($r)=>!in_array($r['profile_id'],$productProfiles)));
        foreach($pdo->query('SELECT * FROM t_qc_product_spec ORDER BY id') as $row){
            $p=json_decode($row['profile_json'],true)?:[];$p=array_replace($p,['id'=>(int)$row['id'],'entity_type'=>'PRODUCT','entity_id'=>(int)$row['product_id'],'spec_type'=>$row['spec_type'],'factory_scope'=>$row['factory_scope'],'active'=>(int)$row['active'],'updated_at'=>$row['updated_at']]);$master['spec_profiles'][]=$p;
            foreach(json_decode($row['rules_json'],true)?:[] as $rule){$rule['active']=$row['active']?(int)($rule['active']??1):0;$master['spec_rules'][]=$rule;}
        }
        return $master;
    }
    public static function remarks(PDO $pdo,bool $all=false): array {
        return array_map(fn($r)=>['id'=>(int)$r['id'],'key'=>$r['remark_key'],'label'=>$r['label'],'text'=>$r['remark_text'],'compulsory'=>(bool)$r['compulsory'],'active'=>(bool)$r['active']],$pdo->query('SELECT * FROM t_qc_coa_remark'.($all?'':' WHERE active=1').' ORDER BY id')->fetchAll());
    }
    public static function productRemarkKeys(PDO $pdo,int $productId): array {
        $s=$pdo->prepare('SELECT r.remark_key FROM t_qc_coa_remark r LEFT JOIN t_qc_product_coa_remark p ON p.remark_id=r.id AND p.product_id=? WHERE r.active=1 AND (p.product_id IS NOT NULL OR r.compulsory=1) ORDER BY r.id');$s->execute([$productId]);return $s->fetchAll(PDO::FETCH_COLUMN);
    }
    public static function remarkIds(PDO $pdo,int $productId): array {$s=$pdo->prepare('SELECT remark_id FROM t_qc_product_coa_remark WHERE product_id=? ORDER BY sort_order,remark_id');$s->execute([$productId]);return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
    public static function saveRemark(PDO $pdo,array $data,array $user): int {
        $id=(int)($data['id']??0);$label=trim((string)($data['label']??''));$text=trim((string)($data['text']??''));
        if($label===''||qc_text_length($label)>160||$text===''||qc_text_length($text)>16000)throw new InvalidArgumentException('Enter a title and remark sentence (up to 16,000 characters).');
        if($id){$s=$pdo->prepare('SELECT id FROM t_qc_coa_remark WHERE id=? AND active=1');$s->execute([$id]);if(!$s->fetchColumn())throw new InvalidArgumentException('Remark not found.');$pdo->prepare('UPDATE t_qc_coa_remark SET label=?,remark_text=?,compulsory=?,updated_by=? WHERE id=?')->execute([$label,$text,(int)!empty($data['compulsory']),$user['name'],$id]);}
        else{$key='remark_'.bin2hex(random_bytes(12));$pdo->prepare('INSERT INTO t_qc_coa_remark (remark_key,label,remark_text,compulsory,updated_by) VALUES (?,?,?,?,?)')->execute([$key,$label,$text,(int)!empty($data['compulsory']),$user['name']]);$id=(int)$pdo->lastInsertId();}return $id;
    }
    public static function parameters(): array {return [
        'PH'=>['pH','',10,2],'VISC'=>['Viscosity (cP)','cP',11,1],'VTQ'=>['Viscosity (Torque(%))','%',11,1],'TSC'=>['TSC%','%',1,3],'ALK'=>['Alkalinity%','%',2,3],'CHL'=>['Chlorine','%',5,3],'FOAM'=>['Foaming Height','cm',6,1],'ACD'=>['Acidity%','%',8,3],'BAC'=>['Bacteria Content','CFU/mL',9,1],'SG'=>['Specific Gravity','g/mL',13,1],
    ];}
    public static function spec(PDO $pdo,int $id): array {
        $s=$pdo->prepare('SELECT * FROM t_qc_product_spec WHERE id=? AND active=1');$s->execute([$id]);$row=$s->fetch();if(!$row)throw new InvalidArgumentException('Product specification not found.');
        $p=$pdo->prepare('SELECT * FROM t_qc_product WHERE id=?');$p->execute([$row['product_id']]);$product=$p->fetch();
        return ['id'=>(int)$row['id'],'product'=>$product,'spec_type'=>$row['spec_type'],'factory_scope'=>$row['factory_scope'],'rules'=>json_decode($row['rules_json'],true),'remark_ids'=>self::remarkIds($pdo,(int)$row['product_id'])];
    }
    public static function saveSpec(PDO $pdo,array $data,array $user): int {
        $id=(int)($data['id']??0);$old=$id?self::spec($pdo,$id):null;
        $p=$data['product']??[];
        if(!is_array($p)||!is_array($data['parameters']??[])||!is_array($data['remark_ids']??[]))throw new InvalidArgumentException('Invalid product specification fields.');
        $name=trim((string)($p['product_name']??''));$type=trim((string)($p['product_type']??''));$batch=trim((string)($p['batch_code']??''));
        if($name===''||$type===''||$batch===''||qc_text_length($name)>200||qc_text_length($type)>100||qc_text_length($batch)>100)throw new InvalidArgumentException('Enter product type, name and batch code within the field limits.');
        $spec=strtoupper(trim((string)($data['spec_type']??'')));$scope=trim((string)($data['factory_scope']??''))?:'ALL';
        if(!in_array($spec,['IQS','QS'],true)||qc_text_length($scope)>200)throw new InvalidArgumentException('Choose IQS or QS and a valid factory scope.');
        $shelf=$p['shelf_life_value']??'';$unit=strtoupper((string)($p['shelf_life_unit']??'MONTH'));
        if($shelf!==''&&(!is_numeric($shelf)||(float)$shelf<=0||(float)$shelf>99999999))throw new InvalidArgumentException('Shelf life must be a positive number.');
        if(!in_array($unit,['DAY','WEEK','MONTH','YEAR'],true))throw new InvalidArgumentException('Choose a shelf-life unit.');
        $remarkIds=array_values(array_unique(array_map('intval',$data['remark_ids']??[])));$allowed=array_column(self::remarks($pdo),'id');if(array_diff($remarkIds,$allowed))throw new InvalidArgumentException('Selected remark is no longer available.');
        $pid=(int)($old['product']['id']??$p['id']??0);
        if($pid){$s=$pdo->prepare('SELECT id FROM t_qc_product WHERE id=?');$s->execute([$pid]);if(!$s->fetchColumn())throw new InvalidArgumentException('Product not found.');$pdo->prepare('UPDATE t_qc_product SET product_name=?,product_type=?,batch_code=?,appearance=?,shelf_life_value=?,shelf_life_unit=?,updated_by=? WHERE id=?')->execute([$name,$type,$batch,trim((string)($p['appearance']??'')),$shelf===''?null:$shelf,$unit,$user['name'],$pid]);}
        else{$pdo->prepare('INSERT INTO t_qc_product (product_name,product_type,batch_code,appearance,shelf_life_value,shelf_life_unit,updated_by) VALUES (?,?,?,?,?,?,?)')->execute([$name,$type,$batch,trim((string)($p['appearance']??'')),$shelf===''?null:$shelf,$unit,$user['name']]);$pid=(int)$pdo->lastInsertId();}
        $duplicate=$pdo->prepare('SELECT id FROM t_qc_product_spec WHERE product_id=? AND spec_type=? AND factory_scope=? AND active=1 AND id<>?');
        $duplicate->execute([$pid,$spec,$scope,$id]);
        if($duplicate->fetchColumn())throw new InvalidArgumentException('This product already has that specification and factory scope. Edit its existing ID.');
        $profile=['entity_type'=>'PRODUCT','entity_id'=>$pid,'spec_type'=>$spec,'factory_scope'=>$scope,'supplier_scope'=>null,'revision'=>'CONTROLLED','source_ref'=>'Product Specification','active'=>1];
        if(!$id){$pdo->prepare('INSERT INTO t_qc_product_spec (product_id,spec_type,factory_scope,profile_json,rules_json,updated_by) VALUES (?,?,?,?,?,?)')->execute([$pid,$spec,$scope,'{}','[]',$user['name']]);$id=(int)$pdo->lastInsertId();}
        $profile['id']=$id;$rules=[];$oldRules=array_column($old['rules']??[],null,'parameter_code');
        $app=$oldRules['APP']??['id'=>100000000+$id*100,'sample_count'=>1,'sequence_no'=>0];$rules[]=array_replace($app,['profile_id'=>$id,'parameter_code'=>'APP','parameter_name'=>'Appearance','data_type'=>'TEXT','operator'=>'TEXT','low_value'=>null,'high_value'=>null,'target_text'=>trim((string)($p['appearance']??'')),'unit'=>'','method_key'=>null,'active'=>1]);
        $submitted=$data['parameters']??[];$i=0;
        foreach(self::parameters() as $code=>$definition){$i++;$r=$submitted[$code]??[];if(empty($r['enabled']))continue;$operator=strtoupper((string)($r['operator']??'RANGE'));
            if(!in_array($operator,['RANGE','MIN','MAX','GTE','LTE','GT','LT','EQ','OBSERVE','INFO'],true))throw new InvalidArgumentException('Invalid operator for '.$code);
            $low=$r['low_value']??'';$high=$r['high_value']??'';foreach([$low,$high] as $n)if($n!==''&&$n!==null&&(!is_numeric($n)||!is_finite((float)$n)))throw new InvalidArgumentException('Enter numeric limits for '.$code);
            $low=$low===''||$low===null?null:(float)$low;$high=$high===''||$high===null?null:(float)$high;
            if($operator==='RANGE'&&($low===null||$high===null||$low>$high))throw new InvalidArgumentException('Enter an ordered lower and upper limit for '.$code);
            if(in_array($operator,['MIN','GTE','GT','EQ'])&&$low===null||in_array($operator,['MAX','LTE','LT'])&&$high===null)throw new InvalidArgumentException('Enter the required limit for '.$code);
            $method=(int)($r['method_key']??$oldRules[$code]['method_key']??$definition[2]);if($method&&!qc_master_method_by_key($method))throw new InvalidArgumentException('Choose a valid calculation method.');
            $rules[]=array_replace($oldRules[$code]??[],['id'=>(int)($oldRules[$code]['id']??100000000+$id*100+$i),'profile_id'=>$id,'parameter_code'=>$code,'parameter_name'=>$definition[0],'data_type'=>'NUMERIC','operator'=>$operator,'low_value'=>$low,'high_value'=>$high,'target_text'=>trim((string)($r['target_text']??'')),'unit'=>$definition[1],'method_key'=>$method?:null,'sample_count'=>max(1,min(3,(int)($r['sample_count']??$oldRules[$code]['sample_count']??$definition[3]))),'sequence_no'=>$i*10,'active'=>1]);
        }
        $pdo->prepare('UPDATE t_qc_product_spec SET product_id=?,spec_type=?,factory_scope=?,profile_json=?,rules_json=?,updated_by=? WHERE id=?')->execute([$pid,$spec,$scope,json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($rules,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$user['name'],$id]);
        $pdo->prepare('DELETE FROM t_qc_product_coa_remark WHERE product_id=?')->execute([$pid]);$s=$pdo->prepare('INSERT INTO t_qc_product_coa_remark (product_id,remark_id,sort_order) VALUES (?,?,?)');foreach($remarkIds as $n=>$rid)$s->execute([$pid,$rid,$n]);return $id;
    }
}
