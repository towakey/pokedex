<?php
// Shared core for encounter.json generation

function loadEnMap($pokedexPath) {
    $pokedex = json_decode(file_get_contents($pokedexPath), true)['pokedex'];
    $enMap = [];
    foreach ($pokedex as $no => $forms) {
        foreach ($forms as $fid => $f) {
            $eng = $f['name']['eng'] ?? null;
            if ($eng) $enMap[strtolower(str_replace('’', "'", $eng))] = ['no' => $no, 'jpn' => $f['name']['jpn']];
        }
    }
    return $enMap;
}

function mkMethod($type, $versions, $locations, $note) {
    $m = ['type' => $type, 'versions' => $versions, 'locations' => $locations];
    if ($note !== '') $m['note'] = $note;
    return $m;
}

function mergeMethods($methods, $verorder) {
    $merged = [];
    foreach ($methods as $m) {
        $key = $m['type'] . '|' . implode('、', $m['locations']) . '|' . ($m['note'] ?? '');
        if (isset($merged[$key])) {
            $merged[$key]['versions'] = array_values(array_unique(array_merge($merged[$key]['versions'], $m['versions'])));
            usort($merged[$key]['versions'], function($a,$b) use ($verorder){ return array_search($a,$verorder)-array_search($b,$verorder); });
        } else {
            $merged[$key] = $m;
        }
    }
    return array_values($merged);
}

// generic qualifier translator — part-based
function qualifierJp($q, $slug, $cfg) {
    $q = preg_replace('/^[\s,]*\b(and|Routes|&)\b\s*/i', '', trim($q));
    $q = preg_replace('/^[\s,]+/', '', $q);
    $q = trim($q, " \t\n");
    $q = trim($q, '()');
    $q = trim($q, " \t\n,.");
    if ($q === '') return '';
    if (preg_match('/revive from/i', $q)) return '';
    if (preg_match('/^(Six|Two|Three|Four|Five|Seven|Eight|Nine|Ten)$/i', $q, $m)) {
        $n = ['two'=>2,'three'=>3,'four'=>4,'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,'ten'=>10][strtolower($m[1])];
        return "（{$n}体）";
    }
    // split into parts on commas / "and" / "&"
    $parts = preg_split('/\s*(?:,|(?<!\d)\s+and\s+|&)\s*/', $q, -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p, " \t\n()");
        if ($p === '') continue;
        $jp = qualifierPartJp($p, $slug, $cfg);
        if ($jp === null) return null;
        if ($jp !== '') $out[] = $jp;
    }
    if (!$out) return '';
    return '（' . implode('・', $out) . '）';
}

function qualifierPartJp($p, $slug, $cfg) {
    static $phraseMap = [
        'all floors' => '全フロア', 'both floors' => '全フロア',
        'all areas' => '全エリア', 'all floors and areas' => '全フロア',
        'outside' => '外', 'summit' => '頂上', 'item rooms' => 'アイテムの部屋',
        'center' => 'センターエリア', 'north' => '北エリア', 'east' => '東エリア',
        'west' => '西エリア', 'south' => '南エリア',
        'grass' => '', 'cave' => '', 'water' => '',
    ];
    $lp = strtolower($p);
    if (isset($cfg['phrase_map'][$lp])) return $cfg['phrase_map'][$lp];
    if (isset($phraseMap[$lp])) return $phraseMap[$lp];
    // floor token(s)
    if (preg_match('/^(B?\d+F)$/i', $p, $m)) return strtoupper($m[1]);
    if (preg_match('/^(B?\d+F) and above$/i', $p, $m)) return strtoupper($m[1]) . '〜';
    if (preg_match('/^(B?\d+F) front$/i', $p, $m)) return strtoupper($m[1]) . '表';
    if (preg_match('/^(B?\d+F) back$/i', $p, $m)) return strtoupper($m[1]) . '奥';
    // "Route 45 side" / "Route 31/46 side"
    if (preg_match('/^route\s*([\d\/]+)\s*side$/i', $p, $m)) return str_replace('/', '・', $m[1]) . '番道路側';
    // "(Seven)" style counts
    if (preg_match('/^(Six|Two|Three|Four|Five|Seven|Eight|Nine|Ten)$/i', $p, $m)) {
        $n = ['two'=>2,'three'=>3,'four'=>4,'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,'ten'=>10][strtolower($m[1])];
        return "{$n}体";
    }
    // "Lugia's Room" style -> phrase_map should have it; unknown
    return null;
}

// resolve locations for one method part -> ['locJp'=>[], 'areas'=>[]]
function resolveLocations($e, $cfg) {
    $locText = $e['locText'];
    if (isset($cfg['loctext_override'])) {
        foreach ($cfg['loctext_override'] as $pat => $arr) {
            if (strpos($locText, $pat) !== false) {
                return ['locJp' => $arr[0], 'areas' => $arr[1]];
            }
        }
    }
    $locs = $e['locations'];
    if (!$locs) {
        return ['locJp' => [$locText], 'areas' => ['grass']];
    }
    $locJpList = [];
    $locAreas = [];
    $remaining = $locText;
    foreach ($locs as $i => $l) {
        $slug = $l['slug']; $en = $l['en'];
        $pos = strpos($remaining, $en);
        $after = '';
        if ($pos !== false) {
            $after = substr($remaining, $pos + strlen($en));
            $remaining = $after;
        }
        $nextEn = $locs[$i+1]['en'] ?? null;
        $qual = $after;
        if ($nextEn !== null) {
            $np = strpos($after, $nextEn);
            if ($np !== false) $qual = substr($after, 0, $np);
        }
        $key = $slug . '|' . $en;
        if (isset($cfg['locmap'][$key])) {
            $base = $cfg['locmap'][$key][0];
            $area = $cfg['locmap'][$key][1];
        } elseif (isset($cfg['locmap'][$slug])) {
            $base = $cfg['locmap'][$slug][0];
            $area = $cfg['locmap'][$slug][1];
        } elseif (preg_match('/^route-(\d+)$/', $slug, $rm)) {
            $base = $rm[1] . (isset($cfg['water_routes']) && in_array((int)$rm[1], $cfg['water_routes']) ? '番水道' : '番道路');
            $area = 'grass';
        } else {
            $base = $en;
            $area = 'grass';
            $cfg['unknown_loc'][] = $key;
        }
        $qj = qualifierJp($qual, $slug, $cfg);
        if ($qj === null) { $cfg['unknown_qual'][] = "$key|$qual"; $qj = ''; }
        $locJpList[] = $base . $qj;
        $locAreas[] = $area;
    }
    return ['locJp' => $locJpList, 'areas' => $locAreas];
}

function jpnName($en, $enMap) {
    $suffix = '';
    if (preg_match('/^(.*?) \(([^)]+)\)$/', trim($en), $m)) {
        $en = $m[1];
        $suffixMap = [
            'west' => 'にし', 'east' => 'ひがし', 'red' => 'あか', 'blue' => 'あお',
            'spring' => 'はる', 'summer' => 'なつ', 'autumn' => 'あき', 'winter' => 'ふゆ',
            'plant' => 'くさ', 'sandy' => 'すな', 'trash' => 'ゴミ',
            'sun' => 'たいよう', 'moon' => 'つき',
        ];
        $sk = strtolower($m[2]);
        $suffix = '（' . ($suffixMap[$sk] ?? $m[2]) . '）';
    }
    $k = strtolower(str_replace('’', "'", $en));
    return ($enMap[$k]['jpn'] ?? $en) . $suffix;
}

function buildEncounter($parsedFile, $cfg, $enMap) {
    $raw = json_decode(file_get_contents($parsedFile), true);
    $vermap = $cfg['vermap'];
    $verorder = $cfg['verorder'];
    $out = [];
    foreach ($raw as $name => $entries) {
        $key = strtolower(str_replace('’', "'", $name));
        if (isset($cfg['name_map'][$key])) $key = $cfg['name_map'][$key];
        $info = $enMap[$key] ?? null;
        if (!$info) { fwrite(STDERR, "unknown pokemon: $name\n"); continue; }
        $methods = [];
        $groupNote = '';
        foreach ($entries as $e) {
            if (isset($e['note'])) $groupNote = $e['note'];
            $versions = [];
            foreach ($e['versions'] as $v => $on) {
                if ($on && isset($vermap[$v])) $versions[] = $vermap[$v];
            }
            if (!$versions) continue;
            usort($versions, function($a,$b) use ($verorder){ return array_search($a,$verorder)-array_search($b,$verorder); });
            $loc = resolveLocations($e, $cfg);
            $cfg['method_handler']($methods, $e, $name, $versions, $loc['locJp'], $loc['areas'], $groupNote, $enMap, $cfg);
        }
        $out[$info['no']] = [
            'globalNo' => $info['no'],
            'name' => $info['jpn'],
            'methods' => mergeMethods($methods, $verorder),
        ];
    }
    // manual extras
    foreach (($cfg['extra_pokemon'] ?? []) as $no => $entry) {
        $out[$no] = $entry;
    }
    ksort($out);
    return $out;
}

function writeEncounter($out, $cfg, $destPath) {
    $json = [
        'update' => $cfg['update'] ?? '20261018',
        'game_version' => $cfg['game_version'],
        'encounter' => [ $cfg['region_key'] => $out ],
    ];
    file_put_contents($destPath, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
