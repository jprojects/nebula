<?php
declare(strict_types=1);

namespace Nebula\Import;

use Nebula\Core\Database;

/**
 * Correspondencia entre los registros de la aplicación de origen y los de Nebula (tabla import_map). Permite volver
 * a importar (p. ej. el día del cambio, con un volcado nuevo) sin duplicar nada: lo que ya se importó se actualiza,
 * salvo que se haya modificado en Nebula después de importarlo, que se respeta.
 */
final class ImportMap
{
    /** @var array<string,array<string,int>> "entidad" => [id de origen => id de Nebula] */
    private array $cache = [];

    public function __construct(private readonly Database $db, private readonly string $source)
    {
    }

    /** La tabla se crea al importar (no es de la tienda). Fuera de transacción: CREATE TABLE confirma la que haya. */
    public static function ensureTable(Database $db): void
    {
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS import_map (
            source       VARCHAR(30)  NOT NULL,
            entity       VARCHAR(30)  NOT NULL,
            source_id    VARCHAR(190) NOT NULL,
            target_id    INT UNSIGNED NOT NULL,
            imported_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (source, entity, source_id),
            KEY ix_import_map_target (entity, target_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function get(string $entity, string|int $sourceId): ?int
    {
        $this->load($entity);
        return $this->cache[$entity][(string) $sourceId] ?? null;
    }

    /** @return array<string,int> */
    public function all(string $entity): array
    {
        $this->load($entity);
        return $this->cache[$entity];
    }

    public function set(string $entity, string|int $sourceId, int $targetId): void
    {
        $this->db->query(
            'INSERT INTO import_map (source, entity, source_id, target_id, imported_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE target_id = VALUES(target_id), imported_at = VALUES(imported_at)',
            [$this->source, $entity, (string) $sourceId, $targetId]
        );
        $this->load($entity);
        $this->cache[$entity][(string) $sourceId] = $targetId;
    }

    /**
     * Fecha de importación = ahora, para los registros que se acaban de escribir (al final de cada paso: lo que se
     * escribe después de set() — slug, padre… — no debe parecer un cambio hecho en Nebula).
     * @param list<string|int> $sourceIds
     */
    public function touch(string $entity, array $sourceIds): void
    {
        foreach (array_chunk(array_values(array_unique(array_map('strval', $sourceIds))), 500) as $chunk) {
            $this->db->query(
                'UPDATE import_map SET imported_at = NOW() WHERE source = ? AND entity = ? AND source_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')',
                [$this->source, $entity, ...$chunk]
            );
        }
    }

    /**
     * ¿Se ha cambiado en Nebula después de importarlo? (updated_at posterior a la importación)
     * Entonces no se vuelve a sobrescribir.
     */
    public function changedSince(string $entity, string|int $sourceId, string $table): bool
    {
        $row = $this->db->fetch(
            "SELECT t.updated_at, m.imported_at FROM import_map m JOIN $table t ON t.id = m.target_id
             WHERE m.source = ? AND m.entity = ? AND m.source_id = ?",
            [$this->source, $entity, (string) $sourceId]
        );
        return $row !== null && $row['updated_at'] !== null && strtotime((string) $row['updated_at']) > strtotime((string) $row['imported_at']);
    }

    private function load(string $entity): void
    {
        if (isset($this->cache[$entity])) {
            return;
        }
        $this->cache[$entity] = [];
        foreach ($this->db->fetchAll('SELECT source_id, target_id FROM import_map WHERE source = ? AND entity = ?', [$this->source, $entity]) as $r) {
            $this->cache[$entity][(string) $r['source_id']] = (int) $r['target_id'];
        }
    }
}
