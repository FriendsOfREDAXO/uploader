/* globals jQuery,$,selectMedia */

jQuery(function() {

    function update_metafields(str_html) {

        var $local_parent = $mediacatselect.closest('.form-group').parent(),
            $ajax_parent = $(str_html).find('#rex-mediapool-category').closest('fieldset'),
            $meta_to_append;

        // neue metas zusammenstellen
        $ajax_parent.find('.form-group').each(function() {
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
            $($meta_to_append.get().reverse()).each(function() {
                $local_parent.find('.append-meta-after').after($(this));
            });
            $(document).trigger('rex:ready', [$local_parent]);
        }

    }

    function get_fileupload_options() {
        var options = {
            dataType: 'json',
            acceptFileTypes: jquery_file_upload_options.acceptFileTypes,
            disableImagePreview: true,
            loadImageMaxFileSize: 20000000, // 20 mb
            maxChunkSize: 10000000, // 10 mb
            messages: jquery_file_upload_options.messages
        };
        if (get_option('resize-images')) {
            options.disableImageResize = /Android(?!.*Chrome)|Opera/.test(window.navigator && navigator.userAgent);
            options.imageMaxWidth = 4000;
            options.imageMaxHeight = 4000;
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

    function get_mime_icon (filename) {
        var ext = filename.toLowerCase().split('.').pop();
        return '<i class="rex-mime rex-mime-' + ext + '" data-extension="' + ext + '"></i>';
    }

    var $mediacatselect = $('#rex-mediapool-category'),
        $form = $mediacatselect.closest('form'),
        $buttonbar = $('#jquery-file-upload-row'),
        $buttonbar_wrapper = $('<fieldset></fieldset>'),
        context = jquery_file_upload_options.context

    // damit die detailansicht regulaer geladen wird
    /*
    if (context == 'mediapool_media') {
        $('[href*="file_id"]').each(function () {
            $(this).attr('data-pjax', 'false');
        });
    }
    */

    // kontextunabhaengig html anpassen
    $mediacatselect.prop('onchange', null).off('onchange');
    $form.attr('action', jquery_file_upload_options.endpoint);
    $form.find('[name="ftitle"]').closest('.form-group').addClass('preserve');
    $mediacatselect.closest('.form-group').addClass('preserve append-meta-after');

    // erlaubte metafelder bei kategoriewechsel holen
    $mediacatselect.on('change', function() {
        $.ajax({
            url: '/redaxo/index.php',
            type: 'POST',
            data: {
                page: 'mediapool/upload',
                rex_file_category: $mediacatselect.val()
            },
            dataType: 'html',
            success: function(result) {
                update_metafields(result);
            }
        });
    });

    // kontextabhaengig html anpassen
    if (context == 'mediapool_upload') {
        $('#rex-mediapool-choose-file').closest('dl').remove();
        $form.find('footer').remove();
        $buttonbar_wrapper.append($buttonbar);
        $form.find('fieldset:last').after($buttonbar_wrapper);
    }
    /*
    else if (context == 'mediapool_media') {
        $('[name="file_new"]').closest('dl').remove();
        $form.find('.rex-form-panel-footer').remove();
        $form.find('.form-control-static').closest('dl').addClass('preserve');
        $buttonbar_wrapper = $('<div class="row"><div class="col-sm-12"></div></div>');
        $buttonbar_wrapper.find('div').append($buttonbar);
        $form.append($buttonbar_wrapper);
    }
    */
    else if (context == 'addon_upload') {
        $buttonbar_wrapper.append($buttonbar);
        $form.find('fieldset').after($buttonbar_wrapper);
        // metainfos holen
        $mediacatselect.trigger('change');
    }

    $form.fileupload(get_fileupload_options());

    $form.bind('fileuploadadded', function(e, data) {
        $(data.context[0]).find('.preview').append(get_mime_icon(data.files[0].name));
    });

    $('#resize-images').on('click', function () {
        $form.fileupload('destroy');
        $form.fileupload(get_fileupload_options());
    });

    $form.bind('fileuploadcompleted', function(e, data) {
        var $li = $(data.context[0]);
        if (data.result.files[0].hasOwnProperty('error')) {
            return true;
        }
    });

    $form.bind('fileuploadprocessfail', function (e, data) {
        var $li = $(data.context[0]);
        $li.find('.size').remove();
        $li.find('.preview').addClass('warning').append(get_mime_icon(data.files[0].name));
    });

    // datei nach upload uebernehmen
    $form.on('click', '.btn-select', function(e) {
        e.preventDefault();
        selectMedia($(this).data('filename'), '');
    });

});