jQuery(function ($) {

    // ============================================================
    // GLOBAL PLUGIN NOTIFICATIONS
    // ============================================================
    var taxocsieToastId = 0;

    function getNotificationText(message) {
        if (message && typeof message === 'object') {
            message = message.message || message.error || 'An unexpected error occurred.';
        }
        return $('<div>').html(String(message || '')).text().replace(/\s+/g, ' ').trim();
    }

    function dismissTaxocsieNotification($toast) {
        window.clearTimeout($toast.data('dismiss-timer'));
        $toast.removeClass('is-visible');
        window.setTimeout(function () { $toast.remove(); }, 220);
    }

    function showTaxocsieNotification(type, message, title, durationOverride) {
        var normalizedType = ['success', 'error', 'warning', 'info'].indexOf(type) !== -1 ? type : 'info';
        var titles = { success: 'Success', error: 'Something went wrong', warning: 'Please check', info: 'Notice' };
        var icons = { success: 'yes-alt', error: 'dismiss', warning: 'warning', info: 'info-outline' };
        var text = getNotificationText(message);
        var duration = normalizedType === 'error' ? 3000 :
            (normalizedType === 'warning' ? 2200 :
            (normalizedType === 'info' ? 1800 : 1600));
        if (Number(durationOverride) > 0) {
            duration = Math.max(1500, Math.min(10000, Number(durationOverride)));
        }
        var $region = $('#taxocsie-toast-region');

        if (!$region.length) {
            $region = $('<div id="taxocsie-toast-region" class="taxocsie-toast-region" aria-live="polite" aria-atomic="false"></div>');
            $('body').append($region);
        }

        var toastId = 'taxocsie-toast-' + (++taxocsieToastId);
        var $toast = $('<div class="taxocsie-toast taxocsie-toast--' + normalizedType + '" id="' + toastId + '" role="' + (normalizedType === 'error' ? 'alert' : 'status') + '">' +
            '<span class="taxocsie-toast__icon dashicons dashicons-' + icons[normalizedType] + '" aria-hidden="true"></span>' +
            '<span class="taxocsie-toast__content"><strong></strong><small></small></span>' +
            '<button type="button" class="taxocsie-toast__close" aria-label="Dismiss notification"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' +
            '<span class="taxocsie-toast__progress" aria-hidden="true"></span>' +
        '</div>');

        $toast.find('strong').text(title || titles[normalizedType]);
        $toast.find('small').text(text);
        $toast.find('.taxocsie-toast__progress').css('animation-duration', duration + 'ms');
        $region.append($toast);

        while ($region.children('.taxocsie-toast').length > 4) {
            $region.children('.taxocsie-toast').first().remove();
        }

        window.requestAnimationFrame(function () { $toast.addClass('is-visible'); });
        $toast.data('dismiss-timer', window.setTimeout(function () {
            dismissTaxocsieNotification($toast);
        }, duration));
    }

    $(document).on('click', '.taxocsie-toast__close', function () {
        dismissTaxocsieNotification($(this).closest('.taxocsie-toast'));
    });

    $('.taxocsie-panel__notice').each(function () {
        var $notice = $(this);
        var type = $notice.hasClass('notice-error') ? 'error' :
            ($notice.hasClass('notice-warning') ? 'warning' :
            ($notice.hasClass('notice-success') ? 'success' : 'info'));
        var title = getNotificationText($notice.find('strong').first().text());
        var message = getNotificationText($notice.find('p').first().text() || $notice.text());
        if (message.length > 280) {
            message = message.substring(0, 277) + '...';
        }
        showTaxocsieNotification(type, message, title || undefined, $notice.attr('data-toast-duration'));
        $notice.remove();
    });

    // ============================================================
    // MANAGER WORKSPACE NAVIGATION
    // ============================================================
    function updateManagerUrl(section, panelId) {
        if (!window.history || !window.history.replaceState) {
            return;
        }

        var url = new URL(window.location.href);
        url.searchParams.delete('preview');
        url.searchParams.delete('imported');
        url.searchParams.delete('user-imported');

        if (section === 'users') {
            url.searchParams.set('manager_view', 'users');
            url.searchParams.set('manager_action', panelId === 'taxocsie-user-import-panel' ? 'import' : 'export');
        } else {
            var managerView = panelId === 'taxocsie-import-panel' ? 'import' :
                (panelId === 'taxocsie-mass-edit-panel' ? 'edit' :
                (panelId === 'taxocsie-order-panel' ? 'order' : 'export'));
            url.searchParams.set('manager_view', managerView);
            url.searchParams.delete('manager_action');
        }

        window.history.replaceState({}, '', url.toString());
    }

    function activateManagerPanel($action, updateUrl) {
        var section = String($action.data('manager-section') || 'terms');
        var panelId = String($action.data('panel') || '');

        if (!panelId) {
            return;
        }

        $('.taxocsie-manager-section')
            .removeClass('is-active')
            .attr('aria-pressed', 'false');
        $('.taxocsie-manager-section[data-manager-section="' + section + '"]')
            .addClass('is-active')
            .attr('aria-pressed', 'true');

        var $activeActionGroup = $('.taxocsie-manager-actions[data-manager-actions="' + section + '"]');
        $('.taxocsie-manager-actions').prop('hidden', true);
        $activeActionGroup.prop('hidden', false);
        $activeActionGroup.find('.taxocsie-manager-action')
            .removeClass('is-active')
            .attr({ 'aria-selected': 'false', 'tabindex': '-1' });
        $action.addClass('is-active').attr({ 'aria-selected': 'true', 'tabindex': '0' });

		$('.taxocsie-manager-panel').hide().attr('hidden', true);
        if (section === 'users') {
            $('#taxocsie-users-panel').removeAttr('hidden').show();
            $('.taxocsie-user-action-panel').hide().attr('hidden', true);
            $('#' + panelId).removeAttr('hidden').show();
        } else {
            $('#' + panelId).removeAttr('hidden').show();
        }

        $('#export-preview-container').hide().empty();

        if (panelId !== 'taxocsie-import-panel') {
            $('#tcie-preview-container').html('');
            $('#tcie-import-actions').hide();
        }

        if (updateUrl) {
            updateManagerUrl(section, panelId);
        }
    }

    $('.taxocsie-manager-section').on('click', function () {
        var section = String($(this).data('manager-section') || 'terms');
        var $actions = $('.taxocsie-manager-actions[data-manager-actions="' + section + '"]');
        var $action = $actions.find('.taxocsie-manager-action.is-active').first();

        if (!$action.length) {
            $action = $actions.find('.taxocsie-manager-action').first();
        }

        activateManagerPanel($action, true);
    });

    $('.taxocsie-manager-action').on('click', function () {
        activateManagerPanel($(this), true);
    });

    $('.taxocsie-manager-actions').on('keydown', '.taxocsie-manager-action', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
            return;
        }

        event.preventDefault();
        var $actions = $(this).closest('.taxocsie-manager-actions').find('.taxocsie-manager-action');
        var currentIndex = $actions.index(this);
        var direction = event.key === 'ArrowRight' ? 1 : -1;
        var nextIndex = (currentIndex + direction + $actions.length) % $actions.length;
        var $next = $actions.eq(nextIndex);
        activateManagerPanel($next, true);
        $next.trigger('focus');
    });

    // ============================================================
    // INLINE MASS TERM EDITOR
    // ============================================================
    var massEditorState = {
        page: 1,
        totalPages: 1,
        perPage: 10,
        dirty: Object.create(null),
        taxonomy: ''
    };

    function massEditorMessage(key, fallback) {
        return window.taxocsieData && taxocsieData[key] ? taxocsieData[key] : fallback;
    }

    function updateMassSaveState() {
        var changedCount = Object.keys(massEditorState.dirty).length;
        $('#taxocsie-mass-save').prop('disabled', changedCount === 0);
    }

    function renderMassEditorRows(rows) {
        var $body = $('#taxocsie-mass-rows').empty();

        rows.forEach(function (row) {
            var id = String(row.id);
            var savedChange = massEditorState.dirty[id] || null;
            var $name = $('<input>', {
                type: 'text',
                class: 'taxocsie-mass-name',
                'aria-label': 'Term name'
            }).val(savedChange ? savedChange.name : row.name).attr('data-original', row.name);
            var $slug = $('<input>', {
                type: 'text',
                class: 'taxocsie-mass-slug',
                'aria-label': 'Term slug'
            }).val(savedChange ? savedChange.slug : row.slug).attr('data-original', row.slug);
            var $row = $('<tr>').attr('data-term-id', id);

            if (savedChange) {
                $row.addClass('is-dirty');
            }

            $('<td>').append($name).appendTo($row);
            $('<td>').append($slug).appendTo($row);
            $('<td>', { class: 'taxocsie-mass-parent' }).text(row.parent || '—').appendTo($row);
            $('<td>', { class: 'taxocsie-mass-count' }).text(row.count).appendTo($row);
            $('<td>', { class: 'taxocsie-mass-row-status' }).appendTo($row);
            $body.append($row);
        });
    }

    function loadMassEditorTerms(page, resetChanges) {
        var taxonomy = String($('#taxocsie-mass-taxonomy').val() || '');
        if (!taxonomy) {
            showTaxocsieNotification('warning', massEditorMessage('massChooseTaxonomy', 'Choose a taxonomy first.'));
            return;
        }

        if (resetChanges && Object.keys(massEditorState.dirty).length &&
            !window.confirm(massEditorMessage('massUnsavedConfirm', 'Discard the unsaved term changes?'))) {
            return;
        }

        if (resetChanges) {
            massEditorState.dirty = Object.create(null);
        }

        massEditorState.taxonomy = taxonomy;
        $('#taxocsie-mass-status').text(massEditorMessage('massLoading', 'Loading terms...'));
        $('#taxocsie-mass-load').prop('disabled', true);

        $.post(taxocsieData.ajaxUrl, {
            action: 'taxocsie_mass_get_terms',
            nonce: taxocsieData.massTermEditorNonce,
            taxonomy: taxonomy,
            search: $('#taxocsie-mass-search').val(),
            page_number: page,
            per_page: massEditorState.perPage
        }).done(function (response) {
            if (!response || !response.success) {
                showTaxocsieNotification('error', response && response.data && response.data.message ? response.data.message : taxocsieData.error);
                return;
            }

            var data = response.data;
            massEditorState.page = Number(data.page) || 1;
            massEditorState.totalPages = Number(data.totalPages) || 1;
            renderMassEditorRows(data.rows || []);
            $('#taxocsie-mass-table-controls, #taxocsie-mass-table-wrap, #taxocsie-mass-footer').prop('hidden', false);
            $('#taxocsie-mass-prev').prop('disabled', massEditorState.page <= 1);
            $('#taxocsie-mass-next').prop('disabled', massEditorState.page >= massEditorState.totalPages);
            $('#taxocsie-mass-total').text(data.total || 0);
            $('#taxocsie-mass-current-page').text(massEditorState.page);
            $('#taxocsie-mass-total-pages').text(massEditorState.totalPages);
            $('#taxocsie-mass-status').text(data.total ? String(data.total) + ' terms found.' : massEditorMessage('massNoTerms', 'No matching terms were found.'));
            updateMassSaveState();
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : taxocsieData.error;
            showTaxocsieNotification('error', message);
        }).always(function () {
            $('#taxocsie-mass-load').prop('disabled', false);
        });
    }

    $('#taxocsie-mass-load').on('click', function () {
        loadMassEditorTerms(1, true);
    });

    $('#taxocsie-mass-search').on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadMassEditorTerms(1, true);
        }
    });

    $('#taxocsie-mass-prev, #taxocsie-mass-next').on('click', function () {
        var direction = this.id === 'taxocsie-mass-next' ? 1 : -1;
        loadMassEditorTerms(massEditorState.page + direction, false);
    });

    $('#taxocsie-mass-per-page').on('change', function () {
        massEditorState.perPage = parseInt(this.value, 10) || 10;
        loadMassEditorTerms(1, false);
    });

    $('#taxocsie-mass-rows').on('input', 'input', function () {
        var $row = $(this).closest('tr');
        var id = String($row.data('term-id'));
        var $name = $row.find('.taxocsie-mass-name');
        var $slug = $row.find('.taxocsie-mass-slug');
        var name = String($name.val() || '').trim();
        var slug = String($slug.val() || '').trim();
        var changed = name !== String($name.attr('data-original')) || slug !== String($slug.attr('data-original'));

        if (changed) {
            if (!massEditorState.dirty[id] && Object.keys(massEditorState.dirty).length >= 100) {
                showTaxocsieNotification('warning', 'Save the current 100 changes before editing more terms.');
                return;
            }
            massEditorState.dirty[id] = { id: Number(id), name: name, slug: slug };
            $row.addClass('is-dirty').removeClass('is-error');
            $row.find('.taxocsie-mass-row-status').text('Changed');
        } else {
            delete massEditorState.dirty[id];
            $row.removeClass('is-dirty is-error');
            $row.find('.taxocsie-mass-row-status').empty();
        }
        updateMassSaveState();
    });

    $('#taxocsie-mass-save').on('click', function () {
        var changes = Object.keys(massEditorState.dirty).map(function (id) {
            return massEditorState.dirty[id];
        });
        if (!changes.length) {
            showTaxocsieNotification('info', massEditorMessage('massNoChanges', 'No term changes to save.'));
            return;
        }
        if (changes.some(function (row) { return !row.name; })) {
            showTaxocsieNotification('warning', 'Every changed term needs a name.');
            return;
        }

        var $button = $(this).prop('disabled', true);
        $('#taxocsie-mass-status').text(massEditorMessage('massSaving', 'Saving term changes...'));
        $.post(taxocsieData.ajaxUrl, {
            action: 'taxocsie_mass_update_terms',
            nonce: taxocsieData.massTermEditorNonce,
            taxonomy: massEditorState.taxonomy,
            terms: JSON.stringify(changes)
        }).done(function (response) {
            if (!response || !response.success) {
                showTaxocsieNotification('error', response && response.data && response.data.message ? response.data.message : taxocsieData.error);
                return;
            }

            (response.data.updated || []).forEach(function (termId) {
                delete massEditorState.dirty[String(termId)];
            });
            var errors = response.data.errors || {};
            Object.keys(errors).forEach(function (termId) {
                var $row = $('#taxocsie-mass-rows tr[data-term-id="' + termId + '"]');
                $row.addClass('is-error');
                $row.find('.taxocsie-mass-row-status').text(errors[termId]);
            });

            showTaxocsieNotification(Object.keys(errors).length ? 'warning' : 'success', response.data.message);
            if (!Object.keys(errors).length) {
                loadMassEditorTerms(massEditorState.page, false);
            } else {
                $('#taxocsie-mass-status').text(response.data.message);
                updateMassSaveState();
            }
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : taxocsieData.error;
            showTaxocsieNotification('error', message);
        }).always(function () {
            $button.prop('disabled', Object.keys(massEditorState.dirty).length === 0);
        });
    });

    var draggedOrderItem = null;
    $('#taxocsie-order-load').on('click', function () {
        var taxonomy = String($('#taxocsie-order-taxonomy').val() || '');
        if (!taxonomy) {
            showTaxocsieNotification('warning', 'Choose a taxonomy first.');
            return;
        }
        $('#taxocsie-order-status').text('Loading terms...');
        $.post(taxocsieData.ajaxUrl, { action: 'taxocsie_get_term_order', nonce: taxocsieData.termOrderingNonce, taxonomy: taxonomy })
            .done(function (response) {
                if (!response || !response.success) {
                    showTaxocsieNotification('error', getNotificationText(response && response.data));
                    return;
                }
                var $list = $('#taxocsie-order-list').empty();
                (response.data.terms || []).forEach(function (term) {
                    $('<li>', { draggable: 'true', 'data-term-id': term.id })
                        .append($('<span>', { class: 'dashicons dashicons-move' }))
                        .append($('<strong>').text(term.name))
                        .append($('<small>').text('ID ' + term.id + ' · ' + term.count + ' items'))
                        .appendTo($list);
                });
                $('#taxocsie-order-frontend').prop('checked', !!response.data.frontend);
                $('#taxocsie-order-list, #taxocsie-order-footer').prop('hidden', false);
                $('#taxocsie-order-status').text(response.data.limited ? 'Showing the first 500 terms.' : response.data.terms.length + ' terms loaded.');
            });
    });

    $('#taxocsie-order-list').on('dragstart', 'li', function (event) {
        draggedOrderItem = this;
        $(this).addClass('is-dragging');
        event.originalEvent.dataTransfer.effectAllowed = 'move';
    }).on('dragover', 'li', function (event) {
        event.preventDefault();
        if (!draggedOrderItem || draggedOrderItem === this) { return; }
        var box = this.getBoundingClientRect();
        this.parentNode.insertBefore(draggedOrderItem, event.originalEvent.clientY < box.top + box.height / 2 ? this : this.nextSibling);
    }).on('dragend', 'li', function () {
        $(this).removeClass('is-dragging');
        draggedOrderItem = null;
    });

    $('#taxocsie-order-save').on('click', function () {
        var taxonomy = String($('#taxocsie-order-taxonomy').val() || '');
        var ids = $('#taxocsie-order-list li').map(function () { return $(this).data('term-id'); }).get();
        var $button = $(this).prop('disabled', true);
        $.post(taxocsieData.ajaxUrl, {
            action: 'taxocsie_save_term_order',
            nonce: taxocsieData.termOrderingNonce,
            taxonomy: taxonomy,
            term_ids: ids,
            frontend: $('#taxocsie-order-frontend').is(':checked') ? '1' : '0'
        }).done(function (response) {
            if (response && response.success) {
                showTaxocsieNotification('success', response.data.message);
                $('#taxocsie-order-status').text(response.data.message);
            } else {
                showTaxocsieNotification('error', getNotificationText(response && response.data));
            }
        }).always(function () { $button.prop('disabled', false); });
    });

    // ============================================================
    // CREATE TERM WORKSPACE
    // ============================================================
    $(document).on('click', '.taxocsie-taxonomy-choice', function () {
        var $choice   = $(this);
        var $workspace = $choice.closest('.taxocsie-create-workspace');

        $workspace.find('.taxocsie-taxonomy-choice')
            .removeClass('is-selected')
            .attr('aria-selected', 'false');
        $choice.addClass('is-selected').attr('aria-selected', 'true');
        $workspace.data('taxonomy', $choice.data('taxonomy'));
        $workspace.find('.taxocsie-selected-taxonomy').text($choice.data('label'));
        $workspace.find('.taxocsie-row-feedback').hide().empty();
        $workspace.find('.taxocsie-row-title').trigger('focus');
    });

    $(document).on('click', '.taxocsie-row-create-btn', function () {
        doCreateTerm($(this).closest('.taxocsie-create-workspace'));
    });

    $(document).on('keydown', '.taxocsie-row-title', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doCreateTerm($(this).closest('.taxocsie-create-workspace'));
        }
    });

    function doCreateTerm($workspace) {
        var taxonomy    = $workspace.data('taxonomy');
        var $title      = $workspace.find('.taxocsie-row-title');
        var $desc       = $workspace.find('.taxocsie-row-desc');
        var $customLink = $workspace.find('.taxocsie-row-custom-link');
        var $btn        = $workspace.find('.taxocsie-row-create-btn');
        var $feedback   = $workspace.find('.taxocsie-row-feedback');
        var name        = $.trim($title.val());
        var description = $.trim($desc.val());
        var customLink  = $.trim($customLink.val());
        var imageId     = String($workspace.find('.taxocsie-create-image-id').val() || '');

        if (!taxonomy) {
            showRowFeedback($feedback, 'Choose a taxonomy before creating a term.', 'error');
            return;
        }

        if (!name) {
            showRowFeedback($feedback, 'Term title is required.', 'error');
            $title.focus();
            return;
        }

        $btn.prop('disabled', true).addClass('is-loading');
        $feedback.hide().empty();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action:      'taxocsie_ajax_create_term',
                taxonomy:    taxonomy,
                name:        name,
                description: description,
                custom_link: customLink,
                image_id:   imageId,
                nonce:       taxocsieData.ajaxCreateTermNonce,
            },
            success: function (res) {
                if (!res.success) {
                    showRowFeedback($feedback, res.data, 'error');
                    return;
                }
                $title.val('');
                $desc.val('');
                $customLink.val('');
                var $imageField = $workspace.find('.taxocsie-term-image-field');
                $imageField.find('.taxocsie-create-image-id').val('');
                $imageField.find('.taxocsie-term-image-preview').empty().prop('hidden', true);
                $imageField.find('.taxocsie-remove-term-image').prop('hidden', true);
                var message = '&#10003; <strong>' + escHtml(res.data.name) + '</strong> created (slug: <code>' + escHtml(res.data.slug) + '</code>).';
                if (res.data.image_warning) {
                    message += ' Image was not saved: ' + escHtml(res.data.image_warning);
                }
                showRowFeedback($feedback, message, res.data.image_warning ? 'warning' : 'success');
            },
            error: function () {
                showRowFeedback($feedback, 'Request failed. Please try again.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).removeClass('is-loading');
            }
        });
    }

    function showRowFeedback($el, message, type) {
        $el.hide().empty();
        showTaxocsieNotification(type, message, type === 'success' ? 'Term created' : undefined);
    }

    // ============================================================
    // EXISTING EXPORT PREVIEW
    // ============================================================

    function premiumFormatError(response, fallback) {
        if (response && response.responseJSON && response.responseJSON.data) {
            return getNotificationText(response.responseJSON.data);
        }
        return fallback;
    }

    function getExportFilterData() {
        var filters = {};
        $('#taxocsie-export-form').serializeArray().forEach(function (field) {
            if (field.name.indexOf('taxocsie_filter_') === 0) {
                filters[field.name] = field.value;
            }
        });
        return filters;
    }

    $('#export-taxonomy').on('change', function () {
        var taxonomy = String($(this).val() || '');
        var $parent = $('#taxocsie-filter-parent');
        $parent.find('option').not('[value="any"], [value="root"]').remove();
        if (!taxonomy) {
            return;
        }

        $.post(taxocsieData.ajaxUrl, {
            action: 'taxocsie_get_parent_terms',
            nonce: taxocsieData.getTermsNonce,
            taxonomy: taxonomy
        }).done(function (response) {
            if (!response || !response.success) {
                return;
            }
            $parent.find('option[value="root"]').prop('disabled', !response.data.hierarchical);
            (response.data.terms || []).forEach(function (term) {
                $('<option>', { value: term.id }).text(term.name).appendTo($parent);
            });
        });
    });

    $('#taxocsie-export-format').on('change', function () {
        var format = $(this).val() || 'csv';
        var labels = { csv: 'Export CSV', xlsx: 'Export Excel', json: 'Export JSON' };
        $('.taxocsie-panel__btn-submit').text(labels[format] || labels.csv);
        var $imageOption = $('#taxocsie-include-images');
        $imageOption.prop('disabled', format !== 'csv' || Number($imageOption.data('available')) !== 1);
        $('#taxocsie-export-images-option').toggle(format === 'csv');
        $('#export-preview-container').hide().empty();
    });

    $('#taxocsie-export-form').on('submit', function (event) {
        var format = $('#taxocsie-export-format').val() || 'csv';
        if (format === 'csv') {
            return;
        }

        event.preventDefault();
        var taxonomy = $('#export-taxonomy').val();
        var $button = $(this).find('.taxocsie-panel__btn-submit');
        if (!taxonomy) {
            showTaxocsieNotification('error', 'Please select a taxonomy first.');
            return;
        }

        $button.prop('disabled', true).text('Preparing…');
        $.ajax({
            url: taxocsieData.ajaxUrl,
            type: 'POST',
            data: $.extend({
                action: taxocsieData.premiumExportAction,
                nonce: taxocsieData.premiumFormatsNonce,
                taxonomy: taxonomy,
                format: format
            }, getExportFilterData())
        }).done(function (response) {
            if (!response.success || !response.data.download_url) {
                showTaxocsieNotification('error', getNotificationText(response.data) || 'The export could not be prepared.');
                return;
            }
            window.location.assign(response.data.download_url);
            showTaxocsieNotification('success', 'Your download is starting.');
        }).fail(function (response) {
            showTaxocsieNotification('error', premiumFormatError(response, 'The export request failed.'));
        }).always(function () {
            var labels = { xlsx: 'Export Excel', json: 'Export JSON' };
            $button.prop('disabled', false).text(labels[format] || 'Export CSV');
        });
    });
    $('#export-preview-btn').on('click', function () {
        var taxonomy = $('#export-taxonomy').val();
        var includeImages = $('#taxocsie-include-images').is(':checked');

        if (!taxonomy) {
            showTaxocsieNotification('error', 'Please select a taxonomy first.');
            return;
        }

        var $btn = $(this);
        var $container = $('#export-preview-container');

        $btn.prop('disabled', true).text('Loading...');
        $container.hide().empty();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $.extend({
                action: 'taxocsie_preview_export',
                taxonomy: taxonomy,
                include_images: includeImages ? '1' : '0',
                nonce: taxocsieData.previewExportNonce,
            }, getExportFilterData()),
            success: function (res) {
                if (!res.success) {
                    showTaxocsieNotification('error', res.data);
                    return;
                }

                var rows = res.data;

                if (rows.length === 0) {
                    showTaxocsieNotification('info', 'No terms found for this taxonomy.', 'Nothing to export');
                    return;
                }

                var html = '<p class="taxocsie-export__preview-count">Preview — <strong>' + rows.length + '</strong> term(s) will be exported.</p>';
                html += '<div class="taxocsie-export__preview-tablewrap">';
                html += '<table class="widefat striped taxocsie-export__preview-table taxocsie-enhanced-table">';
                html += '<thead><tr><th>#</th><th>Name</th><th>Slug</th><th>Description</th><th>Parent Slug</th><th>Custom Link</th>';
                if (includeImages) {
                    html += '<th>Image Source</th>';
                }
                html += '</tr></thead>';
                html += '<tbody>';

                $.each(rows, function (i, row) {
                    html += '<tr>';
                    html += '<td>' + (i + 1) + '</td>';
                    html += '<td>' + escHtml(row.name) + '</td>';
                    html += '<td>' + escHtml(row.slug) + '</td>';
                    html += '<td>' + (row.description ? escHtml(row.description) : '<span class="taxocsie-muted">—</span>') + '</td>';
                    html += '<td>' + (row.parent_slug ? escHtml(row.parent_slug) : '<span class="taxocsie-muted">—</span>') + '</td>';
                    html += '<td>' + (row.custom_link ? escHtml(row.custom_link) : '<span class="taxocsie-muted">&mdash;</span>') + '</td>';
                    if (includeImages) {
                        html += '<td>' + (row.image_source ? escHtml(row.image_source) : '<span class="taxocsie-muted">&mdash;</span>') + '</td>';
                    }
                    html += '</tr>';
                });

                html += '</tbody></table></div>';

                $container.html(html).show();
            },
            error: function () {
                showTaxocsieNotification('error', 'Request failed. Please try again.');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Preview');
            }
        });
    });

    $('#export-taxonomy').on('change', function () {
        $('#export-preview-container').hide().empty();
    });

    $('#taxocsie-include-images').on('change', function () {
        $('#export-preview-container').hide().empty();
    });

    // ============================================================
    // DRY RUN / PREVIEW IMPORT FUNCTIONALITY
    // ============================================================
    
    var tcie = {
        isAnalyzing: false,
        isImporting: false,
        hasPreview: false
    };

    // Helper function to show notices
    function showTcieNotice(type, message) {
        showTaxocsieNotification(type, message);
    }

    // Handle form submission
    $('#taxocsie-import-form').on('submit', function(e) {
        e.preventDefault();
        
        if (tcie.isAnalyzing) return;

        var fileInput = $('#csv_file')[0];
        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            showTcieNotice('error', 'Please select a CSV file.');
            return;
        }

        var fileName = fileInput.files[0].name;
        var fileExt = fileName.split('.').pop().toLowerCase();
        if (!['csv', 'txt'].includes(fileExt)) {
            showTcieNotice('error', 'Please upload a CSV file.');
            return;
        }

        var isDryRun = $('#tcie_dry_run').is(':checked');
        var importMode = $('input[name="tcie_import_mode"]:checked').val() || 'valid_only';

        tcie.isAnalyzing = true;
        var $btn = $('#tcie-import-btn');
        $btn.prop('disabled', true);
        $btn.html('<span class="dashicons dashicons-search" style="vertical-align: middle; margin-right: 5px;"></span> Analyzing...');
        $('#tcie-spinner').addClass('is-active');
        $('#tcie-preview-container').html('');
        $('#tcie-import-actions').hide();

        var formData = new FormData();
        formData.append('action', 'taxocsie_dry_run_preview');
        formData.append('nonce', taxocsieData.dryRunNonce);
        formData.append('csv_file', fileInput.files[0]);
        formData.append('taxonomy', $('#taxocsie_type').val());
        formData.append('dry_run', isDryRun ? '1' : '0');
        formData.append('import_mode', importMode);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 300000,
            success: function(response) {
                tcie.isAnalyzing = false;
                $btn.prop('disabled', false);
                $btn.html('<span class="dashicons dashicons-search" style="vertical-align: middle; margin-right: 5px;"></span> Analyze CSV');
                $('#tcie-spinner').removeClass('is-active');

                if (response.success) {
                    $('#tcie-preview-container').html(response.data.preview_html);
                    tcie.hasPreview = true;
                    
                    var summary = response.data.summary;
                    var readyCount = summary.ready || 0;
                    var errorCount = summary.error || 0;

                    if (readyCount > 0) {
                        $('#tcie-import-actions').show();
                        $('#tcie-start-import').data('import-mode', importMode);
                    }

                    if (errorCount > 0 && readyCount > 0) {
                        showTcieNotice('warning', readyCount + ' rows ready to import. ' + errorCount + ' rows have errors.');
                    } else if (errorCount > 0 && readyCount === 0) {
                        showTcieNotice('error', 'No rows are ready to import. Please fix the errors.');
                    } else if (readyCount > 0) {
                        showTcieNotice('success', readyCount + ' rows ready to import.');
                    } else {
                        showTcieNotice('info', 'No data to import.');
                    }
                } else {
                    showTcieNotice('error', response.data.message || 'An error occurred.');
                }
            },
            error: function(xhr, status, error) {
                tcie.isAnalyzing = false;
                $btn.prop('disabled', false);
                $btn.html('<span class="dashicons dashicons-search" style="vertical-align: middle; margin-right: 5px;"></span> Analyze CSV');
                $('#tcie-spinner').removeClass('is-active');

                var errorMsg = 'An error occurred.';
                if (status === 'timeout') errorMsg = 'Request timed out. The file may be too large.';
                else if (status === 'parsererror') errorMsg = 'Invalid response from server.';
                showTcieNotice('error', errorMsg + ' Please try again.');
            }
        });
    });

    // Start import handler
    $(document).on('click', '#tcie-start-import', function() {
        if (tcie.isImporting) return;

        if (!confirm('Are you sure you want to start the import? This action cannot be undone.')) return;

        tcie.isImporting = true;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $btn.html('<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 5px;"></span> Importing...');

        var importMode = $btn.data('import-mode') || 'valid_only';

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'taxocsie_start_import',
                nonce: taxocsieData.startImportNonce,
                import_mode: importMode
            },
            success: function(response) {
                tcie.isImporting = false;
                $btn.prop('disabled', false);
                $btn.html('<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 5px;"></span> Start Import');

                if (response.success) {
                    $('#tcie-preview-container').html('');
                    $('#tcie-import-actions').hide();
                    tcie.hasPreview = false;
                    showTcieNotice('success', response.data.message);
                    setTimeout(function() {
                        if (confirm('Import completed! Reload the page to see the updated terms?')) {
                            location.reload();
                        }
                    }, 2000);
                } else {
                    showTcieNotice('error', response.data.message || 'Import failed.');
                }
            },
            error: function() {
                tcie.isImporting = false;
                $btn.prop('disabled', false);
                $btn.html('<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 5px;"></span> Start Import');
                showTcieNotice('error', 'An error occurred during import.');
            }
        });
    });

    // Cancel preview handler
    $(document).on('click', '#tcie-cancel-preview', function() {
        if (!confirm('Clear preview data? This will discard all analysis results.')) return;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'taxocsie_cancel_preview',
                nonce: taxocsieData.cancelPreviewNonce
            },
            success: function(response) {
                if (response.success) {
                    $('#tcie-preview-container').html('');
                    $('#tcie-import-actions').hide();
                    tcie.hasPreview = false;
                    showTcieNotice('info', 'Preview data cleared.');
                } else {
                    showTcieNotice('error', response.data && response.data.message ? response.data.message : 'Could not clear preview data.');
                }
            },
            error: function() {
                $('#tcie-preview-container').html('');
                $('#tcie-import-actions').hide();
                tcie.hasPreview = false;
                showTcieNotice('error', 'Could not clear preview data. Please try again.');
            }
        });
    });

    // Download error report
    $(document).on('click', '#tcie-download-errors', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $btn.text('Generating...');

        var form = $('<form>', { method: 'POST', action: ajaxurl });
        $('<input>', { type: 'hidden', name: 'action', value: 'taxocsie_download_error_report' }).appendTo(form);
        $('<input>', { type: 'hidden', name: 'nonce', value: taxocsieData.downloadErrorsNonce }).appendTo(form);
        $('body').append(form);
        form.submit();
        form.remove();

        setTimeout(function() {
            $btn.prop('disabled', false);
            $btn.text('Download Error Report');
        }, 3000);
    });

    // Reset form
    $(document).on('click', '#tcie-reset-form', function() {
        $('#csv_file').val('');
        $('#file-status').html(
            '<span class="dashicons dashicons-media-spreadsheet"></span>' +
            '<span class="taxocsie-file-empty">No file selected</span>'
        );
        $('#tcie-preview-container').html('');
        $('#tcie-import-actions').hide();
        tcie.hasPreview = false;
        $('#tcie-spinner').removeClass('is-active');
        $('.tcie-notice').remove();
        showTcieNotice('info', 'Form has been reset.');
    });

    // ============================================================
    // FILE UPLOAD HANDLING
    // ============================================================
    
    var $uploadSection = $('#taxocsie-upload-section');
    var $fileInput = $('#csv_file');
    var $fileStatus = $('#file-status');
	var $columnMapper = $('#taxocsie-column-mapper');
	var $columnMappingRows = $('#taxocsie-column-mapping-rows');
	var $columnMappingStatus = $('#taxocsie-column-mapper-status');
	var columnMappingValid = false;
    var currentImportStep = 1;
	var columnMappingFields = [
		[ 'ignore', taxocsieData.ignoreColumn ],
		[ 'name', taxocsieData.fieldName ],
		[ 'slug', taxocsieData.fieldSlug ],
		[ 'description', taxocsieData.fieldDescription ],
		[ 'parent_slug', taxocsieData.fieldParent ],
		[ 'custom_link', taxocsieData.fieldLink ],
		[ 'image_source', taxocsieData.fieldImage ]
	];
	var columnHeaderAliases = {
		name: 'name', term_name: 'name', title: 'name',
		slug: 'slug', term_slug: 'slug',
		description: 'description', desc: 'description',
		parent: 'parent_slug', parent_slug: 'parent_slug',
		custom_link: 'custom_link', custom_url: 'custom_link', term_link: 'custom_link',
		image: 'image_source', image_url: 'image_source', image_source: 'image_source'
	};

	function normalizeImportHeader(header) {
		return String(header).replace(/^\uFEFF/, '').trim().toLowerCase().replace(/[ -]/g, '_');
	}

	function parseImportCsvHeader(text) {
		var columns = [];
		var value = '';
		var quoted = false;

		for (var index = 0; index < text.length; index++) {
			var character = text.charAt(index);
			if ('"' === character) {
				if (quoted && '"' === text.charAt(index + 1)) {
					value += '"';
					index++;
				} else {
					quoted = !quoted;
				}
			} else if (',' === character && !quoted) {
				columns.push(value);
				value = '';
			} else if (('\n' === character || '\r' === character) && !quoted) {
				break;
			} else {
				value += character;
			}
		}

		columns.push(value);
		return columns;
	}

	function updateColumnMappingState() {
		var selected = {};
		var duplicate = false;
		var hasName = false;

		$columnMappingRows.find('select').each(function() {
			if ('ignore' === this.value) return;
			if (selected[this.value]) duplicate = true;
			selected[this.value] = true;
			hasName = hasName || 'name' === this.value;
		});

		columnMappingValid = hasName && !duplicate;
		$columnMappingStatus
			.toggleClass('is-ready', columnMappingValid)
			.toggleClass('is-error', !columnMappingValid)
			.text(duplicate ? taxocsieData.mappingDuplicate : (hasName ? taxocsieData.mappingReady : taxocsieData.mappingNameRequired));
	}

	function renderColumnMapper(headers) {
		var used = {};
		$columnMappingRows.empty();

		headers.forEach(function(header, index) {
			var suggested = columnHeaderAliases[normalizeImportHeader(header)] || 'ignore';
			if ('ignore' !== suggested && used[suggested]) suggested = 'ignore';
			if ('ignore' !== suggested) used[suggested] = true;

			var $row = $('<tr>');
			var sourceLabel = header || ('#' + (index + 1));
			var $select = $('<select>')
				.attr('name', 'column_mapping[' + index + ']')
				.attr('aria-label', sourceLabel);

			columnMappingFields.forEach(function(field) {
				$('<option>').val(field[0]).text(field[1]).prop('selected', field[0] === suggested).appendTo($select);
			});

			$row.append($('<th scope="row">').text(sourceLabel));
			$row.append($('<td>').append($select));
			$columnMappingRows.append($row);
		});

		$columnMapper.removeAttr('hidden');
		updateColumnMappingState();
	}

	function resetColumnMapper() {
		columnMappingValid = false;
		$columnMapper.attr('hidden', true);
		$columnMappingRows.empty();
	}

	function readColumnMapper(file) {
		resetColumnMapper();
		if (!file || ($('#taxocsie-import-format').val() || 'csv') !== 'csv') return;

		var reader = new FileReader();
		reader.onload = function() {
			var headers = parseImportCsvHeader(String(reader.result || ''));
			if (!headers.length || (headers.length === 1 && !headers[0].trim())) {
				showTcieNotice('error', taxocsieData.headerReadFailed);
				return;
			}
			renderColumnMapper(headers);
		};
		reader.onerror = function() {
			showTcieNotice('error', taxocsieData.headerReadFailed);
		};
		reader.readAsText(file.slice(0, 131072));
	}

    function updatePremiumImportFormat() {
        var format = $('#taxocsie-import-format').val() || 'csv';
        var details = {
            csv:  { name: 'CSV', accept: '.csv,.txt,text/csv,text/plain', formats: 'Supported formats: .csv and .txt' },
            xlsx: { name: 'Excel', accept: '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', formats: 'Supported format: .xlsx' },
            json: { name: 'JSON', accept: '.json,application/json', formats: 'Supported format: .json' }
        };
        var selected = details[format] || details.csv;

        $fileInput.attr('accept', selected.accept).val('');
		resetColumnMapper();
        $('#taxocsie-import-upload-title').text('Upload your ' + selected.name + ' file');
        $('#taxocsie-upload-heading').text('Drag and drop your ' + selected.name + ' file here');
        $('#taxocsie-upload-formats').text(selected.formats);
        $('#taxocsie-file-button-label').text('Choose a ' + selected.name + ' file');
        $('#taxocsie-review-file-label').text(selected.name + ' file');
		$('.taxocsie-csv-only-note').toggle(format === 'csv');
        $('.taxocsie-import-wizard__submit').html(
            '<span class="dashicons dashicons-upload" aria-hidden="true"></span> Import ' + escHtml(selected.name)
        );
        resetFileStatus();
    }

    $('#taxocsie-import-format').on('change', updatePremiumImportFormat);

    function updateFileStatus(file) {
        var fileName = file.name;
        var fileSize = (file.size / 1024 / 1024).toFixed(2);
        var icon = fileSize > 5 ? 'dashicons-warning' : 'dashicons-yes-alt';
        var color = fileSize > 5 ? '#f56e28' : '#46b450';
        
        $fileStatus.html(
            '<span class="dashicons ' + icon + '" style="color: ' + color + ';"></span>' +
            '<span class="taxocsie-file-name">' + escHtml(fileName) + '</span>' +
            '<span class="taxocsie-file-size">(' + fileSize + ' MB)</span>'
        );

        updateImportWizard();
		readColumnMapper(file);
        
        if (fileSize > 5) {
            $('#tcie_dry_run').prop('checked', true).trigger('change');
            showTcieNotice('info', 'Large file detected. Dry run mode is recommended.');
        }
    }

    function resetFileStatus() {
        $fileStatus.html(
            '<span class="dashicons dashicons-media-spreadsheet"></span>' +
            '<span class="taxocsie-file-empty">No file selected</span>'
        );
		resetColumnMapper();

        updateImportWizard();
    }

    function updateImportWizard() {
        var $wizard = $('#taxocsie-import-wizard-form');
        if (!$wizard.length) return;

        var hasTaxonomy = Boolean($('#taxonomy').val());
        var input = $fileInput[0];
        var hasFile = Boolean(input && input.files && input.files.length);

        if (!hasTaxonomy && currentImportStep > 1) currentImportStep = 1;
        if (!hasFile && currentImportStep > 2) currentImportStep = 2;

        renderImportStep();
        updateImportReview();
    }

    function renderImportStep() {
        var $steps = $('.taxocsie-import-step');

        $('.taxocsie-import-stage').attr('hidden', true).removeClass('is-active');
        $('[data-import-stage="' + currentImportStep + '"]').removeAttr('hidden').addClass('is-active');

        $steps.removeClass('is-active is-complete').removeAttr('aria-current');
        $steps.each(function() {
            var step = Number($(this).data('import-step'));
            if (step < currentImportStep) $(this).addClass('is-complete');
            if (step === currentImportStep) $(this).addClass('is-active').attr('aria-current', 'step');
        });
    }

    function updateImportReview() {
        var $selected = $('#taxonomy option:selected');
        var input = $fileInput[0];
        var file = input && input.files ? input.files[0] : null;

        $('#taxocsie-review-taxonomy').text($('#taxonomy').val() ? $selected.text() : '—');
        $('#taxocsie-review-file').text(file ? file.name : '—');
    }

    function goToImportStep(targetStep) {
        var hasTaxonomy = Boolean($('#taxonomy').val());
        var input = $fileInput[0];
        var file = input && input.files ? input.files[0] : null;

        if (targetStep > 1 && !hasTaxonomy) {
            showTcieNotice('error', 'Select a destination taxonomy to continue.');
            currentImportStep = 1;
            renderImportStep();
            $('#taxonomy').trigger('focus');
            return;
        }

        if (targetStep > 2 && !file) {
            showTcieNotice('error', 'Choose an import file to continue.');
            currentImportStep = 2;
            renderImportStep();
            return;
        }

		if (targetStep > 2 && ($('#taxocsie-import-format').val() || 'csv') === 'csv' && !columnMappingValid) {
			showTcieNotice('error', taxocsieData.mappingNameRequired);
			currentImportStep = 2;
			renderImportStep();
			return;
		}

        currentImportStep = Math.max(1, Math.min(3, targetStep));
        renderImportStep();
        updateImportReview();
    }

    // Drag and drop events
    $uploadSection.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    $uploadSection.on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });

    $uploadSection.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $fileInput[0].files = files;
            updateFileStatus(files[0]);
        }
    });

    $fileInput.on('change', function() {
        if (this.files && this.files[0]) {
            updateFileStatus(this.files[0]);
        } else {
            resetFileStatus();
        }
    });

	$columnMappingRows.on('change', 'select', updateColumnMappingState);

    $('#taxonomy').on('change', updateImportWizard);

    $(document).on('click', '.taxocsie-import-step', function() {
        goToImportStep(Number($(this).data('import-step')));
    });

    $(document).on('click', '.taxocsie-wizard-next', function() {
        goToImportStep(Number($(this).data('next-step')));
    });

    $(document).on('click', '.taxocsie-wizard-back', function() {
        goToImportStep(Number($(this).data('previous-step')));
    });

    $uploadSection.on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $fileInput.trigger('click');
        }
    });

    $('#taxocsie-import-wizard-form').on('submit', function(event) {
        var $form = $(this);
        var $button = $form.find('.taxocsie-import-wizard__submit');
        var format = $('#taxocsie-import-format').val() || 'csv';

		if (format === 'csv' && !columnMappingValid) {
			event.preventDefault();
			showTcieNotice('error', taxocsieData.mappingNameRequired);
			goToImportStep(2);
			return;
		}

        $form.addClass('is-submitting');
        $('[data-import-step="3"]').addClass('is-active');
        $button.prop('disabled', true).html(
            '<span class="dashicons dashicons-update taxocsie-spin" aria-hidden="true"></span> ' +
            escHtml(taxocsieData.importing || 'Importing...')
        );

        if (format === 'csv') {
            return;
        }

        event.preventDefault();
        var input = $fileInput[0];
        if (!input || !input.files || !input.files[0]) {
            $form.removeClass('is-submitting');
            $button.prop('disabled', false).text('Import');
            showTcieNotice('error', 'Choose an import file first.');
            return;
        }

        var formData = new FormData();
        formData.append('action', taxocsieData.premiumImportAction);
        formData.append('nonce', taxocsieData.premiumFormatsNonce);
        formData.append('taxonomy', $('#taxonomy').val());
        formData.append('format', format);
        formData.append('csv_file', input.files[0]);

        $.ajax({
            url: taxocsieData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 300000
        }).done(function (response) {
            if (!response.success) {
                showTcieNotice('error', getNotificationText(response.data) || 'The import failed.');
                return;
            }
            showTcieNotice('success', response.data.message || 'Import completed.');
            $fileInput.val('');
            resetFileStatus();
        }).fail(function (response) {
            showTcieNotice('error', premiumFormatError(response, 'The import request failed.'));
        }).always(function () {
            var names = { xlsx: 'Excel', json: 'JSON' };
            $form.removeClass('is-submitting');
            $button.prop('disabled', false).html(
                '<span class="dashicons dashicons-upload" aria-hidden="true"></span> Import ' + escHtml(names[format] || 'file')
            );
        });
    });

    updatePremiumImportFormat();
    updateImportWizard();

    // ============================================================
    // DEMO GENERATOR FUNCTIONALITY
    // ============================================================

	function updateDemoStructureAvailability() {
		var $selected = $('#demo_taxonomy option:selected');
		var supportsHierarchy = $selected.data('hierarchical') === 1 || $selected.data('hierarchical') === '1';
		var $hierarchical = $('#taxocsie-demo-hierarchical, input[name="demo_structure"][value="hierarchical"]');

		$hierarchical.prop('disabled', !supportsHierarchy);
		if (!supportsHierarchy && $hierarchical.is(':checked')) {
			$('input[name="demo_structure"][value="flat"]').prop('checked', true).trigger('change');
		}
	}

	$('#demo_taxonomy').on('change', updateDemoStructureAvailability);
	updateDemoStructureAvailability();

    $(document).on('click', '.taxocsie-quick-btn', function() {
        var $btn = $(this);
        var taxonomy = $btn.data('taxonomy');
        var count = $btn.data('count');
        
        if (!taxonomy || !count) return;

        if (!confirm('Generate ' + count + ' terms in "' + taxonomy + '"? This will add new terms.')) return;

        $btn.prop('disabled', true);
        $btn.text('Generating...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'taxocsie_ajax_demo_generate',
                nonce: taxocsieData.demoAjaxNonce,
                taxonomy: taxonomy,
                count: count,
                structure: 'flat',
                name_format: 'prefix',
                add_description: 'true',
                skip_existing: 'true'
            },
            success: function(response) {
                $btn.prop('disabled', false);
                $btn.text('+' + count);
                if (response.success) {
                    showTcieNotice('success', response.data.message);
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    showTcieNotice('error', response.data.message || 'Generation failed.');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                $btn.text('+' + count);
                showTcieNotice('error', 'An error occurred. Please try again.');
            }
        });
    });

	var demoBulkBusy = false;
	var demoBulkMaxTaxonomies = 10;

	function updateDemoSelection() {
		var $boxes = $('.taxocsie-panel--demo-stats .taxocsie-demo-select-taxonomy');
		var $visible = $boxes.filter(function () { return $(this).closest('tr').is(':visible'); });
		var selected = $boxes.filter(':checked').length;
		var selectedOnPage = $visible.filter(':checked').length;
		$('.taxocsie-panel--demo-stats .taxocsie-demo-select-page')
			.prop('checked', $visible.length > 0 && selectedOnPage === $visible.length)
			.prop('indeterminate', selectedOnPage > 0 && selectedOnPage < $visible.length)
			.prop('disabled', demoBulkBusy || $visible.length === 0);
		$('.taxocsie-panel--demo-stats .taxocsie-demo-bulk-generate')
			.prop('disabled', demoBulkBusy || selected === 0)
			.find('.taxocsie-demo-bulk-generate__count').text(selected);
	}

	$(document).on('taxocsie-demo-page-changed', '.taxocsie-panel--demo-stats .taxocsie-panel__table', updateDemoSelection);
	$(document).on('change', '.taxocsie-panel--demo-stats .taxocsie-demo-select-taxonomy', function () {
		if ($('.taxocsie-panel--demo-stats .taxocsie-demo-select-taxonomy:checked').length > demoBulkMaxTaxonomies) {
			this.checked = false;
			showTcieNotice('warning', taxocsieData.demoBulkLimit);
		}
		updateDemoSelection();
	});
	$(document).on('change', '.taxocsie-panel--demo-stats .taxocsie-demo-select-page', function () {
		var $visible = $('.taxocsie-panel--demo-stats .taxocsie-demo-select-taxonomy').filter(function () { return $(this).closest('tr').is(':visible'); });
		if (this.checked) {
			var selectedElsewhere = $('.taxocsie-panel--demo-stats .taxocsie-demo-select-taxonomy:checked').not($visible).length;
			var available = Math.max(0, demoBulkMaxTaxonomies - selectedElsewhere);
			$visible.prop('checked', false).slice(0, available).prop('checked', true);
			if ($visible.length > available) {
				showTcieNotice('warning', taxocsieData.demoBulkLimit);
			}
		} else {
			$visible.prop('checked', false);
		}
		updateDemoSelection();
	});
	$(document).on('click', '.taxocsie-demo-bulk-generate', function () {
		if (demoBulkBusy) return;
		var taxonomies = $('.taxocsie-panel--demo-stats .taxocsie-demo-select-taxonomy:checked').map(function () { return this.value; }).get();
		if (!taxonomies.length || !window.confirm(taxocsieData.demoBulkConfirm.replace('%d', taxonomies.length))) return;
		demoBulkBusy = true;
		updateDemoSelection();
		var completed = 0;
		var failed = 0;
		var created = 0;
		function generateNext(index) {
			if (index >= taxonomies.length) {
				demoBulkBusy = false;
				updateDemoSelection();
				var message = failed
					? taxocsieData.demoBulkPartial.replace('%1$d', completed).replace('%2$d', failed)
					: taxocsieData.demoBulkSuccess.replace('%1$d', created).replace('%2$d', completed);
				showTcieNotice(failed ? 'warning' : 'success', message);
				if (created) window.setTimeout(function () { location.reload(); }, 2500);
				return;
			}
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'taxocsie_ajax_demo_generate',
					nonce: taxocsieData.demoAjaxNonce,
					taxonomy: taxonomies[index],
					count: 10,
					structure: 'flat',
					name_format: 'prefix',
					add_description: 'true',
					skip_existing: 'true'
				}
			}).done(function (response) {
				if (response && response.success) {
					var results = response.data && response.data.results;
					created += parseInt(results && results.created, 10) || 0;
					if ((parseInt(results && results.errors, 10) || 0) > 0) {
						failed++;
					} else {
						completed++;
					}
				} else {
					failed++;
				}
			}).fail(function () {
				failed++;
			}).always(function () {
				generateNext(index + 1);
			});
		}
		generateNext(0);
	});

    $('#taxocsie-demo-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#taxocsie-demo-btn');
        var taxonomy = $('#demo_taxonomy').val();
        var count = $('#demo_count').val();

        if (!taxonomy) {
            showTcieNotice('error', 'Please select a taxonomy.');
            return;
        }
        if (count < 1 || count > 500) {
            showTcieNotice('error', 'Please enter a number between 1 and 500.');
            return;
        }

        $btn.prop('disabled', true);
        $btn.html('<span class="spinner is-active taxocsie-demo-generate-spinner"></span><span>Generating...</span>');

        var formData = new FormData(this);
        formData.append('action', 'taxocsie_ajax_demo_generate');
        formData.append('nonce', taxocsieData.demoAjaxNonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $btn.prop('disabled', false);
				$btn.html('<span class="dashicons dashicons-controls-play taxocsie-demo-generate-icon" aria-hidden="true"></span> Generate Demo Terms');
                if (response.success) {
                    showTcieNotice('success', response.data.message);
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    showTcieNotice('error', response.data.message || 'Generation failed.');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
				$btn.html('<span class="dashicons dashicons-controls-play taxocsie-demo-generate-icon" aria-hidden="true"></span> Generate Demo Terms');
                showTcieNotice('error', 'An error occurred. Please try again.');
            }
        });
    });

    // Radio card selection
    $('.taxocsie-mode-card input[type="radio"]').on('change', function() {
        $('.taxocsie-mode-card').removeClass('active');
        $(this).closest('.taxocsie-mode-card').addClass('active');
    });

    // ============================================================
    // EXISTING REGISTER TAXONOMY FUNCTIONALITY
    // ============================================================

    var editingTaxonomySlug = '';

    function resetRegisterTaxonomyForm() {
        editingTaxonomySlug = '';
        $('#reg-label, #reg-singular, #reg-slug, #reg-description').val('');
        $('.reg-post-type').prop('checked', false);
        $('input[name="reg-type"][value="0"]').prop('checked', true).trigger('change');
        $('#taxocsie-reg-feedback').hide().empty();
        $('#taxocsie-register-form-title').text('New taxonomy');
        $('#taxocsie-register-form-description').text('Define its labels, structure, and where it should appear.');
        $('#taxocsie-reg-submit-label').text('Register Taxonomy');
        $('#taxocsie-register-card .taxocsie-register-card__icon')
            .removeClass('dashicons-edit')
            .addClass('dashicons-plus-alt2');
    }

    function getEmptyTaxonomiesRow() {
        return '<tr class="taxocsie-empty-taxonomies"><td colspan="6">' +
            '<div class="taxocsie-empty-taxonomies__content">' +
                '<span class="taxocsie-empty-taxonomies__icon dashicons dashicons-category" aria-hidden="true"></span>' +
                '<div><strong>No custom taxonomies yet</strong>' +
                '<span>Create your first taxonomy to organize content with custom tags or categories.</span></div>' +
            '</div>' +
        '</td></tr>';
    }

    function getTaxonomyActions(data) {
        return '<div class="taxocsie-row-actions">' +
            '<button type="button" class="taxocsie-row-actions__toggle" aria-haspopup="menu" aria-expanded="false">' +
                '<span class="taxocsie-row-actions__dots" aria-hidden="true"></span>' +
                '<span class="screen-reader-text">Open actions for ' + escHtml(data.label) + '</span>' +
            '</button>' +
            '<div class="taxocsie-row-actions__menu" role="menu" hidden>' +
                '<button type="button" class="taxocsie-edit-taxonomy" role="menuitem"' +
                    ' data-slug="' + escAttr(data.slug) + '"' +
                    ' data-label="' + escAttr(data.label) + '"' +
                    ' data-singular="' + escAttr(data.singular_label || data.label) + '"' +
                    ' data-description="' + escAttr(data.description || '') + '"' +
                    ' data-hierarchical="' + (data.hierarchical ? '1' : '0') + '"' +
                    ' data-post-types="' + escAttr(JSON.stringify(data.post_types)) + '">' +
                    '<span class="dashicons dashicons-edit" aria-hidden="true"></span>Edit' +
                '</button>' +
                '<button type="button" class="taxocsie-delete-taxonomy" role="menuitem" data-slug="' + escAttr(data.slug) + '">' +
                    '<span class="dashicons dashicons-trash" aria-hidden="true"></span>Delete' +
                '</button>' +
            '</div>' +
        '</div>';
    }

    function getTaxonomyRow(data) {
        var typeLabel = data.hierarchical ? 'Categories' : 'Tags';
        return '<tr data-slug="' + escAttr(data.slug) + '">' +
            '<td class="taxocsie-select-column"><input type="checkbox" class="taxocsie-select-taxonomy" value="' + escAttr(data.slug) + '" aria-label="Select ' + escAttr(data.label) + '"></td>' +
            '<td><strong class="taxocsie-taxonomy-label">' + escHtml(data.label) + '</strong></td>' +
            '<td><code>' + escHtml(data.slug) + '</code></td>' +
            '<td><span class="taxocsie-taxonomy-type-badge">' + escHtml(typeLabel) + '</span></td>' +
            '<td><span class="taxocsie-post-types-value">' + escHtml(data.post_types.join(', ')) + '</span></td>' +
            '<td>' + getTaxonomyActions(data) + '</td>' +
        '</tr>';
    }

    var taxonomyCurrentPage = 1;
    var taxonomySortColumn = null;
    var taxonomySortDirection = 'asc';
    var bulkDeleteInProgress = false;

    function updateTaxonomySelection() {
        var $checkboxes = $('#taxocsie-reg-table .taxocsie-select-taxonomy');
        var total = $checkboxes.length;
        var selected = $checkboxes.filter(':checked').length;
        $('#taxocsie-select-all')
            .prop('checked', total > 0 && selected === total)
            .prop('indeterminate', selected > 0 && selected < total)
            .prop('disabled', bulkDeleteInProgress || total === 0);
        $checkboxes.prop('disabled', bulkDeleteInProgress);
        $('#taxocsie-selected-count').text(selected);
        $('#taxocsie-delete-selected').prop('disabled', bulkDeleteInProgress || selected === 0);
    }

    function renderTaxonomyTable() {
        var $tbody = $('#taxocsie-reg-table tbody');
        var $rows = $tbody.find('tr[data-slug]');
        var total = $rows.length;
        var perPage = parseInt($('#taxocsie-per-page').val(), 10) || 10;

        if (taxonomySortColumn !== null && total > 1) {
            var sortedRows = $rows.get().sort(function (rowA, rowB) {
                var valueA = $.trim($(rowA).children('td').eq(taxonomySortColumn + 1).text()).toLowerCase();
                var valueB = $.trim($(rowB).children('td').eq(taxonomySortColumn + 1).text()).toLowerCase();
                var comparison = valueA.localeCompare(valueB, undefined, { numeric: true, sensitivity: 'base' });
                return taxonomySortDirection === 'asc' ? comparison : -comparison;
            });
            $tbody.append(sortedRows);
            $rows = $tbody.find('tr[data-slug]');
        }

        var totalPages = Math.max(1, Math.ceil(total / perPage));
        taxonomyCurrentPage = Math.min(Math.max(1, taxonomyCurrentPage), totalPages);
        var start = (taxonomyCurrentPage - 1) * perPage;
        var end = start + perPage;

        $rows.hide().slice(start, end).show();
        $('#taxocsie-table-total').text(total);
        $('#taxocsie-current-page').text(taxonomyCurrentPage);
        $('#taxocsie-total-pages').text(totalPages);
        $('#taxocsie-page-prev').prop('disabled', taxonomyCurrentPage <= 1);
        $('#taxocsie-page-next').prop('disabled', taxonomyCurrentPage >= totalPages);
        updateTaxonomySelection();
    }

    $('#taxocsie-select-all').on('change', function () {
        $('#taxocsie-reg-table .taxocsie-select-taxonomy').prop('checked', this.checked);
        updateTaxonomySelection();
    });

    $(document).on('change', '#taxocsie-reg-table .taxocsie-select-taxonomy', updateTaxonomySelection);

    $('#taxocsie-delete-selected').on('click', function () {
        var slugs = $('#taxocsie-reg-table .taxocsie-select-taxonomy:checked').map(function () {
            return this.value;
        }).get();
        if (!slugs.length || bulkDeleteInProgress) {
            return;
        }
        if (!window.confirm(taxocsieData.bulkDeleteConfirm.replace('%d', slugs.length))) {
            return;
        }

        closeTaxonomyActionsMenu(false);
        bulkDeleteInProgress = true;
        updateTaxonomySelection();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'taxocsie_delete_taxonomies',
                slugs: slugs,
                nonce: taxocsieData.deleteTaxonomiesNonce
            },
            success: function (res) {
                if (!res.success) {
                    showTaxocsieNotification('error', res.data || 'Could not delete the selected taxonomies.');
                    return;
                }
                var deletedSlugs = res.data.slugs || [];
                $('#taxocsie-reg-table tbody tr[data-slug]').filter(function () {
                    return deletedSlugs.indexOf($(this).attr('data-slug')) !== -1;
                }).remove();
                if (!$('#taxocsie-reg-table tbody tr[data-slug]').length) {
                    $('#taxocsie-reg-table tbody').html(getEmptyTaxonomiesRow());
                }
                renderTaxonomyTable();
                showTaxocsieNotification('success', taxocsieData.bulkDeleted.replace('%d', deletedSlugs.length), 'Taxonomies deleted');
            },
            error: function () {
                showTaxocsieNotification('error', 'Request failed. Please try again.');
            },
            complete: function () {
                bulkDeleteInProgress = false;
                updateTaxonomySelection();
            }
        });
    });

    $(document).on('click', '.taxocsie-sortable-column button', function () {
        var $heading = $(this).closest('th');
        var column = parseInt($heading.attr('data-sort-column'), 10);

        if (taxonomySortColumn === column) {
            taxonomySortDirection = taxonomySortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            taxonomySortColumn = column;
            taxonomySortDirection = 'asc';
        }

        taxonomyCurrentPage = 1;
        $('.taxocsie-sortable-column').attr('aria-sort', 'none')
            .find('.dashicons').removeClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2').addClass('dashicons-sort');
        $heading.attr('aria-sort', taxonomySortDirection === 'asc' ? 'ascending' : 'descending');
        $heading.find('.dashicons')
            .removeClass('dashicons-sort')
            .addClass(taxonomySortDirection === 'asc' ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2');
        closeTaxonomyActionsMenu(false);
        renderTaxonomyTable();
    });

    $('#taxocsie-per-page').on('change', function () {
        taxonomyCurrentPage = 1;
        closeTaxonomyActionsMenu(false);
        renderTaxonomyTable();
    });

    $('#taxocsie-page-prev').on('click', function () {
        if (taxonomyCurrentPage > 1) {
            taxonomyCurrentPage--;
            closeTaxonomyActionsMenu(false);
            renderTaxonomyTable();
        }
    });

    $('#taxocsie-page-next').on('click', function () {
        var total = $('#taxocsie-reg-table tbody tr[data-slug]').length;
        var perPage = parseInt($('#taxocsie-per-page').val(), 10) || 10;
        if (taxonomyCurrentPage < Math.max(1, Math.ceil(total / perPage))) {
            taxonomyCurrentPage++;
            closeTaxonomyActionsMenu(false);
            renderTaxonomyTable();
        }
    });

    function showRegisterSuccessNotification(isUpdate) {
        showTaxocsieNotification(
            'success',
            'Refresh the page to start using it.',
            isUpdate ? 'Taxonomy updated' : 'Taxonomy registered'
        );
    }

    function showRegisterTaxonomyView() {
        $('#taxocsie-reg-panel').prop('hidden', true);
        $('#taxocsie-register-card').prop('hidden', false);
        $('#taxocsie-new-taxonomy').attr('aria-expanded', 'true');
        window.requestAnimationFrame(function () {
            $('#reg-label').trigger('focus');
        });
    }

    function showRegisteredTaxonomiesView(restoreFocus) {
        $('#taxocsie-register-card').prop('hidden', true);
        $('#taxocsie-reg-panel').prop('hidden', false);
        $('#taxocsie-new-taxonomy').attr('aria-expanded', 'false');
        if (restoreFocus) {
            window.requestAnimationFrame(function () {
                $('#taxocsie-new-taxonomy').trigger('focus');
            });
        }
    }

    $('#taxocsie-new-taxonomy').on('click', function () {
        resetRegisterTaxonomyForm();
        showRegisterTaxonomyView();
    });

    function closeTaxonomyActionsMenu(restoreFocus) {
        var $menu = $('.taxocsie-row-actions__menu:not([hidden])');
        var $toggle = $menu.siblings('.taxocsie-row-actions__toggle');
        $menu.prop('hidden', true).removeAttr('style');
        $toggle.attr('aria-expanded', 'false');
        if (restoreFocus && $toggle.length) {
            $toggle.trigger('focus');
        }
    }

    $(document).on('click', '.taxocsie-row-actions__toggle', function (event) {
        event.stopPropagation();
        var $toggle = $(this);
        var $menu = $toggle.siblings('.taxocsie-row-actions__menu');
        var wasOpen = !$menu.prop('hidden');

        closeTaxonomyActionsMenu(false);
        if (wasOpen) {
            return;
        }

        var rect = this.getBoundingClientRect();
        $menu.prop('hidden', false).css({ top: 0, left: 0, visibility: 'hidden' });
        var menuWidth = $menu.outerWidth();
        var menuHeight = $menu.outerHeight();
        var top = rect.bottom + 6;
        var left = Math.max(12, rect.right - menuWidth);

        if (top + menuHeight > window.innerHeight - 12) {
            top = rect.top - menuHeight - 6;
        }

        $menu.css({ top: top, left: left, visibility: 'visible' });
        $toggle.attr('aria-expanded', 'true');
        if (event.originalEvent && event.originalEvent.detail === 0) {
            $menu.find('[role="menuitem"]').first().trigger('focus');
        }
    });

    $(document).on('click', function (event) {
        if (!$(event.target).closest('.taxocsie-row-actions').length) {
            closeTaxonomyActionsMenu(false);
        }
    });

    $(window).on('resize scroll', function () {
        closeTaxonomyActionsMenu(false);
    });

    $(document).on('click', '.taxocsie-edit-taxonomy', function () {
        var $button = $(this);
        var postTypes = [];
        try {
            postTypes = JSON.parse($button.attr('data-post-types') || '[]');
        } catch (error) {
            postTypes = [];
        }

        editingTaxonomySlug = $button.attr('data-slug') || '';
        $('#reg-label').val($button.attr('data-label') || '');
        $('#reg-singular').val($button.attr('data-singular') || '');
        $('#reg-slug').val(editingTaxonomySlug);
        $('#reg-description').val($button.attr('data-description') || '');
        $('input[name="reg-type"][value="' + ($button.attr('data-hierarchical') === '1' ? '1' : '0') + '"]')
            .prop('checked', true)
            .trigger('change');
        $('.reg-post-type').prop('checked', false).each(function () {
            $(this).prop('checked', postTypes.indexOf($(this).val()) !== -1);
        });
        $('#taxocsie-register-form-title').text('Edit taxonomy');
        $('#taxocsie-register-form-description').text('Update its labels, structure, and post type connections.');
        $('#taxocsie-reg-submit-label').text('Save Changes');
        $('#taxocsie-register-card .taxocsie-register-card__icon')
            .removeClass('dashicons-plus-alt2')
            .addClass('dashicons-edit');
        closeTaxonomyActionsMenu(false);
        showRegisterTaxonomyView();
    });

    $('#taxocsie-reg-cancel').on('click', function () {
        resetRegisterTaxonomyForm();
        showRegisteredTaxonomiesView(true);
    });

    $(document).on('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        if ($('.taxocsie-row-actions__menu:not([hidden])').length) {
            event.preventDefault();
            closeTaxonomyActionsMenu(true);
        } else if (!$('#taxocsie-register-card').prop('hidden')) {
            $('#taxocsie-reg-cancel').trigger('click');
        }
    });

    $('#reg-label').on('input', function () {
        if (editingTaxonomySlug) {
            return;
        }
        var slug = $(this).val()
            .toLowerCase()
            .replace(/[^a-z0-9\s_-]/g, '')
            .trim()
            .replace(/[\s-]+/g, '_')
            .substring(0, 32);
        $('#reg-slug').val(slug);
    });

    $(document).on('change', 'input[name="reg-type"]', function () {
        $('.taxocsie-type-option').removeClass('taxocsie-type-option--active');
        $(this).closest('.taxocsie-type-option').addClass('taxocsie-type-option--active');
    });

    $('#taxocsie-reg-submit').on('click', function () {
        var label       = $.trim($('#reg-label').val());
        var singular    = $.trim($('#reg-singular').val());
        var slug        = $.trim($('#reg-slug').val());
        var description = $.trim($('#reg-description').val());
        var hierarchical = $('input[name="reg-type"]:checked').val();
        var postTypes   = [];
        var originalSlug = editingTaxonomySlug;
        $('.reg-post-type:checked').each(function () { postTypes.push($(this).val()); });

        var $btn      = $(this);
        var $feedback = $('#taxocsie-reg-feedback');

        if (!label) { showRegFeedback($feedback, 'Label is required.', 'error'); return; }
        if (!slug)  { showRegFeedback($feedback, 'Slug is required.', 'error'); return; }
        if (!postTypes.length) { showRegFeedback($feedback, 'Please select at least one post type.', 'error'); return; }

        $btn.prop('disabled', true);
        $feedback.hide().empty();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action:         'taxocsie_save_taxonomy',
                label:          label,
                singular_label: singular,
                slug:           slug,
                description:    description,
                hierarchical:   hierarchical,
                post_types:     postTypes,
                original_slug:  originalSlug,
                nonce:          taxocsieData.saveTaxonomyNonce,
            },
            success: function (res) {
                if (!res.success) {
                    showRegFeedback($feedback, res.data, 'error');
                    return;
                }
                var d = res.data;
                var row = getTaxonomyRow(d);
                $('#taxocsie-reg-table .taxocsie-empty-taxonomies').remove();
                if (d.updated) {
                    var wasSelected = $('#taxocsie-reg-table tbody tr[data-slug="' + d.original_slug + '"] .taxocsie-select-taxonomy').prop('checked');
                    $('#taxocsie-reg-table tbody tr[data-slug="' + d.original_slug + '"]').replaceWith(row);
                    $('#taxocsie-reg-table tbody tr[data-slug="' + d.slug + '"] .taxocsie-select-taxonomy').prop('checked', wasSelected);
                } else {
                    $('#taxocsie-reg-table tbody').append(row);
                    taxonomyCurrentPage = Math.ceil(
                        $('#taxocsie-reg-table tbody tr[data-slug]').length /
                        (parseInt($('#taxocsie-per-page').val(), 10) || 10)
                    );
                }
                renderTaxonomyTable();
                resetRegisterTaxonomyForm();
                showRegisteredTaxonomiesView(true);
                showRegisterSuccessNotification(d.updated);
            },
            error: function () {
                showRegFeedback($feedback, 'Request failed. Please try again.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.taxocsie-delete-taxonomy', function () {
        var slug = $(this).data('slug');
        closeTaxonomyActionsMenu(false);
        if (!window.confirm('Delete taxonomy "' + slug + '"? This cannot be undone.')) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'taxocsie_delete_taxonomy',
                slug:   slug,
                nonce:  taxocsieData.deleteTaxonomyNonce,
            },
            success: function (res) {
                if (!res.success) {
                    showTaxocsieNotification('error', res.data);
                    $btn.prop('disabled', false);
                    return;
                }
                var $row = $('#taxocsie-reg-table tbody tr[data-slug="' + slug + '"]');
                $row.fadeOut(250, function () {
                    $row.remove();
                    var remaining = $('#taxocsie-reg-table tbody tr[data-slug]').length;
                    if (!remaining) {
                        $('#taxocsie-reg-table tbody').html(getEmptyTaxonomiesRow());
                    }
                    renderTaxonomyTable();
                    showTaxocsieNotification('success', 'The taxonomy was removed.', 'Taxonomy deleted');
                });
            },
            error: function () {
                showTaxocsieNotification('error', 'Request failed. Please try again.');
                $btn.prop('disabled', false);
            }
        });
    });

    function showRegFeedback($el, message, type) {
        $el.hide().empty();
        showTaxocsieNotification(type, message);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    function escHtml(str) {
        return $('<div>').text(String(str)).html();
    }

    function escAttr(str) {
        return escHtml(str).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function enhanceTaxocsieTables(context) {
        var $context = $(context);
        var $tables = $context.filter('table.taxocsie-enhanced-table')
            .add($context.find('table.taxocsie-enhanced-table'));

        $tables.each(function () {
            var $table = $(this);
            if ($table.attr('data-taxocsie-enhanced') === 'true') {
                return;
            }
            $table.attr('data-taxocsie-enhanced', 'true');

            var $toolbar = $(
                '<div class="taxocsie-table-controls taxocsie-auto-table-controls">' +
                    '<div class="taxocsie-table-total-wrap">' +
                        '<span class="dashicons dashicons-list-view" aria-hidden="true"></span>' +
                        '<strong class="taxocsie-auto-table-total">0</strong><span>total</span>' +
                    '</div>' +
                    '<div class="taxocsie-table-pagination">' +
                        '<label>Rows per page</label>' +
                        '<select class="taxocsie-table-per-page" aria-label="Rows per page">' +
                            '<option value="5">5</option><option value="10" selected>10</option>' +
                            '<option value="20">20</option><option value="50">50</option>' +
                        '</select>' +
                        '<span class="taxocsie-page-status"><span class="taxocsie-auto-current-page">1</span> of <span class="taxocsie-auto-total-pages">1</span></span>' +
                        '<div class="taxocsie-pagination-buttons">' +
                            '<button type="button" class="taxocsie-auto-page-prev" aria-label="Previous page" disabled><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>' +
                            '<button type="button" class="taxocsie-auto-page-next" aria-label="Next page" disabled><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
            $table.before($toolbar);
			if ($table.closest('.taxocsie-panel--demo-stats').length) {
				var $bulkButton = $('<button type="button" class="button taxocsie-demo-bulk-generate" disabled></button>');
				$bulkButton.text(taxocsieData.demoBulkGenerate + ' ')
					.append($('<span class="taxocsie-demo-bulk-generate__count"></span>').text('0'));
				$toolbar.find('.taxocsie-table-total-wrap').after($bulkButton);
			}

            var state = { page: 1, sortColumn: null, sortDirection: 'asc' };

            $table.find('thead th').each(function (columnIndex) {
                var $heading = $(this);
                if ($heading.attr('data-no-sort') === 'true') {
                    return;
                }
                var $contents = $heading.contents().detach();
                var $button = $('<button type="button"></button>');
                $button.append($contents).append('<span class="dashicons dashicons-sort" aria-hidden="true"></span>');
                $heading.addClass('taxocsie-sortable-column')
                    .attr({ 'data-sort-column': columnIndex, 'aria-sort': 'none' })
                    .append($button);
            });

            function renderEnhancedTable() {
                var $rows = $table.children('tbody').children('tr');
                var total = $rows.length;
                var perPage = parseInt($toolbar.find('.taxocsie-table-per-page').val(), 10) || 10;

                if (state.sortColumn !== null && total > 1) {
                    var sortedRows = $rows.get().sort(function (rowA, rowB) {
                        var valueA = $.trim($(rowA).children('td').eq(state.sortColumn).text()).toLowerCase();
                        var valueB = $.trim($(rowB).children('td').eq(state.sortColumn).text()).toLowerCase();
                        var comparison = valueA.localeCompare(valueB, undefined, { numeric: true, sensitivity: 'base' });
                        return state.sortDirection === 'asc' ? comparison : -comparison;
                    });
                    $table.children('tbody').append(sortedRows);
                    $rows = $table.children('tbody').children('tr');
                }

                var totalPages = Math.max(1, Math.ceil(total / perPage));
                state.page = Math.min(Math.max(1, state.page), totalPages);
                var start = (state.page - 1) * perPage;

                $rows.hide().slice(start, start + perPage).show();
				if ($table.closest('.taxocsie-panel--demo-stats').length) {
					$table.trigger('taxocsie-demo-page-changed');
				}
                $toolbar.find('.taxocsie-auto-table-total').text(total);
                $toolbar.find('.taxocsie-auto-current-page').text(state.page);
                $toolbar.find('.taxocsie-auto-total-pages').text(totalPages);
                $toolbar.find('.taxocsie-auto-page-prev').prop('disabled', state.page <= 1);
                $toolbar.find('.taxocsie-auto-page-next').prop('disabled', state.page >= totalPages);
            }

            $table.on('click', '.taxocsie-sortable-column button', function () {
                var $heading = $(this).closest('th');
                var column = parseInt($heading.attr('data-sort-column'), 10);
                if (state.sortColumn === column) {
                    state.sortDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortColumn = column;
                    state.sortDirection = 'asc';
                }
                state.page = 1;
                $table.find('.taxocsie-sortable-column').attr('aria-sort', 'none')
                    .find('.dashicons').removeClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2').addClass('dashicons-sort');
                $heading.attr('aria-sort', state.sortDirection === 'asc' ? 'ascending' : 'descending');
                $heading.find('.dashicons').removeClass('dashicons-sort')
                    .addClass(state.sortDirection === 'asc' ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2');
                renderEnhancedTable();
            });

            $toolbar.on('change', '.taxocsie-table-per-page', function () {
                state.page = 1;
                renderEnhancedTable();
            });
            $toolbar.on('click', '.taxocsie-auto-page-prev', function () {
                if (state.page > 1) {
                    state.page--;
                    renderEnhancedTable();
                }
            });
            $toolbar.on('click', '.taxocsie-auto-page-next', function () {
                state.page++;
                renderEnhancedTable();
            });

            renderEnhancedTable();
        });
    }

    function updateTermSelection() {
        var $boxes = $('.taxocsie-all-terms .taxocsie-term-select:not(:disabled)');
        var selected = $boxes.filter(':checked').length;
        $('.taxocsie-terms-select-all')
            .prop('checked', $boxes.length > 0 && selected === $boxes.length)
            .prop('indeterminate', selected > 0 && selected < $boxes.length)
            .prop('disabled', $boxes.length === 0);
        $('#taxocsie-terms-bulk-form button[type="submit"]')
            .prop('disabled', selected === 0)
            .find('.taxocsie-delete-selected__count').text(selected);
    }

    $('.taxocsie-all-terms').on('change', '.taxocsie-terms-select-all', function () {
        $('.taxocsie-all-terms .taxocsie-term-select:not(:disabled)').prop('checked', this.checked);
        updateTermSelection();
    }).on('change', '.taxocsie-term-select', updateTermSelection)
    .on('change', '#taxocsie-terms-per-page', function () {
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', this.value);
        url.searchParams.delete('paged');
        window.location.assign(url.toString());
    }).on('click', '.taxocsie-terms-page-prev, .taxocsie-terms-page-next', function () {
        if (!this.disabled) {
            window.location.assign($(this).attr('data-url'));
        }
    }).on('submit', '#taxocsie-terms-bulk-form', function (event) {
        var count = $('.taxocsie-all-terms .taxocsie-term-select:checked').length;
        if (!count || !window.confirm($(this).attr('data-confirm').replace('%d', count))) {
            event.preventDefault();
        }
    }).on('click', '.taxocsie-term-edit-toggle', function () {
        var $button = $(this);
        var $row = $('#' + $button.attr('data-target'));
        var opening = $row.prop('hidden');
        $('.taxocsie-term-edit-row').prop('hidden', true);
        $('.taxocsie-term-edit-toggle').attr('aria-expanded', 'false');
        $row.prop('hidden', !opening);
        $button.attr('aria-expanded', opening ? 'true' : 'false');
        if (opening) {
            $row.find('input[name="term_name"]').trigger('focus');
        }
    }).on('click', '.taxocsie-term-edit-cancel', function () {
        var $row = $(this).closest('.taxocsie-term-edit-row');
        $row.prop('hidden', true);
        $('.taxocsie-term-edit-toggle[data-target="' + $row.attr('id') + '"]').attr('aria-expanded', 'false').trigger('focus');
    }).on('submit', '.taxocsie-term-delete-form', function (event) {
        if (!window.confirm($(this).attr('data-confirm'))) {
            event.preventDefault();
        }
    });

    renderTaxonomyTable();
    enhanceTaxocsieTables(document);

    if (window.MutationObserver) {
        var taxocsieTableObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                $(mutation.addedNodes).each(function () {
                    if (this.nodeType === 1) {
                        enhanceTaxocsieTables(this);
                    }
                });
            });
        });
        var taxocsieTableRoot = document.getElementById('wpbody-content');
        if (taxocsieTableRoot) {
            taxocsieTableObserver.observe(taxocsieTableRoot, { childList: true, subtree: true });
        }
    }

    // ============================================================
    // INITIALIZATION
    // ============================================================

    if ($('#tcie_dry_run').length) {
        setTimeout(function() {
            $('#tcie_dry_run').trigger('change');
        }, 100);
    }

    if ($('#tcie-preview-container').find('.tcie-preview-container').length) {
        $('#tcie-import-actions').show();
        tcie.hasPreview = true;
    }
});
