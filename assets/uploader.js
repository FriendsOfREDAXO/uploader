/* globals jQuery,$,selectMedia,selectMediaList,uploader_options */

jQuery(function () {

    // Liste bereits hinzugefügter Dateinamen
    var uploadedFiles = [];

    // https://stackoverflow.com/a/11582513
    function getURLParameter(name) {
        return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(location.search) || [null, ''])[1].replace(/\+/g, '%20')) || null;
    }

    function update_metafields(str_html) {

        var $local_parent = $mediacatselect.closest('.form-group').parent(),
            $ajax_parent = $(str_html).find('#rex-mediapool-category').closest('fieldset'),
            $meta_to_append;

        // neue metas zusammenstellen
        $ajax_parent.find('.form-group').each(function () {
            var $this = $(this),
                name = $this.find('[name]:eq(0)').attr('name'),
                $existing_name = $('[name="' + name + '"]'),
                non_meta_names = ['ftitle', 'rex_file_category', 'file_new'];
            // nicht metas entfernen
            if (non_meta_names.indexOf(name) !== -1) {
                $this.remove();
                return true;
            }
            // bereits existierende metas mit werten holen
            if ($existing_name.length) {
                $this.after($existing_name.closest('.form-group').clone(1, 1));
                $this.remove();
            }
        });

        // alte metas entfernen
        $local_parent.find('.form-group').not('.preserve').remove();

        // neue metas einsetzen
        $meta_to_append = $ajax_parent.find('.form-group');
        if ($meta_to_append.length) {
            $($meta_to_append.get().reverse()).each(function () {
                $local_parent.find('.append-meta-after').after($(this));
            });
            $(document).trigger('rex:ready', [$local_parent]);
        }

    }

    function get_fileupload_options() {
        var options = {
            dataType: 'json',
            disableImagePreview: true,
            loadImageMaxFileSize: uploader_options.loadImageMaxFileSize, // 30 mb
            maxChunkSize: 5000000, // 5 mb
            disableImageResize: /Android(?!.*Chrome)|Opera/.test(window.navigator && navigator.userAgent),
            imageMaxWidth: uploader_options.imageMaxWidth,
            imageMaxHeight: uploader_options.imageMaxHeight,
            messages: uploader_options.messages,
            acceptFileTypes: uploader_options.acceptFileTypes
        };
        if (!get_option('resize-images')) {
            delete options.disableImageResize;
            delete options.imageMaxWidth;
            delete options.imageMaxHeight;
        }
        return options;
    }

    function get_option(selector) {
        var $el = $('#' + selector);
        if ($el.length) {
            return $el.is(':checked');
        }
        return false;
    }

    function get_mime_icon(filename) {
        var ext = filename.toLowerCase().split('.').pop();
        return '<i class="rex-mime" data-extension="' + ext + '"></i>';
    }

    var $mediacatselect = $('#rex-mediapool-category'),
        $form = $mediacatselect.closest('form'),
        $buttonbar = $('#uploader-row'),
        $buttonbar_wrapper = $('<fieldset></fieldset>'),
        context = uploader_options.context;

    // reload per pjax verhindern
    $('a[href="index.php?page=mediapool/upload"]').attr('data-pjax', 'false');

    // kontextunabhaengig html anpassen
    $mediacatselect.prop('onchange', null).off('onchange');
    $form.attr('action', uploader_options.endpoint);
    $form.find('[name="ftitle"]').closest('.form-group').addClass('preserve append-meta-after');
    $mediacatselect.closest('.form-group').addClass('preserve');

    // erlaubte metafelder bei kategoriewechsel holen
    $mediacatselect.on('change', function () {
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: {
                page: 'mediapool/upload',
                rex_file_category: $mediacatselect.val()
            },
            dataType: 'html',
            success: function (result) {
                update_metafields(result);
            }
        });
    });

    // kontextabhaengig html anpassen
    if (context === 'mediapool_upload') {
        $('#rex-mediapool-choose-file').closest('dl').remove();
        $form.find('footer').remove();
        $buttonbar_wrapper.append($buttonbar);
        $form.find('fieldset:last').after($buttonbar_wrapper);
    }
    else if (context === 'addon_upload') {
        $buttonbar_wrapper.append($buttonbar);
        $form.find('fieldset').after($buttonbar_wrapper);
        // metainfos holen
        $mediacatselect.trigger('change');
    }

    $form.fileupload(get_fileupload_options());

    $form.bind('fileuploadadded', function (e, data) {
        $(data.context[0]).find('.preview').append(get_mime_icon(data.files[0].name));
    });

    $form.on('fileuploadadd', function (e, data) {
        var name = data.files[0].name;
        if (uploadedFiles.indexOf(name) !== -1) {
            // Duplikat entfernen und Hinweis anzeigen
            data.context.remove();
            alert('Datei "' + name + '" wurde bereits hinzugefügt und wird übersprungen.');
            return false;
        }
        uploadedFiles.push(name);
    });

    $('#resize-images').on('click', function () {
        $form.fileupload('destroy');
        $form.fileupload(get_fileupload_options());
    });

    $form.bind('fileuploadcompleted', function (e, data) {
        var file = data.result.files[0];
        if (file.hasOwnProperty('error') && file.error) {
            // HTML-Tags entfernen
            var cleanError = file.error.replace(/<[^>]*>/g, '');
            // In der UI aktualisieren
            data.context.find('.error').text(cleanError);
            return true;
        }
    });

    $form.bind('fileuploadprocessfail', function (e, data) {
        var $li = $(data.context[0]);
        $li.find('.size').remove();
        $li.find('.preview').addClass('warning').append(get_mime_icon(data.files[0].name));
        // Fehlernachricht tagfrei anzeigen
        var err = data.files[0].error;
        if (err) {
            var clean = err.replace(/<[^>]*>/g, '');
            $li.find('.error').text(clean);
        }
    });

    // datei nach upload uebernehmen
    $form.on('click', '.btn-select', function (e) {
        var opener_input_field = getURLParameter('opener_input_field');
        e.preventDefault();
        if (opener_input_field.substr(0, 14) === 'REX_MEDIALIST_') {
            selectMedialist($(this).data('filename'), '');
        }
        else {
            selectMedia($(this).data('filename'), '');
        }
    });

});
