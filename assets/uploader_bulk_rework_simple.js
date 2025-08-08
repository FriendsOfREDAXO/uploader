/**
 * Uploader Bulk Rework - Vereinfachte Version
 * Bilder werden in Stapeln verarbeitet mit verbesserter Fehlerbehandlung
 */

$(document).ready(function() {
    'use strict';
    
    let isProcessing = false;
    let batchProcessor = null;
    
    // Event Handler für Bulk Rework Button
    $(document).on('click', '.bulk-rework-start', function() {
        if (isProcessing) {
            alert('Es läuft bereits eine Verarbeitung!');
            return;
        }
        
        // Hol die ausgewählten Dateien
        let selectedFiles = [];
        $('.media-selection:checked').each(function() {
            selectedFiles.push($(this).val());
        });
        
        if (selectedFiles.length === 0) {
            alert('Bitte wähle mindestens eine Datei aus!');
            return;
        }
        
        // Größenbeschränkungen
        let maxWidth = parseInt($('#bulk-max-width').val()) || 0;
        let maxHeight = parseInt($('#bulk-max-height').val()) || 0;
        
        if (maxWidth <= 0 && maxHeight <= 0) {
            alert('Bitte gib eine maximale Breite oder Höhe an!');
            return;
        }
        
        startBulkProcessing(selectedFiles, maxWidth, maxHeight);
    });
    
    // Batch verarbeitung starten
    function startBulkProcessing(filenames, maxWidth, maxHeight) {
        isProcessing = true;
        showProgressModal();
        
        $.ajax({
            url: 'index.php?page=uploader/bulk_rework',
            type: 'POST',
            dataType: 'json',
            data: {
                func: 'start-batch',
                filenames: filenames,
                maxWidth: maxWidth,
                maxHeight: maxHeight
            },
            success: function(response) {
                if (response.success) {
                    batchProcessor = new SimpleBatchProcessor(response.data.batchId);
                    batchProcessor.start();
                } else {
                    hideProgressModal();
                    alert('Fehler beim Starten: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                    isProcessing = false;
                }
            },
            error: function(xhr, status, error) {
                hideProgressModal();
                alert('Fehler beim Starten: ' + error);
                isProcessing = false;
            }
        });
    }
    
    // Vereinfachtes Modal anzeigen
    function showProgressModal() {
        const modal = `
            <div id="bulk-progress-modal" class="modal fade" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h4 class="modal-title">
                                <i class="fa fa-cogs"></i> Bilder werden verarbeitet
                            </h4>
                        </div>
                        <div class="modal-body">
                            <!-- Hauptfortschrittsbalken -->
                            <div class="progress" style="height: 25px; margin-bottom: 20px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" 
                                     style="width: 0%; transition: width 0.6s ease;" 
                                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    <span class="sr-only">0%</span>
                                    <strong style="font-size: 14px;">0%</strong>
                                </div>
                            </div>
                            
                            <!-- Status Informationen -->
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Fortschritt</h5>
                                    <p id="batch-progress-text" style="font-size: 18px; font-weight: bold;">
                                        0 von 0 Dateien
                                    </p>
                                    <p>Status: <span id="batch-status-badge" class="badge badge-info">Wird gestartet...</span></p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Aktuelle Datei</h5>
                                    <div id="current-file" style="min-height: 50px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
                                        <i class="fa fa-hourglass-start"></i> Bereit zum Start...
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ergebnisse (nur bei Bedarf anzeigen) -->
                            <div id="results-section" style="margin-top: 20px; display: none;">
                                <h5>Ergebnisse</h5>
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <div class="alert alert-success">
                                            <strong id="success-count">0</strong><br>
                                            Erfolgreich
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="alert alert-warning">
                                            <strong id="skipped-count">0</strong><br>
                                            Übersprungen
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="alert alert-danger">
                                            <strong id="error-count">0</strong><br>
                                            Fehler
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="cancel-batch" disabled>
                                <i class="fa fa-stop"></i> Abbrechen
                            </button>
                            <button type="button" class="btn btn-success" id="close-modal" style="display: none;" onclick="hideProgressModal();">
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
    
    // Modal ausblenden
    function hideProgressModal() {
        $('#bulk-progress-modal').modal('hide');
        setTimeout(() => {
            $('#bulk-progress-modal').remove();
            isProcessing = false;
        }, 500);
    }
    
    // Vereinfachter Batch Processor
    class SimpleBatchProcessor {
        constructor(batchId) {
            this.batchId = batchId;
            this.intervalId = null;
            this.isRunning = false;
        }
        
        start() {
            this.isRunning = true;
            this.intervalId = setInterval(() => {
                this.processNext();
            }, 1000); // Alle Sekunde prüfen
            
            // Sofort starten
            this.processNext();
        }
        
        stop() {
            this.isRunning = false;
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        }
        
        processNext() {
            if (!this.isRunning) return;
            
            $.ajax({
                url: 'index.php?page=uploader/bulk_rework',
                type: 'POST',
                dataType: 'json',
                data: {
                    func: 'process-next',
                    batchId: this.batchId
                },
                success: (response) => {
                    if (response.success && response.data.batch) {
                        this.updateUI(response.data.batch);
                        
                        if (response.data.status === 'completed') {
                            this.stop();
                        }
                    } else {
                        console.error('Fehler bei der Verarbeitung:', response);
                        this.stop();
                        alert('Fehler bei der Verarbeitung: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Fehler:', error);
                    this.stop();
                    alert('Verbindungsfehler: ' + error);
                }
            });
        }
        
        updateUI(batchData) {
            if (!batchData) return;
            
            console.log('Aktualisiere UI mit:', batchData);
            
            const processed = batchData.processed || 0;
            const total = batchData.total || 0;
            const progress = total > 0 ? Math.round((processed / total) * 100) : 0;
            
            // Fortschrittsbalken aktualisieren
            const $progressBar = $('.progress-bar');
            $progressBar.css('width', progress + '%');
            $progressBar.attr('aria-valuenow', progress);
            $progressBar.find('strong').text(progress + '%');
            
            // Status Text aktualisieren
            $('#batch-progress-text').text(processed + ' von ' + total + ' Dateien');
            
            // Status Badge
            const $statusBadge = $('#batch-status-badge');
            if (batchData.status === 'completed') {
                $statusBadge.removeClass().addClass('badge badge-success').text('Abgeschlossen');
            } else if (batchData.status === 'running') {
                $statusBadge.removeClass().addClass('badge badge-info').html('<i class="fa fa-spinner fa-spin"></i> Läuft...');
            }
            
            // Aktuelle Datei anzeigen
            const $currentFile = $('#current-file');
            if (batchData.currentlyProcessing && batchData.currentlyProcessing.length > 0) {
                const currentFile = batchData.currentlyProcessing[0];
                const filename = typeof currentFile === 'string' ? currentFile : currentFile.filename;
                const duration = typeof currentFile === 'object' && currentFile.duration ? currentFile.duration : 0;
                
                $currentFile.html(`
                    <div style="display: flex; align-items: center;">
                        <div class="spinner-border spinner-border-sm text-primary" style="margin-right: 10px;"></div>
                        <div>
                            <strong>${filename}</strong><br>
                            <small class="text-muted">Verarbeitung seit ${duration}s</small>
                        </div>
                    </div>
                `);
            } else if (batchData.status === 'completed') {
                $currentFile.html('<i class="fa fa-check-circle text-success"></i> Alle Dateien verarbeitet');
            } else {
                $currentFile.html('<i class="fa fa-clock-o text-muted"></i> Warten auf nächste Datei...');
            }
            
            // Ergebnisse anzeigen wenn verarbeitet
            if (processed > 0) {
                $('#results-section').show();
                
                const successful = batchData.successful || 0;
                const skipped = batchData.skipped ? Object.keys(batchData.skipped).length : 0;
                const errors = batchData.errors ? Object.keys(batchData.errors).length : 0;
                
                $('#success-count').text(successful);
                $('#skipped-count').text(skipped);
                $('#error-count').text(errors);
            }
            
            // Buttons bei Abschluss anpassen
            if (batchData.status === 'completed') {
                $('#cancel-batch').hide();
                $('#close-modal').show();
                $('.progress-bar').removeClass('progress-bar-animated');
            }
        }
    }
    
    // Global verfügbare Funktion
    window.hideProgressModal = hideProgressModal;
});
