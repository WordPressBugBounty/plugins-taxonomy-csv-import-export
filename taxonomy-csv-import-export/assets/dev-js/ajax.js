jQuery(function ($) {

    // ============================================================
    // EXISTING TAB SWITCHER
    // ============================================================
    $('.taxocsie-tab').on('click', function () {
        var panelId = $(this).data('panel');
        $('.taxocsie-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('#taxocsie-export-panel, #taxocsie-import-panel').hide();
        $('#' + panelId).show();
        $('#export-preview-container').hide().empty();
        
        if (panelId !== 'taxocsie-import-panel') {
            $('#tcie-preview-container').html('');
            $('#tcie-import-actions').hide();
        }
    });

    // ============================================================
    // EXISTING CREATE TERM FUNCTIONALITY
    // ============================================================
    $(document).on('click', '.taxocsie-taxonomy-row__header', function () {
        var $row  = $(this).closest('.taxocsie-taxonomy-row');
        var $body = $row.find('.taxocsie-taxonomy-row__body');
        var isOpen = $body.is(':visible');

        $('.taxocsie-taxonomy-row__body').slideUp(160);
        $('.taxocsie-taxonomy-row__header').removeClass('is-open');

        if (!isOpen) {
            $body.slideDown(160);
            $(this).addClass('is-open');
            $row.find('.taxocsie-row-title').focus();
        }
    });

    $(document).on('click', '.taxocsie-row-create-btn', function () {
        doCreateTerm($(this).closest('.taxocsie-taxonomy-row'));
    });

    $(document).on('keydown', '.taxocsie-row-title', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doCreateTerm($(this).closest('.taxocsie-taxonomy-row'));
        }
    });

    function doCreateTerm($row) {
        var taxonomy    = $row.data('taxonomy');
        var $title      = $row.find('.taxocsie-row-title');
        var $desc       = $row.find('.taxocsie-row-desc');
        var $btn        = $row.find('.taxocsie-row-create-btn');
        var $feedback   = $row.find('.taxocsie-row-feedback');
        var name        = $.trim($title.val());
        var description = $.trim($desc.val());

        if (!name) {
            showRowFeedback($feedback, 'Term title is required.', 'error');
            $title.focus();
            return;
        }

        $btn.prop('disabled', true);
        $feedback.hide().empty();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action:      'taxocsie_ajax_create_term',
                taxonomy:    taxonomy,
                name:        name,
                description: description,
                nonce:       taxocsieData.ajaxCreateTermNonce,
            },
            success: function (res) {
                if (!res.success) {
                    showRowFeedback($feedback, res.data, 'error');
                    return;
                }
                $title.val('');
                $desc.val('');
                showRowFeedback($feedback, '&#10003; <strong>' + escHtml(res.data.name) + '</strong> created (slug: <code>' + escHtml(res.data.slug) + '</code>).', 'success');
            },
            error: function () {
                showRowFeedback($feedback, 'Request failed. Please try again.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    }

    function showRowFeedback($el, message, type) {
        $el.removeClass('taxocsie-row-feedback--success taxocsie-row-feedback--error')
           .addClass('taxocsie-row-feedback--' + type)
           .html(message)
           .show();
    }

    // ============================================================
    // EXISTING EXPORT PREVIEW
    // ============================================================
    $('#export-preview-btn').on('click', function () {
        var taxonomy = $('#export-taxonomy').val();

        if (!taxonomy) {
            alert('Please select a taxonomy first.');
            return;
        }

        var $btn = $(this);
        var $container = $('#export-preview-container');

        $btn.prop('disabled', true).text('Loading...');
        $container.hide().empty();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'taxocsie_preview_export',
                taxonomy: taxonomy,
                nonce: taxocsieData.previewExportNonce,
            },
            success: function (res) {
                if (!res.success) {
                    $container.html('<p class="taxocsie-export__preview-error">' + escHtml(res.data) + '</p>').show();
                    return;
                }

                var rows = res.data;

                if (rows.length === 0) {
                    $container.html('<p class="taxocsie-export__preview-empty">No terms found for this taxonomy.</p>').show();
                    return;
                }

                var html = '<p class="taxocsie-export__preview-count">Preview — <strong>' + rows.length + '</strong> term(s) will be exported.</p>';
                html += '<div class="taxocsie-export__preview-tablewrap">';
                html += '<table class="widefat striped taxocsie-export__preview-table">';
                html += '<thead><tr><th>#</th><th>Name</th><th>Slug</th><th>Description</th><th>Parent Slug</th></tr></thead>';
                html += '<tbody>';

                $.each(rows, function (i, row) {
                    html += '<tr>';
                    html += '<td>' + (i + 1) + '</td>';
                    html += '<td>' + escHtml(row.name) + '</td>';
                    html += '<td>' + escHtml(row.slug) + '</td>';
                    html += '<td>' + (row.description ? escHtml(row.description) : '<span class="taxocsie-muted">—</span>') + '</td>';
                    html += '<td>' + (row.parent_slug ? escHtml(row.parent_slug) : '<span class="taxocsie-muted">—</span>') + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table></div>';

                $container.html(html).show();
            },
            error: function () {
                $container.html('<p class="taxocsie-export__preview-error">Request failed. Please try again.</p>').show();
            },
            complete: function () {
                $btn.prop('disabled', false).text('Preview');
            }
        });
    });

    $('#export-taxonomy').on('change', function () {
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
        $('.tcie-notice').remove();
        var classes = 'notice notice-' + type + ' is-dismissible tcie-notice';
        var $notice = $('<div class="' + classes + '"><p>' + message + '</p></div>');
        var $target = $('.taxocsie-page-header').length ? $('.taxocsie-page-header') : $('.wrap h1');
        $target.after($notice);
        setTimeout(function() {
            $notice.fadeOut(400, function() { $(this).remove(); });
        }, 8000);
        $notice.on('click', '.notice-dismiss', function() { $notice.remove(); });
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
                }
            },
            error: function() {
                $('#tcie-preview-container').html('');
                $('#tcie-import-actions').hide();
                tcie.hasPreview = false;
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

    function updateFileStatus(file) {
        var fileName = file.name;
        var fileSize = (file.size / 1024 / 1024).toFixed(2);
        var icon = fileSize > 5 ? 'dashicons-warning' : 'dashicons-yes-alt';
        var color = fileSize > 5 ? '#f56e28' : '#46b450';
        
        $fileStatus.html(
            '<span class="dashicons ' + icon + '" style="color: ' + color + ';"></span>' +
            '<span class="taxocsie-file-name">' + fileName + '</span>' +
            '<span class="taxocsie-file-size">(' + fileSize + ' MB)</span>'
        );
        
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

    // ============================================================
    // DEMO GENERATOR FUNCTIONALITY
    // ============================================================

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
        $btn.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Generating...');

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
                $btn.html('<span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-right: 5px;"></span> Generate Demo Terms');
                if (response.success) {
                    showTcieNotice('success', response.data.message);
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    showTcieNotice('error', response.data.message || 'Generation failed.');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                $btn.html('<span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-right: 5px;"></span> Generate Demo Terms');
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

    $('#reg-label').on('input', function () {
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
                nonce:          taxocsieData.saveTaxonomyNonce,
            },
            success: function (res) {
                if (!res.success) {
                    showRegFeedback($feedback, res.data, 'error');
                    return;
                }
                var d = res.data;
                var typeLabel = d.hierarchical ? 'Categories' : 'Tags';
                var row = '<tr data-slug="' + escHtml(d.slug) + '">' +
                    '<td><strong>' + escHtml(d.label) + '</strong></td>' +
                    '<td><code>' + escHtml(d.slug) + '</code></td>' +
                    '<td>' + escHtml(typeLabel) + '</td>' +
                    '<td>' + escHtml(d.post_types.join(', ')) + '</td>' +
                    '<td><button type="button" class="button taxocsie-delete-taxonomy" data-slug="' + escHtml(d.slug) + '">Delete</button></td>' +
                    '</tr>';
                $('#taxocsie-reg-table tbody').append(row);
                $('#taxocsie-reg-panel').show();
                $('#reg-label, #reg-singular, #reg-slug, #reg-description').val('');
                $('.reg-post-type').prop('checked', false);
                showRegFeedback($feedback, '&#10003; Taxonomy <strong>' + escHtml(d.label) + '</strong> registered. Refresh the page to use it.', 'success');
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
                if (!res.success) { alert(res.data); $btn.prop('disabled', false); return; }
                var $row = $('#taxocsie-reg-table tbody tr[data-slug="' + slug + '"]');
                $row.fadeOut(250, function () {
                    $row.remove();
                    if (!$('#taxocsie-reg-table tbody tr').length) {
                        $('#taxocsie-reg-panel').hide();
                    }
                });
            },
            error: function () { alert('Request failed.'); $btn.prop('disabled', false); }
        });
    });

    function showRegFeedback($el, message, type) {
        $el.removeClass('taxocsie-reg-feedback--success taxocsie-reg-feedback--error')
           .addClass('taxocsie-reg-feedback--' + type)
           .html(message)
           .show();
    }

    // ============================================================
    // HELPERS
    // ============================================================

    function escHtml(str) {
        return $('<div>').text(String(str)).html();
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

    console.log('Taxonomy CSV Import Export: Admin JS loaded successfully.');
});