<?php
// Benutzereinstellungen aus der AddOn-Konfiguration laden
$resize = $this->getConfig('image-resize-checked') == 'true' ? 'checked' : '';
$filenameAsTitle = $this->getConfig('filename-as-title-checked') == 'true' ? 'checked' : '';

// Verschiedene HTML-Templates basierend auf dem Kontext
if ($this->getProperty('context') == 'mediapool_media') {
    // Kompaktes Template für die Medien-Übersichtsseite
    $template = '
    <dl class="rex-form-group form-group preserve" id="uploader-row">
    <dt></dt>
    <dd>
    <!-- Die Dropzone für den Upload -->
    <div class="uploader-dropzone">
        <span class="hint">' . $this->i18n('buttonbar_dropzone') . '</span>
        <ul role="presentation" class="uploader-queue files"></ul>
    </div>
    <div class="row fileupload-buttonbar">
        <div class="col-lg-12">
            <!-- Der Dateiauswahl-Button -->
            <span class="btn btn-success fileinput-button">
                <i class="rex-icon rex-icon-add-file"></i>
                <span>' . $this->i18n('uploader_buttonbar_add_file') . '</span>
                <input type="file" name="files[]" multiple>
            </span>
            <button type="submit" class="btn btn-primary start">
                <i class="rex-icon rex-icon-save"></i>
                <span>' . $this->i18n('uploader_buttonbar_start_upload') . '</span>
            </button>
        </div>
    </div>
    <div class="row fileupload-options">
        <div class="col-lg-12">
            <label><input type="checkbox" '.$resize.' id="resize-images"> ' . $this->i18n('buttonbar_resize_image') . '</label>
        </div>
        <div class="col-lg-12">
            <label><input type="checkbox" '.$filenameAsTitle.' id="filename-as-title" name="filename-as-title" value="1"> ' . $this->i18n('buttonbar_filename_as_title') . '</label>
        </div>
    </div>
    </dd>
    </dl>
    ';
} else {
    // Vollständiges Template für Upload-Seite mit mehr Optionen
    $template = '
    <dl class="rex-form-group form-group preserve" id="uploader-row">
    <dt></dt>
    <dd>
    <!-- Die Dropzone für den Upload -->
    <div class="uploader-dropzone">
        <span class="hint">' . $this->i18n('buttonbar_dropzone') . '</span>
        <ul role="presentation" class="uploader-queue files"></ul>
    </div>
    <div class="row fileupload-buttonbar">
        <div class="col-lg-7">
            <!-- Der Dateiauswahl-Button -->
            <span class="btn btn-success fileinput-button">
                <i class="rex-icon rex-icon-add-file"></i>
                <span>' . $this->i18n('uploader_buttonbar_add_files') . '</span>
                <input type="file" name="files[]" multiple>
            </span>
            <button type="submit" class="btn btn-primary start">
                <i class="rex-icon rex-icon-save"></i>
                <span>' . $this->i18n('uploader_buttonbar_start_upload') . '</span>
            </button>
            <button type="reset" class="btn btn-danger cancel">
                <i class="rex-icon rex-icon-delete"></i>
                <span>' . $this->i18n('uploader_buttonbar_cancel') . '</span>
            </button>
        </div>
        <!-- Fortschrittsanzeige -->
        <div class="col-lg-5 fileupload-progress fade">
            <!-- Fortschrittsbalken -->
            <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar progress-bar-success" style="width:0%;"></div>
            </div>
            <!-- Erweiterte Fortschrittsinformationen -->
            <div class="progress-extended">&nbsp;</div>
        </div>
    </div>
    <div class="row fileupload-options">
        <div class="col-lg-12">
            <label><input type="checkbox" '.$resize.' id="resize-images"> ' . $this->i18n('buttonbar_resize_images') . '</label>
        </div>
        <div class="col-lg-12">
            <label><input type="checkbox" '.$filenameAsTitle.' id="filename-as-title" name="filename-as-title" value="1"> ' . $this->i18n('buttonbar_filename_as_title') . '</label>
        </div>
    </div>
    </dd>
    </dl>
    ';
}

// Das Template in ein verstecktes Container-Element packen, das via JavaScript eingeblendet wird
return '<div id="uploader-buttonbar-template" style="display: none">' . $template . '</div>';
