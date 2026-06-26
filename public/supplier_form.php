<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$supplier = $id ? find_supplier($id) : null;

if ($id && !$supplier) {
    http_response_code(404);
    exit('Fornecedor não encontrado.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'trade_name' => trim($_POST['trade_name'] ?? ''),
        'document' => trim($_POST['document'] ?? ''),
        'contact_name' => trim($_POST['contact_name'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'state' => strtoupper(trim($_POST['state'] ?? '')),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'state_registration' => trim($_POST['state_registration'] ?? ''),
        'municipal_registration' => trim($_POST['municipal_registration'] ?? ''),
        'company_size' => trim($_POST['company_size'] ?? ''),
        'main_cnae' => is_array($_POST['main_cnae'] ?? null) ? $_POST['main_cnae'] : [],
        'secondary_cnaes' => is_array($_POST['secondary_cnaes'] ?? null) ? $_POST['secondary_cnaes'] : [],
        'participates_bidding' => $_POST['participates_bidding'] ?? '1',
        'website_url' => trim($_POST['website_url'] ?? ''),
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'bank_agency' => trim($_POST['bank_agency'] ?? ''),
        'bank_account' => trim($_POST['bank_account'] ?? ''),
        'owner_cpf' => trim($_POST['owner_cpf'] ?? ''),
        'owner_name' => trim($_POST['owner_name'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'is_active' => isset($_POST['is_active']),
    ];

    if (!$data['name']) {
        $errors[] = 'O nome do fornecedor é obrigatório.';
    }

    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
    }

    if ($data['document'] && !in_array(strlen(only_digits($data['document'])), [11, 14], true)) {
        $errors[] = 'Informe um CPF ou CNPJ valido.';
    }

    if ($data['phone'] && !in_array(strlen(only_digits($data['phone'])), [10, 11], true)) {
        $errors[] = 'Informe um telefone valido com DDD.';
    }

    if ($data['state'] && !preg_match('/^[A-Z]{2}$/', $data['state'])) {
        $errors[] = 'Informe a UF com 2 letras.';
    }

    if ($data['postal_code'] && strlen(only_digits($data['postal_code'])) !== 8) {
        $errors[] = 'Informe um CEP valido com 8 digitos.';
    }

    if ($data['website_url']) {
        $websiteForValidation = preg_match('~^https?://~i', $data['website_url'])
            ? $data['website_url']
            : 'https://' . $data['website_url'];

        if (!filter_var($websiteForValidation, FILTER_VALIDATE_URL)) {
            $errors[] = 'Informe uma URL de site valida.';
        }
    }

    if ($data['owner_cpf'] && strlen(only_digits($data['owner_cpf'])) !== 11) {
        $errors[] = 'Informe um CPF do proprietário válido com 11 dígitos.';
    }

    if (!$errors) {
        try {
            if ($supplier) {
                update_supplier((int) $supplier['id'], $data);
            } else {
                create_supplier($data);
            }

            redirect('/suppliers.php');
        } catch (Throwable) {
            $errors[] = 'Não foi possível salvar. Verifique se o fornecedor ou documento já está cadastrado.';
        }
    }

    $supplier = array_merge($supplier ?? [], $data);
}

$mainCnae = normalize_supplier_cnae($supplier['main_cnae'] ?? []) ?? [
    'code' => '',
    'name' => '',
    'description' => '',
];
$secondaryCnaes = normalize_supplier_cnae_list($supplier['secondary_cnaes'] ?? []);

if (!$secondaryCnaes) {
    $secondaryCnaes = [[
        'code' => '',
        'name' => '',
        'description' => '',
    ]];
}

$participatesBiddingValue = boolish($supplier['participates_bidding'] ?? true, true) ? '1' : '0';
require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $supplier ? 'Editar fornecedor' : 'Novo fornecedor' ?></h1>
        <p class="text-muted mb-0">
            Mantenha os dados de contato utilizados nas cotações.
        </p>
    </div>

    <a href="/suppliers.php" class="btn btn-outline-secondary">
        Voltar
    </a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="supplier-form">
    <div class="card mb-3">
        <div class="card-header fw-semibold">
            <i class="bi bi-building me-1"></i> Identificacao fiscal
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-7">
                    <label class="form-label">Nome/Razao social</label>
                    <input type="text" name="name" class="form-control" required value="<?= e($supplier['name'] ?? '') ?>">
                </div>

                <div class="col-lg-5">
                    <label class="form-label">Nome fantasia</label>
                    <input type="text" name="trade_name" class="form-control" value="<?= e($supplier['trade_name'] ?? '') ?>">
                </div>

                <div class="col-lg-5">
                    <label class="form-label">CNPJ/CPF</label>
                    <div class="input-group">
                        <input
                            type="text"
                            name="document"
                            id="supplierDocument"
                            class="form-control"
                            inputmode="numeric"
                            maxlength="18"
                            placeholder="00.000.000/0000-00"
                            data-mask="cpf-cnpj"
                            value="<?= e(format_brazil_document($supplier['document'] ?? '')) ?>">
                        <button type="button" class="btn btn-outline-secondary" id="lookupCnpjButton">
                            <i class="bi bi-search"></i> Consultar CNPJ
                        </button>
                    </div>
                    <div class="form-text" id="cnpjLookupFeedback">Consulta disponivel para CNPJ.</div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <label class="form-label">Porte da empresa</label>
                    <input type="text" name="company_size" class="form-control" value="<?= e($supplier['company_size'] ?? '') ?>">
                </div>

                <div class="col-md-6 col-lg-4">
                    <label class="form-label">Participa de licitacao?</label>
                    <select name="participates_bidding" class="form-select">
                        <option value="1" <?= $participatesBiddingValue === '1' ? 'selected' : '' ?>>Sim</option>
                        <option value="0" <?= $participatesBiddingValue === '0' ? 'selected' : '' ?>>Nao</option>
                    </select>
                </div>

                <div class="col-md-6 col-lg-3">
                    <label class="form-label">Inscricao Estadual</label>
                    <input type="text" name="state_registration" class="form-control" value="<?= e($supplier['state_registration'] ?? '') ?>">
                </div>

                <div class="col-md-6 col-lg-3">
                    <label class="form-label">Inscricao Municipal</label>
                    <input type="text" name="municipal_registration" class="form-control" value="<?= e($supplier['municipal_registration'] ?? '') ?>">
                </div>

                <div class="col-lg-6">
                    <label class="form-label">URL do site</label>
                    <input type="text" name="website_url" class="form-control" placeholder="https://fornecedor.com.br" value="<?= e($supplier['website_url'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">
            <i class="bi bi-person-lines-fill me-1"></i> Contato
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Contato</label>
                    <input type="text" name="contact_name" class="form-control" value="<?= e($supplier['contact_name'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" value="<?= e($supplier['email'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Telefone</label>
                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        inputmode="numeric"
                        maxlength="15"
                        placeholder="(00) 00000-0000"
                        data-mask="phone"
                        value="<?= e(format_brazil_phone($supplier['phone'] ?? '')) ?>">
                </div>

                <div class="col-md-5">
                    <label class="form-label">CPF do proprietario</label>
                    <input
                        type="text"
                        name="owner_cpf"
                        class="form-control"
                        inputmode="numeric"
                        maxlength="14"
                        placeholder="000.000.000-00"
                        data-mask="cpf"
                        value="<?= e(format_brazil_document($supplier['owner_cpf'] ?? '')) ?>">
                </div>

                <div class="col-md-7">
                    <label class="form-label">Nome do proprietario</label>
                    <input type="text" name="owner_name" class="form-control" value="<?= e($supplier['owner_name'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">
            <i class="bi bi-geo-alt me-1"></i> Endereco
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label">Endereco</label>
                    <textarea name="address" rows="2" class="form-control text-uppercase" data-uppercase><?= e($supplier['address'] ?? '') ?></textarea>
                    <div class="form-text">Rua, numero, complemento e bairro.</div>
                </div>

                <div class="col-md-5 col-lg-3">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="city" class="form-control text-uppercase" data-uppercase value="<?= e($supplier['city'] ?? '') ?>">
                </div>

                <div class="col-md-3 col-lg-1">
                    <label class="form-label">UF</label>
                    <input type="text" name="state" class="form-control text-uppercase" maxlength="2" data-mask="uf" value="<?= e($supplier['state'] ?? '') ?>">
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label">CEP</label>
                    <div class="input-group">
                        <input
                            type="text"
                            name="postal_code"
                            id="postalCodeInput"
                            class="form-control"
                            inputmode="numeric"
                            maxlength="9"
                            placeholder="00000-000"
                            data-mask="cep"
                            value="<?= e(format_brazil_postal_code($supplier['postal_code'] ?? '')) ?>">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            id="lookupCepButton"
                            title="Consultar CEP"
                            aria-label="Consultar CEP">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <div class="form-text" id="cepLookupFeedback"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">
            <i class="bi bi-diagram-3 me-1"></i> CNAE
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <label class="form-label">CNAE principal</label>
                    <input type="text" name="main_cnae[code]" class="form-control" value="<?= e($mainCnae['code'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Nome</label>
                    <input type="text" name="main_cnae[name]" class="form-control" value="<?= e($mainCnae['name'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Descricao</label>
                    <input type="text" name="main_cnae[description]" class="form-control" value="<?= e($mainCnae['description'] ?? '') ?>">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">CNAEs secundarios</label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addSecondaryCnae">
                    <i class="bi bi-plus-lg"></i> Adicionar CNAE
                </button>
            </div>

            <div id="secondaryCnaeRows">
                <?php foreach ($secondaryCnaes as $index => $cnae): ?>
                    <div class="row g-2 align-items-end cnae-row mb-2">
                        <div class="col-md-2">
                            <input type="text" name="secondary_cnaes[<?= (int) $index ?>][code]" class="form-control" placeholder="Codigo" value="<?= e($cnae['code'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="secondary_cnaes[<?= (int) $index ?>][name]" class="form-control" placeholder="Nome" value="<?= e($cnae['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="secondary_cnaes[<?= (int) $index ?>][description]" class="form-control" placeholder="Descricao" value="<?= e($cnae['description'] ?? '') ?>">
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="button" class="btn btn-outline-danger remove-cnae-row" title="Remover CNAE" aria-label="Remover CNAE">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">
            <i class="bi bi-bank me-1"></i> Dados bancarios
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Banco</label>
                    <input type="text" name="bank_name" class="form-control" value="<?= e($supplier['bank_name'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Agencia</label>
                    <input type="text" name="bank_agency" class="form-control" value="<?= e($supplier['bank_agency'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Conta</label>
                    <input type="text" name="bank_account" class="form-control" value="<?= e($supplier['bank_account'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">
            <i class="bi bi-card-text me-1"></i> Observacoes e status
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Observacoes</label>
                    <textarea name="notes" rows="3" class="form-control"><?= e($supplier['notes'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            class="form-check-input"
                            id="isActive"
                            <?= checked_attr($supplier['is_active'] ?? null, true) ?>>
                        <label class="form-check-label" for="isActive">Fornecedor ativo</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="/suppliers.php" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary">Salvar</button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const cnpjButton = document.getElementById('lookupCnpjButton');
        const cepButton = document.getElementById('lookupCepButton');
        const addSecondaryCnaeButton = document.getElementById('addSecondaryCnae');
        const secondaryCnaeRows = document.getElementById('secondaryCnaeRows');
        const documentInput = document.getElementById('supplierDocument');
        const cnpjFeedback = document.getElementById('cnpjLookupFeedback');
        const cepFeedback = document.getElementById('cepLookupFeedback');
        let secondaryCnaeIndex = <?= count($secondaryCnaes) ?>;

        const fields = {
            name: document.querySelector('[name="name"]'),
            trade_name: document.querySelector('[name="trade_name"]'),
            document: documentInput,
            email: document.querySelector('[name="email"]'),
            phone: document.querySelector('[name="phone"]'),
            address: document.querySelector('[name="address"]'),
            city: document.querySelector('[name="city"]'),
            state: document.querySelector('[name="state"]'),
            postal_code: document.querySelector('[name="postal_code"]'),
            state_registration: document.querySelector('[name="state_registration"]'),
            municipal_registration: document.querySelector('[name="municipal_registration"]'),
            company_size: document.querySelector('[name="company_size"]'),
            website_url: document.querySelector('[name="website_url"]'),
        };

        function onlyDigits(value) {
            return String(value || '').replace(/\D/g, '');
        }

        function formatCpf(value) {
            const digits = onlyDigits(value).slice(0, 11);

            return digits
                .replace(/^(\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
        }

        function formatCnpj(value) {
            const digits = onlyDigits(value).slice(0, 14);

            return digits
                .replace(/^(\d{2})(\d)/, '$1.$2')
                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3/$4')
                .replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d)/, '$1.$2.$3/$4-$5');
        }

        function formatCpfCnpj(value) {
            return onlyDigits(value).length <= 11 ? formatCpf(value) : formatCnpj(value);
        }

        function formatPhone(value) {
            const digits = onlyDigits(value).slice(0, 11);

            if (digits.length <= 10) {
                return digits
                    .replace(/^(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
            }

            return digits
                .replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
        }

        function formatCep(value) {
            return onlyDigits(value).slice(0, 8).replace(/^(\d{5})(\d)/, '$1-$2');
        }

        function applyMask(input) {
            if (!input) {
                return;
            }

            if (input.dataset.mask === 'cpf-cnpj') {
                input.value = formatCpfCnpj(input.value);
            }

            if (input.dataset.mask === 'cpf') {
                input.value = formatCpf(input.value);
            }

            if (input.dataset.mask === 'phone') {
                input.value = formatPhone(input.value);
            }

            if (input.dataset.mask === 'cep') {
                input.value = formatCep(input.value);
            }

            if (input.dataset.mask === 'uf') {
                input.value = input.value.replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase();
            }

            if (input.hasAttribute('data-uppercase')) {
                input.value = input.value.toUpperCase();
            }
        }

        document.querySelectorAll('[data-mask], [data-uppercase]').forEach(function(input) {
            input.addEventListener('input', function() {
                applyMask(input);
            });
            applyMask(input);
        });

        function setFeedback(feedback, message, state) {
            if (!feedback) {
                return;
            }

            feedback.textContent = message;
            feedback.classList.remove('text-danger', 'text-success', 'text-muted');
            feedback.classList.add(state === 'error' ? 'text-danger' : state === 'success' ? 'text-success' : 'text-muted');
        }

        function fillField(field, value, force) {
            if (!field || value === null || value === undefined || value === '') {
                return;
            }

            if (field.value && field.name !== 'document' && !force) {
                return;
            }

            field.value = value;
            applyMask(field);
        }

        function setMainCnae(cnae) {
            if (!cnae || typeof cnae !== 'object') {
                return;
            }

            fillField(document.querySelector('[name="main_cnae[code]"]'), cnae.code || '', false);
            fillField(document.querySelector('[name="main_cnae[name]"]'), cnae.name || '', false);
            fillField(document.querySelector('[name="main_cnae[description]"]'), cnae.description || '', false);
        }

        function bindRemoveCnaeButton(row) {
            const button = row.querySelector('.remove-cnae-row');

            if (!button) {
                return;
            }

            button.addEventListener('click', function() {
                if (secondaryCnaeRows.querySelectorAll('.cnae-row').length <= 1) {
                    row.querySelectorAll('input').forEach(function(input) {
                        input.value = '';
                    });
                    return;
                }

                row.remove();
            });
        }

        function addSecondaryCnaeRow(cnae) {
            const index = secondaryCnaeIndex++;
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-end cnae-row mb-2';
            row.innerHTML = `
                <div class="col-md-2">
                    <input type="text" name="secondary_cnaes[${index}][code]" class="form-control" placeholder="Codigo">
                </div>
                <div class="col-md-4">
                    <input type="text" name="secondary_cnaes[${index}][name]" class="form-control" placeholder="Nome">
                </div>
                <div class="col-md-5">
                    <input type="text" name="secondary_cnaes[${index}][description]" class="form-control" placeholder="Descricao">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="button" class="btn btn-outline-danger remove-cnae-row" title="Remover CNAE" aria-label="Remover CNAE">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>`;

            secondaryCnaeRows.appendChild(row);
            bindRemoveCnaeButton(row);

            if (cnae) {
                row.querySelector(`[name="secondary_cnaes[${index}][code]"]`).value = cnae.code || '';
                row.querySelector(`[name="secondary_cnaes[${index}][name]"]`).value = cnae.name || '';
                row.querySelector(`[name="secondary_cnaes[${index}][description]"]`).value = cnae.description || '';
            }
        }

        function setSecondaryCnaes(cnaes) {
            if (!Array.isArray(cnaes) || cnaes.length === 0) {
                return;
            }

            secondaryCnaeRows.innerHTML = '';
            cnaes.forEach(addSecondaryCnaeRow);
        }

        document.querySelectorAll('.cnae-row').forEach(bindRemoveCnaeButton);

        if (addSecondaryCnaeButton) {
            addSecondaryCnaeButton.addEventListener('click', function() {
                addSecondaryCnaeRow(null);
            });
        }

        if (cnpjButton && documentInput && cnpjFeedback) {
            cnpjButton.addEventListener('click', async function() {
                const cnpj = onlyDigits(documentInput.value);

                if (cnpj.length !== 14) {
                    setFeedback(cnpjFeedback, 'Informe um CNPJ valido com 14 digitos para consultar.', 'error');
                    documentInput.focus();
                    return;
                }

                cnpjButton.disabled = true;
                setFeedback(cnpjFeedback, 'Consultando CNPJ...', 'muted');

                try {
                    const response = await fetch('/supplier_cnpj_lookup.php?cnpj=' + encodeURIComponent(cnpj));
                    const payload = await response.json();

                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Nao foi possivel consultar o CNPJ.');
                    }

                    Object.entries(payload.data || {}).forEach(function(entry) {
                        if (entry[0] === 'main_cnae') {
                            setMainCnae(entry[1]);
                            return;
                        }

                        if (entry[0] === 'secondary_cnaes') {
                            setSecondaryCnaes(entry[1]);
                            return;
                        }

                        const field = fields[entry[0]];
                        fillField(field, entry[1], false);
                    });

                    setFeedback(cnpjFeedback, 'Dados encontrados e preenchidos.', 'success');
                } catch (error) {
                    setFeedback(cnpjFeedback, error.message || 'Nao foi possivel consultar o CNPJ.', 'error');
                } finally {
                    cnpjButton.disabled = false;
                }
            });
        }

        async function lookupCep() {
            const postalCode = onlyDigits(fields.postal_code?.value || '');

            if (postalCode.length !== 8) {
                setFeedback(cepFeedback, 'Informe um CEP valido com 8 digitos para consultar.', 'error');
                fields.postal_code?.focus();
                return;
            }

            cepButton.disabled = true;
            setFeedback(cepFeedback, 'Consultando CEP...', 'muted');

            try {
                const response = await fetch('/supplier_cep_lookup.php?cep=' + encodeURIComponent(postalCode));
                const payload = await response.json();

                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Nao foi possivel consultar o CEP.');
                }

                Object.entries(payload.data || {}).forEach(function(entry) {
                    const field = fields[entry[0]];
                    fillField(field, entry[1], entry[0] !== 'postal_code');
                });

                setFeedback(cepFeedback, 'Endereco encontrado e preenchido.', 'success');
            } catch (error) {
                setFeedback(cepFeedback, error.message || 'Nao foi possivel consultar o CEP.', 'error');
            } finally {
                cepButton.disabled = false;
            }
        }

        if (cepButton && fields.postal_code && cepFeedback) {
            cepButton.addEventListener('click', lookupCep);
            fields.postal_code.addEventListener('blur', function() {
                const postalCode = onlyDigits(fields.postal_code.value);

                if (postalCode.length === 8 && (!fields.address?.value || !fields.city?.value || !fields.state?.value)) {
                    lookupCep();
                }
            });
        }
    });
</script>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
