class uploader_resizer_standalone {
  debug = false
  #options = null
  #shouldResize = false

  constructor() {
    const self = this
    this.#options = window.uploader_options || null
    if (!this.#options) {
      self.#log('Image Resizer Standalone is not enabled.')
      return
    }
    this.#options.maxWidth = this.#options.imageMaxWidth || 0
    this.#options.maxHeight = this.#options.imageMaxHeight || 0

    const shouldResizeInput = document.querySelector('#resize-image')
    self.#shouldResize = shouldResizeInput && shouldResizeInput.checked
    const fileInput = document.querySelector('input[name="file_new"]')
    self.#options.fileInput = fileInput
    if (fileInput) {
      self.#log('Image Resizer Standalone Loaded')
      fileInput.addEventListener('change', function (event) {
        self.#processFiles(event.target.files)
      })
    } else {
      self.#log('No file input found for image resizing.')
    }
  }

  #log(...args) {
    if (this.debug) {
      console.log('Image Resizer Standalone:', ...args)
    }
  }

  #processFiles(files) {
    const self = this
    if (!this.#shouldResize) return
    if (files && files.length > 0) {
      const data = {
        files: Array.from(files),
        index: 0
      }

      // Process the image through the pipeline
      Promise.resolve(data)
        .then((d) => {
          self.#log(
            'Processing image:',
            d.files[d.index].name,
            d.files[d.index]
          )
          return d
        })
        .then((d) => self.#loadImageMetaData.call(self, d))
        .then((d) => self.#loadImage.call(self, d))
        .then((d) => self.#resizeImage.call(self, d))
        .then((d) => {
          d.canvas = d.preview
          return d
        })
        .then((d) => self.#saveImage.call(self, d))
        .then((d) => self.#saveImageMetaData.call(self, d))
        .then((d) => self.#setImage.call(self, d))
        .then((d) => self.#deleteImageReferences.call(self, d))
        .then((d) => {
          self.#log(
            'Image processing completed:',
            d.files[d.index].name,
            self.#options
          )
          // Replace the original file input with the processed file
          const originalInput = document.querySelector('input[name="file_new"]')
          if (originalInput) {
            const dataTransfer = new DataTransfer()
            // Convert Blob to File if necessary
            const processedFile = d.files[d.index]
            const file =
              processedFile instanceof File
                ? processedFile
                : new File(
                    [processedFile],
                    processedFile.name || 'processed_image.jpg',
                    {
                      type: processedFile.type || 'image/jpeg'
                    }
                  )

            dataTransfer.items.add(file)
            originalInput.files = dataTransfer.files
            self.#log(
              'Processed Image assigned to original input:',
              originalInput
            )
          }
        })
        .catch((error) => console.error('Image processing error:', error))
    }
  }

  #loadImageMetaData(data) {
    const that = this
    that.#log('loadImageMetaData', data)
    if (that.#options.disabled) {
      return data
    }
    const // eslint-disable-next-line new-cap
      dfd = $.Deferred()
    loadImage.parseMetaData(
      data.files[data.index],
      function (result) {
        $.extend(data, result)
        dfd.resolveWith(that, [data])
      },
      that.#options
    )
    return dfd.promise()
  }

  #loadImage(data) {
    const that = this
    if (that.#options.disabled) {
      that.#log('loadImage', 'Disabled or no image to load')
      return data
    }
    that.#log('loadImage', data)
    const file = data.files[data.index],
      // eslint-disable-next-line new-cap
      dfd = $.Deferred()
    if (
      ($.type(that.#options.maxFileSize) === 'number' &&
        file.size > that.#options.maxFileSize) ||
      (that.#options.fileTypes && !that.#options.fileTypes.test(file.type)) ||
      !loadImage(
        file,
        function (img) {
          if (img.src) {
            data.img = img
          }
          dfd.resolveWith(that, [data])
        },
        that.#options
      )
    ) {
      return data
    }
    return dfd.promise()
  }

  #resizeImage(data) {
    const that = this
    if (that.#options.disabled || !(data.canvas || data.img)) {
      that.#log('resizeImage', 'No image to resize or disabled')
      return data
    }
    that.#log('resizeImage', data)
    // eslint-disable-next-line no-param-reassign
    that.#options = $.extend({ canvas: true }, that.#options)
    const // eslint-disable-next-line new-cap
      dfd = $.Deferred(),
      img = (that.#options.canvas && data.canvas) || data.img,
      resolve = function (newImg) {
        if (
          newImg &&
          (newImg.width !== img.width ||
            newImg.height !== img.height ||
            that.#options.forceResize)
        ) {
          data[newImg.getContext ? 'canvas' : 'img'] = newImg
        }
        data.preview = newImg
        dfd.resolveWith(that, [data])
      }
    let thumbnail, thumbnailBlob
    if (data.exif && that.#options.thumbnail) {
      thumbnail = data.exif.get('Thumbnail')
      thumbnailBlob = thumbnail && thumbnail.get('Blob')
      if (thumbnailBlob) {
        that.#options.orientation = data.exif.get('Orientation')
        loadImage(thumbnailBlob, resolve, that.#options)
        return dfd.promise()
      }
    }
    if (data.orientation) {
      // Prevent orienting the same image twice:
      delete that.#options.orientation
    } else {
      data.orientation = that.#options.orientation || loadImage.orientation
    }
    if (img) {
      that.#log('Resizing image:', img.width, 'x', img.height)
      resolve(loadImage.scale(img, that.#options, data))
      return dfd.promise()
    }
    return data
  }

  #saveImage(data) {
    const that = this
    if (!data.canvas || that.#options.disabled) {
      return data
    }
    const file = data.files[data.index],
      // eslint-disable-next-line new-cap
      dfd = $.Deferred()
    if (data.canvas.toBlob) {
      data.canvas.toBlob(
        function (blob) {
          if (!blob.name) {
            if (file.type === blob.type) {
              blob.name = file.name
            } else if (file.name) {
              blob.name = file.name.replace(/\.\w+$/, '.' + blob.type.substr(6))
            }
          }
          // Don't restore invalid meta data:
          if (file.type !== blob.type) {
            delete data.imageHead
          }
          // Store the created blob at the position
          // of the original file in the files list:
          data.files[data.index] = blob
          dfd.resolveWith(that, [data])
        },
        that.#options.type || file.type,
        that.#options.quality
      )
    } else {
      return data
    }
    return dfd.promise()
  }

  #saveImageMetaData(data) {
    const that = this
    if (
      !(
        data.imageHead &&
        data.canvas &&
        data.canvas.toBlob &&
        !that.#options.disabled
      )
    ) {
      that.#log(
        'saveImageMetaData',
        'No imageHead or canvas.toBlob, skipping saveImageMetaData',
        data
      )
      return data
    }
    that.#log('saveImageMetaData', data)

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
      dfd.resolveWith(that, [data])
    })
    return dfd.promise()
  }

  // Sets the resized version of the image as a property of the
  // file object, must be called after "saveImage":
  #setImage(data) {
    const that = this
    if (data.preview && !that.#options.disabled) {
      that.#log('setImage', data)
      data.files[data.index][that.#options.name || 'preview'] = data.preview
    }
    return data
  }

  #deleteImageReferences(data) {
    const that = this
    that.#log('deleteImageReferences', data)
    if (!that.#options.disabled) {
      delete data.img
      delete data.canvas
      delete data.preview
      delete data.imageHead
    }
    return data
  }
}
document.addEventListener('DOMContentLoaded', function () {
  new uploader_resizer_standalone()
})

$(document).on('pjax:success', function () {
  new uploader_resizer_standalone()
})
