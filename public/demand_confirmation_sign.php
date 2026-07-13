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
    exit('Link de confirmação inválido ou inexistente.');
}

$items = get_demand_items((int) $request['demand_list_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $request = sign_demand_confirmation_request($token, $_POST, $_FILES['proof_files'] ?? ($_FILES['document_photo'] ?? []));
        $signed = true;
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage() ?: 'Não foi possível registrar a assinatura.';
        $request = find_demand_confirmation_request_by_token($token) ?? $request;
    }
}

$status = $request['effective_status'] ?? demand_confirmation_effective_status($request);
$municipalLogo = render_municipal_logo('public-sign-logo');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Confirmação de demanda</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; }
        .signature-pad { width: 100%; height: 220px; border: 1px solid #adb5bd; border-radius: .5rem; background: #fff; touch-action: none; }
        .mobile-shell { max-width: 920px; }
        .public-sign-logo { width: 54px; height: 64px; object-fit: contain; }
        code { word-break: break-all; }
    </style>
</head>
<body>
<main class="container mobile-shell py-3 py-md-4">
    <section class="border rounded bg-white shadow-sm p-3 p-md-4 mb-3">
        <div class="d-flex align-items-center gap-3">
            <?php if ($municipalLogo !== ''): ?><?= $municipalLogo ?><?php else: ?><i class="bi bi-building fs-2 text-primary"></i><?php endif; ?>
            <div class="flex-grow-1">
                <div class="small text-uppercase text-muted">Confirmação formal</div>
                <h1 class="h4 mb-0"><?= e($request['flow_title'] ?? 'Confirmação da demanda') ?></h1>
                <div class="text-muted small"><?= e($request['project_name'] ?? '-') ?></div>
            </div>
            <span class="badge <?= e(demand_confirmation_status_badge_class($status)) ?>"><?= e(demand_confirmation_status_label($status)) ?></span>
        </div>
        <div class="row g-2 small mt-3 pt-3 border-top">
            <div class="col-md-6"><span class="text-muted">Demanda:</span> <strong><?= e($request['demand_name'] ?? '-') ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Secretaria:</span> <strong><?= e($request['secretariat_name'] ?? '-') ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Unidade:</span> <strong><?= e($request['requester_department'] ?? '-') ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Assinante:</span> <strong><?= e($request['requester_name'] ?? '-') ?></strong></div>
            <?php if ((int) ($request['flow_signer_count'] ?? 1) > 1): ?>
                <div class="col-12">
                    <span class="text-muted">Fluxo:</span>
                    <strong><?= e(demand_signature_flow_mode_label($request['flow_mode'] ?? null)) ?></strong>
                    · etapa <?= (int) ($request['signer_order'] ?? 1) ?> de <?= (int) ($request['flow_signer_count'] ?? 1) ?>
                    · <?= (int) ($request['flow_signed_count'] ?? 0) ?> concluída(s)
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($signed): ?>
        <div class="alert alert-success">
            <div class="fw-semibold"><i class="bi bi-check-circle me-1"></i>Assinatura registrada com sucesso.</div>
            <div class="small mt-1">Hash individual: <code><?= e($request['content_hash'] ?? '') ?></code></div>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($status === 'waiting'): ?>
        <div class="alert alert-info">
            <div class="fw-semibold"><i class="bi bi-hourglass-split me-1"></i>Esta etapa ainda não está liberada.</div>
            A assinatura anterior do fluxo sequencial precisa ser concluída. Este mesmo link será habilitado automaticamente depois disso.
        </div>
    <?php elseif ($status !== 'pending'): ?>
        <div class="alert alert-warning">Esta solicitação está <?= e(mb_strtolower(demand_confirmation_status_label($status))) ?> e não aceita nova assinatura.</div>
    <?php else: ?>
        <section class="border rounded bg-white shadow-sm mb-3">
            <div class="px-3 py-2 border-bottom fw-semibold">Itens confirmados</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Código</th><th>Item</th><th>Unidade</th><th class="text-end">Qtd.</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><span class="badge text-bg-dark"><?= e($item['tracking_code'] ?? '') ?></span></td>
                            <td><?= e($item['item_name'] ?? '') ?></td>
                            <td>
                                <?= e(($item['unit_type_abbreviation'] ?? '') ?: ($item['unit_type_name'] ?? '-')) ?>
                                <?php if (format_package_content($item) !== '-'): ?><div class="small text-muted"><?= e(format_package_content($item)) ?></div><?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold"><?= e((string) ($item['approved_quantity'] ?? $item['quantity'] ?? '0')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <form method="post" enctype="multipart/form-data" class="border rounded bg-white shadow-sm">
            <input type="hidden" name="public_action" value="demand_confirmation_sign">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="signature_data" id="signatureData">
            <div class="p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Nome completo</label><input type="text" name="requester_name" class="form-control" required value="<?= e($request['requester_name'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">CPF</label><input type="text" name="requester_document" class="form-control" inputmode="numeric" maxlength="14" value="<?= e(format_brazil_document($request['requester_document'] ?? '')) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Cargo/Função</label><input type="text" name="requester_role" class="form-control" value="<?= e($request['requester_role'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="requester_email" class="form-control" value="<?= e($request['requester_email'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Telefone</label><input type="text" name="requester_phone" class="form-control" maxlength="15" value="<?= e(format_brazil_phone($request['requester_phone'] ?? '')) ?>"></div>
                    <div class="col-12">
                        <label class="form-label">Comprovantes de identificação</label>
                        <input type="file" name="proof_files[]" class="form-control" accept="image/*,application/pdf" multiple required>
                        <div class="form-text">Envie uma ou mais fotos/PDFs. Os arquivos ficam no storage privado e integram o hash desta assinatura.</div>
                    </div>
                    <div class="col-12"><div class="border rounded p-3 bg-light"><?= nl2br(e($request['statement_text'] ?? demand_confirmation_default_statement())) ?></div></div>
                    <div class="col-12">
                        <label class="form-label">Assinatura</label>
                        <canvas id="signatureCanvas" class="signature-pad"></canvas>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <button type="button" class="btn btn-outline-secondary" id="clearSignature"><i class="bi bi-eraser"></i>Limpar</button>
                            <span class="small text-muted">Assine com o dedo ou caneta touch.</span>
                        </div>
                    </div>
                    <div class="col-12"><div class="form-check"><input type="checkbox" name="accepted_statement" value="1" class="form-check-input" id="acceptedStatement" required><label class="form-check-label" for="acceptedStatement">Confirmo que li e concordo com a declaração acima.</label></div></div>
                </div>
            </div>
            <div class="p-3 border-top d-grid"><button class="btn btn-primary btn-lg"><i class="bi bi-pen"></i>Assinar demanda</button></div>
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
    function start(event) { event.preventDefault(); drawing = true; const p = point(event); context.beginPath(); context.moveTo(p.x, p.y); }
    function move(event) { if (!drawing) return; event.preventDefault(); const p = point(event); context.lineTo(p.x, p.y); context.stroke(); hasStroke = true; }
    function stop() { drawing = false; input.value = hasStroke ? canvas.toDataURL('image/png') : ''; }

    resizeCanvas();
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', stop);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', stop);
    clear?.addEventListener('click', function() { context.clearRect(0, 0, canvas.width, canvas.height); hasStroke = false; input.value = ''; });
    canvas.closest('form')?.addEventListener('submit', function(event) {
        input.value = hasStroke ? canvas.toDataURL('image/png') : '';
        if (!input.value) { event.preventDefault(); alert('Assine no campo indicado antes de enviar.'); }
    });
});
</script>
</body>
</html>