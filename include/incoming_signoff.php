<?php

/** QA names available for the printed form's signatory dropdown. */
function incoming_verifier_options(PDO $pdo): array {
    $rows=$pdo->query('SELECT Badge, Name FROM m_user WHERE Level >= 2 ORDER BY Name ASC, Badge ASC')->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array=>['badge'=>(string)$row['Badge'],'name'=>(string)$row['Name']],$rows);
}
