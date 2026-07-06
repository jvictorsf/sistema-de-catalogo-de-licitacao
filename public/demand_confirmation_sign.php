<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$request = find_demand_confirmation_request_by_token($token);
$errors = [];
$signed = false;

if (!$request) {
    http_response_code(404);
    exit('Link de confirmacao invalido ou expirado.');
}

$items = get_demand_items((int) $request['demand_list_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $request = sign_demand_confirmation_request($token, $_POST, $_FILES['document_photo'] ?? []);
        $signed = true;
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage() ?: 'Nao foi possivel registrar a assinatura.';
    }
}

$status = $request['effective_status'] ?? demand_confirmation_effective_status($request);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Confirmacao de demanda</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; }
        .signature-pad { width: 100%; height: 220px; border: 1px solid #adb5bd; border-radius: .5rem; background: #fff; touch-action: none; }
        .mobile-shell { max-width: 900px; }
    </style>
</head>
<body>
<main class="container mobile-shell py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-clipboard-check fs-3 text-primary"></i>
                <div>
                    <h1 class="h4 mb-0">Confirmacao da demanda</h1>
                    <div class="text-muted small"><?= e($request['project_name'] ?? '-') ?></div>
                </div>
            </div>
            <div class="row g-2 small mt-3">
                <div class="col-md-6"><span class="text-muted">Demanda:</span> <strong><?= e($request['demand_name'] ?? '-') ?></strong></div>
                <div class="col-md-6"><span class="text-muted">Secretaria:</span> <strong><?= e($request['secretariat_name'] ?? '-') ?></strong></div>
                <div class="col-md-6"><span class="text-muted">Unidade:</span> <strong><?= e($request['requester_department'] ?? '-') ?></strong></div>
                <div class="col-md-6"><span class="text-muted">Responsavel:</span> <strong><?= e($request['requester_name'] ?? '-') ?></strong></div>
            </div>
        </div>
    </div>

    <?php if ($signed): ?>
        <div class="alert alert-success">
            <div class="fw-semibold">Assinatura registrada com sucesso.</div>
            <div>Hash da confirmacao: <code><?= e($request['content_hash'] ?? '') ?></code></div>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($status !== 'pending'): ?>
        <div class="alert alert-warning">
            Esta solicitacao esta <?= e(mb_strtolower(demand_confirmation_status_label($status))) ?> e nao aceita nova assinatura.
        </div>
    <?php else: ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Itens da demanda</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Codigo</th>
                            <th>Item</th>
                            <th>Unidade</th>
                            <th class="text-end">Qtd.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><span class="badge text-bg-dark"><?= e($item['tracking_code'] ?? '') ?></span></td>
                                <td><?= e($item['item_name'] ?? '') ?></td>
                                <td>
                                    <?= e(($item['unit_type_abbreviation'] ?? '') ?: ($item['unit_type_name'] ?? '-')) ?>
                                    <?php if (format_package_content($item) !== '-'): ?>
                                        <div class="small text-muted"><?= e(format_package_content($item)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-semibold"><?= e((string) ($item['approved_quantity'] ?? $item['quantity'] ?? '0')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="card shadow-sm">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="signature_data" id="signatureData">

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome completo</label>
                        <input type="text" name="requester_name" class="form-control" required value="<?= e($request['requester_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CPF</label>
                        <input type="text" name="requester_document" class="form-control" inputmode="numeric" maxlength="14" value="<?= e(format_brazil_document($request['requester_document'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cargo/Função</label>
                        <input type="text" name="requester_role" class="form-control" value="<?= e($request['requester_role'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="requester_email" class="form-control" value="<?= e($request['requester_email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="requester_phone" class="form-control" maxlength="15" value="<?= e(format_brazil_phone($request['requester_phone'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Documento pessoal para comprovacao</label>
                        <input type="file" name="document_photo" class="form-control" accept="image/*,application/pdf" capture="environment" required>
                        <div class="form-text">O arquivo sera guardado em storage privado e usado como evidencia da assinatura.</div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-3 bg-light">
                            <?= nl2br(e($request['statement_text'] ?? demand_confirmation_default_statement())) ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Assinatura</label>
                        <canvas id="signatureCanvas" class="signature-pad"></canvas>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <button type="button" class="btn btn-outline-secondary" id="clearSignature"><i class="bi bi-eraser"></i>Limpar</button>
                            <span class="small text-muted">Assine com o dedo ou caneta touch.</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="accepted_statement" value="1" class="form-check-input" id="acceptedStatement" required>
                            <label class="form-check-label" for="acceptedStatement">Confirmo que li e concordo com a declaracao acima.</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-grid">
                <button class="btn btn-primary btn-lg"><i class="bi bi-pen"></i>Assinar demanda</button>
            </div>
        </form>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signatureCanvas');
    const input = document.getElementById('signatureData');
    const clear = document.getElementById('clearSignature');
    let drawing = false;
    let hasStroke = false;

    if (!canvas || !input) return;

    const context = canvas.getContext('2d');

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.lineWidth = 2.5;
        context.lineCap = 'round';
        context.strokeStyle = '#111827';
    }

    function point(event) {
        const rect = canvas.getBoundingClientRect();
        const touch = event.touches ? event.touches[0] : event;
        return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
    }

    function start(event) {
        event.preventDefault();
        drawing = true;
        const p = point(event);
        context.beginPath();
        context.moveTo(p.x, p.y);
    }

    function move(event) {
        if (!drawing) return;
        event.preventDefault();
        const p = point(event);
        context.lineTo(p.x, p.y);
        context.stroke();
        hasStroke = true;
    }

    function stop() {
        drawing = false;
        input.value = hasStroke ? canvas.toDataURL('image/png') : '';
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', stop);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', stop);

    clear?.addEventListener('click', function() {
        context.clearRect(0, 0, canvas.width, canvas.height);
        hasStroke = false;
        input.value = '';
    });

    canvas.closest('form')?.addEventListener('submit', function(event) {
        input.value = hasStroke ? canvas.toDataURL('image/png') : '';
        if (!input.value) {
            event.preventDefault();
            alert('Assine no campo indicado antes de enviar.');
        }
    });
});
</script>
</body>
</html>