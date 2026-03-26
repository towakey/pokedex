<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$descriptionTagGeneratorShouldRunMain = !defined('DESCRIPTION_TAG_GENERATOR_SKIP_MAIN');

if ($descriptionTagGeneratorShouldRunMain && PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "CLI only\n";
    exit(1);
}

mb_internal_encoding('UTF-8');
ini_set('memory_limit', '1024M');
set_time_limit(0);

function usage(): string {
    return implode(PHP_EOL, [
        'Usage:',
        '  php pokedex/tags/generate_from_descriptions.php [--write-db] [--dry-run] [--output=path]',
        '    [--status=pending|approved|rejected] [--area=slug] [--max-tags=8]',
        '    [--min-score=14] [--min-occurrences=1] [--max-document-frequency=80]',
        '',
        'Examples:',
        '  php pokedex/tags/generate_from_descriptions.php --output=pokedex/tags/auto-tags.json',
        '  php pokedex/tags/generate_from_descriptions.php --write-db --status=pending',
        '  php pokedex/tags/generate_from_descriptions.php --write-db --dry-run --area=global',
    ]) . PHP_EOL;
}

function getDescriptionTagGeneratorDefaultOptions(): array {
    return [
        'write_db' => false,
        'dry_run' => false,
        'output' => '',
        'status' => 'pending',
        'area' => 'all',
        'max_tags' => 8,
        'min_score' => 14,
        'min_occurrences' => 1,
        'max_document_frequency' => 80,
    ];
}

function fail(string $message, int $exitCode = 1): never {
    if (defined('DESCRIPTION_TAG_GENERATOR_THROW_ERRORS') && DESCRIPTION_TAG_GENERATOR_THROW_ERRORS) {
        throw new RuntimeException($message, $exitCode);
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit($exitCode);
}

function readJsonFile(string $filePath): array {
    if (!is_file($filePath)) {
        fail('File not found: ' . $filePath);
    }

    $raw = file_get_contents($filePath);
    if ($raw === false) {
        fail('Failed to read file: ' . $filePath);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fail('Invalid JSON: ' . $filePath);
    }

    return $decoded;
}

function normalizeDescriptionTagGeneratorOptions(array $options): array {
    $defaults = getDescriptionTagGeneratorDefaultOptions();
    $status = trim((string)($options['status'] ?? $defaults['status']));

    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        throw new InvalidArgumentException('Invalid status value: ' . $status);
    }

    return [
        'write_db' => (bool)($options['write_db'] ?? $defaults['write_db']),
        'dry_run' => (bool)($options['dry_run'] ?? $defaults['dry_run']),
        'output' => trim((string)($options['output'] ?? $defaults['output'])),
        'status' => $status,
        'area' => trim((string)($options['area'] ?? $defaults['area'])),
        'max_tags' => max(1, (int)($options['max_tags'] ?? $defaults['max_tags'])),
        'min_score' => max(1, (int)($options['min_score'] ?? $defaults['min_score'])),
        'min_occurrences' => max(1, (int)($options['min_occurrences'] ?? $defaults['min_occurrences'])),
        'max_document_frequency' => max(1, (int)($options['max_document_frequency'] ?? $defaults['max_document_frequency'])),
    ];
}

function parseOptions(): array {
    $options = getopt('', [
        'help',
        'write-db',
        'dry-run',
        'output::',
        'status::',
        'area::',
        'max-tags::',
        'min-score::',
        'min-occurrences::',
        'max-document-frequency::',
    ]);

    if ($options === false) {
        fail('Failed to parse options.');
    }

    if (isset($options['help'])) {
        echo usage();
        exit(0);
    }

    return normalizeDescriptionTagGeneratorOptions([
        'write_db' => isset($options['write-db']),
        'dry_run' => isset($options['dry-run']),
        'output' => $options['output'] ?? '',
        'status' => $options['status'] ?? 'pending',
        'area' => $options['area'] ?? 'all',
        'max_tags' => $options['max-tags'] ?? 8,
        'min_score' => $options['min-score'] ?? 14,
        'min_occurrences' => $options['min-occurrences'] ?? 1,
        'max_document_frequency' => $options['max-document-frequency'] ?? 80,
    ]);
}

function normalizeScalar(mixed $value): string {
    return is_string($value) ? trim($value) : '';
}

function parsePokemonNo(string $value): int {
    $normalized = ltrim(trim($value), '0');
    if ($normalized === '') {
        return 0;
    }

    return ctype_digit($normalized) ? (int)$normalized : 0;
}

function parseNationalNoFromPokemonId(string $pokemonId): int {
    if (!preg_match('/^(\d{4})_/', $pokemonId, $matches)) {
        return 0;
    }

    return (int)$matches[1];
}

function normalizeText(string $text): string {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/<rt\b[^>]*>.*?<\/rt>/uis', '', $text) ?? $text;
    $text = preg_replace('/<rp\b[^>]*>.*?<\/rp>/uis', '', $text) ?? $text;
    $text = preg_replace('/<br\s*\/?>/iu', ' ', $text) ?? $text;
    $text = strip_tags($text);
    $text = preg_replace('/[\(（][^\)）]*(?:☆|★)[^\)）]*[\)）]/u', ' ', $text) ?? $text;
    $text = str_replace(["\r", "\n", "\t", '　', '…'], ' ', $text);
    $text = preg_replace('/[「」『』【】［］\[\]<>]/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function splitTokens(string $text): array {
    $tokens = preg_split('/[\s、。．，,！!？?／\/・:：;；]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($tokens)) {
        return [];
    }

    return array_values(array_filter(array_map(static fn ($token) => trim((string)$token), $tokens), static fn ($token) => $token !== ''));
}

function normalizeCandidateToken(string $token): string {
    $token = trim($token);
    if ($token === '') {
        return '';
    }

    $trimmed = preg_replace('/(から|まで|より|ほど|だけ|しか|こそ|でも|って|には|では|とは|へは|のは|は|が|を|に|へ|と|も|で|の|や|か|な)$/u', '', $token);
    if (is_string($trimmed) && $trimmed !== '' && mb_strlen($trimmed) >= 2) {
        return trim($trimmed);
    }

    return $token;
}

function extractCandidateSet(string $text): array {
    $rawTokens = splitTokens($text);
    $candidates = [];

    foreach ($rawTokens as $rawToken) {
        $token = normalizeCandidateToken($rawToken);
        if ($token === '') {
            continue;
        }

        if (isLikelyJapaneseWord($token) || isLikelyEnglishWord($token)) {
            $candidates[$token] = true;
        }
    }

    $tokenCount = count($rawTokens);
    for ($index = 0; $index <= $tokenCount - 3; $index++) {
        $left = normalizeCandidateToken($rawTokens[$index]);
        $middle = trim($rawTokens[$index + 1]);
        $right = normalizeCandidateToken($rawTokens[$index + 2]);

        if ($left === '' || $right === '') {
            continue;
        }

        if (!in_array($middle, ['の', 'な'], true)) {
            continue;
        }

        $phrase = $left . $middle . $right;
        if (mb_strlen($phrase) >= 3 && mb_strlen($phrase) <= 20) {
            $candidates[$phrase] = true;
        }
    }

    return $candidates;
}

function japaneseStopwords(): array {
    static $words = [
        'の', 'に', 'を', 'は', 'が', 'へ', 'で', 'と', 'も', 'や', 'か', 'な', 'ね', 'よ', 'から', 'まで', 'だけ', 'ほど',
        'ため', 'よう', 'もの', 'こと', 'ところ', 'とき', 'あいだ', 'あと', 'まえ', 'なか', 'そと', 'それ', 'これ', 'あれ',
        'この', 'その', 'あの', 'どの', 'ここ', 'そこ', 'あそこ', 'じぶん', 'みずから', 'みんな', 'ひと', 'もの', 'ばしょ',
        'すがた', 'ようす', 'ように', 'ような', 'という', 'として', 'ために', 'しばらく', 'なにも', 'たくさん', 'あらゆる',
        'すこしずつ', 'いっぱい', 'とても', 'もっと', 'すでに', 'たいてい', 'ふだん', 'じっと', 'いつも', 'ポケモン', 'からだ',
        '体', '自分', '相手', '仲間', '人', '場所', '力', '姿', '様子', 'ために', 'ことが', 'ものが'
    ];

    return array_fill_keys($words, true);
}

function englishStopwords(): array {
    static $words = [
        'the', 'and', 'for', 'with', 'from', 'into', 'onto', 'that', 'this', 'these', 'those', 'their', 'there', 'while', 'where',
        'which', 'what', 'when', 'then', 'than', 'have', 'has', 'had', 'been', 'being', 'were', 'was', 'are', 'is', 'its', 'it',
        'they', 'them', 'will', 'would', 'could', 'should', 'about', 'after', 'before', 'during', 'through', 'without', 'under',
        'over', 'very', 'some', 'more', 'most', 'just', 'only', 'also', 'into', 'your', 'their', 'pokemon'
    ];

    return array_fill_keys($words, true);
}

function isLikelyJapaneseWord(string $token): bool {
    $token = trim($token);
    if ($token === '' || isset(japaneseStopwords()[$token])) {
        return false;
    }

    $length = mb_strlen($token);
    if ($length < 2 || $length > 20) {
        return false;
    }

    if (preg_match('/^\d+$/u', $token) === 1) {
        return false;
    }

    if (preg_match('/(する|した|して|され|れる|られる|なる|なった|ない|たい|ようだ|という|ながら|たり|だろう|でした|です|ます|かもしれない|できる|みえる|見える|育つ|育った|育てる|生きる|住む|飛ぶ|集める)$/u', $token) === 1) {
        return false;
    }

    if (preg_match('/^[ぁ-ゖ]{1,2}$/u', $token) === 1) {
        return false;
    }

    if (preg_match('/^[ぁ-ゖ]{7,}$/u', $token) === 1) {
        return false;
    }

    if (preg_match('/\p{Han}[ぁ-ゖ]{4,}/u', $token) === 1 && !preg_match('/[ァ-ヶー]/u', $token)) {
        return false;
    }

    return preg_match('/[\p{Han}ぁ-ゖァ-ヶー]/u', $token) === 1;
}

function isLikelyEnglishWord(string $token): bool {
    $token = strtolower(trim($token));
    if ($token === '' || isset(englishStopwords()[$token])) {
        return false;
    }

    if (!preg_match('/^[a-z][a-z0-9-]{2,19}$/', $token)) {
        return false;
    }

    return !preg_match('/(ing|ed|tion|ment|ness|able|less|ally)$/', $token) || strlen($token) <= 5;
}

function createVersionFileIndex(string $sourcePokedexDir): array {
    $result = [];
    $entries = scandir($sourcePokedexDir);
    if ($entries === false) {
        return $result;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $directoryPath = $sourcePokedexDir . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($directoryPath)) {
            continue;
        }

        $preferred = $directoryPath . DIRECTORY_SEPARATOR . $entry . '.json';
        $candidates = is_file($preferred) ? [$preferred] : [];
        if ($candidates === []) {
            $files = scandir($directoryPath);
            if ($files === false) {
                continue;
            }
            foreach ($files as $fileName) {
                if (str_ends_with($fileName, '.json')) {
                    $candidates[] = $directoryPath . DIRECTORY_SEPARATOR . $fileName;
                }
            }
        }

        foreach ($candidates as $filePath) {
            $json = readJsonFile($filePath);
            if (isset($json['game_version']) && is_string($json['game_version']) && isset($json['pokedex']) && is_array($json['pokedex'])) {
                $result[$json['game_version']] = $filePath;
                break;
            }
        }
    }

    return $result;
}

function pickPreferredText(mixed $value): ?string {
    if (is_string($value)) {
        $text = normalizeText($value);
        return $text === '' ? null : $text;
    }

    if (!is_array($value)) {
        return null;
    }

    foreach (['jpn', 'ja', 'eng', 'en', 'default'] as $key) {
        if (isset($value[$key]) && is_string($value[$key])) {
            $text = normalizeText($value[$key]);
            if ($text !== '') {
                return $text;
            }
        }
    }

    foreach ($value as $entry) {
        if (is_string($entry)) {
            $text = normalizeText($entry);
            if ($text !== '') {
                return $text;
            }
        }
    }

    return null;
}

function addGroupText(array &$groups, string $area, int $no, ?string $text): void {
    if ($no <= 0 || $text === null || $text === '') {
        return;
    }

    $key = $area . ':' . $no;
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'area' => $area,
            'no' => $no,
            'texts' => [],
        ];
    }

    $groups[$key]['texts'][] = $text;
}

function collectGlobalGroups(array $descriptionData): array {
    $groups = [];

    foreach ($descriptionData as $formsValue) {
        if (!is_array($formsValue)) {
            continue;
        }

        foreach ($formsValue as $pokemonId => $versionMap) {
            if (!is_array($versionMap)) {
                continue;
            }

            $nationalNo = parseNationalNoFromPokemonId((string)$pokemonId);
            if ($nationalNo <= 0) {
                continue;
            }

            foreach ($versionMap as $localizedMap) {
                addGroupText($groups, 'global', $nationalNo, pickPreferredText($localizedMap));
            }
        }
    }

    return $groups;
}

function collectRegionalGroups(array $regionConfigMap, array $versionFileIndex): array {
    $groups = [];

    foreach ($regionConfigMap as $slug => $config) {
        if (!is_array($config)) {
            continue;
        }

        $versionKey = normalizeScalar($config['version_key'] ?? '');
        if ($versionKey === '' || !isset($versionFileIndex[$versionKey])) {
            continue;
        }

        $versionSource = readJsonFile($versionFileIndex[$versionKey]);
        $regionalPokedexMap = isset($versionSource['pokedex']) && is_array($versionSource['pokedex']) ? $versionSource['pokedex'] : [];
        if ($regionalPokedexMap === []) {
            continue;
        }

        $displayName = normalizeScalar($config['display_jpn'] ?? '');
        $index = max(0, (int)($config['pokedex_index'] ?? 0));
        $fallback = array_values($regionalPokedexMap)[$index] ?? null;
        $targetRegion = ($displayName !== '' && isset($regionalPokedexMap[$displayName]) && is_array($regionalPokedexMap[$displayName]))
            ? $regionalPokedexMap[$displayName]
            : (is_array($fallback) ? $fallback : null);

        if (!is_array($targetRegion)) {
            continue;
        }

        foreach ($targetRegion as $localDex => $formsValue) {
            if (!is_array($formsValue)) {
                continue;
            }

            foreach ($formsValue as $pokemonId => $pokemonEntry) {
                if (!is_array($pokemonEntry)) {
                    continue;
                }

                $nationalNo = parseNationalNoFromPokemonId((string)$pokemonId);
                if ($nationalNo <= 0) {
                    continue;
                }

                $descriptionValue = $pokemonEntry['description'] ?? null;
                if (is_array($descriptionValue)) {
                    foreach ($descriptionValue as $description) {
                        addGroupText($groups, (string)$slug, $nationalNo, pickPreferredText($description));
                    }
                    continue;
                }

                addGroupText($groups, (string)$slug, $nationalNo, pickPreferredText($descriptionValue));
            }
        }
    }

    return $groups;
}

function mergeGroups(array ...$groupsList): array {
    $merged = [];

    foreach ($groupsList as $groups) {
        foreach ($groups as $key => $group) {
            if (!isset($merged[$key])) {
                $merged[$key] = $group;
                continue;
            }

            $merged[$key]['texts'] = array_merge($merged[$key]['texts'], $group['texts']);
        }
    }

    return $merged;
}

function prepareGroupCandidates(array $groups, array $options): array {
    $prepared = [];
    $documentFrequency = [];

    foreach ($groups as $key => $group) {
        if ($options['area'] !== 'all' && $group['area'] !== $options['area']) {
            continue;
        }

        $texts = array_values(array_unique(array_filter(array_map(static fn ($text) => normalizeText((string)$text), $group['texts']), static fn ($text) => $text !== '')));
        if ($texts === []) {
            continue;
        }

        $candidateCounts = [];
        foreach ($texts as $text) {
            $candidates = extractCandidateSet($text);
            foreach (array_keys($candidates) as $candidate) {
                $candidateCounts[$candidate] = ($candidateCounts[$candidate] ?? 0) + 1;
            }
        }

        if ($candidateCounts === []) {
            continue;
        }

        foreach (array_keys($candidateCounts) as $candidate) {
            $documentFrequency[$candidate] = ($documentFrequency[$candidate] ?? 0) + 1;
        }

        $prepared[$key] = [
            'area' => $group['area'],
            'no' => $group['no'],
            'description_count' => count($texts),
            'candidate_counts' => $candidateCounts,
        ];
    }

    return [$prepared, $documentFrequency];
}

function isPhraseTag(string $tag): bool {
    return str_contains($tag, 'の') || str_contains($tag, 'な') || str_contains($tag, ' ') || str_contains($tag, '-');
}

function computeScore(string $tag, int $coverage, int $descriptionCount, int $documentFrequency): int {
    $score = $coverage * 10;
    $score += min(6, mb_strlen($tag));

    if (isPhraseTag($tag)) {
        $score += 4;
    }

    if (preg_match('/[\p{Han}ァ-ヶー]/u', $tag) === 1) {
        $score += 2;
    }

    if ($descriptionCount >= 4 && $coverage >= 2) {
        $score += 3;
    }

    if ($documentFrequency > 1) {
        $score -= min(12, (int)floor(log((float)$documentFrequency, 2)));
    }

    return $score;
}

function shouldSkipContainedTag(string $tag, array $selectedTags): bool {
    foreach ($selectedTags as $selectedTag) {
        if (mb_strlen($selectedTag) >= mb_strlen($tag) + 2 && mb_strpos($selectedTag, $tag) !== false) {
            return true;
        }
    }

    return false;
}

function selectTags(array $preparedGroups, array $documentFrequency, array $options): array {
    $result = [];

    foreach ($preparedGroups as $group) {
        $rows = [];
        $descriptionCount = (int)$group['description_count'];
        $baseMinCoverage = $descriptionCount >= 6 ? 2 : 1;
        $requiredCoverage = max($baseMinCoverage, (int)$options['min_occurrences']);

        foreach ($group['candidate_counts'] as $tag => $coverage) {
            $coverage = (int)$coverage;
            $documentCount = (int)($documentFrequency[$tag] ?? 1);
            $length = mb_strlen($tag);

            if ($coverage < $requiredCoverage) {
                continue;
            }

            if ($length < 2 || $length > 20) {
                continue;
            }

            if ($descriptionCount >= 4 && $coverage === 1 && !isPhraseTag($tag)) {
                continue;
            }

            if ($coverage === 1 && preg_match('/^[ぁ-ゖ]{2}$/u', $tag) === 1) {
                continue;
            }

            if ($documentCount > (int)$options['max_document_frequency'] && $coverage < 3) {
                continue;
            }

            $score = computeScore($tag, $coverage, $descriptionCount, $documentCount);
            if ($score < (int)$options['min_score']) {
                continue;
            }

            $rows[] = [
                'tag' => $tag,
                'score' => $score,
                'coverage' => $coverage,
                'document_frequency' => $documentCount,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return $right['score'] <=> $left['score']
                ?: $right['coverage'] <=> $left['coverage']
                ?: mb_strlen($right['tag']) <=> mb_strlen($left['tag'])
                ?: strcmp($left['tag'], $right['tag']);
        });

        $selected = [];
        foreach ($rows as $row) {
            if (count($selected) >= (int)$options['max_tags']) {
                break;
            }

            if (shouldSkipContainedTag($row['tag'], array_column($selected, 'tag'))) {
                continue;
            }

            $selected[] = $row;
        }

        if ($selected === []) {
            continue;
        }

        $result[] = [
            'area' => $group['area'],
            'no' => $group['no'],
            'description_count' => $descriptionCount,
            'tags' => array_values(array_map(static fn (array $row) => $row['tag'], $selected)),
        ];
    }

    usort($result, static function (array $left, array $right): int {
        return strcmp((string)$left['area'], (string)$right['area']) ?: ((int)$left['no'] <=> (int)$right['no']);
    });

    return $result;
}

function buildImportPayload(array $entries, string $status): array {
    $tags = [];

    foreach ($entries as $entry) {
        $paddedNo = str_pad((string)$entry['no'], 4, '0', STR_PAD_LEFT);
        if (!isset($tags[$paddedNo])) {
            $tags[$paddedNo] = [];
        }
        if (!isset($tags[$paddedNo][$entry['area']])) {
            $tags[$paddedNo][$entry['area']] = [];
        }

        foreach ($entry['tags'] as $tag) {
            $tags[$paddedNo][$entry['area']][] = [
                'name' => $tag,
                'status' => $status,
                'good' => 0,
                'bad' => 0,
            ];
        }
    }

    ksort($tags, SORT_STRING);
    foreach ($tags as &$areas) {
        ksort($areas, SORT_STRING);
    }

    return [
        'update' => date('YmdHis'),
        'meta' => [
            'tool' => 'generate_from_descriptions.php',
            'generated_at' => date(DATE_ATOM),
        ],
        'tags' => $tags,
    ];
}

function ensureTagDatabase(PDO $pdo): void {
    $initSqlPath = __DIR__ . '/init.sql';
    if (is_file($initSqlPath)) {
        $initSql = file_get_contents($initSqlPath);
        if ($initSql !== false && trim($initSql) !== '') {
            $pdo->exec($initSql);
        }
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS tag_settings (key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
}

function insertIntoDatabase(array $entries, string $status, bool $dryRun): array {
    $pdo = new PDO('sqlite:' . TAGS_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    ensureTagDatabase($pdo);

    $checkApproved = $pdo->prepare('SELECT 1 FROM approved_tags WHERE area = :area AND pokedex_no = :no AND tag = :tag LIMIT 1');
    $insertSuggestion = $pdo->prepare('INSERT OR IGNORE INTO tag_suggestions (area, pokedex_no, tag, status) VALUES (:area, :no, :tag, :status)');

    $inserted = 0;
    $skipped = 0;

    if (!$dryRun) {
        $pdo->beginTransaction();
    }

    try {
        foreach ($entries as $entry) {
            foreach ($entry['tags'] as $tag) {
                $params = [
                    ':area' => $entry['area'],
                    ':no' => (int)$entry['no'],
                    ':tag' => $tag,
                    ':status' => $status,
                ];

                $checkApproved->execute([
                    ':area' => $entry['area'],
                    ':no' => (int)$entry['no'],
                    ':tag' => $tag,
                ]);

                if ($checkApproved->fetchColumn()) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $inserted++;
                    continue;
                }

                $insertSuggestion->execute($params);
                if ($insertSuggestion->rowCount() > 0) {
                    $inserted++;
                } else {
                    $skipped++;
                }
            }
        }

        if (!$dryRun) {
            $pdo->commit();
        }
    } catch (Throwable $throwable) {
        if (!$dryRun && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $throwable;
    }

    return ['inserted' => $inserted, 'skipped' => $skipped];
}

function isAbsolutePath(string $path): bool {
    return preg_match('~^(?:[A-Za-z]:[\\\\/]|\\\\|/)~', $path) === 1;
}

function writeJsonOutput(string $targetPath, array $payload): string {
    $resolvedPath = isAbsolutePath($targetPath)
        ? $targetPath
        : dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);

    $directory = dirname($resolvedPath);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        fail('Failed to create directory: ' . $directory);
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($encoded)) {
        fail('Failed to encode JSON output.');
    }

    if (file_put_contents($resolvedPath, $encoded) === false) {
        fail('Failed to write output file: ' . $resolvedPath);
    }

    return $resolvedPath;
}

function runDescriptionTagGeneration(array $options = []): array {
    $normalizedOptions = normalizeDescriptionTagGeneratorOptions($options);
    $configPath = dirname(__DIR__) . '/config/pokedex_config.json';
    $sourcePokedexDir = dirname(__DIR__) . '/pokedex';
    $descriptionMapPath = $sourcePokedexDir . '/description_map.json';

    $configSource = readJsonFile($configPath);
    $descriptionSource = readJsonFile($descriptionMapPath);
    $versionFileIndex = createVersionFileIndex($sourcePokedexDir);
    $globalGroups = collectGlobalGroups(isset($descriptionSource['data']) && is_array($descriptionSource['data']) ? $descriptionSource['data'] : []);
    $regionalGroups = collectRegionalGroups(isset($configSource['regions']) && is_array($configSource['regions']) ? $configSource['regions'] : [], $versionFileIndex);
    $allGroups = mergeGroups($globalGroups, $regionalGroups);
    [$preparedGroups, $documentFrequency] = prepareGroupCandidates($allGroups, $normalizedOptions);
    $entries = selectTags($preparedGroups, $documentFrequency, $normalizedOptions);
    $payload = buildImportPayload($entries, $normalizedOptions['status']);

    $outputPath = null;
    if ($normalizedOptions['output'] !== '') {
        $outputPath = writeJsonOutput($normalizedOptions['output'], $payload);
    }

    $dbSummary = null;
    if ($normalizedOptions['write_db']) {
        $dbSummary = insertIntoDatabase($entries, $normalizedOptions['status'], $normalizedOptions['dry_run']);
    }

    $totalTags = 0;
    foreach ($entries as $entry) {
        $totalTags += count($entry['tags']);
    }

    return [
        'options' => $normalizedOptions,
        'entries' => $entries,
        'payload' => $payload,
        'summary' => [
            'groups' => count($entries),
            'total_tags' => $totalTags,
            'status' => $normalizedOptions['status'],
            'area' => $normalizedOptions['area'],
            'output' => $outputPath,
            'write_db' => $normalizedOptions['write_db'],
            'dry_run' => $normalizedOptions['dry_run'],
            'db' => $dbSummary,
        ],
    ];
}

if ($descriptionTagGeneratorShouldRunMain) {
    try {
        $result = runDescriptionTagGeneration(parseOptions());
        echo json_encode($result['summary'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    } catch (Throwable $throwable) {
        fail($throwable->getMessage());
    }
}
