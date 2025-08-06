
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
                            <h4 class="modal-title"><i class="fa fa-cogs"></i> Bilder werden verarbeitet...</h4>
                        </div>
                        <div class="modal-body" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
                            <!-- Main Progress Bar -->
                            <div class="progress" style="margin-bottom: 20px; height: 25px; border-radius: 12px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" 
                                     style="background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); transition: width 0.6s ease;" 
                                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    <span class="progress-text" style="font-weight: bold; line-height: 25px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);"></span>
                                </div>
                            </div>
                            
                            <!-- Status Cards -->
                            <div class="row" style="margin-bottom: 20px;">
                                <div class="col-md-3 col-sm-6">
                                    <div class="status-card" style="background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #28a745;">
                                        <div class="status-value" style="font-size: 24px; font-weight: bold; color: #28a745;" id="batch-success-count">0</div>
                                        <div style="color: #666; font-size: 12px;">ERFOLGREICH</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="status-card" style="background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #ffc107;">
                                        <div class="status-value" style="font-size: 24px; font-weight: bold; color: #ffc107;" id="batch-skipped-count">0</div>
                                        <div style="color: #666; font-size: 12px;">ÜBERSPRUNGEN</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="status-card" style="background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #dc3545;">
                                        <div class="status-value" style="font-size: 24px; font-weight: bold; color: #dc3545;" id="batch-error-count">0</div>
                                        <div style="color: #666; font-size: 12px;">FEHLER</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="status-card" style="background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #17a2b8;">
                                        <div class="status-value" style="font-size: 24px; font-weight: bold; color: #17a2b8;" id="active-processes-count">0</div>
                                        <div style="color: #666; font-size: 12px;">AKTIV</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Processing Details -->
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="processing-panel" style="background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                        <h5 style="margin: 0 0 10px 0; color: #495057;"><i class="fa fa-tasks"></i> Aktuell verarbeitet</h5>
                                        <div id="current-files-list" style="min-height: 60px;">
                                            <div class="text-muted text-center" style="padding: 20px;"><i class="fa fa-hourglass-start"></i> Bereit zum Start...</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-panel" style="background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                        <h5 style="margin: 0 0 10px 0; color: #495057;"><i class="fa fa-info-circle"></i> Status</h5>
                                        <div style="font-size: 14px; line-height: 1.6;">
                                            <div><strong>Status:</strong> <span id="batch-status-text" class="badge badge-info">Wird gestartet...</span></div>
                                            <div style="margin-top: 8px;"><strong>Fortschritt:</strong> <span id="batch-progress-text">0 von 0</span></div>
                                            <div style="margin-top: 8px;"><strong>Warteschlange:</strong> <span id="queue-length">0</span></div>
                                            <div id="remaining-time-info" style="margin-top: 10px; font-size: 13px; color: #666;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="batch-details" style="margin-top: 20px;">
                                <!-- Batch details will be shown here -->
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
        let progressPercent = batchStatus.progress || 0;
        
        // Update main progress bar
        $('.progress-bar').css('width', progressPercent + '%').attr('aria-valuenow', progressPercent);
        $('.progress-text').text(Math.round(progressPercent) + '%');
        
        // Update status cards with animation
        animateCounter('#batch-success-count', batchStatus.successful || 0);
        animateCounter('#batch-skipped-count', Object.keys(batchStatus.skipped || {}).length);
        animateCounter('#batch-error-count', Object.keys(batchStatus.errors || {}).length);
        animateCounter('#active-processes-count', batchStatus.activeProcesses || 0);
        
        $('#batch-progress-text').text(batchStatus.processed + ' von ' + batchStatus.total);
        $('#queue-length').text(batchStatus.queueLength || 0);
        
        // Update status badge
        if (batchStatus.status === 'running') {
            $('#batch-status-text').removeClass().addClass('badge badge-primary pulse').html('<i class="fa fa-spinner fa-spin"></i> Läuft...');
        }
        
        // Aktuell verarbeitete Dateien mit cooler Animation
        let currentFilesHtml = '';
        if (batchStatus.currentlyProcessing && batchStatus.currentlyProcessing.length > 0) {
            currentFilesHtml = batchStatus.currentlyProcessing.map((file, index) => {
                let filename = typeof file === 'string' ? file : file.filename;
                let duration = typeof file === 'object' && file.duration ? ` (${file.duration}s)` : '';
                let colors = ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a'];
                let color = colors[index % colors.length];
                
                return `
                    <div class="processing-file" style="
                        background: linear-gradient(135deg, ${color}22 0%, ${color}44 100%);
                        border: 1px solid ${color}66;
                        border-radius: 6px;
                        padding: 8px 12px;
                        margin-bottom: 6px;
                        animation: pulse-file 2s infinite;
                        display: flex;
                        align-items: center;
                        font-size: 13px;
                    ">
                        <div class="spinner" style="
                            width: 16px;
                            height: 16px;
                            border: 2px solid ${color}44;
                            border-top: 2px solid ${color};
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            margin-right: 8px;
                            flex-shrink: 0;
                        "></div>
                        <div style="flex-grow: 1; font-weight: 500; color: #495057;">
                            ${filename.length > 30 ? '...' + filename.slice(-30) : filename}
                        </div>
                        ${duration ? `<div style="color: ${color}; font-size: 11px; margin-left: 8px;">${duration}</div>` : ''}
                    </div>
                `;
            }).join('');
        } else {
            currentFilesHtml = `
                <div class="text-center" style="padding: 30px; color: #6c757d;">
                    <i class="fa fa-clock-o" style="font-size: 24px; margin-bottom: 10px; opacity: 0.5;"></i>
                    <div>Keine Dateien in Verarbeitung</div>
                </div>
            `;
        }
        $('#current-files-list').html(currentFilesHtml);
        
        // Zeitschätzung mit Icon
        if (batchStatus.remainingTime) {
            let minutes = Math.floor(batchStatus.remainingTime / 60);
            let seconds = batchStatus.remainingTime % 60;
            let timeStr = minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
            $('#remaining-time-info').html(`
                <div style="color: #17a2b8;">
                    <i class="fa fa-clock-o"></i> Restzeit: <strong>${timeStr}</strong>
                </div>
            `);
        } else {
            $('#remaining-time-info').html('');
        }
        
        if (batchStatus.status === 'completed') {
            $('#batch-status-text').removeClass().addClass('badge badge-success').html('<i class="fa fa-check"></i> Abgeschlossen');
            $('.progress-bar').removeClass('progress-bar-animated');
            $('#cancel-batch').hide();
            $('#close-modal').show();
            $('#current-files-list').html(`
                <div class="text-center" style="padding: 30px; color: #28a745;">
                    <i class="fa fa-check-circle" style="font-size: 36px; margin-bottom: 10px;"></i>
                    <div><strong>Alle Dateien verarbeitet!</strong></div>
                </div>
            `);
            $('#remaining-time-info').html('');
            
            // Animiere den Erfolg
            $('.progress-bar').css('background', 'linear-gradient(45deg, #28a745 0%, #20c997 100%)');
            
            // Zeige detaillierte Zusammenfassung
            let summary = `
                <div class="alert alert-success" style="border-radius: 8px; border: none; box-shadow: 0 2px 10px rgba(40,167,69,0.2);">
                    <h5><i class="fa fa-check-circle"></i> Verarbeitung abgeschlossen!</h5>
                    <div class="row text-center" style="margin-top: 15px;">
                        <div class="col-4">
                            <div style="font-size: 24px; font-weight: bold; color: #28a745;">${batchStatus.successful || 0}</div>
                            <small>Erfolgreich</small>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 24px; font-weight: bold; color: #ffc107;">${Object.keys(batchStatus.skipped || {}).length}</div>
                            <small>Übersprungen</small>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 24px; font-weight: bold; color: #dc3545;">${Object.keys(batchStatus.errors || {}).length}</div>
                            <small>Fehler</small>
                        </div>
                    </div>
                </div>
            `;
            
            // Zeige Details zu übersprungenen und fehlerhaften Dateien
            let details = '';
            if (Object.keys(batchStatus.skipped || {}).length > 0) {
                details += '<div class="alert alert-warning" style="border-radius: 8px;"><strong><i class="fa fa-exclamation-triangle"></i> Übersprungene Dateien:</strong><ul style="margin-top: 10px; margin-bottom: 0;">';
                Object.entries(batchStatus.skipped).forEach(([file, reason]) => {
                    details += `<li>${file}: <em>${reason}</em></li>`;
                });
                details += '</ul></div>';
            }
            
            if (Object.keys(batchStatus.errors || {}).length > 0) {
                details += '<div class="alert alert-danger" style="border-radius: 8px;"><strong><i class="fa fa-times-circle"></i> Fehlerhafte Dateien:</strong><ul style="margin-top: 10px; margin-bottom: 0;">';
                Object.entries(batchStatus.errors).forEach(([file, error]) => {
                    details += `<li>${file}: <em>${error}</em></li>`;
                });
                details += '</ul></div>';
            }
            
            $('#batch-details').html(summary + details);
            
            // Nach erfolgreicher Verarbeitung Seite neu laden
            setTimeout(() => {
                location.reload();
            }, 3000);
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
