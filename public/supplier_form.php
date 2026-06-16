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
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'state' => strtoupper(trim($_POST['state'] ?? '')),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
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

    if ($data['state'] && !preg_match('/^[A-Z]{2}$/', $data['state'])) {
        $errors[] = 'Informe a UF com 2 letras.';
    }

    if ($data['postal_code'] && strlen(only_digits($data['postal_code'])) !== 8) {
        $errors[] = 'Informe um CEP válido com 8 dígitos.';
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

<form method="post" class="card card-body shadow-sm supplier-form">
    <div class="row g-3">
        <div class="col-lg-7">
            <label class="form-label">Nome/Razão social</label>
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
                    value="<?= e(format_brazil_document($supplier['document'] ?? '')) ?>">
                <button type="button" class="btn btn-outline-secondary" id="lookupCnpjButton">
                    <i class="bi bi-search"></i>Consultar CNPJ
                </button>
            </div>
            <div class="form-text" id="cnpjLookupFeedback">
                Use a consulta para preencher os dados cadastrais do fornecedor.
            </div>
        </div>

        <div class="col-md-4 col-lg-3">
            <label class="form-label">Contato</label>
            <input type="text" name="contact_name" class="form-control" value="<?= e($supplier['contact_name'] ?? '') ?>">
        </div>

        <div class="col-md-4 col-lg-2">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-control" value="<?= e($supplier['email'] ?? '') ?>">
        </div>

        <div class="col-md-4 col-lg-2">
            <label class="form-label">Telefone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($supplier['phone'] ?? '') ?>">
        </div>

        <div class="col-12">
            <hr class="my-2">
        </div>

        <div class="col-lg-6">
            <label class="form-label">Endereço</label>
            <textarea name="address" rows="2" class="form-control"><?= e($supplier['address'] ?? '') ?></textarea>
            <div class="form-text">Rua, número, complemento e bairro.</div>
        </div>

        <div class="col-md-5 col-lg-3">
            <label class="form-label">Cidade</label>
            <input type="text" name="city" class="form-control" value="<?= e($supplier['city'] ?? '') ?>">
        </div>

        <div class="col-md-3 col-lg-1">
            <label class="form-label">UF</label>
            <input type="text" name="state" class="form-control text-uppercase" maxlength="2" value="<?= e($supplier['state'] ?? '') ?>">
        </div>

        <div class="col-md-4 col-lg-2">
            <label class="form-label">CEP</label>
            <div class="input-group">
                <input
                    type="text"
                    name="postal_code"
                    id="postalCodeInput"
                    class="form-control"
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

        <div class="col-12">
            <hr class="my-2">
        </div>

        <div class="col-md-4">
            <label class="form-label">Banco</label>
            <input type="text" name="bank_name" class="form-control" value="<?= e($supplier['bank_name'] ?? '') ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Agência</label>
            <input type="text" name="bank_agency" class="form-control" value="<?= e($supplier['bank_agency'] ?? '') ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Conta</label>
            <input type="text" name="bank_account" class="form-control" value="<?= e($supplier['bank_account'] ?? '') ?>">
        </div>

        <div class="col-12">
            <hr class="my-2">
        </div>

        <div class="col-md-5">
            <label class="form-label">CPF do proprietário</label>
            <input type="text" name="owner_cpf" class="form-control" value="<?= e(format_brazil_document($supplier['owner_cpf'] ?? '')) ?>">
        </div>

        <div class="col-md-7">
            <label class="form-label">Nome do proprietário</label>
            <input type="text" name="owner_name" class="form-control" value="<?= e($supplier['owner_name'] ?? '') ?>">
        </div>

        <div class="col-12">
            <label class="form-label">Observações</label>
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

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/suppliers.php" class="btn btn-outline-secondary">
                Cancelar
            </a>

            <button class="btn btn-primary">
                Salvar
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const cnpjButton = document.getElementById('lookupCnpjButton');
        const cepButton = document.getElementById('lookupCepButton');
        const documentInput = document.getElementById('supplierDocument');
        const cnpjFeedback = document.getElementById('cnpjLookupFeedback');
        const cepFeedback = document.getElementById('cepLookupFeedback');

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
        };

        function setFeedback(feedback, message, state) {
            if (!feedback) {
                return;
            }

            feedback.textContent = message;
            feedback.classList.remove('text-danger', 'text-success', 'text-muted');
            feedback.classList.add(state === 'error' ? 'text-danger' : state === 'success' ? 'text-success' : 'text-muted');
        }

        function fillField(field, value, force) {
            if (!field || !value) {
                return;
            }

            if (field.value && field.name !== 'document' && !force) {
                return;
            }

            field.value = value;
        }

        if (cnpjButton && documentInput && cnpjFeedback) {
            cnpjButton.addEventListener('click', async function() {
                const cnpj = documentInput.value.replace(/\D/g, '');

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
            const postalCode = (fields.postal_code?.value || '').replace(/\D/g, '');

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
                const postalCode = fields.postal_code.value.replace(/\D/g, '');

                if (postalCode.length === 8 && (!fields.address?.value || !fields.city?.value || !fields.state?.value)) {
                    lookupCep();
                }
            });
        }
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
