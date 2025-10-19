# 📜 Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und das Projekt hält sich an [Semantic Versioning](https://semver.org/lang/de/).

---

## [Unreleased]

### 🔧 Geplant
- Verbindung zur OXID-Datenbank herstellen und testen
- CLI-Befehl `bin/console migration:oxid` aktivieren
- Datenvalidierung und Fehlermanagement verbessern
- Migration von Medien und Varianten umsetzen

---

## [0.1.0-dev] – 2025-10-19

### 🚀 Initialer Stand
- Plugin-Skeleton erstellt
- Grundstruktur für:
    - Command-Klassen (`MigrationCommand`, `MigrateProductsCommand`, `MigrateOxidCommand`)
    - Service-Klassen (`ProductMigrator`, `ShopwareConnector`, `OxidConnector`, `MediaUploader`)
- Erste `config.xml`-Version für Plugin-Einstellungen im Backend
- Composer-Konfiguration erstellt
- PHPUnit-Stub hinzugefügt
- DDEV-kompatibles Setup

---

## 📘 Hinweise zur Versionierung

- **MAJOR** (`1.0.0`) – inkompatible Änderungen
- **MINOR** (`0.2.0`) – neue Features, abwärtskompatibel
- **PATCH** (`0.1.1`) – Fehlerbehebungen, kleine Anpassungen

---

👷‍♂️ **Maintainer:** [@swinde](https://github.com/swinde)  
📅 **Stand:** 2025-10-19
