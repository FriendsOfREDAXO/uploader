# Uploader

Upload mehrerer Dateien gleichzeitig in den Medienpool. Übergroße Bilder können vorher verkleinert werden.

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/uploader/assets/uploader_01.jpg)

## Fehlermeldung _Empty file upload result_

Wahrscheinlich verhindern serverseitige Limits den Upload - bei PHP sind dies

```upload_max_filesize
post_max_size
max_execution_time
max_input_time
memory_limit
```

Basiert auf [jQuery-File-Upload](https://blueimp.github.io/jQuery-File-Upload/).  
Erste Version entwickelt von [@IngoWinter](https://github.com/IngoWinter).
