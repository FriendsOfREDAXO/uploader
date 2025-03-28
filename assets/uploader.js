/* globals jQuery, $, selectMedia, selectMedialist, uploader_options */

/**
 * REDAXO Uploader AddOn - Frontend-Funktionalität
 */
jQuery(function ($) {
    'use strict';

    /**
     * Helper-Funktion zum Auslesen von URL-Parametern
     * @param {string} name - Name des Parameters
     * @return {string|null} Wert des Parameters oder null
     */
    function getURLParameter(name) {
        const regex = new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)');
        const matches = regex.exec(location.search);
        return matches ? decodeURIComponent(matches[1].replace(/\+/g, '%20')) : null;
    }

    /**
     * Aktualisiert die Metafelder basierend auf der ausgewählten Kategorie
     * @param {string} htmlContent - HTML-Inhalt mit den Metafeldern
     */
    function updateMetafields(htmlContent) {
        const $localParent = $mediacatselect.closest('.form-group').parent();
        const $ajaxParent = $(htmlContent).find('#rex-mediapool-category').closest('fieldset');
        
        // Alle Metafelder finden und filtern
        const nonMetaNames = ['ftitle', 'rex_file_category', 'file_new'];
        
        // Neue Metafelder zusammenstellen
        $ajaxParent.find('.form-group').each(function () {
            const $this = $(this);
            const $input = $this.find('[name]:eq(0)');
            
            // Wenn kein Input gefunden wurde, überspringen
            if (!$input.length) return true;
            
            const name = $input.attr('name');
            const $existingInput = $('[name="' + name + '"]');
            
            // Keine Metafelder behandeln
            if (nonMetaNames.includes(name)) {
                $this.remove();
                return true;
            }
            
            // Vorhandene Metafelder mit Werten beibehalten
            if ($existingInput.length) {
                $this.after($existingInput.closest('.form-group').clone(true, true));
                $this.remove();
            }
        });

        // Alte Metafelder entfernen
        $localParent.find('.form-group').not('.preserve').remove();

        // Neue Metafelder einfügen
        const $metaToAppend = $ajaxParent.find('.form-group');
        if ($metaToAppend.length) {
            $($metaToAppend.get().reverse()).each(function () {
                $localParent.find('.append-meta-after').after($(this));
            });
            
            // REDAXO-Komponenten initialisieren
            $(document).trigger('rex:ready', [$localParent]);
        }
    }

    /**
     * Gibt die konfigurierten Optionen für den Datei-Upload zurück
     * @return {Object} Konfigurationsobjekt für jQuery File Upload
     */
    function getFileuploadOptions() {
        const options = {
            dataType: 'json',
            disableImagePreview: true,
            loadImageMaxFileSize: uploader_options.loadImageMaxFileSize || 30000000, // 30 MB default
            maxChunkSize: 5000000, // 5 MB Chunk-Größe
            disableImageResize: /Android(?!.*Chrome)|Opera/.test(window.navigator && navigator.userAgent),
            imageMaxWidth: uploader_options.imageMaxWidth || 4000,
            imageMaxHeight: uploader_options.imageMaxHeight || 4000,
            messages: uploader_options.messages || {},
            acceptFileTypes: uploader_options.acceptFileTypes || null,
            formData: {
                _csrf_token: uploader_options.csrf_token || ''
            }
        };

        // Bildverkleinerung nur anwenden, wenn aktiviert
        if (!getOption('resize-images')) {
            delete options.disableImageResize;
            delete options.imageMaxWidth;
            delete options.imageMaxHeight;
        }

        return options;
    }

    /**
     * Prüft, ob eine Option aktiviert ist
     * @param {string} selector - ID des Checkbox-Elements
     * @return {boolean} true wenn aktiviert, sonst false
     */
    function getOption(selector) {
        const $el = $('#' + selector);
        return $el.length ? $el.is(':checked') : false;
    }

    /**
     * Erzeugt ein Icon-Element für den angegebenen Dateityp
     * @param {string} filename - Dateiname
     * @return {string} HTML für das Icon
     */
    function getMimeIcon(filename) {
        const ext = filename.toLowerCase().split('.').pop();
        return '<i class="rex-mime" data-extension="' + ext + '"></i>';
    }

    // Hauptfunktionalität des Uploaders
    const $mediacatselect = $('#rex-mediapool-category');
    const $form = $mediacatselect.closest('form');
    const $buttonbar = $('#uploader-row');
    const $buttonbarWrapper = $('<fieldset></fieldset>');
    const context = uploader_options.context || '';

    // Reload per PJAX verhindern
    $('a[href="index.php?page=mediapool/upload"]').attr('data-pjax', 'false');

    // Kategorieauswahl anpassen
    $mediacatselect.prop('onchange', null).off('onchange');
    $form.attr('action', uploader_options.endpoint || '');
    $form.find('[name="ftitle"]').closest('.form-group').addClass('preserve append-meta-after');
    $mediacatselect.closest('.form-group').addClass('preserve');

    // Bei Kategoriewechsel Metafelder aktualisieren
    $mediacatselect.on('change', function () {
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: {
                page: 'mediapool/upload',
                rex_file_category: $mediacatselect.val()
            },
            dataType: 'html',
            success: updateMetafields
        });
    });

    // Kontext-abhängiges Layout
    if (context === 'mediapool_upload') {
        // Nativen Datei-Upload entfernen und eigenen hinzufügen
        $('#rex-mediapool-choose-file').closest('dl').remove();
        $form.find('footer').remove();
        $buttonbarWrapper.append($buttonbar);
        $form.find('fieldset:last').after($buttonbarWrapper);
    }
    else if (context === 'addon_upload') {
        // Im Addon-Kontext einfach an passender Stelle einfügen
        $buttonbarWrapper.append($buttonbar);
        $form.find('fieldset').after($buttonbarWrapper);
        // Initial Metainfos laden
        $mediacatselect.trigger('change');
    }

    // jQuery File Upload initialisieren
    $form.fileupload(getFileuploadOptions());

    // Event-Handler für die Datei-Vorschau
    $form.on('fileuploadadded', function (e, data) {
        $(data.context[0]).find('.preview').append(getMimeIcon(data.files[0].name));
    });

    // Resize-Option aktualisieren
    $('#resize-images').on('click', function () {
        $form.fileupload('destroy');
        $form.fileupload(getFileuploadOptions());
    });

    // Fehlerbehandlung für fehlgeschlagene Uploads
    $form.on('fileuploadprocessfail', function (e, data) {
        const $li = $(data.context[0]);
        $li.find('.size').remove();
        $li.find('.preview').addClass('warning').append(getMimeIcon(data.files[0].name));
    });

    // Datei nach Upload übernehmen (für Widgets)
    $form.on('click', '.btn-select', function (e) {
        e.preventDefault();
        const filename = $(this).data('filename');
        const openerInputField = getURLParameter('opener_input_field');
        
        if (openerInputField) {
            if (openerInputField.substr(0, 14) === 'REX_MEDIALIST_') {
                selectMedialist(filename, '');
            } else {
                selectMedia(filename, '');
            }
        }
    });
});
