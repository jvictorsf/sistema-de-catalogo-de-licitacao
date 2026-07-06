<?php

declare(strict_types=1);

function collaborators_table_exists(): bool
{
    return database_table_exists('collaborators');
}

function demand_confirmation_requests_table_exists(): bool
{
    return database_table_exists('demand_confirmation_requests');
}

function normalize_collaborator_data(array $data): array
{
    $data['name'] = trim((string) ($data['name'] ?? ''));
    $data['document_number'] = trim((string) ($data['document_number'] ?? '')) ?: null;
    $data['registration_number'] = trim((string) ($data['registration_number'] ?? '')) ?: null;
    $data['role'] = trim((string) ($data['role'] ?? '')) ?: null;
    $data['department'] = trim((string) ($data['department'] ?? '')) ?: null;
    $data['email'] = trim((string) ($data['email'] ?? '')) ?: null;
    $data['phone'] = only_digits((string) ($data['phone'] ?? '')) ?: null;
    $data['is_active'] = array_key_exists('is_active', $data)
        ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
        : true;

    if ($data['name'] === '') {
        throw new InvalidArgumentException('Informe o nome do colaborador.');
    }

    return $data;
}

function get_collaborators(bool $activeOnly = false, string $search = ''): array
{
    if (!collaborators_table_exists()) {
        return [];
    }

    $filters = [];
    $params = [];
    $search = mb_strtolower(trim($search));

    if ($activeOnly) {
        $filters[] = 'is_active = TRUE';
    }

    if ($search !== '') {
        $filters[] = "(
            lower(name) LIKE :search
            OR lower(COALESCE(document_number, '')) LIKE :search
            OR lower(COALESCE(registration_number, '')) LIKE :search
            OR lower(COALESCE(role, '')) LIKE :search
            OR lower(COALESCE(department, '')) LIKE :search
            OR lower(COALESCE(email, '')) LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    $sql = 'SELECT * FROM collaborators';

    if ($filters) {
        $sql .= ' WHERE ' . implode(' AND ', $filters);
    }

    $sql .= ' ORDER BY is_active DESC, lower(name)';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function find_collaborator(int $id): ?array
{
    if (!collaborators_table_exists()) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM collaborators WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function create_collaborator(array $data): int
{
    if (!collaborators_table_exists()) {
        throw new RuntimeException('Tabela de colaboradores nao encontrada. Rode o schema atualizado.');
    }

    $data = normalize_collaborator_data($data);
    $stmt = db()->prepare("
        INSERT INTO collaborators (name, document_number, registration_number, role, department, email, phone, is_active)
        VALUES (:name, :document_number, :registration_number, :role, :department, :email, :phone, :is_active)
        RETURNING id
    ");
    $stmt->execute([
        'name' => $data['name'],
        'document_number' => $data['document_number'],
        'registration_number' => $data['registration_number'],
        'role' => $data['role'],
        'department' => $data['department'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'is_active' => pg_bool($data['is_active']),
    ]);

    return (int) $stmt->fetchColumn();
}

function update_collaborator(int $id, array $data): void
{
    if (!collaborators_table_exists()) {
        throw new RuntimeException('Tabela de colaboradores nao encontrada. Rode o schema atualizado.');
    }

    $data = normalize_collaborator_data($data);
    $stmt = db()->prepare("
        UPDATE collaborators SET
            name = :name,
            document_number = :document_number,
            registration_number = :registration_number,
            role = :role,
            department = :department,
            email = :email,
            phone = :phone,
            is_active = :is_active
        WHERE id = :id
    ");
    $stmt->execute([
        'id' => $id,
        'name' => $data['name'],
        'document_number' => $data['document_number'],
        'registration_number' => $data['registration_number'],
        'role' => $data['role'],
        'department' => $data['department'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'is_active' => pg_bool($data['is_active']),
    ]);
}

function set_collaborator_active(int $id, bool $active): void
{
    if (!collaborators_table_exists()) {
        throw new RuntimeException('Tabela de colaboradores nao encontrada. Rode o schema atualizado.');
    }

    $stmt = db()->prepare('UPDATE collaborators SET is_active = :is_active WHERE id = :id');
    $stmt->execute(['id' => $id, 'is_active' => pg_bool($active)]);
}
function demand_confirmation_token_hash(string $token): string
{
    return hash('sha256', trim($token));
}

function demand_confirmation_effective_status(array $request): string
{
    $status = (string) ($request['status'] ?? 'pending');

    if ($status === 'pending' && !empty($request['expires_at'])) {
        $expiresAt = strtotime((string) $request['expires_at']);

        if ($expiresAt !== false && $expiresAt < time()) {
            return 'expired';
        }
    }

    return $status;
}

function enrich_demand_confirmation_request(array $request): array
{
    $request['effective_status'] = demand_confirmation_effective_status($request);
    $request['snapshot'] = repository_json_array($request['snapshot'] ?? []);

    return $request;
}

function get_demand_confirmation_requests(int $demandListId): array
{
    if (!demand_confirmation_requests_table_exists()) {
        return [];
    }

    $stmt = db()->prepare("
        SELECT dcr.*, c.name AS collaborator_name, c.role AS collaborator_role, c.department AS collaborator_department
        FROM demand_confirmation_requests dcr
        LEFT JOIN collaborators c ON c.id = dcr.collaborator_id
        WHERE dcr.demand_list_id = :demand_list_id
        ORDER BY dcr.created_at DESC, dcr.id DESC
    ");
    $stmt->execute(['demand_list_id' => $demandListId]);

    return array_map('enrich_demand_confirmation_request', $stmt->fetchAll());
}

function find_demand_confirmation_request(int $id): ?array
{
    if (!demand_confirmation_requests_table_exists()) {
        return null;
    }

    $stmt = db()->prepare("
        SELECT dcr.*, dl.project_id, dl.name AS demand_name, dl.requester_department,
               dl.responsible_name AS demand_responsible_name, s.name AS secretariat_name,
               p.name AS project_name, p.status AS project_status,
               c.name AS collaborator_name, c.role AS collaborator_role, c.department AS collaborator_department
        FROM demand_confirmation_requests dcr
        INNER JOIN demand_lists dl ON dl.id = dcr.demand_list_id
        INNER JOIN procurement_projects p ON p.id = dl.project_id
        LEFT JOIN secretariats s ON s.id = dl.secretariat_id
        LEFT JOIN collaborators c ON c.id = dcr.collaborator_id
        WHERE dcr.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ? enrich_demand_confirmation_request($row) : null;
}

function find_demand_confirmation_request_by_token(string $token): ?array
{
    if (!demand_confirmation_requests_table_exists()) {
        return null;
    }

    $token = trim($token);

    if ($token === '') {
        return null;
    }

    $stmt = db()->prepare("
        SELECT dcr.*, dl.project_id, dl.name AS demand_name, dl.requester_department,
               dl.responsible_name AS demand_responsible_name, s.name AS secretariat_name,
               p.name AS project_name, p.status AS project_status,
               c.name AS collaborator_name, c.role AS collaborator_role, c.department AS collaborator_department
        FROM demand_confirmation_requests dcr
        INNER JOIN demand_lists dl ON dl.id = dcr.demand_list_id
        INNER JOIN procurement_projects p ON p.id = dl.project_id
        LEFT JOIN secretariats s ON s.id = dl.secretariat_id
        LEFT JOIN collaborators c ON c.id = dcr.collaborator_id
        WHERE dcr.token_hash = :token_hash
    ");
    $stmt->execute(['token_hash' => demand_confirmation_token_hash($token)]);
    $row = $stmt->fetch();

    return $row ? enrich_demand_confirmation_request($row) : null;
}

function demand_confirmation_request_expires_at(mixed $value): string
{
    $raw = trim((string) $value);

    if ($raw === '') {
        return (new DateTimeImmutable('+7 days 23:59:59'))->format('Y-m-d H:i:s');
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $raw .= ' 23:59:59';
    }

    return (new DateTimeImmutable($raw))->format('Y-m-d H:i:s');
}

function create_demand_confirmation_request(int $demandListId, array $data): array
{
    if (!demand_confirmation_requests_table_exists()) {
        throw new RuntimeException('Tabela de confirmacoes de demanda nao encontrada. Rode o schema atualizado.');
    }

    $demand = find_demand_list($demandListId);

    if (!$demand) {
        throw new InvalidArgumentException('Demanda nao encontrada.');
    }

    assert_project_editable((int) $demand['project_id']);
    $collaborator = null;
    $collaboratorId = !empty($data['collaborator_id']) ? (int) $data['collaborator_id'] : null;

    if ($collaboratorId) {
        $collaborator = find_collaborator($collaboratorId);

        if (!$collaborator) {
            throw new InvalidArgumentException('Colaborador nao encontrado.');
        }
    }

    $requesterName = trim((string) ($data['requester_name'] ?? ''))
        ?: trim((string) ($collaborator['name'] ?? ''))
        ?: trim((string) ($demand['responsible_name'] ?? ''));

    if ($requesterName === '') {
        throw new InvalidArgumentException('Informe o responsavel que assinara a demanda.');
    }

    $token = bin2hex(random_bytes(24));
    $statement = trim((string) ($data['statement_text'] ?? '')) ?: demand_confirmation_default_statement();
    $stmt = db()->prepare("
        INSERT INTO demand_confirmation_requests (
            demand_list_id, collaborator_id, token_hash, requester_name, requester_document,
            requester_role, requester_email, requester_phone, statement_text, expires_at
        ) VALUES (
            :demand_list_id, :collaborator_id, :token_hash, :requester_name, :requester_document,
            :requester_role, :requester_email, :requester_phone, :statement_text, :expires_at
        )
        RETURNING id
    ");
    $stmt->execute([
        'demand_list_id' => $demandListId,
        'collaborator_id' => $collaboratorId,
        'token_hash' => demand_confirmation_token_hash($token),
        'requester_name' => $requesterName,
        'requester_document' => trim((string) ($data['requester_document'] ?? ($collaborator['document_number'] ?? ''))) ?: null,
        'requester_role' => trim((string) ($data['requester_role'] ?? ($collaborator['role'] ?? ''))) ?: null,
        'requester_email' => trim((string) ($data['requester_email'] ?? ($collaborator['email'] ?? ''))) ?: null,
        'requester_phone' => only_digits((string) ($data['requester_phone'] ?? ($collaborator['phone'] ?? ''))) ?: null,
        'statement_text' => $statement,
        'expires_at' => demand_confirmation_request_expires_at($data['expires_at'] ?? null),
    ]);

    $id = (int) $stmt->fetchColumn();
    $request = find_demand_confirmation_request($id) ?? ['id' => $id];
    $request['token'] = $token;
    $request['sign_url'] = demand_confirmation_token_url($token);

    return $request;
}

function revoke_demand_confirmation_request(int $id): void
{
    $request = find_demand_confirmation_request($id);

    if (!$request) {
        throw new InvalidArgumentException('Solicitacao de confirmacao nao encontrada.');
    }

    assert_project_editable((int) $request['project_id']);

    if (($request['status'] ?? '') === 'signed') {
        throw new RuntimeException('Confirmacoes assinadas nao podem ser revogadas.');
    }

    $stmt = db()->prepare("UPDATE demand_confirmation_requests SET status = 'revoked' WHERE id = :id");
    $stmt->execute(['id' => $id]);
}
function demand_confirmation_snapshot(int $demandListId): array
{
    $demand = find_demand_list($demandListId);

    if (!$demand) {
        throw new InvalidArgumentException('Demanda nao encontrada.');
    }

    $project = find_project((int) $demand['project_id']) ?? [];
    $items = array_map(static fn (array $item): array => [
        'tracking_code' => (string) ($item['tracking_code'] ?? ''),
        'name' => (string) ($item['item_name'] ?? ''),
        'unit' => (string) (($item['unit_type_abbreviation'] ?? '') ?: ($item['unit_type_name'] ?? '')),
        'package_content' => function_exists('format_package_content') ? format_package_content($item) : '',
        'quantity' => (float) ($item['quantity'] ?? 0),
        'approved_quantity' => (float) ($item['approved_quantity'] ?? $item['quantity'] ?? 0),
        'notes' => (string) ($item['notes'] ?? ''),
    ], get_demand_items($demandListId));

    return [
        'project' => [
            'id' => (int) ($project['id'] ?? 0),
            'name' => (string) ($project['name'] ?? ''),
            'status' => (string) ($project['status'] ?? ''),
        ],
        'demand' => [
            'id' => (int) $demandListId,
            'name' => (string) ($demand['name'] ?? ''),
            'secretariat' => (string) ($demand['secretariat_name'] ?? ''),
            'requester_department' => (string) (($demand['requester_department'] ?? '') ?: ($demand['requester_unit_name'] ?? '')),
            'responsible_name' => (string) ($demand['responsible_name'] ?? ''),
        ],
        'items' => $items,
    ];
}

function demand_confirmation_file_hash(?string $filename): ?string
{
    if (!$filename) {
        return null;
    }

    $path = demand_confirmation_file_path($filename);

    return is_file($path) ? hash_file('sha256', $path) : null;
}

function sign_demand_confirmation_request(string $token, array $data, array $documentFile): array
{
    $request = find_demand_confirmation_request_by_token($token);

    if (!$request) {
        throw new RuntimeException('Link de confirmacao invalido ou inexistente.');
    }

    if (($request['effective_status'] ?? '') !== 'pending') {
        throw new RuntimeException('Esta solicitacao nao esta disponivel para assinatura.');
    }

    if (empty($data['accepted_statement'])) {
        throw new RuntimeException('Confirme a declaracao antes de assinar.');
    }

    $requesterName = trim((string) ($data['requester_name'] ?? $request['requester_name'] ?? ''));

    if ($requesterName === '') {
        throw new RuntimeException('Informe o nome do responsavel pela assinatura.');
    }

    $signaturePath = save_demand_confirmation_signature((string) ($data['signature_data'] ?? ''));
    $documentPath = upload_demand_confirmation_document($documentFile);
    $signedAt = date('Y-m-d H:i:s');
    $snapshot = demand_confirmation_snapshot((int) $request['demand_list_id']);
    $payload = [
        'type' => 'demand_confirmation',
        'request_id' => (int) $request['id'],
        'demand_list_id' => (int) $request['demand_list_id'],
        'requester' => [
            'name' => $requesterName,
            'document' => trim((string) ($data['requester_document'] ?? $request['requester_document'] ?? '')),
            'role' => trim((string) ($data['requester_role'] ?? $request['requester_role'] ?? '')),
            'email' => trim((string) ($data['requester_email'] ?? $request['requester_email'] ?? '')),
            'phone' => only_digits((string) ($data['requester_phone'] ?? $request['requester_phone'] ?? '')),
        ],
        'statement' => (string) ($request['statement_text'] ?? ''),
        'signed_at' => $signedAt,
        'snapshot' => $snapshot,
        'signature_sha256' => demand_confirmation_file_hash($signaturePath),
        'document_sha256' => demand_confirmation_file_hash($documentPath),
    ];
    $contentHash = project_annex_hash($payload);

    $stmt = db()->prepare("
        UPDATE demand_confirmation_requests SET
            requester_name = :requester_name,
            requester_document = :requester_document,
            requester_role = :requester_role,
            requester_email = :requester_email,
            requester_phone = :requester_phone,
            status = 'signed',
            signed_at = :signed_at,
            signature_path = :signature_path,
            document_photo_path = :document_photo_path,
            snapshot = CAST(:snapshot AS jsonb),
            content_hash = :content_hash,
            signer_ip = :signer_ip,
            signer_user_agent = :signer_user_agent
        WHERE id = :id AND status = 'pending'
    ");
    $stmt->execute([
        'id' => (int) $request['id'],
        'requester_name' => $requesterName,
        'requester_document' => trim((string) ($data['requester_document'] ?? $request['requester_document'] ?? '')) ?: null,
        'requester_role' => trim((string) ($data['requester_role'] ?? $request['requester_role'] ?? '')) ?: null,
        'requester_email' => trim((string) ($data['requester_email'] ?? $request['requester_email'] ?? '')) ?: null,
        'requester_phone' => only_digits((string) ($data['requester_phone'] ?? $request['requester_phone'] ?? '')) ?: null,
        'signed_at' => $signedAt,
        'signature_path' => $signaturePath,
        'document_photo_path' => $documentPath,
        'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
        'content_hash' => $contentHash,
        'signer_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'signer_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000) ?: null,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('A solicitacao ja foi assinada, revogada ou expirou.');
    }

    return find_demand_confirmation_request((int) $request['id']) ?? $request;
}

function demand_confirmation_file_info(int $requestId, string $kind): ?array
{
    $request = find_demand_confirmation_request($requestId);

    if (!$request) {
        return null;
    }

    $filename = match ($kind) {
        'signature' => $request['signature_path'] ?? null,
        'document' => $request['document_photo_path'] ?? null,
        default => null,
    };

    if (!$filename) {
        return null;
    }

    $path = demand_confirmation_file_path((string) $filename);

    if (!is_file($path)) {
        return null;
    }

    return [
        'path' => $path,
        'filename' => basename((string) $filename),
        'mime' => mime_content_type($path) ?: 'application/octet-stream',
        'download_name' => ($kind === 'signature' ? 'assinatura-demanda-' : 'documento-demanda-') . $requestId . '.' . pathinfo($path, PATHINFO_EXTENSION),
    ];
}