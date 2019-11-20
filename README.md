# Uploader

Upload mehrerer Dateien gleichzeitig in den Medienpool. Übergroße Bilder können vorher verkleinert werden.

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/uploader/assets/uploader_01.jpg)

## Fehler 

###Empty file upload result

Wahrscheinlich verhindern serverseitige Limits den Upload - bei PHP sind dies

```upload_max_filesize
post_max_size
max_execution_time
max_input_time
memory_limit
```

###Mittwald Special

Bei Mittwald gibt es einen Fehler, wenn PHP als FPM läuft. Wenn eine Umstellung auf CGI nicht möglich ist, gibts in diesem [Issue](https://github.com/FriendsOfREDAXO/uploader/issues/57) noch einen Tipp.

Basiert auf [jQuery-File-Upload](https://blueimp.github.io/jQuery-File-Upload/).  
Erste Version entwickelt von [@IngoWinter](https://github.com/IngoWinter).
