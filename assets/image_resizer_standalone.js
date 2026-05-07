class uploader_resizer_standalone {
  debug = false
  #options = null
  #shouldResize = false

  #form = null
  #shouldResizeInput = null
  #imgSizeWrapper = null
  #imgSizwErrorWrapper = null
  #oldSizeLabel = null
  #newSizeLabel = null
  #fileInput = null
  #fileTrigger = null
  #fileNameInput = null
  #currentImageLink = null
  #currentImagePreview = null
  #currentImageSize = null
  #currentImageStatus = null
  #currentImageObjectUrl = null

  #originalCurrentImageLinkHref = ''
  #originalCurrentImagePreviewSrc = ''
  #originalCurrentImageSizeText = ''
  #originalCurrentImageStatusText = ''
  #originalCurrentImageStatusClass = ''

  #data = {
    files: [],
    index: 0
  }

  constructor() {
    const self = this
    this.#options = window.uploader_options || null
    if (!this.#options) {
      self.#log('not enabled.')
      return
    }

    self.#shouldResizeInput = document.querySelector('#resize-image')
    if (!self.#shouldResizeInput) return

    self.#form = self.#shouldResizeInput.closest('form')
    self.#imgSizeWrapper = self.#form.querySelector('[data-new-size-wrap]')
    self.#newSizeLabel = self.#form.querySelector('[data-new-size]')
    self.#oldSizeLabel = self.#form.querySelector('[data-old-size]')
    self.#imgSizwErrorWrapper = self.#form.querySelector(
      '[data-new-size-error]'
    )

    self.#fileInput = self.#form.querySelector('input[name="file_new"]')
    self.#fileTrigger = self.#form.querySelector('[data-uploader-file-trigger]')
    self.#fileNameInput = self.#form.querySelector('[data-uploader-file-name]')
    self.#currentImageLink = self.#form.querySelector('[data-current-image-link]')
    self.#currentImagePreview = self.#form.querySelector(
      '[data-current-image-preview]'
    )
    self.#currentImageSize = self.#form.querySelector('[data-current-image-size]')
    self.#currentImageStatus = self.#form.querySelector(
      '[data-current-image-status]'
    )

    self.#originalCurrentImageLinkHref = self.#currentImageLink
      ? self.#currentImageLink.getAttribute('href') || ''
      : ''
    self.#originalCurrentImagePreviewSrc = self.#currentImagePreview
      ? self.#currentImagePreview.getAttribute('src') || ''
      : ''
    self.#originalCurrentImageSizeText = self.#currentImageSize
      ? self.#currentImageSize.textContent || ''
      : ''
    self.#originalCurrentImageStatusText = self.#currentImageStatus
      ? self.#currentImageStatus.textContent || ''
      : ''
    self.#originalCurrentImageStatusClass = self.#currentImageStatus
      ? self.#currentImageStatus.className || ''
      : ''

    if (self.#fileTrigger && self.#fileInput) {
      self.#fileTrigger.addEventListener('click', function (event) {
        event.preventDefault()
        self.#fileInput.click()
      })
    }

    if (self.#fileInput) {
      self.#log('Loaded')
      self.#fileInput.addEventListener('change', function (event) {
        if (self.#fileNameInput) {
          self.#fileNameInput.value =
            event.target.files && event.target.files.length > 0
              ? event.target.files[0].name
              : ''
        }
        self.#handleFileSelection(event.target.files)
      })
    } else {
      self.#log('No file input found for image resizing.')
    }

    // Some REDAXO pages replace/duplicate file fields dynamically.
    self.#form.addEventListener('change', function (event) {
      const input = event.target.closest('input[type="file"][name="file_new"]')
      if (!input) return

      self.#fileInput = input
      if (self.#fileNameInput) {
        self.#fileNameInput.value =
          input.files && input.files.length > 0 ? input.files[0].name : ''
      }
      self.#handleFileSelection(input.files)
    })

    self.#shouldResizeInput.addEventListener('change', function () {
      self.#shouldResize = self.#shouldResizeInput.checked
      if (self.#shouldResize && self.#data.files.length) {
        self.#log('shouldResizeInput change: true: showing new size info')
        self.#updateFileInput(self.#data.files[0])
        self.#updateSizeInfo.call(self)
        self.#showSizeInfo.call(self)
      } else if (
        self.#shouldResize &&
        !self.#data.files.length &&
        self.#fileInput &&
        self.#fileInput.files.length
      ) {
        self.#log('shouldResizeInput change: true: no files to resize')
        self.#processFiles(self.#fileInput.files)
      } else {
        self.#log('shouldResizeInput change: false: hiding new size info')
        if (self.#options.originalFiles) {
          self.#log('Restoring original files:', self.#options.originalFiles[0])
          self.#updateFileInput(self.#options.originalFiles[0])
        }
        self.#hideSizeInfo.call(self)
      }
    })
  }

  #log(...args) {
    if (this.debug) {
      console.log('URS:', ...args)
    }
  }

  #isSvgFile(file) {
    if (!file) return false
    
    // Check MIME type first
    if (file.type === 'image/svg+xml') {
      return true
    }
    
    // Check file extension as fallback
    if (file.name) {
      const extension = file.name.toLowerCase().split('.').pop()
      return extension === 'svg'
    }
    
    return false
  }

  #isImageFile(file) {
    if (!file) return false

    if ((file.type || '').startsWith('image/')) {
      return true
    }

    if (file.name) {
      const extension = file.name.toLowerCase().split('.').pop()
      return [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'avif',
        'bmp',
        'tif',
        'tiff',
        'svg'
      ].includes(extension)
    }

    return false
  }

  #handleFileSelection(files) {
    const self = this

    if (!files || files.length === 0) {
      self.#restoreCurrentMediaInfo()
      self.#enableResizeOption()
      return
    }

    const file = files[0]
    self.#updateCurrentMediaInfo(file)
    
    if (self.#isSvgFile(file)) {
      self.#log('SVG file detected:', file.name)
      self.#disableResizeOption()
    } else {
      self.#enableResizeOption()
      self.#processFiles(files)
    }
  }

  #updateCurrentMediaInfo(file) {
    const self = this
    if (!self.#isImageFile(file)) {
      return
    }

    if (self.#currentImageObjectUrl) {
      URL.revokeObjectURL(self.#currentImageObjectUrl)
      self.#currentImageObjectUrl = null
    }

    const objectUrl = URL.createObjectURL(file)
    self.#currentImageObjectUrl = objectUrl

    if (self.#currentImagePreview) {
      self.#currentImagePreview.setAttribute('src', objectUrl)
    }
    if (self.#currentImageLink) {
      self.#currentImageLink.setAttribute('href', objectUrl)
    }

    const tempImage = new Image()
    tempImage.onload = function () {
      const width = tempImage.naturalWidth || tempImage.width || 0
      const height = tempImage.naturalHeight || tempImage.height || 0
      const maxWidth = self.#options.imageMaxWidth || 0
      const maxHeight = self.#options.imageMaxHeight || 0
      const isOversized =
        (maxWidth > 0 && width > maxWidth) || (maxHeight > 0 && height > maxHeight)

      if (self.#currentImageSize) {
        self.#currentImageSize.textContent =
          width +
          ' × ' +
          height +
          ' px (max. ' +
          maxWidth +
          ' × ' +
          maxHeight +
          ')'
      }

      if (self.#currentImageStatus) {
        self.#currentImageStatus.className =
          'label ' + (isOversized ? 'label-warning' : 'label-success')
        self.#currentImageStatus.textContent = isOversized
          ? self.#options.messages.oversized
          : self.#options.messages.withinLimits
      }
    }
    tempImage.src = objectUrl
  }

  #restoreCurrentMediaInfo() {
    const self = this
    if (self.#currentImageObjectUrl) {
      URL.revokeObjectURL(self.#currentImageObjectUrl)
      self.#currentImageObjectUrl = null
    }

    if (self.#currentImagePreview) {
      self.#currentImagePreview.setAttribute('src', self.#originalCurrentImagePreviewSrc)
    }
    if (self.#currentImageLink) {
      self.#currentImageLink.setAttribute('href', self.#originalCurrentImageLinkHref)
    }
    if (self.#currentImageSize) {
      self.#currentImageSize.textContent = self.#originalCurrentImageSizeText
    }
    if (self.#currentImageStatus) {
      self.#currentImageStatus.textContent = self.#originalCurrentImageStatusText
      self.#currentImageStatus.className = self.#originalCurrentImageStatusClass
    }
  }

  #disableResizeOption() {
    const self = this
    if (self.#shouldResizeInput) {
      self.#shouldResizeInput.checked = false
      self.#shouldResizeInput.disabled = true
      
      // Find the label and add explanatory text
      const label = self.#shouldResizeInput.closest('label')
      if (label && !label.querySelector('.svg-notice')) {
        const notice = document.createElement('span')
        notice.className = 'svg-notice text-muted'
        notice.style.display = 'block'
        notice.style.fontSize = '0.9em'
        notice.style.marginTop = '5px'
        notice.textContent = (self.#options && self.#options.messages && self.#options.messages.svgNotice) || 
                             'SVG files are not resized as they are vector-based.'
        label.appendChild(notice)
      }
      
      self.#hideSizeInfo()
    }
  }

  #enableResizeOption() {
    const self = this
    if (self.#shouldResizeInput) {
      // Don't re-enable if locked by admin config
      if (!(self.#options && self.#options.imageResizeLocked)) {
        self.#shouldResizeInput.disabled = false
      }

      // Remove SVG notice if present
      const label = self.#shouldResizeInput.closest('label')
      if (label) {
        const notice = label.querySelector('.svg-notice')
        if (notice) {
          notice.remove()
        }
      }
    }
  }

  #processFiles(files) {
    const self = this
    self.#shouldResize =
      (self.#options && self.#options.imageResizeLocked) ||
      (self.#shouldResizeInput && self.#shouldResizeInput.checked)
    if (!self.#shouldResize) return

    if (self.#options.canvas) delete self.#options.canvas
    if (self.#options.maxWidth) delete self.#options.maxWidth
    if (self.#options.maxHeight) delete self.#options.maxHeight

    self.#hideSizeInfo.call(self)

    if (files && files.length > 0) {
      // Check if the file is an SVG - if so, skip processing
      const file = files[0]
      if (self.#isSvgFile(file)) {
        self.#log('SVG file detected, skipping resize processing:', file.name)
        return
      }

      const data = {
        files: Array.from(files),
        index: 0
      }
      self.#data = data
      self.#options.originalFiles = [...data.files]
      self.#log('Storing original files:', self.#options.originalFiles)
      self.#imgSizeWrapper.style.display = 'none'

      // Process the image through the pipeline
      Promise.resolve(data)
        .then(async (data) => {
          self.#log('Processing image:', { ...data })
          data = await self.#loadImageMetaData.call(self, data)
          self.#log('Metadata loaded', { ...data })
          data = await self.#loadImage.call(self, data)
          if (!data.img) {
            self.#imgSizwErrorWrapper.style.display = 'block'
            self.#log('Image loading failed or no image found in the file.')
            throw new Error(
              'Image loading failed or no image found in the file.'
            )
          } else self.#log('Image loaded', { ...data })
          // Store original dimensions so #saveImage can detect if the image
          // was actually scaled down. If not, the original file is kept as-is
          // to avoid re-encoding (which can increase file size for optimised images).
          data.originalWidth = data.img ? (data.img.naturalWidth || data.img.width || 0) : 0
          data.originalHeight = data.img ? (data.img.naturalHeight || data.img.height || 0) : 0
          self.#options.maxWidth = self.#options.imageMaxWidth || 0
          self.#options.maxHeight = self.#options.imageMaxHeight || 0
          data = await self.#resizeImage.call(self, data)
          self.#log('Image resized', { ...data })
          data.canvas = data.preview
          data = await self.#saveImage.call(self, data)
          self.#log('Image saved', { ...data })
          data = await self.#saveImageMetaData.call(self, data)
          self.#log('Image metadata saved', { ...data })
          data = await self.#setImage.call(self, data)
          self.#log('Image set', { ...data })
          self.#log('Image processing completed:', { ...data })
          self.#updateFileInput.call(self, self.#data.files[self.#data.index])
          self.#updateSizeInfo.call(self)
          self.#showSizeInfo.call(self)
          data = await self.#deleteImageReferences.call(self, data)
          self.#data = data
          data = null
          self.#log('Image references deleted', { ...data })
        })
        .catch((error) => console.error('Image processing error:', error))
    }
  }

  #loadImageMetaData(data) {
    const self = this
    self.#log('loadImageMetaData')
    if (self.#options.disabled) {
      return data
    }
    const // eslint-disable-next-line new-cap
      dfd = $.Deferred()
    loadImage.parseMetaData(
      data.files[data.index],
      function (result) {
        $.extend(data, result)
        dfd.resolveWith(self, [data])
      },
      self.#options
    )
    return dfd.promise()
  }

  #loadImage(data) {
    const self = this
    if (self.#options.disabled) {
      self.#log('loadImage', 'Disabled or no image to load')
      return data
    }
    const file = data.files[data.index],
      // eslint-disable-next-line new-cap
      dfd = $.Deferred()
    self.#log('loadImage', file)
    if (
      ($.type(self.#options.maxFileSize) === 'number' &&
        file.size > self.#options.maxFileSize) ||
      (self.#options.fileTypes && !self.#options.fileTypes.test(file.type)) ||
      !loadImage(
        file,
        function (img) {
          self.#log('loadImage: loaded:', img)
          if (img.src) {
            data.img = img
          }
          dfd.resolveWith(self, [data])
        },
        self.#options
      )
    ) {
      return data
    }
    return dfd.promise()
  }

  #resizeImage(data) {
    const self = this
    if (self.#options.disabled || !(data.canvas || data.img)) {
      self.#log('resizeImage', 'No image to resize or disabled')
      return data
    }
    self.#log('resizeImage')
    // eslint-disable-next-line no-param-reassign
    self.#options = $.extend({ canvas: true }, self.#options)
    const // eslint-disable-next-line new-cap
      dfd = $.Deferred(),
      img = (self.#options.canvas && data.canvas) || data.img,
      resolve = function (newImg) {
        if (
          newImg &&
          (newImg.width !== img.width ||
            newImg.height !== img.height ||
            self.#options.forceResize)
        ) {
          data[newImg.getContext ? 'canvas' : 'img'] = newImg
        }
        data.preview = newImg
        dfd.resolveWith(self, [data])
      }
    let thumbnail, thumbnailBlob
    if (data.exif && self.#options.thumbnail) {
      thumbnail = data.exif.get('Thumbnail')
      thumbnailBlob = thumbnail && thumbnail.get('Blob')
      if (thumbnailBlob) {
        self.#options.orientation = data.exif.get('Orientation')
        loadImage(thumbnailBlob, resolve, self.#options)
        return dfd.promise()
      }
    }
    if (data.orientation) {
      // Prevent orienting the same image twice:
      delete self.#options.orientation
    } else {
      data.orientation = self.#options.orientation || loadImage.orientation
    }
    if (img) {
      self.#log('Resizing image:', img.width, 'x', img.height)
      resolve(loadImage.scale(img, self.#options, data))
      return dfd.promise()
    }
    return data
  }

  #saveImage(data) {
    const self = this
    if (!data.canvas || self.#options.disabled) {
      self.#log('saveImage', 'No canvas or disabled, skipping saveImage')
      return data
    }
    self.#log('saveImage')
    const file = data.files[data.index],
      // eslint-disable-next-line new-cap
      dfd = $.Deferred()
    if (data.canvas.toBlob) {
      data.canvas.toBlob(
        function (blob) {
          if (!blob || typeof blob !== 'object') {
            // toBlob returned null (e.g. tainted canvas or browser limitation)
            dfd.resolveWith(self, [data])
            return
          }

          // If the canvas has the same dimensions as the original image, the image
          // was NOT scaled down (it was within limits). Skip re-encoding to preserve
          // the original file quality and avoid bloating pre-optimised images.
          const canvasW = data.canvas.width || 0
          const canvasH = data.canvas.height || 0
          const origW = data.originalWidth || 0
          const origH = data.originalHeight || 0
          if (
            origW > 0 &&
            origH > 0 &&
            canvasW === origW &&
            canvasH === origH
          ) {
            self.#log('saveImage: image within limits, keeping original file unchanged', {
              origW, origH, canvasW, canvasH
            })
            data.skipMetaDataSave = true
            dfd.resolveWith(self, [data])
            return
          }

          const originalType = file && file.type ? file.type : ''
          const originalName = file && file.name ? file.name : 'image'

          // Keep extension/type stable for mediapool replace.
          // If browser cannot encode the original format (e.g. AVIF), keep original file.
          if (originalType && blob.type && originalType !== blob.type) {
            self.#log('saveImage: browser output type differs, keep original file', {
              originalType,
              blobType: blob.type
            })
            data.skipMetaDataSave = true
            if (self.#imgSizwErrorWrapper) {
              self.#imgSizwErrorWrapper.style.display = 'block'
            }
            dfd.resolveWith(self, [data])
            return
          }

          if (!blob.name) {
            blob.name = originalName
          }
          delete data.skipMetaDataSave
          // Don't restore invalid meta data:
          if (originalType !== blob.type) {
            delete data.imageHead
          }
          // Store the created blob at the position
          // of the original file in the files list:
          data.files[data.index] = blob
          dfd.resolveWith(self, [data])
        },
        self.#options.type || file.type,
        self.#options.quality
      )
    } else {
      return data
    }
    return dfd.promise()
  }

  #saveImageMetaData(data) {
    const self = this
    if (
      !(
        data.imageHead &&
        data.canvas &&
        data.canvas.toBlob &&
        !self.#options.disabled
      ) ||
      data.skipMetaDataSave
    ) {
      self.#log(
        'saveImageMetaData',
        'No imageHead or canvas.toBlob, skipping saveImageMetaData',
        { ...data }
      )
      return data
    }
    self.#log('saveImageMetaData')

    const file = data.files[data.index],
      // eslint-disable-next-line new-cap
      dfd = $.Deferred()
    if (data.orientation === true && data.exifOffsets) {
      // Reset Exif Orientation data:
      loadImage.writeExifData(data.imageHead, data, 'Orientation', 1)
    }
    loadImage.replaceHead(file, data.imageHead, function (blob) {
      console.log('Blob created:', blob)
      blob.name = file.name
      data.files[data.index] = blob
      dfd.resolveWith(self, [data])
    })
    return dfd.promise()
  }

  // Sets the resized version of the image as a property of the
  // file object, must be called after "saveImage":
  #setImage(data) {
    const self = this
    if (data.preview && !self.#options.disabled) {
      self.#log('setImage')
      data.files[data.index][self.#options.name || 'preview'] = data.preview
    }
    return data
  }

  #deleteImageReferences(data) {
    const self = this
    self.#log('deleteImageReferences')
    if (!self.#options.disabled) {
      delete data.canvas
      delete data.imageHead
      delete self.#data.canvas
      delete self.#data.imageHead
    }
    return data
  }

  #updateFileInput(file) {
    const self = this
    if (self.#fileInput && file) {
      // Replace the original file input with the processed file
      const dataTransfer = new DataTransfer()
      // Convert Blob to File if necessary
      const tempFile =
        file instanceof File
          ? file
          : new File([file], file.name || 'processed_image.jpg', {
              type: file.type || 'image/jpeg'
            })

      dataTransfer.items.add(tempFile)
      self.#fileInput.files = dataTransfer.files
      self.#log('Processed Image assigned to original input:', self.#fileInput)
    }
  }

  #updateSizeInfo() {
    const self = this
    if (self.#imgSizeWrapper) {
      // Update the new size label if resizing was done
      if (
        self.#data?.preview &&
        self.#data?.preview.width &&
        self.#data?.preview.height
      ) {
        self.#log(
          'Updating size info:',
          self.#data.preview.width,
          'x',
          self.#data.preview.height
        )
        self.#oldSizeLabel.textContent =
          self.#data.img.width + 'px / ' + self.#data.img.height + 'px'

        self.#newSizeLabel.textContent =
          self.#data.preview.width + 'px / ' + self.#data.preview.height + 'px'
      }
    }
  }

  #showSizeInfo() {
    const self = this
    self.#log('Showing new size info')
    self.#imgSizeWrapper.style.display = 'block'
  }

  #hideSizeInfo() {
    const self = this
    self.#log('Hiding new size info')
    self.#imgSizeWrapper.style.display = 'none'
    self.#newSizeLabel.textContent = ''
    self.#imgSizwErrorWrapper.style.display = 'none'
  }
}

document.addEventListener('DOMContentLoaded', function () {
  new uploader_resizer_standalone()
})

$(document).on('pjax:success', function () {
  new uploader_resizer_standalone()
})
