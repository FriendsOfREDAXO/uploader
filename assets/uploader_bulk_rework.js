
$(document).on('rex:ready', function (event, element) {
    if(!$('body#rex-page-uploader-bulk-rework').length) {
        return;
    }

    let $table = $('#uploader-bulk-rework-table');
    let $checkboxes = $table.find('tbody input[type=checkbox][name="rework-file[]"]');
    let $submitButtons = $('button[type="submit"][name="rework-files-submit"]')
    let $searchResetButton = $('button[type="submit"][name="rework-files-search-reset"]')
    let $form = $('.rex-page-main form');

    // Batch processing variables
    let batchProcessor = null;

    // toggle all checkbox in thead
    $table.find('thead th input[type=checkbox][name="rework-files-toggle"]').click(function(e) {
        $checkboxes.prop('checked', $(this).prop('checked'));
        $checkboxes.change();
    });

    $checkboxes.change(function(e) {
        $submitButtons.children('.number').text($checkboxes.filter(':checked').length);
    });

    // Async Batch Processing
    $submitButtons.click(function(e) {
        e.preventDefault();
        
        let selectedFiles = [];
        $checkboxes.filter(':checked').each(function() {
            selectedFiles.push($(this).val());
        });

        if (selectedFiles.length === 0) {
            alert('Bitte wählen Sie mindestens eine Datei aus.');
            return;
        }

        startBatchProcessing(selectedFiles);
    });

    $searchResetButton.click(function(e) {
        e.preventDefault();
        let $searchInputs = $form.find('.rework-files-search').find('input,select,textarea');

        $searchInputs.each(function(idx, elem) {
            switch(elem.type) {
                case 'number':
                    elem.value = '0';
                    break;

                default:
                    elem.value = '';
                    break;
            }
        });

        $form.submit();
    });

    function startBatchProcessing(filenames) {
        // Erstelle Progress Modal
        showProgressModal();
        
        // Hole aktuelle Einstellungen
        let maxWidth = parseInt($('.uploader-bulk-rework-current-settings b').first().text()) || 0;
        let maxHeight = parseInt($('.uploader-bulk-rework-current-settings b').eq(1).text()) || 0;
        let allowTifConversion = $('#allow-tif-conversion').is(':checked');
        let parallelProcessing = parseInt($('#parallel-processing').val()) || 3;

        // Starte Batch
        $.ajax({
            url: window.location.pathname + '?rex-api-call=uploader_bulk_process',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'start',
                filenames: filenames,
                maxWidth: maxWidth,
                maxHeight: maxHeight,
                allowTifConversion: allowTifConversion,
                parallelProcessing: parallelProcessing
            },
            success: function(response) {
                if (response.success) {
                    batchProcessor = new BatchProcessor(response.data.batchId, response.data.status);
                    batchProcessor.start();
                } else {
                    hideProgressModal();
                    alert('Fehler beim Starten der Verarbeitung: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                }
            },
            error: function(xhr, status, error) {
                hideProgressModal();
                alert('Fehler beim Starten der Verarbeitung: ' + error);
            }
        });
    }

    function showProgressModal() {
        let modal = `
            <div id="bulk-progress-modal" class="modal fade" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Bilder werden verarbeitet...</h4>
                        </div>
                        <div class="modal-body">
                            <div class="progress" style="margin-bottom: 15px;">
                                <div class="progress-bar progress-bar-striped active" role="progressbar" 
                                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
                                    <span class="sr-only">0% Complete</span>
                                </div>
                            </div>
                            <div class="batch-status">
                                <div><strong>Status:</strong> <span id="batch-status-text">Wird gestartet...</span></div>
                                <div><strong>Fortschritt:</strong> <span id="batch-progress-text">0 von 0</span></div>
                                <div><strong>Erfolgreich:</strong> <span id="batch-success-count">0</span></div>
                                <div><strong>Übersprungen:</strong> <span id="batch-skipped-count">0</span></div>
                                <div><strong>Fehler:</strong> <span id="batch-error-count">0</span></div>
                                <div style="margin-top: 10px;"><small>Aktuell verarbeitet: <span id="current-file">-</span></small></div>
                            </div>
                            <div id="batch-details" style="margin-top: 15px; max-height: 200px; overflow-y: auto;">
                                <!-- Batch details will be shown here -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-warning" id="cancel-batch">Abbrechen</button>
                            <button type="button" class="btn btn-primary" id="close-modal" style="display: none;">Schließen</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modal);
        $('#bulk-progress-modal').modal('show');
    }

    function hideProgressModal() {
        $('#bulk-progress-modal').modal('hide');
        setTimeout(() => {
            $('#bulk-progress-modal').remove();
        }, 500);
    }

    function updateProgressModal(batchStatus) {
        let progressPercent = batchStatus.total > 0 ? (batchStatus.processed / batchStatus.total * 100) : 0;
        
        $('.progress-bar').css('width', progressPercent + '%').attr('aria-valuenow', progressPercent);
        $('.progress-bar .sr-only').text(Math.round(progressPercent) + '% Complete');
        
        $('#batch-progress-text').text(batchStatus.processed + ' von ' + batchStatus.total);
        $('#batch-success-count').text(batchStatus.successful);
        $('#batch-skipped-count').text(Object.keys(batchStatus.skipped || {}).length);
        $('#batch-error-count').text(Object.keys(batchStatus.errors || {}).length);
        
        // Zeige aktuell verarbeitete Dateien (parallel)
        let currentFiles = batchStatus.currentFiles || [];
        if (currentFiles.length > 0) {
            let filesList = currentFiles.length > 3 
                ? currentFiles.slice(0, 3).join(', ') + ' und ' + (currentFiles.length - 3) + ' weitere...'
                : currentFiles.join(', ');
            $('#current-file').text(filesList);
        } else {
            $('#current-file').text('-');
        }
        
        // Zeige Parallelverarbeitungsinfo
        let parallelCount = batchStatus.parallelProcessing || 1;
        if (parallelCount > 1 && batchStatus.status === 'running') {
            $('#batch-status-text').text(`Läuft... (${parallelCount} parallel)`);
        } else if (batchStatus.status === 'completed') {
            $('#batch-status-text').text('Abgeschlossen');
            $('.progress-bar').removeClass('active');
            $('#cancel-batch').hide();
            $('#close-modal').show();
            
            // Zeige Zusammenfassung
            let summary = `
                <div class="alert alert-success">
                    <strong>Verarbeitung abgeschlossen!</strong><br>
                    ${batchStatus.successful} Bilder erfolgreich verarbeitet<br>
                    ${Object.keys(batchStatus.skipped || {}).length} übersprungen<br>
                    ${Object.keys(batchStatus.errors || {}).length} Fehler
                </div>
            `;
            $('#batch-details').html(summary);
            
            // Nach erfolgreicher Verarbeitung Seite neu laden
            setTimeout(() => {
                location.reload();
            }, 2000);
            
        } else if (batchStatus.status === 'cancelling') {
            $('#batch-status-text').text('Wird abgebrochen... (laufende Verarbeitungen werden beendet)');
            $('.progress-bar').addClass('progress-bar-warning').removeClass('progress-bar-striped active');
            $('#cancel-batch').prop('disabled', true).text('Wird abgebrochen...');
        } else if (batchStatus.status === 'cancelled') {
            $('#batch-status-text').text('Abgebrochen');
            $('.progress-bar').addClass('progress-bar-warning').removeClass('progress-bar-striped active');
            $('#cancel-batch').hide();
            $('#close-modal').show();
            
            // Zeige Abbruch-Zusammenfassung
            let summary = `
                <div class="alert alert-warning">
                    <strong>Verarbeitung abgebrochen!</strong><br>
                    ${batchStatus.successful} Bilder erfolgreich verarbeitet<br>
                    ${Object.keys(batchStatus.skipped || {}).length} übersprungen<br>
                    ${Object.keys(batchStatus.errors || {}).length} Fehler<br>
                    <small>Laufende Verarbeitungen wurden ordnungsgemäß beendet.</small>
                </div>
            `;
            $('#batch-details').html(summary);
        } else if (batchStatus.status === 'running') {
            $('#batch-status-text').text('Läuft...');
        }
    }

    // Batch Processor Class
    class BatchProcessor {
        constructor(batchId, initialStatus) {
            this.batchId = batchId;
            this.status = initialStatus;
            this.running = false;
            this.processInterval = null;
        }

        start() {
            this.running = true;
            updateProgressModal(this.status);
            this.processNext();
        }

        stop() {
            this.running = false;
            if (this.processInterval) {
                clearTimeout(this.processInterval);
            }
        }

        cancel() {
            // Sende Cancel-Request an Server
            $.ajax({
                url: window.location.pathname + '?rex-api-call=uploader_bulk_process',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'cancel',
                    batchId: this.batchId
                },
                success: (response) => {
                    if (response.success) {
                        this.status = response.data.status;
                        updateProgressModal(this.status);
                        this.running = false;
                    } else {
                        this.handleError('Fehler beim Abbrechen: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                    }
                },
                error: (xhr, status, error) => {
                    this.handleError('Netzwerkfehler beim Abbrechen: ' + error);
                }
            });
        }

        processNext() {
            if (!this.running) return;

            $.ajax({
                url: window.location.pathname + '?rex-api-call=uploader_bulk_process',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'process',
                    batchId: this.batchId
                },
                success: (response) => {
                    if (response.success) {
                        this.status = response.data.batch;
                        updateProgressModal(this.status);

                        if (this.status.status === 'completed') {
                            this.running = false;
                        } else if (this.status.status === 'cancelled') {
                            this.running = false;
                        } else if (response.data.status === 'processing') {
                            // Weiter verarbeiten nach kurzer Pause
                            this.processInterval = setTimeout(() => {
                                this.processNext();
                            }, 500);
                        } else {
                            this.handleError('Unerwarteter Status: ' + response.data.status);
                        }
                    } else {
                        this.handleError('Verarbeitungsfehler: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                    }
                },
                error: (xhr, status, error) => {
                    this.handleError('Netzwerkfehler bei der Verarbeitung: ' + error);
                }
            });
        }

        handleError(message) {
            this.running = false;
            $('#batch-status-text').text('Fehler');
            $('#batch-details').html(`<div class="alert alert-danger">${message}</div>`);
            $('#cancel-batch').hide();
            $('#close-modal').show();
        }
    }

    // Cancel batch processing
    $(document).on('click', '#cancel-batch', function() {
        if (batchProcessor && batchProcessor.running) {
            // Confirmation Dialog
            if (confirm('Möchten Sie die Verarbeitung wirklich abbrechen?\n\nLaufende Konvertierungen werden ordnungsgemäß beendet, aber keine neuen werden gestartet.')) {
                batchProcessor.cancel();
            }
        }
    });

    // Close modal
    $(document).on('click', '#close-modal', function() {
        hideProgressModal();
    });
});
