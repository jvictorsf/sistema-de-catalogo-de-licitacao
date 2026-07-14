(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-item-supply-conditions]');
        if (!root) return;

        const unitType = document.getElementById('unit_type_id');
        const classificationInputs = Array.from(root.querySelectorAll('input[name="item_classification"]'));
        const serviceClassification = root.querySelector('[data-service-classification]');
        const serviceNotice = root.querySelector('[data-service-classification-notice]');
        const warrantyInput = root.querySelector('[name="warranty_months"]');
        const warrantyHelp = root.querySelector('[data-warranty-help]');
        const warrantyPreview = root.querySelector('[data-warranty-preview]');
        const validityWrap = root.querySelector('[data-validity-toggle-wrap]');
        const validityToggle = root.querySelector('[data-validity-toggle]');
        const validityHidden = root.querySelector('[data-validity-hidden]');
        const validityMonthsWrap = root.querySelector('[data-validity-months-wrap]');
        const validityMonths = root.querySelector('[data-validity-months]');
        const justificationWrap = root.querySelector('[data-validity-justification-wrap]');
        const justification = root.querySelector('[data-validity-justification]');
        const validityPreviewTitle = root.querySelector('[data-validity-preview-title]');
        const validityPreview = root.querySelector('[data-validity-preview]');
        const canPermanentValidity = root.dataset.canPermanentValidity === '1';
        const wordsElement = document.getElementById('itemSupplyMonthWords');
        let monthWords = {};

        try {
            monthWords = JSON.parse(wordsElement ? wordsElement.textContent : '{}');
        } catch (error) {
            monthWords = {};
        }

        const configs = {
            permanent: { nature: 'PERMANENTE', defaultWarranty: 12, minimumWarranty: 12 },
            consumption_nonperishable: { nature: 'CONSUMO', defaultWarranty: 3, minimumWarranty: 3 },
            consumption_perishable: { nature: 'CONSUMO', defaultWarranty: 3, minimumWarranty: 3 },
            service: { nature: 'SERVICO', defaultWarranty: 3, minimumWarranty: 1 }
        };

        function selectedClassification() {
            const selected = classificationInputs.find(input => input.checked);
            return selected ? selected.value : '';
        }

        function selectedUnitIsService() {
            if (!unitType) return false;
            const option = unitType.options[unitType.selectedIndex];
            return Boolean(option && option.dataset.specKind === 'service');
        }

        function monthPeriod(value) {
            const months = Number(value);
            if (!Number.isInteger(months) || months <= 0) return '';
            const words = monthWords[String(months)];
            return String(months) + (words ? ' (' + words + ')' : '') + ' ' + (months === 1 ? 'mês' : 'meses');
        }

        function warrantyText(classification, months) {
            const period = monthPeriod(months);
            if (!period) return 'Selecione a classificação e informe o prazo.';
            if (classification === 'permanent') {
                return 'Garantia mínima de ' + period + ' contra defeitos de fabricação, contada a partir do recebimento definitivo, compreendendo reparo ou substituição do produto, sem custos adicionais para a Administração.';
            }
            if (classification === 'service') {
                return 'Garantia mínima dos serviços de ' + period + ', contada a partir do recebimento definitivo, compreendendo a correção de falhas, vícios ou desconformidades sem custos adicionais para a Administração.';
            }
            return 'Garantia mínima de ' + period + ' contra defeitos de fabricação, contada a partir do recebimento definitivo, sem prejuízo da obrigatoriedade de substituição de produtos avariados, defeituosos, divergentes ou em desconformidade com as especificações.';
        }

        function validityText(months) {
            const period = monthPeriod(months);
            return period ? 'O produto deverá possuir prazo de validade remanescente mínimo de ' + period + ', contado da data da entrega.' : '';
        }

        function updateSupplyConditions(applyDefaults) {
            const isService = selectedUnitIsService();

            classificationInputs.forEach(input => {
                if (input !== serviceClassification) input.disabled = isService;
            });

            if (isService && serviceClassification) {
                serviceClassification.checked = true;
            } else if (!isService && serviceClassification && serviceClassification.checked) {
                serviceClassification.checked = false;
            }

            if (serviceNotice) serviceNotice.classList.toggle('d-none', !isService);

            const classification = selectedClassification();
            const config = configs[classification];

            if (!config) {
                warrantyPreview.textContent = 'Selecione a classificação e informe o prazo.';
                validityWrap.classList.add('d-none');
                validityMonthsWrap.classList.add('d-none');
                justificationWrap.classList.add('d-none');
                return;
            }

            warrantyInput.min = String(config.minimumWarranty);
            const warrantyValue = Number(warrantyInput.value);
            if (applyDefaults && (!Number.isInteger(warrantyValue) || warrantyValue < config.minimumWarranty)) {
                warrantyInput.value = String(config.defaultWarranty);
            }

            const normalizedWarranty = Number(warrantyInput.value);
            warrantyInput.setCustomValidity(
                Number.isInteger(normalizedWarranty) && normalizedWarranty >= config.minimumWarranty
                    ? ''
                    : 'Informe um número inteiro igual ou superior a ' + config.minimumWarranty + '.'
            );
            warrantyHelp.textContent = 'Mínimo de ' + config.minimumWarranty + ' meses para esta classificação.';
            warrantyPreview.textContent = warrantyText(classification, warrantyInput.value);

            const isPerishable = classification === 'consumption_perishable';
            const isNonperishable = classification === 'consumption_nonperishable';
            const isPermanent = classification === 'permanent';
            const canShowPermanentToggle = isPermanent && (canPermanentValidity || validityToggle.checked);
            const showToggle = isPerishable || isNonperishable || canShowPermanentToggle;

            validityWrap.classList.toggle('d-none', !showToggle);
            validityToggle.disabled = isPerishable || (isPermanent && !canPermanentValidity);

            if (isPerishable) validityToggle.checked = true;
            if (classification === 'service') validityToggle.checked = false;

            validityHidden.value = validityToggle.disabled && validityToggle.checked ? '1' : '0';
            const validityActive = isPerishable || (showToggle && validityToggle.checked);
            validityMonthsWrap.classList.toggle('d-none', !validityActive);
            validityMonths.required = validityActive;

            if (validityActive && applyDefaults && !Number.isInteger(Number(validityMonths.value))) {
                validityMonths.value = '12';
            }

            const validityValue = Number(validityMonths.value);
            validityMonths.setCustomValidity(
                !validityActive || (Number.isInteger(validityValue) && validityValue > 0)
                    ? ''
                    : 'Informe um número inteiro maior que zero.'
            );

            const needsJustification = isPermanent && validityActive;
            justificationWrap.classList.toggle('d-none', !needsJustification);
            justification.required = needsJustification;
            justification.readOnly = needsJustification && !canPermanentValidity;

            validityPreviewTitle.classList.toggle('d-none', !validityActive);
            validityPreview.classList.toggle('d-none', !validityActive);
            validityPreview.textContent = validityActive
                ? (validityText(validityMonths.value) || 'Informe a validade mínima em meses.')
                : '';
        }

        classificationInputs.forEach(input => input.addEventListener('change', function () {
            if (this.value !== 'consumption_perishable' && validityToggle) validityToggle.checked = false;
            updateSupplyConditions(true);
        }));
        if (unitType) unitType.addEventListener('change', function () { updateSupplyConditions(true); });
        warrantyInput.addEventListener('input', function () { updateSupplyConditions(false); });
        validityToggle.addEventListener('change', function () { updateSupplyConditions(true); });
        validityMonths.addEventListener('input', function () { updateSupplyConditions(false); });
        justification.addEventListener('input', function () { updateSupplyConditions(false); });

        updateSupplyConditions(true);
    });
})();
