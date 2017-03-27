# README #

Dieses Repository stellt weitere Tasks für den Task-Runner [robo.li](http://robo.li) zur Verfügung.

### Folgende zusätzliche Tasks werden zur Verfügung gestellt: ###

* PullDbViaSsh
      * Aktualisieren/Anlegen einer lokalen Kopie der Remote-Datenbank
* SyncFiles
      * Download von Dateien die auf dem Remote-System hinterlegt sind.

## Wie verwende ich den Task Runner? ##

### Installation ###
Zur Installation muss lediglich die Datei composer.json innerhalb der Konfiguration "require-dev" um folgende Zeile erweitert werden: 
```
#!json
"require-dev": {
   ...
   "cag/robo-tasks": "^1.0",
   ...
}
```

### Environment konfigurieren ###
Um die Synchronisation der Datenbank und Dateien verwenden zu können, müssen die entsprechenden Konfigurationen in der Datei .env angepasst werden. Siehe /example/.env

### Build-Pipeline erstellen ###
Unter /example/RoboFile.php liegt eine beispielhafte Build-Pipeline die als Basis für neue Projekte verwendet werden kann. Diese muss lediglich in das Rootverzeichnis des Projekts gelegt werden. Anschließend können die enthaltenen Tasks über den folgenden Befehl aufgerugen werden:
```
#!bash
./vendor/consolidation/robo/robo build:assets
```