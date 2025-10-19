# 🧩 MigrationSwindeMigrationOxidToShopware

**⚠️ Dieses Plugin befindet sich in aktiver Entwicklung und ist aktuell NICHT für den Produktiveinsatz geeignet.**  
Es dient ausschließlich zu Test- und Entwicklungszwecken im Rahmen der OXID → Shopware 6 Migration.

---

## 🧭 Zweck

Dieses Plugin soll eine automatisierte Migration von Artikeldaten und Mediendateien  
aus einem bestehenden **OXID eShop** in eine **Shopware 6 Installation** ermöglichen.

Ziel ist es, relevante Produktinformationen, Varianten, Bilder und Zuordnungen  
über die Shopware Admin API zu übertragen und in der Datenbank zu persistieren.

---

## ⚙️ Installation (Entwickler)

1. Plugin in das Verzeichnis:
   ```bash
   custom/plugins/MigrationSwindeMigrationOxidToShopware

2. Dann im Shopware-Root ausführen:

    ```bash
    bin/console plugin:refresh
    bin/console plugin:install --activate MigrationSwindeMigrationOxidToShopware

3. Anschließend Cache leeren:
    
    ```bash
    bin/console cache:clear

4. Zugangsdaten für OXID-DB und Shopware API im Admin-Backend unter
 Einstellungen → System → Plugins → MigrationSwindeMigrationOxidToShopware eintragen.

🚀 Migration starten

Wenn das Plugin installiert und konfiguriert ist, kann (später) die Migration per CLI gestartet werden:

    ```bash
    bin/console migration:oxid

(Der Befehl ist derzeit noch in Entwicklung und nicht aktiv.)

🧩 Entwicklungs-Setup

Branches nach Konvention:

| Zweck         | Präfix      | Beispiel                   |
| ------------- | ----------- | -------------------------- |
| Neue Features | `feature/`  | `feature/media-upload`     |
| Bugfixes      | `fix/`      | `fix/variant-error`        |
| Refactorings  | `refactor/` | `refactor/class-structure` |
| Tests         | `test/`     | `test/migration-run`       |
| Hotfixes      | `hotfix/`   | `hotfix/critical-db-error` |

🧑‍💻 Lizenz / Haftungsausschluss

Dieses Projekt befindet sich in der Entwicklungsphase.
Die Nutzung erfolgt auf eigenes Risiko – Datenverlust oder Fehlverhalten sind möglich.

Maintainer: @swinde
Version: Entwicklungsstand v0.1.0-dev


