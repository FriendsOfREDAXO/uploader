<?php
$resize = $this->getConfig('image-resize-checked') == 'true' ? 'checked' : '';
$filenameAsTitle = $this->getConfig('filename-as-title-checked') == 'true' ? 'checked' : '';

$tmp = '
    <dl class="form-group rex-form-group preserve" id="uploader-row">
    <dt></dt>
    <dd>
    <!-- The table listing the files available for upload/download -->
    <div class="uploader-dropzone"><span class="hint">' . $this->i18n('buttonbar_dropzone') . '</span>
    <ul role="presentation" class="uploader-queue files"></ul>
    </div>
    <div class="row fileupload-buttonbar">
        <div class="col-lg-7">
            <!-- The fileinput-button span is used to style the file input field as button -->
            <span class="btn btn-success fileinput-button">
                <i class="glyphicon glyphicon-plus"></i>
                <span>' . $this->i18n('uploader_buttonbar_add_files') . '</span>
                <input type="file" name="files[]" multiple="">
            </span>
            <button type="submit" class="btn btn-primary start">
                <i class="glyphicon glyphicon-upload"></i>
                <span>' . $this->i18n('uploader_buttonbar_start_upload') . '</span>
            </button>
            <button type="reset" class="btn btn-warning cancel">
                <i class="glyphicon glyphicon-ban-circle"></i>
                <span>' . $this->i18n('uploader_buttonbar_cancel') . '</span>
            </button>
        </div>
        <!-- The global progress state -->
        <div class="col-lg-5 fileupload-progress fade">
            <!-- The global progress bar -->
            <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar progress-bar-success" style="width:0%;"></div>
            </div>
            <!-- The extended global progress state -->
            <div class="progress-extended">&nbsp;</div>
        </div>
    </div>
    <div class="row fileupload-options">
        <div class="col-lg-12">
            <label><input type="checkbox" ' . $resize . ' id="resize-images"> ' . $this->i18n('buttonbar_resize_images') . '</label>
        </div>
        <div class="col-lg-12">
            <label><input type="checkbox" ' . $filenameAsTitle . ' id="filename-as-title" name="filename-as-title" value="1"> ' . $this->i18n('buttonbar_filename_as_title') . '</label>
        </div>
    </div>
    </dd>
    </dl>
    ';

return '<div id="uploader-buttonbar-template" style="display: none">' . $tmp . '</div>';
