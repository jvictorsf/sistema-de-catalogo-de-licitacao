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

function demand_signature_flows_table_exists(): bool
{
    return database_table_exists('demand_signature_flows');
}

function demand_confirmation_attachments_table_exists(): bool
{
    return database_table_exists('demand_confirmation_attachments');
}
function normalize_collaborator_data(array $data): array
{
    $data['name'] = trim((string) ($data['name'] ?? ''));
    $data['document_number'] = trim((string) ($data['document_number'] ?? '')) ?: null;
    $data['registration_number'] = trim((string) ($data['registration_number'] ?? '')) ?: null;
    $data['role'] = trim((string) ($data['role'] ?? '')) ?: null;
    $data['department'] = trim((string) ($data['department'] ?? '')) ?: null;
    $data['requester_unit_id'] = (int) ($data['requester_unit_id'] ?? 0) ?: null;
    $data['email'] = trim((string) ($data['email'] ?? '')) ?: null;
    $data['phone'] = only_digits((string) ($data['phone'] ?? '')) ?: null;
    $data['branch'] = trim((string) ($data['branch'] ?? '')) ?: null;
    $data['whatsapp'] = only_digits((string) ($data['whatsapp'] ?? '')) ?: null;
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
        $filters[] = 'c.is_active = TRUE';
    }

    if ($search !== '') {
        $filters[] = "(
            lower(c.name) LIKE :search
            OR lower(COALESCE(c.document_number, '')) LIKE :search
            OR lower(COALESCE(c.registration_number, '')) LIKE :search
            OR lower(COALESCE(c.role, '')) LIKE :search
            OR lower(COALESCE(c.department, '')) LIKE :search
            OR lower(COALESCE(c.email, '')) LIKE :search
            OR lower(COALESCE(c.branch, '')) LIKE :search
            OR lower(COALESCE(c.whatsapp, '')) LIKE :search
            OR lower(COALESCE(ru.name, '')) LIKE :search
            OR lower(COALESCE(parent_ru.name, '')) LIKE :search
            OR lower(COALESCE(s.name, '')) LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    $sql = "
        SELECT
            c.*,
            CASE
                WHEN parent_ru.id IS NOT NULL THEN parent_ru.name || ' - ' || ru.name
                ELSE ru.name
            END AS requester_unit_name,
            s.name AS secretariat_name
        FROM collaborators c
        LEFT JOIN requester_units ru ON ru.id = c.requester_unit_id
        LEFT JOIN requester_units parent_ru ON parent_ru.id = ru.parent_id
        LEFT JOIN secretariats s ON s.id = ru.secretariat_id
    ";

    if ($filters) {
        $sql .= ' WHERE ' . implode(' AND ', $filters);
    }

    $sql .= ' ORDER BY c.is_active DESC, lower(c.name)';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}
function find_collaborator(int $id): ?array
{
    if (!collaborators_table_exists()) {
        return null;
    }

    $stmt = db()->prepare("
        SELECT
            c.*,
            CASE
                WHEN parent_ru.id IS NOT NULL THEN parent_ru.name || ' - ' || ru.name
                ELSE ru.name
            END AS requester_unit_name,
            s.name AS secretariat_name
        FROM collaborators c
        LEFT JOIN requester_units ru ON ru.id = c.requester_unit_id
        LEFT JOIN requester_units parent_ru ON parent_ru.id = ru.parent_id
        LEFT JOIN secretariats s ON s.id = ru.secretariat_id
        WHERE c.id = :id
    ");
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
        INSERT INTO collaborators (name, document_number, registration_number, role, department, requester_unit_id, email, phone, branch, whatsapp, is_active)
        VALUES (:name, :document_number, :registration_number, :role, :department, :requester_unit_id, :email, :phone, :branch, :whatsapp, :is_active)
        RETURNING id
    ");
    $stmt->execute([
        'name' => $data['name'],
        'document_number' => $data['document_number'],
        'registration_number' => $data['registration_number'],
        'role' => $data['role'],
        'department' => $data['department'],
        'requester_unit_id' => $data['requester_unit_id'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'branch' => $data['branch'],
        'whatsapp' => $data['whatsapp'],
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
            requester_unit_id = :requester_unit_id,
            email = :email,
            phone = :phone,
            branch = :branch,
            whatsapp = :whatsapp,
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
        'requester_unit_id' => $data['requester_unit_id'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'branch' => $data['branch'],
        'whatsapp' => $data['whatsapp'],
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

function demand_signature_flow_mode(mixed $mode): string
{
    return in_array($mode, ['parallel', 'sequential'], true) ? (string) $mode : 'parallel';
}

function demand_signature_flow_mode_label(?string $mode): string
{
    return match ($mode) {
        'sequential' => 'Sequencial',
        'parallel' => 'Paralelo',
        default => 'Assinatura individual',
    };
}

function demand_confirmation_initial_request_status(string $mode, int $signerOrder): string
{
    return demand_signature_flow_mode($mode) === 'sequential' && $signerOrder > 1 ? 'waiting' : 'pending';
}

function demand_confirmation_effective_status(array $request): string
{
    $status = (string) ($request['status'] ?? 'pending');

    if (in_array($status, ['pending', 'waiting'], true) && !empty($request['expires_at'])) {
        $expiresAt = strtotime((string) $request['expires_at']);

        if ($expiresAt !== false && $expiresAt < time()) {
            return 'expired';
        }
    }

    return $status;
}

function demand_confirmation_request_select_sql(): string
{
    $attachmentCount = demand_confirmation_attachments_table_exists()
        ? '(SELECT COUNT(*) FROM demand_confirmation_attachments dca WHERE dca.demand_confirmation_request_id = dcr.id)'
        : '0';

    if (!demand_signature_flows_table_exists()) {
        return "
            SELECT dcr.*, NULL::integer AS flow_id, 1::integer AS signer_order,
                   NULL::timestamp AS activated_at, NULL::varchar AS flow_title,
                   NULL::varchar AS flow_mode, NULL::varchar AS flow_status,
                   NULL::timestamp AS flow_completed_at, 1::integer AS flow_signer_count,
                   CASE WHEN dcr.status = 'signed' THEN 1 ELSE 0 END::integer AS flow_signed_count,
                   {$attachmentCount}::integer AS attachment_count,
                   dl.project_id, dl.name AS demand_name, dl.requester_department,
                   dl.responsible_name AS demand_responsible_name, dl.secretariat_id,
                   s.name AS secretariat_name, p.name AS project_name, p.status AS project_status,
                   c.name AS collaborator_name, c.role AS collaborator_role, c.department AS collaborator_department
            FROM demand_confirmation_requests dcr
            INNER JOIN demand_lists dl ON dl.id = dcr.demand_list_id
            INNER JOIN procurement_projects p ON p.id = dl.project_id
            LEFT JOIN secretariats s ON s.id = dl.secretariat_id
            LEFT JOIN collaborators c ON c.id = dcr.collaborator_id
        ";
    }

    return "
        SELECT dcr.*, dsf.title AS flow_title, dsf.mode AS flow_mode,
               dsf.status AS flow_status, dsf.completed_at AS flow_completed_at,
               (SELECT COUNT(*) FROM demand_confirmation_requests all_requests WHERE all_requests.flow_id = dcr.flow_id)::integer AS flow_signer_count,
               (SELECT COUNT(*) FROM demand_confirmation_requests signed_requests WHERE signed_requests.flow_id = dcr.flow_id AND signed_requests.status = 'signed')::integer AS flow_signed_count,
               {$attachmentCount}::integer AS attachment_count,
               dl.project_id, dl.name AS demand_name, dl.requester_department,
               dl.responsible_name AS demand_responsible_name, dl.secretariat_id,
               s.name AS secretariat_name, p.name AS project_name, p.status AS project_status,
               c.name AS collaborator_name, c.role AS collaborator_role, c.department AS collaborator_department
        FROM demand_confirmation_requests dcr
        INNER JOIN demand_lists dl ON dl.id = dcr.demand_list_id
        INNER JOIN procurement_projects p ON p.id = dl.project_id
        LEFT JOIN secretariats s ON s.id = dl.secretariat_id
        LEFT JOIN collaborators c ON c.id = dcr.collaborator_id
        LEFT JOIN demand_signature_flows dsf ON dsf.id = dcr.flow_id
    ";
}

function enrich_demand_confirmation_request(array $request): array
{
    $request['effective_status'] = demand_confirmation_effective_status($request);
    $request['snapshot'] = repository_json_array($request['snapshot'] ?? []);
    $request['signer_order'] = max(1, (int) ($request['signer_order'] ?? 1));
    $request['flow_signer_count'] = max(1, (int) ($request['flow_signer_count'] ?? 1));
    $request['flow_signed_count'] = max(0, (int) ($request['flow_signed_count'] ?? 0));
    $request['attachment_count'] = max(0, (int) ($request['attachment_count'] ?? 0));

    return $request;
}

function get_demand_confirmation_requests(int $demandListId): array
{
    if (!demand_confirmation_requests_table_exists()) {
        return [];
    }

    $sql = demand_confirmation_request_select_sql()
        . ' WHERE dcr.demand_list_id = :demand_list_id'
        . (demand_signature_flows_table_exists()
            ? ' ORDER BY COALESCE(dcr.flow_id, 0) DESC, dcr.signer_order, dcr.id'
            : ' ORDER BY dcr.created_at DESC, dcr.id DESC');
    $stmt = db()->prepare($sql);
    $stmt->execute(['demand_list_id' => $demandListId]);

    return array_map('enrich_demand_confirmation_request', $stmt->fetchAll());
}

function find_demand_confirmation_request(int $id): ?array
{
    if (!demand_confirmation_requests_table_exists()) {
        return null;
    }

    $stmt = db()->prepare(demand_confirmation_request_select_sql() . ' WHERE dcr.id = :id');
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

    $stmt = db()->prepare(demand_confirmation_request_select_sql() . ' WHERE dcr.token_hash = :token_hash');
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

function normalize_demand_confirmation_signer(array $data, array $demand): array
{
    $collaboratorId = !empty($data['collaborator_id']) ? (int) $data['collaborator_id'] : null;
    $collaborator = $collaboratorId ? find_collaborator($collaboratorId) : null;

    if ($collaboratorId && !$collaborator) {
        throw new InvalidArgumentException('Um dos colaboradores selecionados nao foi encontrado.');
    }

    $name = trim((string) ($data['requester_name'] ?? ''))
        ?: trim((string) ($collaborator['name'] ?? ''));

    if ($name === '') {
        throw new InvalidArgumentException('Informe o nome de todos os assinantes.');
    }

    return [
        'collaborator_id' => $collaboratorId,
        'requester_name' => $name,
        'requester_document' => trim((string) ($data['requester_document'] ?? ($collaborator['document_number'] ?? ''))) ?: null,
        'requester_role' => trim((string) ($data['requester_role'] ?? ($collaborator['role'] ?? ''))) ?: null,
        'requester_email' => trim((string) ($data['requester_email'] ?? ($collaborator['email'] ?? ''))) ?: null,
        'requester_phone' => only_digits((string) ($data['requester_phone'] ?? ($collaborator['phone'] ?? ''))) ?: null,
    ];
}

function create_demand_confirmation_flow(int $demandListId, array $data): array
{
    if (!demand_confirmation_requests_table_exists() || !demand_signature_flows_table_exists()) {
        throw new RuntimeException('Estrutura de fluxos de assinatura nao encontrada. Rode o schema atualizado.');
    }

    $demand = find_demand_list($demandListId);

    if (!$demand) {
        throw new InvalidArgumentException('Demanda nao encontrada.');
    }

    assert_project_editable((int) $demand['project_id']);
    $rawSigners = is_array($data['signers'] ?? null) ? $data['signers'] : [];
    $signers = [];

    foreach ($rawSigners as $rawSigner) {
        if (!is_array($rawSigner)) {
            continue;
        }

        $hasContent = (int) ($rawSigner['collaborator_id'] ?? 0) > 0
            || trim((string) ($rawSigner['requester_name'] ?? '')) !== '';

        if ($hasContent) {
            $signers[] = normalize_demand_confirmation_signer($rawSigner, $demand);
        }
    }

    if (!$signers) {
        throw new InvalidArgumentException('Adicione ao menos um assinante.');
    }

    if (count($signers) > 20) {
        throw new InvalidArgumentException('Cada fluxo pode ter no maximo 20 assinantes.');
    }

    $mode = demand_signature_flow_mode($data['mode'] ?? 'parallel');
    $statement = trim((string) ($data['statement_text'] ?? '')) ?: demand_confirmation_default_statement();
    $expiresAt = demand_confirmation_request_expires_at($data['expires_at'] ?? null);
    $title = trim((string) ($data['title'] ?? '')) ?: 'Confirmacao da demanda ' . (string) ($demand['name'] ?? $demandListId);
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $flowStmt = $pdo->prepare("
            INSERT INTO demand_signature_flows (demand_list_id, title, mode, status, statement_text, expires_at)
            VALUES (:demand_list_id, :title, :mode, 'pending', :statement_text, :expires_at)
            RETURNING id
        ");
        $flowStmt->execute([
            'demand_list_id' => $demandListId,
            'title' => $title,
            'mode' => $mode,
            'statement_text' => $statement,
            'expires_at' => $expiresAt,
        ]);
        $flowId = (int) $flowStmt->fetchColumn();
        $created = [];
        $requestStmt = $pdo->prepare("
            INSERT INTO demand_confirmation_requests (
                flow_id, signer_order, activated_at, demand_list_id, collaborator_id, token_hash,
                requester_name, requester_document, requester_role, requester_email, requester_phone,
                statement_text, status, expires_at
            ) VALUES (
                :flow_id, :signer_order, :activated_at, :demand_list_id, :collaborator_id, :token_hash,
                :requester_name, :requester_document, :requester_role, :requester_email, :requester_phone,
                :statement_text, :status, :expires_at
            ) RETURNING id
        ");

        foreach ($signers as $index => $signer) {
            $order = $index + 1;
            $status = demand_confirmation_initial_request_status($mode, $order);
            $token = bin2hex(random_bytes(24));
            $requestStmt->execute([
                'flow_id' => $flowId,
                'signer_order' => $order,
                'activated_at' => $status === 'pending' ? date('Y-m-d H:i:s') : null,
                'demand_list_id' => $demandListId,
                'collaborator_id' => $signer['collaborator_id'],
                'token_hash' => demand_confirmation_token_hash($token),
                'requester_name' => $signer['requester_name'],
                'requester_document' => $signer['requester_document'],
                'requester_role' => $signer['requester_role'],
                'requester_email' => $signer['requester_email'],
                'requester_phone' => $signer['requester_phone'],
                'statement_text' => $statement,
                'status' => $status,
                'expires_at' => $expiresAt,
            ]);
            $created[] = [
                'id' => (int) $requestStmt->fetchColumn(),
                'token' => $token,
                'sign_url' => demand_confirmation_token_url($token),
                'signer_order' => $order,
                'requester_name' => $signer['requester_name'],
                'status' => $status,
            ];
        }

        $pdo->commit();

        return [
            'id' => $flowId,
            'title' => $title,
            'mode' => $mode,
            'requests' => $created,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function create_demand_confirmation_request(int $demandListId, array $data): array
{
    $flow = create_demand_confirmation_flow($demandListId, [
        'title' => $data['title'] ?? null,
        'mode' => 'parallel',
        'statement_text' => $data['statement_text'] ?? null,
        'expires_at' => $data['expires_at'] ?? null,
        'signers' => [$data],
    ]);

    return $flow['requests'][0];
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

    $pdo = db();
    $pdo->beginTransaction();

    try {
        if (demand_signature_flows_table_exists() && (int) ($request['flow_id'] ?? 0) > 0) {
            $stmt = $pdo->prepare("
                UPDATE demand_confirmation_requests
                SET status = 'revoked'
                WHERE flow_id = :flow_id AND status IN ('pending', 'waiting')
            ");
            $stmt->execute(['flow_id' => (int) $request['flow_id']]);
            $stmt = $pdo->prepare("UPDATE demand_signature_flows SET status = 'revoked' WHERE id = :id");
            $stmt->execute(['id' => (int) $request['flow_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE demand_confirmation_requests SET status = 'revoked' WHERE id = :id");
            $stmt->execute(['id' => $id]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
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

function get_demand_confirmation_attachments(int $requestId): array
{
    if (!demand_confirmation_attachments_table_exists()) {
        return [];
    }

    $stmt = db()->prepare("
        SELECT * FROM demand_confirmation_attachments
        WHERE demand_confirmation_request_id = :request_id
        ORDER BY id
    ");
    $stmt->execute(['request_id' => $requestId]);

    return $stmt->fetchAll();
}

function store_demand_confirmation_attachments(array $files): array
{
    $normalized = array_values(array_filter(
        normalize_uploaded_file_list($files),
        static fn (array $file): bool => ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ));

    if (!$normalized) {
        throw new RuntimeException('Envie ao menos uma foto ou PDF como comprovacao.');
    }

    if (!demand_confirmation_attachments_table_exists() && count($normalized) > 1) {
        throw new RuntimeException('Para enviar varios comprovantes, rode o schema atualizado.');
    }

    $stored = [];

    try {
        foreach ($normalized as $file) {
            $mime = is_file((string) ($file['tmp_name'] ?? ''))
                ? (mime_content_type((string) $file['tmp_name']) ?: 'application/octet-stream')
                : 'application/octet-stream';
            $storedPath = upload_demand_confirmation_document($file);
            $stored[] = [
                'kind' => 'evidence',
                'original_name' => basename(str_replace('\\', '/', (string) ($file['name'] ?? 'comprovante'))),
                'stored_path' => $storedPath,
                'mime_type' => $mime,
                'file_size' => (int) ($file['size'] ?? 0),
                'file_hash' => demand_confirmation_file_hash($storedPath) ?? hash('sha256', $storedPath),
            ];
        }
    } catch (Throwable $exception) {
        remove_demand_confirmation_files(array_column($stored, 'stored_path'));
        throw $exception;
    }

    return $stored;
}

function remove_demand_confirmation_files(array $filenames): void
{
    foreach ($filenames as $filename) {
        if (!$filename) {
            continue;
        }

        $path = demand_confirmation_file_path((string) $filename);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function demand_confirmation_signature_hash_payload(
    array $request,
    array $signer,
    array $snapshot,
    ?string $signatureHash,
    array $attachments,
    string $signedAt
): array {
    return [
        'type' => 'demand_confirmation',
        'request_id' => (int) ($request['id'] ?? 0),
        'flow_id' => (int) ($request['flow_id'] ?? 0),
        'signer_order' => (int) ($request['signer_order'] ?? 1),
        'demand_list_id' => (int) ($request['demand_list_id'] ?? 0),
        'requester' => $signer,
        'statement' => (string) ($request['statement_text'] ?? ''),
        'signed_at' => $signedAt,
        'snapshot' => $snapshot,
        'signature_sha256' => $signatureHash,
        'attachments' => array_map(static fn (array $attachment): array => [
            'name' => (string) ($attachment['original_name'] ?? ''),
            'mime' => (string) ($attachment['mime_type'] ?? ''),
            'size' => (int) ($attachment['file_size'] ?? 0),
            'sha256' => (string) ($attachment['file_hash'] ?? ''),
        ], $attachments),
    ];
}

function advance_demand_signature_flow(int $flowId): void
{
    if ($flowId <= 0 || !demand_signature_flows_table_exists()) {
        return;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM demand_signature_flows WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $flowId]);
    $flow = $stmt->fetch();

    if (!$flow || ($flow['status'] ?? '') === 'revoked') {
        return;
    }

    if (($flow['mode'] ?? 'parallel') === 'sequential') {
        $activate = $pdo->prepare("
            UPDATE demand_confirmation_requests
            SET status = 'pending', activated_at = CURRENT_TIMESTAMP
            WHERE id = (
                SELECT id FROM demand_confirmation_requests
                WHERE flow_id = :flow_id AND status = 'waiting'
                ORDER BY signer_order
                LIMIT 1
            )
        ");
        $activate->execute(['flow_id' => $flowId]);
    }

    $count = $pdo->prepare("
        SELECT COUNT(*) FROM demand_confirmation_requests
        WHERE flow_id = :flow_id AND status IN ('pending', 'waiting')
    ");
    $count->execute(['flow_id' => $flowId]);
    $remaining = (int) $count->fetchColumn();
    $update = $pdo->prepare("
        UPDATE demand_signature_flows
        SET status = :status, completed_at = :completed_at
        WHERE id = :id
    ");
    $update->execute([
        'id' => $flowId,
        'status' => $remaining === 0 ? 'completed' : 'pending',
        'completed_at' => $remaining === 0 ? date('Y-m-d H:i:s') : null,
    ]);
}

function sign_demand_confirmation_request(string $token, array $data, array $documentFiles): array
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

    $signer = [
        'name' => $requesterName,
        'document' => trim((string) ($data['requester_document'] ?? $request['requester_document'] ?? '')),
        'role' => trim((string) ($data['requester_role'] ?? $request['requester_role'] ?? '')),
        'email' => trim((string) ($data['requester_email'] ?? $request['requester_email'] ?? '')),
        'phone' => only_digits((string) ($data['requester_phone'] ?? $request['requester_phone'] ?? '')),
    ];
    $signaturePath = null;
    $attachments = [];
    $pdo = db();

    try {
        $signaturePath = save_demand_confirmation_signature((string) ($data['signature_data'] ?? ''));
        $attachments = store_demand_confirmation_attachments($documentFiles);
        $signedAt = date('Y-m-d H:i:s');
        $snapshot = demand_confirmation_snapshot((int) $request['demand_list_id']);
        $payload = demand_confirmation_signature_hash_payload(
            $request,
            $signer,
            $snapshot,
            demand_confirmation_file_hash($signaturePath),
            $attachments,
            $signedAt
        );
        $contentHash = project_annex_hash($payload);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE demand_confirmation_requests SET
                requester_name = :requester_name,
                requester_document = :requester_document,
                requester_role = :requester_role,
                requester_email = :requester_email,
                requester_phone = :requester_phone,
                status = 'signed', signed_at = :signed_at,
                signature_path = :signature_path,
                document_photo_path = :document_photo_path,
                snapshot = CAST(:snapshot AS jsonb), content_hash = :content_hash,
                signer_ip = :signer_ip, signer_user_agent = :signer_user_agent
            WHERE id = :id AND status = 'pending'
        ");
        $stmt->execute([
            'id' => (int) $request['id'],
            'requester_name' => $signer['name'],
            'requester_document' => $signer['document'] ?: null,
            'requester_role' => $signer['role'] ?: null,
            'requester_email' => $signer['email'] ?: null,
            'requester_phone' => $signer['phone'] ?: null,
            'signed_at' => $signedAt,
            'signature_path' => $signaturePath,
            'document_photo_path' => $attachments[0]['stored_path'],
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            'content_hash' => $contentHash,
            'signer_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'signer_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000) ?: null,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('A solicitacao ja foi assinada, revogada ou expirou.');
        }

        if (demand_confirmation_attachments_table_exists()) {
            $attachmentStmt = $pdo->prepare("
                INSERT INTO demand_confirmation_attachments (
                    demand_confirmation_request_id, kind, original_name, stored_path,
                    mime_type, file_size, file_hash
                ) VALUES (
                    :request_id, :kind, :original_name, :stored_path,
                    :mime_type, :file_size, :file_hash
                )
            ");

            foreach ($attachments as $attachment) {
                $attachmentStmt->execute([
                    'request_id' => (int) $request['id'],
                    'kind' => $attachment['kind'],
                    'original_name' => $attachment['original_name'],
                    'stored_path' => $attachment['stored_path'],
                    'mime_type' => $attachment['mime_type'],
                    'file_size' => $attachment['file_size'],
                    'file_hash' => $attachment['file_hash'],
                ]);
            }
        }

        advance_demand_signature_flow((int) ($request['flow_id'] ?? 0));
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        remove_demand_confirmation_files(array_merge(
            $signaturePath ? [$signaturePath] : [],
            array_column($attachments, 'stored_path')
        ));
        throw $exception;
    }

    return find_demand_confirmation_request((int) $request['id']) ?? $request;
}

function demand_confirmation_file_info(int $requestId, string $kind, int $attachmentId = 0): ?array
{
    $request = find_demand_confirmation_request($requestId);

    if (!$request) {
        return null;
    }

    if ($kind === 'attachment' && $attachmentId > 0 && demand_confirmation_attachments_table_exists()) {
        $stmt = db()->prepare("
            SELECT * FROM demand_confirmation_attachments
            WHERE id = :id AND demand_confirmation_request_id = :request_id
        ");
        $stmt->execute(['id' => $attachmentId, 'request_id' => $requestId]);
        $attachment = $stmt->fetch();

        if (!$attachment) {
            return null;
        }

        $filename = (string) $attachment['stored_path'];
        $path = demand_confirmation_file_path($filename);

        if (!is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'filename' => basename($filename),
            'mime' => (string) (($attachment['mime_type'] ?? '') ?: (mime_content_type($path) ?: 'application/octet-stream')),
            'download_name' => basename((string) (($attachment['original_name'] ?? '') ?: $filename)),
        ];
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

function get_demand_signature_pending_rows(array $filters = []): array
{
    if (!demand_confirmation_requests_table_exists()) {
        return [];
    }

    $where = ["dcr.status IN ('pending', 'waiting')"];
    $params = [];
    $search = trim((string) ($filters['q'] ?? ''));

    if ($search !== '') {
        $where[] = "(
            lower(dcr.requester_name) LIKE :search
            OR lower(dl.name) LIKE :search
            OR lower(p.name) LIKE :search
            OR lower(COALESCE(s.name, '')) LIKE :search
            OR lower(COALESCE(dcr.requester_role, '')) LIKE :search
        )";
        $params['search'] = '%' . mb_strtolower($search) . '%';
    }

    $projectId = (int) ($filters['project_id'] ?? 0);
    if ($projectId > 0) {
        $where[] = 'dl.project_id = :project_id';
        $params['project_id'] = $projectId;
    }

    $secretariatId = (int) ($filters['secretariat_id'] ?? 0);
    if ($secretariatId > 0) {
        $where[] = 'dl.secretariat_id = :secretariat_id';
        $params['secretariat_id'] = $secretariatId;
    }

    $mode = trim((string) ($filters['mode'] ?? ''));
    if ($mode !== '') {
        if (!demand_signature_flows_table_exists()) {
            if ($mode !== 'legacy') {
                return [];
            }
        } elseif ($mode === 'legacy') {
            $where[] = 'dcr.flow_id IS NULL';
        } else {
            $where[] = 'dsf.mode = :mode';
            $params['mode'] = demand_signature_flow_mode($mode);
        }
    }

    $sql = demand_confirmation_request_select_sql()
        . ' WHERE ' . implode(' AND ', $where)
        . ' ORDER BY dcr.expires_at NULLS LAST, p.name, dl.name, dcr.signer_order LIMIT 500';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = array_map('enrich_demand_confirmation_request', $stmt->fetchAll());
    $status = trim((string) ($filters['status'] ?? ''));

    if ($status !== '') {
        $rows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => ($row['effective_status'] ?? '') === $status
        ));
    }

    return $rows;
}

function demand_signature_pending_summary(array $rows): array
{
    $summary = ['total' => count($rows), 'pending' => 0, 'waiting' => 0, 'expired' => 0];

    foreach ($rows as $row) {
        $status = (string) ($row['effective_status'] ?? 'pending');
        if (array_key_exists($status, $summary)) {
            $summary[$status]++;
        }
    }

    return $summary;
}