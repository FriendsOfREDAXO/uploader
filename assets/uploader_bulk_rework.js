
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

        // Starte Batch
        $.ajax({
            url: window.location.pathname + '?rex-api-call=uploader_bulk_process',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'start',
                filenames: filenames,
                maxWidth: maxWidth,
                maxHeight: maxHeight
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
                <div class="modal-dialog modal-lg" role="document">
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
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="batch-status">
                                        <div><strong>Status:</strong> <span id="batch-status-text">Wird gestartet...</span></div>
                                        <div><strong>Fortschritt:</strong> <span id="batch-progress-text">0 von 0</span></div>
                                        <div><strong>Erfolgreich:</strong> <span id="batch-success-count">0</span></div>
                                        <div><strong>Übersprungen:</strong> <span id="batch-skipped-count">0</span></div>
                                        <div><strong>Fehler:</strong> <span id="batch-error-count">0</span></div>
                                        <div><strong>Aktive Prozesse:</strong> <span id="active-processes-count">0</span></div>
                                        <div><strong>Warteschlange:</strong> <span id="queue-length">0</span></div>
                                        <div id="remaining-time-info" style="margin-top: 5px; font-size: 0.9em; color: #666;"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div id="currently-processing">
                                        <strong>Aktuell verarbeitet:</strong>
                                        <div id="current-files-list" style="margin-top: 5px; font-size: 0.9em;">
                                            <div class="text-muted">Keine Dateien in Verarbeitung</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="batch-details" style="margin-top: 15px; max-height: 200px; overflow-y: auto;">
                                <!-- Batch details will be shown here -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="cancel-batch" disabled>Abbrechen</button>
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
        let progressPercent = batchStatus.progress || 0;
        
        $('.progress-bar').css('width', progressPercent + '%').attr('aria-valuenow', progressPercent);
        $('.progress-bar .sr-only').text(Math.round(progressPercent) + '% Complete');
        
        $('#batch-progress-text').text(batchStatus.processed + ' von ' + batchStatus.total);
        $('#batch-success-count').text(batchStatus.successful || 0);
        $('#batch-skipped-count').text(Object.keys(batchStatus.skipped || {}).length);
        $('#batch-error-count').text(Object.keys(batchStatus.errors || {}).length);
        $('#active-processes-count').text(batchStatus.activeProcesses || 0);
        $('#queue-length').text(batchStatus.queueLength || 0);
        
        // Aktuell verarbeitete Dateien anzeigen
        let currentFilesHtml = '';
        if (batchStatus.currentlyProcessing && batchStatus.currentlyProcessing.length > 0) {
            currentFilesHtml = batchStatus.currentlyProcessing.map(file => {
                let filename = typeof file === 'string' ? file : file.filename;
                let duration = typeof file === 'object' && file.duration ? ` (${file.duration}s)` : '';
                return `<div class="text-info"><i class="fa fa-spinner fa-spin"></i> ${filename}${duration}</div>`;
            }).join('');
        } else {
            currentFilesHtml = '<div class="text-muted">Keine Dateien in Verarbeitung</div>';
        }
        $('#current-files-list').html(currentFilesHtml);
        
        // Zeitschätzung anzeigen
        if (batchStatus.remainingTime) {
            let minutes = Math.floor(batchStatus.remainingTime / 60);
            let seconds = batchStatus.remainingTime % 60;
            let timeStr = minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
            $('#remaining-time-info').html(`<i class="fa fa-clock-o"></i> Geschätzte Restzeit: ${timeStr}`);
        } else {
            $('#remaining-time-info').html('');
        }
        
        if (batchStatus.status === 'completed') {
            $('#batch-status-text').text('Abgeschlossen');
            $('.progress-bar').removeClass('active');
            $('#cancel-batch').hide();
            $('#close-modal').show();
            $('#current-files-list').html('<div class="text-success"><i class="fa fa-check"></i> Alle Dateien verarbeitet</div>');
            $('#remaining-time-info').html('');
            
            // Zeige Zusammenfassung
            let summary = `
                <div class="alert alert-success">
                    <strong>Verarbeitung abgeschlossen!</strong><br>
                    ${batchStatus.successful || 0} Bilder erfolgreich verarbeitet<br>
                    ${Object.keys(batchStatus.skipped || {}).length} übersprungen<br>
                    ${Object.keys(batchStatus.errors || {}).length} Fehler
                </div>
            `;
            
            // Zeige Details zu übersprungenen und fehlerhaften Dateien
            let details = '';
            if (Object.keys(batchStatus.skipped || {}).length > 0) {
                details += '<div class="alert alert-warning"><strong>Übersprungene Dateien:</strong><ul>';
                Object.entries(batchStatus.skipped).forEach(([file, reason]) => {
                    details += `<li>${file}: ${reason}</li>`;
                });
                details += '</ul></div>';
            }
            
            if (Object.keys(batchStatus.errors || {}).length > 0) {
                details += '<div class="alert alert-danger"><strong>Fehlerhafte Dateien:</strong><ul>';
                Object.entries(batchStatus.errors).forEach(([file, error]) => {
                    details += `<li>${file}: ${error}</li>`;
                });
                details += '</ul></div>';
            }
            
            $('#batch-details').html(summary + details);
            
            // Nach erfolgreicher Verarbeitung Seite neu laden
            setTimeout(() => {
                location.reload();
            }, 3000);
            
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
                        } else if (response.data.status === 'processing') {
                            // Schnelleres Polling für parallele Verarbeitung (200ms statt 500ms)
                            this.processInterval = setTimeout(() => {
                                this.processNext();
                            }, 200);
                        } else {
                            this.handleError('Unerwarteter Status: ' + response.data.status);
                        }
                    } else {
                        this.handleError('Verarbeitungsfehler: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                    }
                },
                error: (xhr, status, error) => {
                    // Bei Netzwerkfehlern etwas länger warten bevor Retry
                    if (this.running) {
                        console.warn('Netzwerkfehler, versuche erneut in 1s:', error);
                        this.processInterval = setTimeout(() => {
                            this.processNext();
                        }, 1000);
                    }
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
        if (batchProcessor) {
            batchProcessor.stop();
            $('#batch-status-text').text('Abgebrochen');
            $('#cancel-batch').hide();
            $('#close-modal').show();
        }
    });

    // Close modal
    $(document).on('click', '#close-modal', function() {
        hideProgressModal();
    });
});
