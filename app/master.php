<?php
require_once __DIR__.'/bootstrap.php';

function qc_master_data(): array {
    static $data = null;
    if (is_array($data)) return $data;
    $path = __DIR__.'/../data/qachem_master.json';
    if (!is_file($path)) {
        throw new RuntimeException('QACHEM master reference file is missing: data/qachem_master.json');
    }
    $raw = (string)file_get_contents($path);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('QACHEM master reference file is invalid JSON.');
    }
    foreach (['factories','locations','products','raw_materials','methods','spec_profiles','spec_rules'] as $key) {
        if (!isset($decoded[$key]) || !is_array($decoded[$key])) $decoded[$key] = [];
    }
    require_once __DIR__.'/ReferenceStore.php';
    $pdo=db_connect();
    if(ReferenceStore::ready($pdo))$decoded=ReferenceStore::overlay($pdo,$decoded);
    return $data = $decoded;
}

function qc_master_active(string $key): array {
    return array_values(array_filter(qc_master_data()[$key] ?? [], fn($row) => !isset($row['active']) || (int)$row['active'] === 1));
}

function qc_master_methods(): array {
    $rows = qc_master_active('methods');
    usort($rows, fn($a, $b) => ((int)$a['method_key']) <=> ((int)$b['method_key']));
    return $rows;
}

function qc_master_method_by_key($key): ?array {
    foreach (qc_master_methods() as $row) {
        if ((int)$row['method_key'] === (int)$key) return $row;
    }
    return null;
}

function qc_master_products(): array {
    return qc_master_active('products');
}

function qc_master_product_by_id(int $id): ?array {
    foreach (qc_master_products() as $row) {
        if ((int)$row['id'] === $id) return $row;
    }
    return null;
}

function qc_master_raw_material_by_id(int $id): ?array {
    foreach (qc_master_active('raw_materials') as $row) {
        if ((int)$row['id'] === $id) return $row;
    }
    return null;
}

function qc_master_factory_exists(string $factory): bool {
    $factory = strtoupper(trim($factory));
    if ($factory === '') return true;
    foreach (qc_master_active('factories') as $row) {
        if (strtoupper((string)$row['factory_code']) === $factory) return true;
    }
    return false;
}

function qc_master_location_exists(string $plant, string $location): bool {
    $plant = strtoupper(trim($plant));
    $location = strtoupper(trim($location));
    if ($plant === '' || $location === '') return true;
    foreach (qc_master_active('locations') as $row) {
        if (strtoupper((string)$row['factory_code']) === $plant && strtoupper((string)$row['location_code']) === $location) return true;
    }
    return false;
}

function qc_master_rules_for_profile(int $profileId): array {
    $rules = array_values(array_filter(qc_master_active('spec_rules'), fn($row) => (int)$row['profile_id'] === $profileId));
    usort($rules, fn($a, $b) => ((int)$a['sequence_no'] <=> (int)$b['sequence_no']) ?: ((int)$a['id'] <=> (int)$b['id']));
    foreach ($rules as &$rule) {
        if (!empty($rule['method_key'])) {
            $method = qc_master_method_by_key((int)$rule['method_key']);
            if ($method) {
                $rule += [
                    'method_code' => $method['method_code'] ?? null,
                    'method_name' => $method['method_name'] ?? null,
                    'description' => $method['description'] ?? null,
                    'input_schema' => $method['input_schema'] ?? null,
                    'formula_text' => $method['formula_text'] ?? null,
                    'result_unit' => $method['result_unit'] ?? null,
                ];
            }
        }
    }
    unset($rule);
    return $rules;
}

function qc_master_profile_by_id(int $profileId): ?array {
    foreach (qc_master_active('spec_profiles') as $profile) {
        if ((int)$profile['id'] !== $profileId) continue;
        $profile['rules'] = qc_master_rules_for_profile($profileId);
        return $profile;
    }
    return null;
}

function qc_master_profile(string $entityType, int $entityId, string $specType, ?string $factory = null, ?string $supplier = null): ?array {
    require_once __DIR__.'/spec.php';
    $matches = [];
    foreach (qc_master_active('spec_profiles') as $profile) {
        if (strtoupper((string)$profile['entity_type']) !== strtoupper($entityType)) continue;
        if ((int)$profile['entity_id'] !== $entityId) continue;
        if (strtoupper((string)$profile['spec_type']) !== strtoupper($specType)) continue;
        if (!qc_factory_scope_match($profile['factory_scope'] ?? '', $factory)) continue;
        if (!qc_supplier_scope_match($profile['supplier_scope'] ?? '', $supplier)) continue;
        $profile['_score'] = qc_factory_scope_score($profile['factory_scope'] ?? '') + ((trim((string)($profile['supplier_scope'] ?? '')) !== '') ? 2 : 0);
        $matches[] = $profile;
    }
    if (!$matches) return null;
    usort($matches, fn($a, $b) => ($b['_score'] <=> $a['_score']) ?: ((int)$b['id'] <=> (int)$a['id']));
    $profile = $matches[0];
    unset($profile['_score']);
    $profile['rules'] = qc_master_rules_for_profile((int)$profile['id']);
    return $profile;
}

function qc_master_profile_entity(array $profile): array {
    if (($profile['entity_type'] ?? '') === 'PRODUCT') {
        $p = qc_master_product_by_id((int)$profile['entity_id']);
        return [
            'product_name' => $p['product_name'] ?? null,
            'product_type' => $p['product_type'] ?? null,
            'batch_code' => $p['batch_code'] ?? null,
            'shelf_life_value' => $p['shelf_life_value'] ?? null,
            'shelf_life_unit' => $p['shelf_life_unit'] ?? null,
            'appearance' => $p['appearance'] ?? null,
            'material_name' => null,
            'material_type' => null,
            'supplier' => null,
        ];
    }
    $m = qc_master_raw_material_by_id((int)$profile['entity_id']);
    return [
        'product_name' => null,
        'product_type' => null,
        'batch_code' => null,
        'shelf_life_value' => null,
        'shelf_life_unit' => null,
        'appearance' => $m['appearance'] ?? null,
        'material_name' => $m['material_name'] ?? null,
        'material_type' => $m['material_type'] ?? null,
        'supplier' => $m['supplier'] ?? null,
    ];
}
