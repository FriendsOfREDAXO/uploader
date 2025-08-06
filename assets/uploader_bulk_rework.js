
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
                        <div class="modal-header bg-primary text-white">
                            <h4 class="modal-title"><i class="fa fa-cogs"></i> Bilder werden verarbeitet</h4>
                        </div>
                        <div class="modal-body" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 30px;">
                            
                            <!-- Hauptfortschrittsbalken -->
                            <div class="progress" style="height: 30px; margin-bottom: 25px; border-radius: 15px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     style="background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); 
                                            font-size: 16px; 
                                            font-weight: bold; 
                                            line-height: 30px;
                                            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
                                            transition: width 0.6s ease;" 
                                     role="progressbar" 
                                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    <span class="progress-text">0%</span>
                                </div>
                            </div>
                            
                            <!-- Status Info -->
                            <div class="row" style="margin-bottom: 25px;">
                                <div class="col-md-6">
                                    <div style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                                        <h5 style="margin: 0 0 15px 0; color: #495057;"><i class="fa fa-list"></i> Fortschritt</h5>
                                        <div style="font-size: 18px; margin-bottom: 10px;">
                                            <span id="batch-progress-text" style="font-weight: bold; color: #667eea;">0 von 0</span> Dateien
                                        </div>
                                        <div style="font-size: 14px; color: #666;">
                                            Status: <span id="batch-status-text" class="badge badge-primary">Wird gestartet...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                                        <h5 style="margin: 0 0 15px 0; color: #495057;"><i class="fa fa-file-image-o"></i> Aktuell</h5>
                                        <div id="current-file-display" style="font-size: 14px; word-break: break-word; min-height: 40px; display: flex; align-items: center;">
                                            <div class="text-muted"><i class="fa fa-hourglass-start"></i> Bereit zum Start...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ergebnisse (nur anzeigen wenn es welche gibt) -->
                            <div id="results-section" style="display: none;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div style="background: #d4edda; border-radius: 8px; padding: 15px; text-align: center; border: 1px solid #c3e6cb;">
                                            <div style="font-size: 24px; font-weight: bold; color: #155724;" id="successful-count">0</div>
                                            <div style="font-size: 12px; color: #155724;">ERFOLGREICH</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div style="background: #fff3cd; border-radius: 8px; padding: 15px; text-align: center; border: 1px solid #ffeaa7;">
                                            <div style="font-size: 24px; font-weight: bold; color: #856404;" id="skipped-count">0</div>
                                            <div style="font-size: 12px; color: #856404;">ÜBERSPRUNGEN</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div style="background: #f8d7da; border-radius: 8px; padding: 15px; text-align: center; border: 1px solid #f5c6cb;">
                                            <div style="font-size: 24px; font-weight: bold; color: #721c24;" id="error-count">0</div>
                                            <div style="font-size: 12px; color: #721c24;">FEHLER</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="batch-details" style="margin-top: 20px;">
                                <!-- Details werden hier angezeigt -->
                            </div>
                        </div>
                        <div class="modal-footer" style="background: #f8f9fa;">
                            <button type="button" class="btn btn-secondary" id="cancel-batch" disabled>
                                <i class="fa fa-stop"></i> Abbrechen
                            </button>
                            <button type="button" class="btn btn-success" id="close-modal" style="display: none;">
                                <i class="fa fa-check"></i> Schließen
                            </button>
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
        if (!batchStatus) {
            return;
        }
        
        console.log('Updating modal with batch status:', batchStatus);
        console.log('currentlyProcessing:', batchStatus.currentlyProcessing);
        console.log('activeProcesses:', batchStatus.activeProcesses);
        console.log('currentFiles:', batchStatus.currentFiles);
        
        let progressPercent = batchStatus.progress || 0;
        
        // Update main progress bar
        $('.progress-bar').css('width', progressPercent + '%').attr('aria-valuenow', progressPercent);
        $('.progress-text').text(Math.round(progressPercent) + '%');
        
        // Update status info
        $('#batch-progress-text').text((batchStatus.processed || 0) + ' von ' + (batchStatus.total || 0));
        
        // Update status badge
        if (batchStatus.status === 'running') {
            $('#batch-status-text').removeClass().addClass('badge badge-primary').text('Läuft...');
        } else if (batchStatus.status === 'completed') {
            $('#batch-status-text').removeClass().addClass('badge badge-success').text('Abgeschlossen');
        }

        // Update currently processing files
        const $currentFileDisplay = $('#current-file-display');
        
        if (batchStatus.currentlyProcessing && batchStatus.currentlyProcessing.length > 0) {
            let filesHtml = '';
            batchStatus.currentlyProcessing.forEach((file, index) => {
                const filename = typeof file === 'string' ? file : file.filename;
                const duration = (typeof file === 'object' && file.duration) ? file.duration : 0;
                
                filesHtml += `
                    <div style="margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; border-left: 3px solid #007bff;">
                        <div style="display: flex; align-items: center;">
                            <div class="spinner-border spinner-border-sm text-primary" style="margin-right: 8px; width: 16px; height: 16px;"></div>
                            <div>
                                <div style="font-weight: bold; font-size: 13px;">${filename}</div>
                                <div style="font-size: 11px; color: #666;">seit ${duration}s</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            $currentFileDisplay.html(filesHtml);
        } else if (batchStatus.status === 'completed') {
            $currentFileDisplay.html('<div class="text-success"><i class="fa fa-check-circle"></i> Alle Dateien verarbeitet</div>');
        } else {
            $currentFileDisplay.html('<div class="text-muted"><i class="fa fa-clock-o"></i> Warten auf nächste Datei...</div>');
        }

        // Show results if any files processed
        if ((batchStatus.processed || 0) > 0) {
            $('#results-section').show();
            
            const successful = batchStatus.successful || 0;
            const skipped = batchStatus.skipped ? Object.keys(batchStatus.skipped).length : 0;
            const errors = batchStatus.errors ? Object.keys(batchStatus.errors).length : 0;
            
            $('#successful-count').text(successful);
            $('#skipped-count').text(skipped);
            $('#error-count').text(errors);
        }

        // Update modal buttons
        if (batchStatus.status === 'completed') {
            $('#cancel-batch').hide();
            $('#close-modal').show();
            $('.progress-bar').removeClass('progress-bar-animated');
        }
    }

    // Animation helper function
    function animateCounter(selector, targetValue) {
        let $element = $(selector);
        let currentValue = parseInt($element.text()) || 0;
        
        if (currentValue !== targetValue) {
            $element.css('transform', 'scale(1.2)').css('transition', 'transform 0.3s ease');
            setTimeout(() => {
                $element.text(targetValue).css('transform', 'scale(1)');
            }, 150);
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
                        // Debug: Logge die Antwort
                        console.log('Batch response:', response.data);
                        
                        // Verwende die korrekten Daten
                        this.status = response.data.batch || response.data;
                        updateProgressModal(this.status);

                        if (this.status.status === 'completed') {
                            this.running = false;
                        } else if (response.data.status === 'processing' || this.status.status === 'running') {
                            // Schnelleres Polling für parallele Verarbeitung (200ms statt 500ms)
                            this.processInterval = setTimeout(() => {
                                this.processNext();
                            }, 200);
                        } else {
                            this.handleError('Unerwarteter Status: ' + (response.data.status || this.status.status));
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
    
    // Add CSS animations dynamically
    if (!$('#bulk-processing-styles').length) {
        $('head').append(`
            <style id="bulk-processing-styles">
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                @keyframes pulse-file {
                    0%, 100% { 
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
                        transform: translateY(0);
                    }
                    50% { 
                        box-shadow: 0 4px 16px rgba(0,0,0,0.15); 
                        transform: translateY(-1px);
                    }
                }
                
                .pulse {
                    animation: pulse 2s infinite;
                }
                
                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.7; }
                    100% { opacity: 1; }
                }
                
                .status-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
                    transition: all 0.3s ease;
                }
                
                .processing-panel, .info-panel {
                    transition: all 0.3s ease;
                }
                
                .processing-panel:hover, .info-panel:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
                }
                
                .progress-bar {
                    position: relative;
                    overflow: visible;
                }
                
                .progress-bar::after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                    animation: shine 2s infinite;
                }
                
                @keyframes shine {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
            </style>
        `);
    }
});
