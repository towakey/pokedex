<?php
// RotomLabs locations scraper
// Usage: php scrape_rotomlabs.php <game-slug> [hub...]
// Extracts sections JSON embedded in each location page's RSC payload,
// writes <slug>.rl.json intermediate: {locSlug:{name, sections:[{version_group, areas:[{area, methods:[{method, tables:[{time, rows:[{pokemon,games,note}]}]}]}]}]}

if ($argc < 2) { fwrite(STDERR, "usage: scrape_rotomlabs.php slug...\n"); exit(1); }

function rlFetch($url) {
    static $delay = 0;
    if ($delay) usleep($delay);
    $delay = 200000; // 0.2s
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0\r\n"]]);
    for ($i = 0; $i < 3; $i++) {
        $h = @file_get_contents($url, false, $ctx);
        if ($h !== false) return $h;
        sleep(1);
    }
    return false;
}

// extract balanced-brace JSON starting at $pos (position of '{' or '[') in UNescaped text
function rlExtractJson($text, $pos) {
    $len = strlen($text); $depth = 0; $inStr = false;
    for ($i = $pos; $i < $len; $i++) {
        $c = $text[$i];
        if ($inStr) {
            if ($c === '\\') { $i++; continue; }
            if ($c === '"') $inStr = false;
            continue;
        }
        if ($c === '"') { $inStr = true; continue; }
        if ($c === '{' || $c === '[') $depth++;
        elseif ($c === '}' || $c === ']') { if (--$depth === 0) return substr($text, $pos, $i - $pos + 1); }
    }
    return null;
}

function rlParsePage($html) {
    // RSC payload strings use \" escapes; unescape globally for extraction
    $u = str_replace(['\\"', '\\\\'], ['"', "\x01"], $html);
    $p = strpos($u, '"sections":[{');
    if ($p === false) return null;
    $arrStart = $p + strlen('"sections":');
    $json = rlExtractJson($u, $arrStart);
    if (!$json) return null;
    $json = str_replace("\x01", '\\', $json);
    $data = json_decode($json, true);
    return $data;
}

function rlLocationSlugs($hubHtml) {
    $u = str_replace('\\"', '"', $hubHtml);
    preg_match_all('/"location_slug":"([a-z0-9-]+)","location_name":"([^"]+)"/', $u, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $x) $out[$x[1]] = html_entity_decode($x[2]);
    return $out;
}

$slugs = array_slice($argv, 1);
foreach ($slugs as $game) {
    echo "== $game ==\n";
    $hub = rlFetch("https://rotomlabs.net/locations/$game");
    if ($hub === false) { fwrite(STDERR, "hub fetch failed\n"); continue; }
    file_put_contents(__DIR__ . "/{$game}_hub.html", $hub);
    $locs = rlLocationSlugs($hub);
    echo count($locs) . " locations\n";
    $all = [];
    foreach ($locs as $slug => $name) {
        $f = __DIR__ . "/rl_{$game}_{$slug}.html";
        $done = json_decode(@file_get_contents(__DIR__ . "/{$game}.rl.json"), true) ?: [];
        if (isset($done[$slug]) || isset($done["$slug-area-one"]) || isset($done["$slug-area-two"]) || isset($done["$slug-area-three"])) { echo "  $slug skip(done)\n"; continue; }
        if (file_exists($f)) $h = file_get_contents($f);
        else { $h = rlFetch("https://rotomlabs.net/locations/$game/$slug"); if ($h !== false) file_put_contents($f, $h); }
        if ($h === false) { fwrite(STDERR, "  fetch fail: $slug\n"); continue; }
        $sec = rlParsePage($h);
        if (!$sec) {
            // maybe sub-area pages: /game/loc/sub
            preg_match_all('/' . preg_quote("/locations/$game/$slug/", '/') . '([a-z0-9-]+)/', $h, $sm);
            $subs = array_unique($sm[1]);
            if ($subs) {
                foreach ($subs as $sub) {
                    $f2 = __DIR__ . "/rl_{$game}_{$slug}_{$sub}.html";
                    if (file_exists($f2)) $h2 = file_get_contents($f2);
                    else { $h2 = rlFetch("https://rotomlabs.net/locations/$game/$slug/$sub"); if ($h2 !== false) file_put_contents($f2, $h2); }
                    if ($h2 === false) { fwrite(STDERR, "  fetch fail: $slug/$sub\n"); continue; }
                    $sec2 = rlParsePage($h2);
                    if (!$sec2) { fwrite(STDERR, "  parse fail: $slug/$sub\n"); continue; }
                    $subName = ucwords(str_replace('-', ' ', $sub));
                    $all["$slug-$sub"] = ['name' => "$name ($subName)", 'sections' => $sec2];
                    echo "  $slug/$sub ok\n";
                }
                continue;
            }
            fwrite(STDERR, "  parse fail: $slug\n"); continue;
        }
        $all[$slug] = ['name' => $name, 'sections' => $sec];
        echo "  $slug ok\n";
    }
    $all = $all + $done;
    file_put_contents(__DIR__ . "/{$game}.rl.json", json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "== $game done: " . count($all) . "\n";
}
