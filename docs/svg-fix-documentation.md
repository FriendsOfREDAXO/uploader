# SVG File Extension Fix Documentation

## Issue Description
When the "resize oversized image" option was enabled and a user tried to replace an SVG file with another SVG, the file extension would change from `.svg` to `.png`, preventing the file replacement from working.

## Root Cause
The HTML5 Canvas API used for image resizing converts SVG files to raster format (typically PNG) because canvas cannot maintain vector formats. This caused:
1. SVG files to be processed through the canvas
2. Canvas output to be a PNG blob  
3. File extension to be changed to match the blob type (PNG)
4. File replacement to fail due to extension mismatch

## Solution
Implemented SVG file detection to skip resize processing entirely for SVG files, since:
- SVGs are vector graphics and don't have resolution constraints like raster images
- SVGs are scalable by nature and don't need to be "resized" for file size optimization  
- Processing SVGs through canvas destroys their vector nature

## Implementation Details

### Key Changes Made

1. **SVG Detection Logic** (`#isSvgFile()`)
   - Checks MIME type first: `image/svg+xml`
   - Falls back to file extension check: `.svg` (case-insensitive)
   - Handles edge cases: null files, missing names, etc.

2. **File Selection Handler** (`#handleFileSelection()`)
   - Intercepts file selection before processing
   - Routes SVG files to disable resize option
   - Routes other files to normal processing

3. **User Experience Enhancement**
   - Automatically disables resize checkbox for SVG files
   - Shows informative translated message explaining why
   - Re-enables resize option when non-SVG file is selected

4. **Internationalization**
   - Added translated messages in all supported languages
   - Messages accessible via JavaScript through `uploader_options.messages.svgNotice`

### Files Modified

- `assets/image_resizer_standalone.js` - Main fix implementation
- `inc/vars.php` - Added translated message to JavaScript options
- `lang/de_de.lang` - German translation
- `lang/en_gb.lang` - English translation  
- `lang/es_es.lang` - Spanish translation
- `lang/sv_se.lang` - Swedish translation

## Backward Compatibility
✅ **No breaking changes** - All existing image formats (JPG, PNG, GIF, WebP, BMP, TIFF) continue to work exactly as before.

## Testing Performed
- SVG file detection accuracy (MIME type and extension based)
- Non-SVG file processing continues normally
- Edge case handling (null files, missing extensions, etc.)
- User interface behavior (checkbox disable/enable)
- Internationalization support

## Alignment with Existing Code
This solution aligns with the existing `BulkRework.php` which already includes SVG in the `SKIPPED_FORMATS` array, confirming that SVGs should not be processed for resizing.

## Future Considerations
- Monitor for any new vector formats that might need similar treatment
- Consider extending this pattern to other file types that shouldn't be resized (e.g., PDFs)
- Could potentially add configuration option to override SVG skipping if needed in specific use cases