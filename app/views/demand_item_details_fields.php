<?php

declare(strict_types=1);

$fieldPrefix = (string) ($demandItemFieldPrefix ?? 'demandItem');
$fieldValues = is_array($demandItemFieldValues ?? null) ? $demandItemFieldValues : [];
$fieldValue = static fn (string $name, string $default = ''): string => (string) ($fieldValues[$name] ?? $default);
?>

<div class="col-12"><hr class="my-1"></div>
<div class="col-12">
    <h3 class="h6 mb-0">Caracterização e validação da necessidade</h3>
    <p class="small text-muted mb-0">Estes dados fundamentam a composição do quantitativo do projeto.</p>
</div>
<div class="col-md-4">
    <label class="form-label" for="<?= e($fieldPrefix) ?>NeedType">Tipo da necessidade</label>
    <select class="form-select" id="<?= e($fieldPrefix) ?>NeedType" name="need_type" required>
        <option value="">Selecione...</option>
        <?php foreach (demand_need_type_options() as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $fieldValue('need_type') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="<?= e($fieldPrefix) ?>Priority">Prioridade</label>
    <select class="form-select" id="<?= e($fieldPrefix) ?>Priority" name="priority" required>
        <?php foreach (demand_priority_options() as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $fieldValue('priority', 'MEDIUM') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="<?= e($fieldPrefix) ?>NeededBy">Necessário até</label>
    <input class="form-control" id="<?= e($fieldPrefix) ?>NeededBy" type="date" name="needed_by_date" value="<?= e($fieldValue('needed_by_date')) ?>">
</div>
<div class="col-12">
    <label class="form-label" for="<?= e($fieldPrefix) ?>Justification">Justificativa da necessidade</label>
    <textarea class="form-control" id="<?= e($fieldPrefix) ?>Justification" name="need_justification" rows="3" required><?= e($fieldValue('need_justification')) ?></textarea>
</div>
<div class="col-md-6">
    <label class="form-label" for="<?= e($fieldPrefix) ?>Use">Uso pretendido</label>
    <input class="form-control" id="<?= e($fieldPrefix) ?>Use" name="intended_use" value="<?= e($fieldValue('intended_use')) ?>">
</div>
<div class="col-md-6">
    <label class="form-label" for="<?= e($fieldPrefix) ?>Destination">Destino / local de utilização</label>
    <input class="form-control" id="<?= e($fieldPrefix) ?>Destination" name="destination" value="<?= e($fieldValue('destination')) ?>">
</div>
<div class="col-md-6">
    <label class="form-label" for="<?= e($fieldPrefix) ?>Assets">Bens ou ativos relacionados</label>
    <input class="form-control" id="<?= e($fieldPrefix) ?>Assets" name="related_assets" value="<?= e($fieldValue('related_assets')) ?>">
</div>
<div class="col-md-6">
    <label class="form-label" for="<?= e($fieldPrefix) ?>Project">Projeto relacionado</label>
    <input class="form-control" id="<?= e($fieldPrefix) ?>Project" name="related_project" value="<?= e($fieldValue('related_project')) ?>">
</div>
<div class="col-12">
    <label class="form-label" for="<?= e($fieldPrefix) ?>Evidence">Referências e evidências</label>
    <textarea class="form-control" id="<?= e($fieldPrefix) ?>Evidence" name="evidence_references" rows="2"><?= e($fieldValue('evidence_references')) ?></textarea>
</div>
<div class="col-md-4">
    <label class="form-label" for="<?= e($fieldPrefix) ?>ValidationStatus">Validação técnica</label>
    <select class="form-select" id="<?= e($fieldPrefix) ?>ValidationStatus" name="validation_status" required>
        <?php foreach (demand_validation_status_options() as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $fieldValue('validation_status', 'PENDING') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-8">
    <label class="form-label" for="<?= e($fieldPrefix) ?>ValidationNotes">Parecer / justificativa do ajuste ou rejeição</label>
    <input class="form-control" id="<?= e($fieldPrefix) ?>ValidationNotes" name="validation_notes" value="<?= e($fieldValue('validation_notes')) ?>">
</div>
