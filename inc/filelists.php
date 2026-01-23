<?php
$add_to_widget = '0';
if (rex_get('opener_input_field', 'string')) {
    $add_to_widget = '1';
}
return '
<script id="template-upload" type="text/x-tmpl">
{% for (var i=0, file; file=o.files[i]; i++) { %}
    <li class="template-upload fade">
        <div class="preview"></div>

        <p class="name">{%=file.name%}</p>
        <p class="size">'.$this->i18n('uploader_filelist_processing').'</p>
        <p class="error"></p>

        <div class="buttons">
            {% if (!i && !o.options.autoUpload) { %}
                <button style="display: none" class="btn btn-primary start" disabled>
                    <i class="glyphicon glyphicon-upload"></i>
                    <span>Start</span>
                </button>
            {% } %}
            {% if (!i) { %}
                <button class="btn btn-warning cancel">
                    <i class="glyphicon glyphicon-ban-circle"></i>
                    <span>'.$this->i18n('uploader_filelist_cancel').'</span>
                </button>
            {% } %}
            <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar progress-bar-success" style="width:0%;"></div></div>
        </div>
    </li>
{% } %}
</script>
<!-- The template to display files available for download -->
<script id="template-download" type="text/x-tmpl">
{% for (var i=0, file; file=o.files[i]; i++) { %}
    <li class="template-download fade">
        <div class="preview">
            {% if (file.thumbnailUrl) { %}
                <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" data-gallery><img src="{%=file.thumbnailUrl%}"></a>
            {% } %}
            {% if (file.icon) { %}
                <i class="rex-mime {%=file.iconclass%}" data-extension="{%=file.iconextension%}"></i>
            {% } %}
        </div>
        <p class="name">
            {% if (file.url) { %}
                <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" {%=file.thumbnailUrl?\'data-gallery\':\'\'%}>{%=file.name%}</a>
            {% } else { %}
                <span>{%=file.name%}</span>
            {% } %}
        </p>
        
        {% if (file.error) { %}
            <p class="error">{%=file.error%}</p>
        {% } else { %}
            <p class="size">{%=o.formatFileSize(file.size)%}</p>
        {% } %}
        <div class="buttons">
            <button style="display: none" class="btn btn-warning cancel">
                <i class="glyphicon glyphicon-ban-circle"></i>
                <span>'.$this->i18n('uploader_filelist_cancel').'</span>
            </button>
            {% if ('.$add_to_widget.' && !file.error) { %}
                <a class="btn btn-xs btn-select" data-filename="{%=file.name%}">'.$this->i18n('uploader_filelist_add_to_widget').'</a>
            {% } %}
        </div>
    </li>
{% } %}
</script>
';