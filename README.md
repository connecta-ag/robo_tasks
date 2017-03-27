# README #

Dieses Repository stellt weitere Tasks für den Task-Runner [robo.li](http://robo.li) zur Verfügung.

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
# Zusätzliche Tasks #

Folgende zusätzliche Tasks werden zur Verfügung gestellt:

## PullDbViaSsh ##
Datenbank auf aktuellen Stand des Remote-Systems angleichen. Falls keine Datenbank unter dem angegebenen Namen verfügbar ist wird diese angelegt.
```
#!php

$this->taskPullDbViaSsh()
      ->sshHost(getenv('CONTENT_SYNC_HOST'))
      ->sshUser(getenv('CONTENT_SYNC_SSH_USER'))
      ->sshKey(getenv('CONTENT_SYNC_SSH_KEY'))
      ->remoteDbHost(getenv('CONTENT_SYNC_DATABASE_REMOTE_HOST'))
      ->remoteDbUser(getenv('CONTENT_SYNC_DATABASE_REMOTE_DB_USER'))
      ->remoteDbName(getenv('CONTENT_SYNC_DATABASE_REMOTE_DB_NAME'))
      ->remoteDbPass(getenv('CONTENT_SYNC_DATABASE_REMOTE_DB_PASS'))
      ->localDbName(getenv('DB_NAME'))
      ->localDbPass(getenv('DB_PASS'))
      ->run();
```


## SyncFiles ##
Dateien in angegebenen Ordnern herunterladen und lokal zur Verfügung stellen.
```
#!php

$this->taskSyncFiles()
    ->host(getenv('CONTENT_SYNC_HOST'))
    ->folders(getenv('CONTENT_SYNC_FOLDERS'))
    ->remoteUser(getenv('CONTENT_SYNC_SSH_USER'))
    ->remoteBasePath(getenv('CONTENT_SYNC_FILES_HOST_BASE_PATH'))
    ->localBasePath(self::BASE_DIR)
    ->localPathCorrection(getenv('CONTENT_SYNC_FILES_LOCAL_BASE_PATH_CORRECTION'))
    ->run();
```