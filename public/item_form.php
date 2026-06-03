<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$item = $id ? find_item($id) : null;
$justificationTemplates = get_justification_templates();
$impactTemplates = get_environmental_impact_templates();

if ($id && !$item) {
    http_response_code(404);
    exit('Item não encontrado.');
}

$errors = [];
$parentCategories = get_parent_categories();
$subcategories = get_subcategories();
$unitTypes = get_unit_types();
$similarItems = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'category_id' => (int) ($_POST['category_id'] ?? 0),
        'subcategory_id' => (int) ($_POST['subcategory_id'] ?? 0),
        'unit_type_id' => (int) ($_POST['unit_type_id'] ?? 0),
        'level' => trim($_POST['level'] ?? ''),
        'status' => trim($_POST['status'] ?? 'draft'),
        'name' => trim($_POST['name'] ?? ''),
        'specification' => trim($_POST['specification'] ?? '{}'),
        'justification' => trim($_POST['justification'] ?? ''),
        'warranty' => trim($_POST['warranty'] ?? ''),
        'environmental_impacts' => trim($_POST['environmental_impacts'] ?? ''),
    ];

    try {
        $uploadedImages = upload_item_images($_FILES['images'] ?? []);
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
        $uploadedImages = [];
    }

    if (!$data['name']) {
        $errors[] = 'O nome é obrigatório.';
    }

    if ($data['name']) {
        $similarItems = find_similar_items(
            $data['name'],
            isset($item['id']) ? (int) $item['id'] : null,
            0.45
        );
    }

    if (!in_array($data['level'], ['A', 'B', 'C'], true)) {
        $errors[] = 'O nível deve ser A, B ou C.';
    }

    if (!$data['justification']) {
        $errors[] = 'A justificativa é obrigatória.';
    }

    if (!validate_json($data['specification'])) {
        $errors[] = 'A especificação precisa estar em JSON válido.';
    }

    if (!$errors) {
        if ($item) {
            create_item_version(
                (int) $item['id'],
                'Snapshot automático antes da edição'
            );

            update_item((int) $item['id'], $data);

            if ($uploadedImages) {
                add_item_images((int) $item['id'], $uploadedImages);
            }

            redirect('/item_show.php?id=' . (int) $item['id']);
        }

        $newId = create_item($data);

        if ($uploadedImages) {
            add_item_images($newId, $uploadedImages);
        }

        redirect('/item_show.php?id=' . $newId);
    }

    $item = array_merge($item ?? [], $data);
}

require __DIR__ . '/../app/views/header.php';

$specification = old($item ?? [], 'specification', default_item_specification_json());
$environmentalImpactItems = environmental_impacts_to_array((string) old($item ?? [], 'environmental_impacts', ''));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $item ? 'Editar item' : 'Novo item' ?></h1>
        <p class="text-muted mb-0">Preencha os dados técnicos e administrativos do item.</p>
        <p class="small text-muted mb-0">A sugestão por IA é apenas apoio inicial e deve ser revisada antes do uso em licitação.</p>
    </div>

    <a href="/" class="btn btn-outline-secondary">Voltar</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <strong>Corrija os erros:</strong>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Codigo</label>
            <input
                type="text"
                class="form-control"
                value="<?= e($item['tracking_code'] ?? 'Gerado ao salvar') ?>"
                disabled>
        </div>

        <div class="col-md-6">
            <label class="form-label">Categoria</label>
            <select name="category_id" id="category_id" class="form-select">
                <option value="">Selecione...</option>
                <?php foreach ($parentCategories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) old($item ?? [], 'category_id') === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Subcategoria</label>
            <select name="subcategory_id" id="subcategory_id" class="form-select" data-current="<?= (int) old($item ?? [], 'subcategory_id') ?>">
                <option value="">Selecione...</option>
                <?php foreach ($subcategories as $subcategory): ?>
                    <option value="<?= (int) $subcategory['id'] ?>" data-parent="<?= (int) $subcategory['parent_id'] ?>" <?= (int) old($item ?? [], 'subcategory_id') === (int) $subcategory['id'] ? 'selected' : '' ?>>
                        <?= e($subcategory['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Tipo de unidade</label>

            <select name="unit_type_id" class="form-select" required>
                <option value="">Selecione...</option>

                <?php foreach ($unitTypes as $unitType): ?>
                    <option
                        value="<?= (int) $unitType['id'] ?>"
                        <?= (int) old($item ?? [], 'unit_type_id') === (int) $unitType['id'] ? 'selected' : '' ?>>

                        <?= e($unitType['name']) ?>
                        <?= $unitType['abbreviation'] ? ' (' . e($unitType['abbreviation']) . ')' : '' ?>

                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Nível</label>
            <select name="level" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach (['A', 'B', 'C'] as $level): ?>
                    <option value="<?= $level ?>" <?= old($item ?? [], 'level') === $level ? 'selected' : '' ?>>
                        <?= $level ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Ex.: A = crítico/essencial, B = importante, C = comum.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label">Status</label>

            <select name="status" class="form-select" required>
                <?php
                $statuses = [
                    'draft' => 'Rascunho',
                    'review' => 'Em revisão',
                    'standardized' => 'Padronizado',
                    'deprecated' => 'Descontinuado',
                    'blocked' => 'Bloqueado',
                ];
                ?>

                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= old($item ?? [], 'status', 'draft') === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-9">
            <label class="form-label">Nome</label>
            <div class="input-group">
                <input type="text" name="name" id="item_name" class="form-control" required value="<?= e(old($item ?? [], 'name')) ?>">
                <!-- <button type="button" id="btnAiSuggest" class="btn btn-outline-primary">
                    Gerar com IA
                </button> -->
            </div>
            <!-- <div class="form-text">Digite o nome do produto e use a IA para gerar uma primeira sugestão revisável.</div> -->

            <div id="similarItemsAlert" class="alert alert-warning mt-3 d-none">
                <strong>Atenção:</strong>
                foram encontrados itens parecidos no catálogo.

                <ul id="similarItemsList" class="mb-0 mt-2"></ul>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label">Imagens ilustrativas</label>

            <input
                type="file"
                name="images[]"
                class="form-control"
                accept="image/jpeg,image/png,image/webp"
                multiple>

            <div class="form-text">
                As imagens serão utilizadas apenas como referência visual ilustrativa,
                sem vínculo com marca, modelo, fornecedor ou fabricante específico.
            </div>
        </div>

        <div class="col-12">
            <div id="aiSuggestionAlert" class="alert d-none mb-0" role="alert"></div>
        </div>

        <div class="col-12">
            <label class="form-label">Especificação técnica em JSON</label>
            <textarea name="specification" id="specification" rows="10" class="form-control font-monospace" required><?= e(pretty_json($specification)) ?></textarea>
            <div id="jsonFeedback" class="form-text">Informe um JSON válido com as características técnicas mínimas.</div>
        </div>

        <div class="col-12">
            <label class="form-label">Justificativa</label>
            <textarea name="justification" rows="4" class="form-control" required><?= e(old($item ?? [], 'justification')) ?></textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Usar justificativa da biblioteca</label>

            <select id="justificationTemplateSelect" class="form-select">
                <option value="">Selecione para preencher...</option>

                <?php foreach ($justificationTemplates as $template): ?>
                    <option value="<?= e($template['content']) ?>">
                        <?= e($template['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Garantia</label>
            <textarea name="warranty" rows="4" class="form-control" placeholder="Ex.: garantia mínima de 12 meses, padrão de mercado."><?= e(old($item ?? [], 'warranty', 'Garantia mínima de 12 meses, conforme padrão de mercado, contra defeitos de fabricação.')) ?></textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label">Possiveis impactos ambientais</label>

            <input
                type="hidden"
                name="environmental_impacts"
                id="environmentalImpactsInput"
                value="<?= e(json_encode($environmentalImpactItems, JSON_UNESCAPED_UNICODE)) ?>">

            <div class="input-group mb-2">
                <select id="impactTemplateSelect" class="form-select">
                    <option value="">Selecionar impacto da biblioteca...</option>

                    <?php foreach ($impactTemplates as $template): ?>
                        <option value="<?= e($template['content']) ?>">
                            <?= e(trim(($template['code'] ?? '') . ' - ' . $template['title'], ' -')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" id="btnAddImpactTemplate" class="btn btn-outline-primary">
                    <i class="bi bi-plus-lg"></i>Adicionar
                </button>
            </div>

            <div class="input-group mb-2">
                <input
                    type="text"
                    id="customImpactInput"
                    class="form-control"
                    placeholder="Adicionar impacto manualmente">

                <button type="button" id="btnAddCustomImpact" class="btn btn-outline-secondary">
                    <i class="bi bi-plus-lg"></i>Adicionar
                </button>
            </div>

            <ul id="environmentalImpactList" class="list-group"></ul>
            <div class="form-text">Os impactos serao salvos como lista estruturada.</div>
        </div>

        <?php if (!empty($similarItems)): ?>
            <div class="col-12">
                <div class="alert alert-warning">
                    <strong>Possíveis itens semelhantes já cadastrados:</strong>

                    <ul class="mb-0 mt-2">
                        <?php foreach ($similarItems as $similar): ?>
                            <li>
                                <a href="/item_show.php?id=<?= (int) $similar['id'] ?>" target="_blank">
                                    <?= e($similar['tracking_code'] . ' - ' . $similar['name']) ?>
                                </a>

                                <span class="text-muted">
                                    <?= number_format((float) $similar['similarity_score'] * 100, 0) ?>% parecido
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="small mt-2">
                        A existência de itens parecidos não impede o cadastro, mas recomenda-se revisar para evitar duplicidade.
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Salvar</button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const justificationSelect = document.getElementById('justificationTemplateSelect');
        const impactSelect = document.getElementById('impactTemplateSelect');
        const impactInput = document.getElementById('environmentalImpactsInput');
        const impactList = document.getElementById('environmentalImpactList');
        const customImpactInput = document.getElementById('customImpactInput');
        const btnAddImpactTemplate = document.getElementById('btnAddImpactTemplate');
        const btnAddCustomImpact = document.getElementById('btnAddCustomImpact');

        if (justificationSelect) {
            justificationSelect.addEventListener('change', function() {
                const textarea = document.querySelector('[name="justification"]');

                if (textarea && this.value) {
                    textarea.value = this.value;
                }
            });
        }

        function getImpacts() {
            if (!impactInput || !impactInput.value) {
                return [];
            }

            try {
                const parsed = JSON.parse(impactInput.value);
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function setImpacts(items) {
            if (!impactInput || !impactList) {
                return;
            }

            const normalized = items
                .map(item => String(item).trim())
                .filter(item => item.length > 0);

            impactInput.value = JSON.stringify([...new Set(normalized)]);
            renderImpacts();
        }

        function addImpact(value) {
            const text = String(value || '').trim();

            if (!text) {
                return;
            }

            setImpacts([...getImpacts(), text]);
        }

        function renderImpacts() {
            if (!impactList) {
                return;
            }

            impactList.innerHTML = '';

            const impacts = getImpacts();

            if (impacts.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'list-group-item text-muted';
                empty.textContent = 'Nenhum impacto adicionado.';
                impactList.appendChild(empty);
                return;
            }

            impacts.forEach((impact, index) => {
                const item = document.createElement('li');
                item.className = 'list-group-item d-flex justify-content-between align-items-start gap-3';

                const text = document.createElement('span');
                text.textContent = impact;

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-outline-danger';
                button.innerHTML = '<i class="bi bi-trash"></i>Remover';
                button.addEventListener('click', () => {
                    const updated = getImpacts();
                    updated.splice(index, 1);
                    setImpacts(updated);
                });

                item.appendChild(text);
                item.appendChild(button);
                impactList.appendChild(item);
            });
        }

        if (btnAddImpactTemplate && impactSelect) {
            btnAddImpactTemplate.addEventListener('click', function() {
                addImpact(impactSelect.value);
                impactSelect.value = '';
            });
        }

        if (btnAddCustomImpact && customImpactInput) {
            btnAddCustomImpact.addEventListener('click', function() {
                addImpact(customImpactInput.value);
                customImpactInput.value = '';
                customImpactInput.focus();
            });
        }

        if (customImpactInput) {
            customImpactInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addImpact(customImpactInput.value);
                    customImpactInput.value = '';
                }
            });
        }

        if (impactInput) {
            impactInput.addEventListener('change', renderImpacts);
        }

        renderImpacts();
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const nameInput = document.getElementById('item_name');
        const alertBox = document.getElementById('similarItemsAlert');
        const list = document.getElementById('similarItemsList');

        if (!nameInput || !alertBox || !list) {
            return;
        }

        let timeout = null;

        nameInput.addEventListener('input', function() {
            clearTimeout(timeout);

            const name = this.value.trim();

            if (name.length < 3) {
                alertBox.classList.add('d-none');
                list.innerHTML = '';
                return;
            }

            timeout = setTimeout(async function() {
                const params = new URLSearchParams({
                    name: name,
                    ignore_id: '<?= (int) ($item['id'] ?? 0) ?>'
                });

                const response = await fetch('/item_similar_check.php?' + params.toString());
                const result = await response.json();

                list.innerHTML = '';

                if (!result.success || !result.items || result.items.length === 0) {
                    alertBox.classList.add('d-none');
                    return;
                }

                result.items.forEach(function(item) {
                    const li = document.createElement('li');

                    const score = Math.round(parseFloat(item.similarity_score) * 100);

                    li.innerHTML =
                        '<a href="/item_show.php?id=' + item.id + '" target="_blank">' +
                        item.tracking_code + ' - ' + item.name +
                        '</a> ' +
                        '<span class="text-muted">(' + score + '% parecido)</span>';

                    list.appendChild(li);
                });

                alertBox.classList.remove('d-none');
            }, 500);
        });
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
