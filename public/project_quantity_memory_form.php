<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$projectId = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$itemId = (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
$project = find_project($projectId);
$item = find_item($itemId);
$memory = find_project_item_quantity_memory($projectId, $itemId);

if (!$project || !$item || !$memory) {
    http_response_code(404);
    exit('Memória de cálculo não encontrada. Inicialize as memórias do projeto antes de editar.');
}

$errors = [];
$projectLocked = project_is_locked($project);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_require_permission('projects.manage');
    try {
        $memory = save_project_item_quantity_memory(
            $projectId,
            $itemId,
            $_POST,
            (string) ($_POST['action'] ?? 'draft')
        );
        $message = ($memory['status'] ?? '') === 'VALIDATED'
            ? 'Memória validada e anexos anteriores invalidados.'
            : 'Memória salva como rascunho.';
        redirect('/project_quantity_memory_form.php?project_id=' . $projectId
            . '&item_id=' . $itemId . '&success=' . rawurlencode($message));
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
        $memory = array_merge($memory, $_POST);
        $memory['calculation_data'] = normalize_quantity_memory_calculation_data($_POST['calculation_data'] ?? []);
        $memory['supporting_references'] = normalize_quantity_memory_supporting_references($_POST['supporting_references'] ?? []);
    }
}

$memory = normalize_project_quantity_memory_row($memory);
$calculationData = $memory['calculation_data'];
$references = $memory['supporting_references'];
$snapshots = get_project_item_quantity_snapshots($projectId, $itemId);
$versions = get_project_item_quantity_memory_versions((int) $memory['id']);
$success = trim((string) ($_GET['success'] ?? ''));
$canEdit = !$projectLocked && auth_can('projects.manage');
$manualFinal = abs((float) ($memory['final_quantity'] ?? 0) - (float) ($memory['calculated_quantity'] ?? 0)) > 0.00001
    ? (string) $memory['final_quantity']
    : '';
$numberValue = static function (string $section, string $field) use ($calculationData): string {
    $value = (float) ($calculationData[$section][$field] ?? 0);
    return $value == 0.0 ? '' : (string) $value;
};
$textValue = static fn (string $section, string $field): string => (string) ($calculationData[$section][$field] ?? '');

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
        <h1 class="h3 mb-1">Memória de cálculo do quantitativo</h1>
        <p class="text-muted mb-0"><?= e($project['name']) ?> · <?= e($item['tracking_code']) ?> · <?= e($item['name']) ?></p>
    </div>
    <a href="/project_quantity_memories.php?id=<?= $projectId ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i>Voltar</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<?php if ($projectLocked): ?><div class="alert alert-warning"><?= e(project_locked_edit_message($project)) ?></div><?php endif; ?>
<?php if (!empty($memory['needs_review'])): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Esta memória precisa ser revisada. Alterações nas demandas invalidam sua validação e os anexos gerados.</div>
<?php endif; ?>

<section class="mb-4" aria-labelledby="compositionTitle">
    <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-2">
        <div>
            <h2 class="h5 mb-1" id="compositionTitle">Composição das demandas</h2>
            <p class="small text-muted mb-0">A quantidade aprovada é a base auditável da memória.</p>
        </div>
        <div class="d-flex gap-4">
            <div><span class="small text-muted d-block">Solicitada</span><strong><?= e(format_decimal_quantity($snapshots['requested_quantity'])) ?></strong></div>
            <div><span class="small text-muted d-block">Aprovada</span><strong><?= e(format_decimal_quantity($snapshots['approved_quantity'])) ?></strong></div>
        </div>
    </div>
    <div class="table-responsive border rounded">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Secretaria / unidade</th><th>Demanda</th><th class="text-end">Solicitada</th><th class="text-end">Aprovada</th><th>Validação</th></tr>
            </thead>
            <tbody>
            <?php foreach ($snapshots['composition'] as $row): ?>
                <tr>
                    <td>
                        <strong><?= e($row['secretariat_name'] ?: 'Sem secretaria') ?></strong>
                        <div class="small text-muted"><?= e($row['requester_unit_name'] ?: '-') ?></div>
                    </td>
                    <td><a href="/demand_show.php?id=<?= (int) $row['demand_id'] ?>"><?= e($row['demand_name']) ?></a></td>
                    <td class="text-end"><?= e(format_decimal_quantity($row['quantity'])) ?></td>
                    <td class="text-end"><?= e(format_decimal_quantity($row['approved_quantity'] ?? $row['quantity'])) ?></td>
                    <td><?= e(demand_validation_status_label($row['validation_status'] ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$snapshots['composition']): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma demanda vinculada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<form method="post" id="quantityMemoryForm">
    <input type="hidden" name="project_id" value="<?= $projectId ?>">
    <input type="hidden" name="item_id" value="<?= $itemId ?>">
    <fieldset <?= $canEdit ? '' : 'disabled' ?>>
        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <section class="border rounded p-3 mb-4">
                    <h2 class="h5 mb-3">Método e período de planejamento</h2>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="calculationMethod">Método de cálculo</label>
                            <select class="form-select" id="calculationMethod" name="calculation_method" required>
                                <?php foreach (quantity_memory_calculation_method_options() as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($memory['calculation_method'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="planningPeriod">Período (meses)</label>
                            <input class="form-control" id="planningPeriod" type="number" min="1" max="120" name="planning_period_months" value="<?= (int) ($memory['planning_period_months'] ?? 12) ?>" required>
                        </div>
                        <div class="col-12" data-methods="HYBRID">
                            <div class="form-check form-switch">
                                <input type="hidden" name="include_approved_demands" value="0">
                                <input class="form-check-input" type="checkbox" id="includeApprovedDemands" name="include_approved_demands" value="1" <?= !empty($memory['include_approved_demands']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="includeApprovedDemands">Usar demandas aprovadas como base do método híbrido</label>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="border rounded p-3 mb-4 method-section" data-methods="HISTORICAL_CONSUMPTION HYBRID">
                    <h2 class="h5 mb-3">Projeção histórica</h2>
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Quantidade</label><input class="form-control q-number" type="number" min="0" step="0.01" name="calculation_data[historical_projection][quantity]" value="<?= e($numberValue('historical_projection', 'quantity')) ?>"></div>
                        <div class="col-md-9"><label class="form-label">Descrição</label><input class="form-control" name="calculation_data[historical_projection][description]" value="<?= e($textValue('historical_projection', 'description')) ?>"></div>
                        <div class="col-12"><label class="form-label">Fonte ou documento</label><input class="form-control" name="calculation_data[historical_projection][source_reference]" value="<?= e($textValue('historical_projection', 'source_reference')) ?>"></div>
                    </div>
                </section>

                <section class="border rounded p-3 mb-4 method-section" data-methods="ASSET_REPLACEMENT HYBRID">
                    <h2 class="h5 mb-3">Substituição e ampliação de bens</h2>
                    <div class="row g-3">
                        <?php foreach (['obsolete' => 'Obsoletos', 'irreparable' => 'Irreparáveis', 'incompatible' => 'Incompatíveis', 'new_positions' => 'Novos postos'] as $field => $label): ?>
                            <div class="col-6 col-md-3"><label class="form-label"><?= e($label) ?></label><input class="form-control q-number" type="number" min="0" step="0.01" name="calculation_data[asset_replacement][<?= e($field) ?>]" value="<?= e($numberValue('asset_replacement', $field)) ?>"></div>
                        <?php endforeach; ?>
                        <div class="col-12"><label class="form-label">Descrição</label><textarea class="form-control" rows="2" name="calculation_data[asset_replacement][description]"><?= e($textValue('asset_replacement', 'description')) ?></textarea></div>
                    </div>
                </section>

                <?php foreach ([
                    'planned_projects' => ['Projetos previstos', 'HYBRID'],
                    'technical_project' => ['Projeto técnico', 'TECHNICAL_PROJECT HYBRID'],
                ] as $section => [$title, $methods]): ?>
                    <section class="border rounded p-3 mb-4 method-section" data-methods="<?= e($methods) ?>">
                        <h2 class="h5 mb-3"><?= e($title) ?></h2>
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Quantidade</label><input class="form-control q-number" type="number" min="0" step="0.01" name="calculation_data[<?= e($section) ?>][quantity]" value="<?= e($numberValue($section, 'quantity')) ?>"></div>
                            <div class="col-md-9"><label class="form-label">Descrição</label><input class="form-control" name="calculation_data[<?= e($section) ?>][description]" value="<?= e($textValue($section, 'description')) ?>"></div>
                            <div class="col-12"><label class="form-label">Fonte ou documento</label><input class="form-control" name="calculation_data[<?= e($section) ?>][source_reference]" value="<?= e($textValue($section, 'source_reference')) ?>"></div>
                        </div>
                    </section>
                <?php endforeach; ?>

                <section class="border rounded p-3 mb-4 method-section" data-methods="INSTALLED_BASE HYBRID">
                    <h2 class="h5 mb-3">Base instalada</h2>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Quantidade instalada</label><input class="form-control q-number" type="number" min="0" step="0.01" name="calculation_data[installed_base][quantity]" value="<?= e($numberValue('installed_base', 'quantity')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Falha anual (%)</label><input class="form-control q-number" type="number" min="0" step="0.01" name="calculation_data[installed_base][annual_failure_rate_percent]" value="<?= e($numberValue('installed_base', 'annual_failure_rate_percent')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Projeção calculada</label><input class="form-control" id="installedProjection" value="<?= e(format_decimal_quantity($calculationData['installed_base']['projected_quantity'] ?? 0)) ?>" readonly></div>
                        <div class="col-12"><label class="form-label">Descrição</label><textarea class="form-control" rows="2" name="calculation_data[installed_base][description]"><?= e($textValue('installed_base', 'description')) ?></textarea></div>
                    </div>
                </section>
                <section class="border rounded p-3 mb-4">
                    <h2 class="h5 mb-3">Acréscimos complementares</h2>
                    <div class="row g-3">
                        <?php foreach (['technical_reserve' => 'Reserva técnica', 'technical_loss' => 'Perda técnica'] as $section => $title): ?>
                            <div class="col-md-6">
                                <div class="border-start border-3 border-info ps-3 h-100">
                                    <h3 class="h6"><?= e($title) ?></h3>
                                    <div class="row g-2">
                                        <div class="col-6"><label class="form-label">Tipo</label>
                                            <select class="form-select q-type" name="calculation_data[<?= e($section) ?>][type]">
                                                <option value="NONE" <?= ($calculationData[$section]['type'] ?? '') === 'NONE' ? 'selected' : '' ?>>Não aplicar</option>
                                                <option value="FIXED" <?= ($calculationData[$section]['type'] ?? '') === 'FIXED' ? 'selected' : '' ?>>Quantidade fixa</option>
                                                <option value="PERCENTAGE" <?= ($calculationData[$section]['type'] ?? '') === 'PERCENTAGE' ? 'selected' : '' ?>>Percentual</option>
                                            </select>
                                        </div>
                                        <div class="col-6"><label class="form-label">Valor</label><input class="form-control q-number" type="number" min="0" step="0.01" name="calculation_data[<?= e($section) ?>][value]" value="<?= e($numberValue($section, 'value')) ?>"></div>
                                        <div class="col-12"><label class="form-label">Justificativa</label><textarea class="form-control" rows="2" name="calculation_data[<?= e($section) ?>][justification]"><?= e($textValue($section, 'justification')) ?></textarea></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-md-3"><label class="form-label">Outros acréscimos</label><input class="form-control q-number" type="number" min="0" step="0.01" name="calculation_data[other_additions][quantity]" value="<?= e($numberValue('other_additions', 'quantity')) ?>"></div>
                        <div class="col-md-9"><label class="form-label">Justificativa</label><input class="form-control" name="calculation_data[other_additions][justification]" value="<?= e($textValue('other_additions', 'justification')) ?>"></div>
                    </div>
                </section>

                <section class="border rounded p-3 mb-4">
                    <h2 class="h5 mb-3">Deduções</h2>
                    <div class="row g-3">
                        <?php foreach ([
                            'stock_available' => 'Estoque disponível',
                            'framework_agreement_balance' => 'Saldo de ata',
                            'contract_balance' => 'Saldo contratual',
                            'reusable_quantity' => 'Bens reaproveitáveis',
                            'purchases_in_progress' => 'Compras em andamento',
                            'other_quantity' => 'Outras deduções',
                        ] as $field => $label): ?>
                            <div class="col-6 col-md-4"><label class="form-label"><?= e($label) ?></label><input class="form-control q-deduction" type="number" min="0" step="0.01" name="calculation_data[deductions][<?= e($field) ?>]" value="<?= e($numberValue('deductions', $field)) ?>"></div>
                        <?php endforeach; ?>
                        <div class="col-12"><label class="form-label">Justificativa das outras deduções</label><input class="form-control" name="calculation_data[deductions][other_justification]" value="<?= e($textValue('deductions', 'other_justification')) ?>"></div>
                    </div>
                </section>
                <section class="border rounded p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                        <div><h2 class="h5 mb-1">Referências de suporte</h2><p class="small text-muted mb-0">Obrigatórias para validar memórias com deduções.</p></div>
                        <?php if ($canEdit): ?><button class="btn btn-sm btn-outline-primary" type="button" id="addReference"><i class="bi bi-plus-lg"></i>Referência</button><?php endif; ?>
                    </div>
                    <div id="referenceRows">
                        <?php foreach ($references as $index => $reference): ?>
                            <div class="row g-2 align-items-end mb-2 reference-row">
                                <div class="col-md-3"><label class="form-label">Tipo</label>
                                    <select class="form-select" data-name="type" name="supporting_references[<?= $index ?>][type]">
                                        <?php foreach (quantity_memory_supporting_reference_type_options() as $value => $label): ?>
                                            <option value="<?= e($value) ?>" <?= $reference['type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4"><label class="form-label">Descrição</label><input class="form-control" data-name="description" name="supporting_references[<?= $index ?>][description]" value="<?= e($reference['description']) ?>"></div>
                                <div class="col-md-4"><label class="form-label">Referência</label><input class="form-control" data-name="reference" name="supporting_references[<?= $index ?>][reference]" value="<?= e($reference['reference']) ?>"></div>
                                <div class="col-md-1"><button class="btn btn-outline-danger w-100 remove-reference" type="button" title="Remover referência"><i class="bi bi-trash"></i></button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <template id="referenceTemplate">
                    <div class="row g-2 align-items-end mb-2 reference-row">
                        <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" data-name="type"><?php foreach (quantity_memory_supporting_reference_type_options() as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Descrição</label><input class="form-control" data-name="description"></div>
                        <div class="col-md-4"><label class="form-label">Referência</label><input class="form-control" data-name="reference"></div>
                        <div class="col-md-1"><button class="btn btn-outline-danger w-100 remove-reference" type="button" title="Remover referência"><i class="bi bi-trash"></i></button></div>
                    </div>
                </template>
            </div>
            <div class="col-xl-4">
                <aside class="border rounded p-3 position-sticky" style="top: 1rem">
                    <h2 class="h5 mb-3">Resultado estimado</h2>
                    <dl class="row small mb-3">
                        <dt class="col-7">Demandas aprovadas</dt><dd class="col-5 text-end" id="previewApproved"><?= e(format_decimal_quantity($snapshots['approved_quantity'])) ?></dd>
                        <dt class="col-7">Acréscimos</dt><dd class="col-5 text-end text-success" id="previewAdditions">+<?= e(format_decimal_quantity($memory['additions_total'] ?? 0)) ?></dd>
                        <dt class="col-7">Deduções</dt><dd class="col-5 text-end text-danger" id="previewDeductions">-<?= e(format_decimal_quantity($memory['deductions_total'] ?? 0)) ?></dd>
                        <dt class="col-7">Quantidade calculada</dt><dd class="col-5 text-end fw-semibold" id="previewCalculated"><?= e(format_decimal_quantity($memory['calculated_quantity'] ?? 0)) ?></dd>
                    </dl>
                    <div class="bg-light border rounded p-3 mb-3">
                        <div class="small text-muted mb-1">Fórmula</div>
                        <div class="fw-semibold" id="previewFormula"><?= e(project_item_quantity_memory_formula($memory)) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="roundingRule">Arredondamento</label>
                        <select class="form-select" id="roundingRule" name="rounding_rule">
                            <?php foreach (quantity_memory_rounding_rule_options() as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($memory['rounding_rule'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="finalQuantity">Quantidade final manual</label>
                        <input class="form-control" id="finalQuantity" type="number" min="0" step="0.01" name="final_quantity" value="<?= e($manualFinal) ?>" placeholder="Usar a quantidade calculada">
                        <div class="form-text">Preencha somente quando houver ajuste administrativo.</div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="manualJustification">Justificativa do ajuste manual</label><textarea class="form-control" id="manualJustification" rows="3" name="manual_adjustment_justification"><?= e((string) ($memory['manual_adjustment_justification'] ?? '')) ?></textarea></div>
                    <div class="mb-3"><label class="form-label" for="versionNotes">Registro da alteração</label><input class="form-control" id="versionNotes" name="version_notes" placeholder="Resumo opcional desta versão"></div>
                    <div class="small text-muted mb-3">Hash da fonte: <code class="text-break"><?= e((string) ($memory['source_hash'] ?? '-')) ?></code></div>
                    <?php if ($canEdit): ?>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" name="action" value="validate"><i class="bi bi-check2-circle"></i>Validar memória</button>
                            <button class="btn btn-outline-secondary" name="action" value="draft"><i class="bi bi-save"></i>Salvar rascunho</button>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </fieldset>
</form>

<?php if ($versions): ?>
    <section class="mt-5" aria-labelledby="versionsTitle">
        <h2 class="h5 mb-3" id="versionsTitle">Histórico de versões</h2>
        <div class="table-responsive border rounded">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Versão</th><th>Data</th><th>Responsável</th><th>Alteração</th><th class="text-end">Quantidade anterior</th></tr></thead>
                <tbody>
                <?php foreach ($versions as $version): ?>
                    <tr>
                        <td>v<?= (int) $version['version_number'] ?></td>
                        <td><?= e(format_datetime($version['created_at'])) ?></td>
                        <td><?= e($version['created_by_user_name'] ?: '-') ?></td>
                        <td><?= e($version['notes'] ?: '-') ?></td>
                        <td class="text-end"><?= e(format_decimal_quantity($version['snapshot']['final_quantity'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('quantityMemoryForm');
    if (!form) return;
    var approved = <?= json_encode((float) $snapshots['approved_quantity']) ?>;
    var number = function (name) {
        var input = form.querySelector('[name="' + name + '"]');
        return input ? Math.max(0, parseFloat(String(input.value).replace(',', '.')) || 0) : 0;
    };
    var format = function (value) {
        return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(value);
    };
    var updateVisibility = function () {
        var method = document.getElementById('calculationMethod').value;
        form.querySelectorAll('[data-methods]').forEach(function (element) {
            element.hidden = element.dataset.methods.split(' ').indexOf(method) === -1;
        });
    };
    var updatePreview = function () {
        var method = document.getElementById('calculationMethod').value;
        var includeDemand = method === 'DEMAND_CONSOLIDATION'
            || (method === 'HYBRID' && document.getElementById('includeApprovedDemands').checked);
        var base = includeDemand ? approved : 0;
        var components = [];
        var add = function (label, value) {
            if (value > 0) components.push({ label: label, value: value });
        };

        if (method === 'HISTORICAL_CONSUMPTION' || method === 'HYBRID') {
            add('Projeção histórica', number('calculation_data[historical_projection][quantity]'));
        }
        if (method === 'ASSET_REPLACEMENT' || method === 'HYBRID') {
            add('Bens obsoletos', number('calculation_data[asset_replacement][obsolete]'));
            add('Bens irreparáveis', number('calculation_data[asset_replacement][irreparable]'));
            add('Bens incompatíveis', number('calculation_data[asset_replacement][incompatible]'));
            add('Novos postos', number('calculation_data[asset_replacement][new_positions]'));
        }
        if (method === 'HYBRID') {
            add('Projetos previstos', number('calculation_data[planned_projects][quantity]'));
        }
        var technical = number('calculation_data[technical_project][quantity]');
        if (method === 'TECHNICAL_PROJECT' || method === 'HYBRID') {
            add('Projeto técnico', technical);
        }
        var installed = number('calculation_data[installed_base][quantity]')
            * number('calculation_data[installed_base][annual_failure_rate_percent]')
            * number('planning_period_months') / 1200;
        document.getElementById('installedProjection').value = format(installed);
        if (method === 'INSTALLED_BASE' || method === 'HYBRID') {
            add('Base instalada', installed);
        }
        add('Outros acréscimos', number('calculation_data[other_additions][quantity]'));
        var subtotal = base + components.reduce(function (sum, component) {
            return sum + component.value;
        }, 0);
        ['technical_reserve', 'technical_loss'].forEach(function (section) {
            var type = form.querySelector('[name="calculation_data[' + section + '][type]"]').value;
            var raw = number('calculation_data[' + section + '][value]');
            var calculationBase = section === 'technical_loss' && technical > 0 ? technical : subtotal;
            var value = type === 'FIXED' ? raw : (type === 'PERCENTAGE' ? calculationBase * raw / 100 : 0);
            add(section === 'technical_reserve' ? 'Reserva técnica' : 'Perda técnica', value);
        });
        var additions = components.reduce(function (sum, component) {
            return sum + component.value;
        }, 0);
        var deductionNames = [
            'stock_available',
            'framework_agreement_balance',
            'contract_balance',
            'reusable_quantity',
            'purchases_in_progress',
            'other_quantity'
        ];
        var deductions = deductionNames.reduce(function (sum, field) {
            return sum + number('calculation_data[deductions][' + field + ']');
        }, 0);
        var calculated = Math.max(0, base + additions - deductions);
        var rounding = document.getElementById('roundingRule').value;
        if (rounding === 'CEIL') calculated = Math.ceil(calculated);
        if (rounding === 'FLOOR') calculated = Math.floor(calculated);
        if (rounding === 'NEAREST') calculated = Math.round(calculated);

        document.getElementById('previewApproved').textContent = format(base);
        document.getElementById('previewAdditions').textContent = '+' + format(additions);
        document.getElementById('previewDeductions').textContent = '-' + format(deductions);
        document.getElementById('previewCalculated').textContent = format(calculated);

        var terms = [];
        if (base > 0) terms.push(format(base));
        components.forEach(function (component) {
            if (component.value > 0) terms.push('+ ' + format(component.value));
        });
        deductionNames.forEach(function (field) {
            var value = number('calculation_data[deductions][' + field + ']');
            if (value > 0) terms.push('- ' + format(value));
        });
        document.getElementById('previewFormula').textContent =
            (terms.length ? terms.join(' ') : '0') + ' = ' + format(calculated);
    };
    form.addEventListener('input', updatePreview);
    form.addEventListener('change', function () {
        updateVisibility();
        updatePreview();
    });
    updateVisibility();
    updatePreview();
    var rows = document.getElementById('referenceRows');
    var reindex = function () {
        rows.querySelectorAll('.reference-row').forEach(function (row, index) {
            row.querySelectorAll('[data-name]').forEach(function (input) {
                input.name = 'supporting_references[' + index + '][' + input.dataset.name + ']';
            });
        });
    };
    var addButton = document.getElementById('addReference');
    if (addButton) {
        addButton.addEventListener('click', function () {
            rows.appendChild(document.getElementById('referenceTemplate').content.cloneNode(true));
            reindex();
        });
    }
    rows.addEventListener('click', function (event) {
        var button = event.target.closest('.remove-reference');
        if (button) {
            button.closest('.reference-row').remove();
            reindex();
        }
    });
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
