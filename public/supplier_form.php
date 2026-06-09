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
        'document' => trim($_POST['document'] ?? ''),
        'contact_name' => trim($_POST['contact_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'is_active' => isset($_POST['is_active']),
    ];

    if (!$data['name']) {
        $errors[] = 'O nome do fornecedor é obrigatório.';
    }

    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
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

<form method="post" class="card card-body shadow-sm">
    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label">Nome/Razão social</label>
            <input type="text" name="name" class="form-control" required value="<?= e($supplier['name'] ?? '') ?>">
        </div>

        <div class="col-md-5">
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
            <input type="text" name="phone" class="form-control" value="<?= e($supplier['phone'] ?? '') ?>">
        </div>

        <div class="col-12">
            <label class="form-label">Endereço</label>
            <textarea name="address" rows="2" class="form-control"><?= e($supplier['address'] ?? '') ?></textarea>
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
                    <?= ($supplier['is_active'] ?? true) ? 'checked' : '' ?>>
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
        const button = document.getElementById('lookupCnpjButton');
        const documentInput = document.getElementById('supplierDocument');
        const feedback = document.getElementById('cnpjLookupFeedback');

        if (!button || !documentInput || !feedback) {
            return;
        }

        const fields = {
            name: document.querySelector('[name="name"]'),
            document: documentInput,
            email: document.querySelector('[name="email"]'),
            phone: document.querySelector('[name="phone"]'),
            address: document.querySelector('[name="address"]'),
            notes: document.querySelector('[name="notes"]'),
        };

        function setFeedback(message, state) {
            feedback.textContent = message;
            feedback.classList.remove('text-danger', 'text-success', 'text-muted');
            feedback.classList.add(state === 'error' ? 'text-danger' : state === 'success' ? 'text-success' : 'text-muted');
        }

        function fillField(field, value) {
            if (!field || !value) {
                return;
            }

            if (field.value && field.name !== 'document') {
                return;
            }

            field.value = value;
        }

        button.addEventListener('click', async function() {
            const cnpj = documentInput.value.replace(/\D/g, '');

            if (cnpj.length !== 14) {
                setFeedback('Informe um CNPJ valido com 14 digitos para consultar.', 'error');
                documentInput.focus();
                return;
            }

            button.disabled = true;
            setFeedback('Consultando CNPJ...', 'muted');

            try {
                const response = await fetch('/supplier_cnpj_lookup.php?cnpj=' + encodeURIComponent(cnpj));
                const payload = await response.json();

                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Nao foi possivel consultar o CNPJ.');
                }

                Object.entries(payload.data || {}).forEach(function(entry) {
                    const field = fields[entry[0]];
                    fillField(field, entry[1]);
                });

                setFeedback('Dados encontrados e preenchidos.', 'success');
            } catch (error) {
                setFeedback(error.message || 'Nao foi possivel consultar o CNPJ.', 'error');
            } finally {
                button.disabled = false;
            }
        });
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
