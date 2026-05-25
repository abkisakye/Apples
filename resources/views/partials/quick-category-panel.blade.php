@once
    <style>
        .quick-category-field {
            display: grid;
            gap: 8px;
            position: relative;
        }
        .quick-category-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .quick-category-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quick-category-row select {
            flex: 1;
            min-width: 0;
        }
        .quick-category-panel {
            position: absolute;
            inset: auto;
        }
        .quick-category-panel[hidden] {
            display: none;
        }
        .quick-category-modal {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            background: rgba(15, 23, 42, .34);
        }
        .quick-category-dialog {
            display: grid;
            gap: 12px;
            width: min(460px, 100%);
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--panel, #fffdf7);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
        }
        .quick-category-dialog h3 {
            margin: 0;
            font-size: 1.08rem;
        }
        .quick-category-inputs {
            display: grid;
            gap: 8px;
        }
        .quick-category-inputs input {
            width: 100%;
        }
        .quick-category-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 2px;
        }
        .quick-category-message {
            color: var(--muted);
            font-size: .82rem;
        }
        .quick-category-message.is-error {
            color: #b42318;
            font-weight: 700;
        }
        @media (max-width: 760px) {
            .quick-category-row {
                flex-direction: column;
                align-items: stretch;
            }
            .quick-category-modal {
                align-items: flex-start;
                padding-top: 70px;
            }
            .quick-category-actions {
                flex-direction: column;
            }
        }
    </style>

    <script>
        (() => {
            document.addEventListener('click', async (event) => {
                const toggle = event.target.closest('[data-quick-category-toggle]');
                if (toggle) {
                    const panel = document.getElementById(toggle.dataset.quickCategoryToggle);
                    if (panel) {
                        openCategoryPanel(panel);
                    }
                    return;
                }

                const panelBackdrop = event.target.matches('[data-quick-category-modal]') ? event.target.closest('[data-quick-category-panel]') : null;
                const cancelButton = event.target.closest('[data-cancel-category]');
                if (cancelButton || panelBackdrop) {
                    const panel = cancelButton?.closest('[data-quick-category-panel]')
                        || panelBackdrop;

                    closeCategoryPanel(panel);
                    return;
                }

                const saveButton = event.target.closest('[data-save-category]');
                if (! saveButton) {
                    return;
                }

                const panel = saveButton.closest('[data-quick-category-panel]');
                const input = panel?.querySelector('[data-category-name]');
                const message = panel?.querySelector('[data-category-message]');
                const select = document.getElementById(panel?.dataset.selectId || '');

                if (! panel || ! input || ! message || ! select) {
                    return;
                }

                message.classList.remove('is-error');
                message.textContent = '';
                saveButton.disabled = true;

                try {
                    const response = await fetch(panel.dataset.endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': panel.dataset.csrfToken,
                        },
                        body: JSON.stringify({ name: input.value.trim() }),
                    });
                    const payload = await response.json();

                    if (! response.ok) {
                        const errors = payload.errors || {};
                        throw new Error(errors.name?.[0] || payload.message || 'Could not save category.');
                    }

                    const category = payload.category;
                    let option = Array.from(select.options).find((item) => item.value === String(category.id));
                    if (! option) {
                        option = new Option(category.name, category.id, true, true);
                        select.add(option);
                    }
                    option.selected = true;
                    input.value = '';
                    panel.hidden = true;
                    showCategorySuccess(select, `${category.name} saved.`);
                } catch (error) {
                    message.classList.add('is-error');
                    message.textContent = error.message;
                } finally {
                    saveButton.disabled = false;
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                document.querySelectorAll('[data-quick-category-panel]:not([hidden])').forEach((panel) => {
                    closeCategoryPanel(panel);
                });
            });

            function openCategoryPanel(panel) {
                panel.hidden = false;
                const input = panel.querySelector('[data-category-name]');
                const message = panel.querySelector('[data-category-message]');

                if (message) {
                    message.textContent = '';
                    message.classList.remove('is-error');
                }
                window.setTimeout(() => input?.focus(), 0);
            }

            function closeCategoryPanel(panel) {
                if (! panel) {
                    return;
                }

                const input = panel.querySelector('[data-category-name]');
                const message = panel.querySelector('[data-category-message]');

                if (input) {
                    input.value = '';
                }
                if (message) {
                    message.textContent = '';
                    message.classList.remove('is-error');
                }

                panel.hidden = true;
            }

            function showCategorySuccess(select, text) {
                const field = select.closest('.quick-category-field');
                if (! field) {
                    return;
                }

                let success = field.querySelector('[data-category-success]');
                if (! success) {
                    success = document.createElement('div');
                    success.className = 'quick-category-message';
                    success.dataset.categorySuccess = '';
                    field.appendChild(success);
                }

                success.textContent = text;
                window.setTimeout(() => {
                    success.textContent = '';
                }, 3000);
            }
        })();
    </script>
@endonce

<div class="quick-category-panel" id="{{ $panelId }}" data-quick-category-panel data-endpoint="{{ $endpoint }}" data-select-id="{{ $selectId }}" data-csrf-token="{{ csrf_token() }}" hidden>
    <div class="quick-category-modal" data-quick-category-modal>
        <div class="quick-category-dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $panelId }}-title">
            <h3 id="{{ $panelId }}-title">New Category</h3>
            <label class="form-field">
                <span>Category Name</span>
                <input type="text" data-category-name placeholder="Category Name" autocomplete="off">
            </label>
            <div class="quick-category-message" data-category-message></div>
            <div class="quick-category-actions">
                <button type="button" data-save-category>Save Category</button>
                <button type="button" class="button-link" data-cancel-category>Cancel</button>
            </div>
        </div>
    </div>
</div>
