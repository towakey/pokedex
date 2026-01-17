# PokedexDB Library (Low-level)

This library exposes low-level access to the SQLite database and cache table.

## Usage

```php
<?php
require_once __DIR__ . '/PokedexDB.php';

$pokedexDb = new PokedexDB(__DIR__ . '/../pokedex.db');

// Simple read example
$row = $pokedexDb->querySingle(
    "SELECT id, globalNo FROM pokedex WHERE globalNo = :globalNo LIMIT 1",
    [':globalNo' => '0001']
);

// Cache example
$cacheKey = 'example_key';
$cached = $pokedexDb->fetchCache($cacheKey, 3600);
if ($cached === null) {
    $payload = json_encode(['ok' => true]);
    $pokedexDb->saveCache($cacheKey, $payload);
}
```

## API

- `PokedexDB::__construct($dbPath)`
- `PokedexDB::query($sql, $params = [])`
- `PokedexDB::querySingle($sql, $params = [])`
- `PokedexDB::execute($sql, $params = [])`
- `PokedexDB::lastInsertId()`
- `PokedexDB::getError()`
- `PokedexDB::fetchCache($cacheKey, $ttlSeconds = null)`
- `PokedexDB::saveCache($cacheKey, $payloadJson)`

### 直接Databaseを使いたい場合

- `Database::__construct($dbPath)`
- `Database::query($sql, $params = [])`
- `Database::querySingle($sql, $params = [])`
- `Database::execute($sql, $params = [])`
- `Database::lastInsertId()`
- `Database::getError()`
