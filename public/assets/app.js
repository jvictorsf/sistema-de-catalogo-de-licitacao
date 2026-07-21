document.addEventListener('DOMContentLoaded', () => {
    const category = document.getElementById('category_id');
    const subcategory = document.getElementById('subcategory_id');
    const specification = document.getElementById('specification');
    const feedback = document.getElementById('jsonFeedback');

    function filterSubcategories() {
        if (!category || !subcategory) return;

        const selectedParent = category.value;

        Array.from(subcategory.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            option.hidden = option.dataset.parent !== selectedParent;
        });

        const selectedOption = subcategory.options[subcategory.selectedIndex];

        if (selectedOption && selectedOption.hidden) {
            subcategory.value = '';
        }
    }

    function validateJson() {
        if (!specification || !feedback) return;

        try {
            JSON.parse(specification.value);
            specification.classList.remove('is-invalid');
            specification.classList.add('is-valid');
            feedback.textContent = 'JSON válido.';
            feedback.classList.remove('text-danger');
            feedback.classList.add('text-success');
        } catch (error) {
            specification.classList.remove('is-valid');
            specification.classList.add('is-invalid');
            feedback.textContent = 'JSON inválido: ' + error.message;
            feedback.classList.remove('text-success');
            feedback.classList.add('text-danger');
        }
    }

    if (category) {
        category.addEventListener('change', filterSubcategories);
        filterSubcategories();
    }

    if (specification) {
        specification.addEventListener('input', validateJson);
        validateJson();
    }
    const btnAiSuggest = document.getElementById('btnAiSuggest');
    const itemName = document.getElementById('item_name');
    const aiAlert = document.getElementById('aiSuggestionAlert');

    function showAiAlert(type, message) {
        if (!aiAlert) return;

        aiAlert.className = 'alert alert-' + type;
        aiAlert.textContent = message;
    }

    async function generateAiSuggestion() {
        if (!itemName || !btnAiSuggest) return;

        const name = itemName.value.trim();

        if (name.length < 3) {
            showAiAlert('warning', 'Informe o nome do item antes de gerar a sugestão.');
            itemName.focus();
            return;
        }

        btnAiSuggest.disabled = true;
        btnAiSuggest.textContent = 'Gerando...';
        showAiAlert('info', 'Gerando sugestão com IA. Revise cuidadosamente antes de salvar.');

        try {
            const response = await fetch('/ai_suggest.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ name }),
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Não foi possível gerar a sugestão.');
            }

            const data = result.data;

            const level = document.querySelector('[name="level"]');
            const specification = document.querySelector('[name="specification"]');
            const justification = document.querySelector('[name="justification"]');
            const warranty = document.querySelector('[name="warranty"]');
            const environmentalImpacts = document.querySelector('[name="environmental_impacts"]');

            if (level && data.level) {
                level.value = data.level;
            }

            if (specification && data.specification) {
                specification.value = JSON.stringify(data.specification, null, 2);
                specification.dispatchEvent(new Event('input'));
            }

            if (justification && data.justification) {
                justification.value = data.justification;
            }

            if (warranty && data.warranty) {
                warranty.value = data.warranty;
            }

            if (environmentalImpacts && data.environmental_impacts) {
                environmentalImpacts.value = JSON.stringify(
                    Array.isArray(data.environmental_impacts)
                        ? data.environmental_impacts
                        : [data.environmental_impacts]
                );
                environmentalImpacts.dispatchEvent(new Event('change'));
            }

            let message = 'Sugestão gerada. Revise os campos antes de salvar.';

            if (Array.isArray(data.warnings) && data.warnings.length > 0) {
                message += ' Alertas: ' + data.warnings.join(' | ');
            }

            showAiAlert('success', message);
        } catch (error) {
            showAiAlert('danger', error.message);
        } finally {
            btnAiSuggest.disabled = false;
            btnAiSuggest.textContent = 'Gerar com IA';
        }
    }

    if (btnAiSuggest) {
        btnAiSuggest.addEventListener('click', generateAiSuggestion);
    }

    const kitItemSearch = document.getElementById('kitItemSearch');
    const kitItemSelect = document.getElementById('kitItemSelect');

    function filterKitItems() {
        if (!kitItemSearch || !kitItemSelect) return;

        const term = kitItemSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        Array.from(kitItemSelect.options).forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }

            const haystack = (option.dataset.search || option.textContent || '').toLowerCase();
            const visible = !term || haystack.includes(term);
            option.hidden = !visible;

            if (visible) {
                visibleCount++;
            }
        });

        const selectedOption = kitItemSelect.options[kitItemSelect.selectedIndex];

        if (selectedOption && selectedOption.hidden) {
            kitItemSelect.value = '';
        }

        kitItemSelect.classList.toggle('is-invalid', visibleCount === 0 && term.length > 0);
    }

    if (kitItemSearch && kitItemSelect) {
        kitItemSearch.addEventListener('input', filterKitItems);
        filterKitItems();
    }

    const appSidebar = document.getElementById('appSidebar');
    const sidebarScroll = document.querySelector('[data-sidebar-scroll]');
    const activeSidebarLink = document.querySelector('.app-sidebar-link.active');

    if (sidebarScroll && activeSidebarLink) {
        const linkTop = activeSidebarLink.offsetTop;
        const linkBottom = linkTop + activeSidebarLink.offsetHeight;
        const visibleTop = sidebarScroll.scrollTop;
        const visibleBottom = visibleTop + sidebarScroll.clientHeight;

        if (linkTop < visibleTop || linkBottom > visibleBottom) {
            sidebarScroll.scrollTop = Math.max(0, linkTop - (sidebarScroll.clientHeight / 2));
        }
    }

    if (appSidebar) {
        appSidebar.querySelectorAll('[data-sidebar-link]').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth >= 992 || !window.bootstrap?.Offcanvas) {
                    return;
                }

                const sidebarInstance = window.bootstrap.Offcanvas.getInstance(appSidebar);

                if (sidebarInstance) {
                    sidebarInstance.hide();
                }
            });
        });
    }

});
