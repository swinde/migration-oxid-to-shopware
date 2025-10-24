# 🧩 Migration OXID → Shopware 6

**Version:** `0.3.0-beta`  
**Autor:** MigrationSwinde  
**Lizenz:** MIT  
**Kompatibel mit:** Shopware 6.5+  
**Status:** Beta – Kategorien werden bereits erfolgreich migriert, Produktmigration in Arbeit.

---

## 🚀 Übersicht

Dieses Plugin ermöglicht die **Migration von Kategorien, Produkten und Medien**  
aus einem bestehenden **OXID eShop** in eine **Shopware 6-Installation**.

Der aktuelle Stand (`0.3.0-beta`) unterstützt:

| Bereich | Status | Beschreibung |
|----------|--------|--------------|
| 🗂 Kategorien | ✅ Fertig | Kategorien (inkl. Beschreibung & Hierarchie) werden migriert |
| 📦 Produkte | ⚙️ In Arbeit | Produkte mit Preisen und Beständen (ohne Medien) |
| 🖼 Medien | 🚧 Geplant | Medien-Uploads & Zuordnung zu Produkten |
| 🔗 Zuordnungen | 🚧 Geplant | Produkt–Kategorie-Mappings nach Migration |

---

## ⚙️ Installation

1. Lege das Plugin im Shopware-Verzeichnis ab:
   ```bash
   custom/plugins/MigrationSwindeMigrationOxidToShopware
   ```

2. Führe die Installation durch:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate MigrationSwindeMigrationOxidToShopware
   ```

3. Lösche den Cache:
   ```bash
   bin/console cache:clear
   ```

---

## 🧩 Konfiguration

Im Shopware Admin-Panel findest du das Plugin unter:

**Einstellungen → System → Plugins → Migration OXID → Shopware**

### Datenbankverbindung (OXID)

| Feld | Beschreibung | Beispiel |
|------|---------------|----------|
| `oxidHost` | Hostname der OXID-Datenbank | `localhost` |
| `oxidPort` | Port der Datenbank | `3306` |
| `oxidDatabase` | Name der Datenbank | `oxid_db` |
| `oxidUser` | Benutzername | `oxid_user` |
| `oxidPassword` | Passwort | `secret` |
| `oxidImageBasePath` | Pfad zu den Produktbildern | `/var/www/html/oxid/out/pictures/master/product` |

### Shopware API

| Feld | Beschreibung | Beispiel |
|------|---------------|----------|
| `apiUrl` | URL zur Shopware API | `https://meinshop.de` |
| `accessKeyId` | Admin-API Access Key ID | `SWIAK...` |
| `accessKeySecret` | Admin-API Access Key Secret | `SWIAS...` |

---

## 🧮 Nutzung

### Kategorien migrieren
```bash
bin/console migration:oxid --only=categories
```

### Produkte migrieren
```bash
bin/console migration:oxid --only=products
```

### Gesamte Migration
```bash
bin/console migration:oxid
```

### Konfiguration prüfen
```bash
bin/console migration:check-config
```

---

## 📁 Technische Struktur

| Datei / Verzeichnis | Zweck |
|----------------------|-------|
| `src/Service/OxidConnector.php` | Zugriff auf OXID-Datenbank |
| `src/Service/ShopwareConnector.php` | Verbindung zur Shopware 6 Admin-API |
| `src/Service/ProductMigrator.php` | Migration von Produkten |
| `src/Service/CategoryMigrator.php` | Migration von Kategorien (inkl. Hierarchie) |
| `src/Service/MediaUploader.php` | Upload von Produktbildern zu Shopware |
| `src/Service/ProductMigratorFactory.php` | Erstellt konfigurierten ProductMigrator |
| `src/Service/CategoryMigratorFactory.php` | Erstellt konfigurierten CategoryMigrator |
| `src/Command/MigrateOxidCommand.php` | Hauptkonsolenbefehl `migration:oxid` |
| `src/Command/CheckMigrationConfigCommand.php` | Prüft Plugin-Konfiguration |
| `src/Resources/category_map.json` | Zuordnung OXID→Shopware Kategorie-IDs |

---

## 🧠 Bekannte Einschränkungen

- Produktbilder werden noch nicht automatisch migriert.
- Varianten (OXID-Child-Produkte) sind aktuell deaktiviert.
- Produkt–Kategorie-Verknüpfung folgt nach Abschluss der CategoryMap-Integration.

---

## 🧩 Changelog

### `0.3.0-beta`
- Shopware API-Integration via OAuth Token  
- Kategorie-Migration inkl. Beschreibung & Hierarchie  
- CategoryMap-Persistenz als JSON-Datei  
- SystemConfig-basierte Konfiguration  
- CLI-Befehle `migration:oxid` & `migration:check-config`

---

## 🧑‍💻 Mitwirken

Pull Requests, Issues und Feedback sind willkommen!  
Bitte beschreibe deinen Anwendungsfall oder das Problem so konkret wie möglich.

---

### 🧩 Lizenz

Dieses Projekt steht unter der **MIT-Lizenz**.
