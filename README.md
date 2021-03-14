# Uploader

Upload mehrerer Dateien gleichzeitig in den Medienpool. Übergroße Bilder können vorher verkleinert werden.

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/uploader/assets/uploader_01.jpg)

## Fehler 

### Empty file upload result

Wahrscheinlich verhindern serverseitige Limits den Upload - bei PHP sind dies

```upload_max_filesize
post_max_size
max_execution_time
max_input_time
memory_limit
```

### SyntaxError: JSON.parse: unexpected character at line 2 column 1 of the JSON data

Im Quellcode des Frontend schauen (egal welche Seite), ob vor dem Doctype noch etwas steht, das da nicht hingehört. Mögliche Ursachen:
* der XOutputfilter, der noch nen Kommentar zu viel ausgibt
* in der boot.php des Project-Addons ein  <?php , bei dem ein Leerzeichen danach zwingend erforderlich ist
* ein Addon, das ebenfalls für eine Ausgabe vorab sorgt

Basiert auf [jQuery-File-Upload](https://blueimp.github.io/jQuery-File-Upload/).  
Erste Version entwickelt von [@IngoWinter](https://github.com/IngoWinter).
