<?php
declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use PDO;
use PDOException;

/**
 * Stellt eine Verbindung zur OXID-Datenbank her
 * und bietet Methoden zum Abrufen von Produkten und Kategorien.
 */
class OxidConnector
{
    private array $dbConfig;
    private PDO $pdo;

    /**
     * Konstruktor – Initialisiert die Verbindung zur OXID-Datenbank
     */
    public function __construct(array $dbConfig)
    {
        $this->dbConfig = $dbConfig;
        $this->connect();
    }

    /**
     * Stellt die Verbindung zur OXID-Datenbank her
     */
    private function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->dbConfig['host'],
            $this->dbConfig['port'] ?? 3306,
            $this->dbConfig['dbname']
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->dbConfig['user'],
                $this->dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('Fehler bei der Verbindung zur OXID-Datenbank: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------
    // 🗂 KATEGORIEN
    // ------------------------------------------------------------

    /**
     * Holt alle Kategorien aus der OXID-Datenbank (auch inaktive)
     */
    public function fetchCategories(): array
    {
        $sql = 'SELECT * FROM oxcategories';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------
    // 📦 PRODUKTE
    // ------------------------------------------------------------

    /**
     * Holt alle Produkte aus der OXID-Datenbank (ohne Varianten)
     */
    public function fetchProducts(): array
    {
        $sql = 'SELECT * FROM oxarticles WHERE OXPARENTID = "" OR OXPARENTID IS NULL';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------
    // 🧩 PRODUKT-BILDVERKNÜPFUNGEN
    // ------------------------------------------------------------

    /**
     * Holt alle zugehörigen Produktbilder für ein OXID-Produkt
     */
    public function fetchImages(array $product): array
    {
        $images = [];
        for ($i = 1; $i <= 12; $i++) {
            $col = "OXPIC{$i}";
            if (!empty($product[$col])) {
                $images[] = rtrim($this->dbConfig['image_base_path'], '/') . '/' . $i . '/' . $product[$col];
            }
        }
        return $images;
    }

    // ------------------------------------------------------------
    // 🔗 PRODUKT–KATEGORIE ZUORDNUNG
    // ------------------------------------------------------------

    /**
     * Liefert die Zuordnungen von Produkten zu Kategorien aus oxobject2category
     *
     * @return array Array mit ['oxobjectid' => 'Produkt-ID', 'oxcatid' => 'Kategorie-ID']
     */
    public function fetchProductCategoryRelations(): array
    {
        $sql = 'SELECT oxobjectid, oxcatid FROM oxobject2category';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Holt alle Produkt-Kategorie-Zuordnungen aus OXID
     *
     * @return array<int, array{product_id: string, category_id: string}>
     */
    public function fetchProductCategoryAssignments(): array
    {
        $stmt = $this->pdo->query('SELECT oxobjectid AS product_id, oxcatid AS category_id FROM oxobject2category');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
