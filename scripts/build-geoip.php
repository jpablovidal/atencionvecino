<?php

/*
 * Genera la base de geolocalización por país (sin consultas externas en tiempo de ejecución) y el mapa del mundo:
 *   php scripts/build-geoip.php dbip-country-ipv4.csv dbip-country-ipv6.csv paises.geojson
 * Fuentes: DB-IP.com «IP to Country Lite» (CC BY 4.0, requiere atribución) y Natural Earth (dominio público).
 * Salida: resources/geoip/ipv4.bin (6 bytes por tramo: inicio uint32 + país ISO-2), ipv6.bin (10 bytes: 64 bits de prefijo + país), mapa.json.
 */
[$_, $f4, $f6, $gj] = $argv + [null, null, null, null];
if (! $f4 || ! $f6 || ! $gj) {
    fwrite(STDERR, "Uso: php scripts/build-geoip.php ipv4.csv ipv6.csv paises.geojson\n");
    exit(1);
}
$out = __DIR__.'/../resources/geoip';

// ---- IPv4
$rec = [];
$prevFin = -1;
foreach (new SplFileObject($f4) as $l) {
    $p = str_getcsv(trim((string) $l));
    if (count($p) < 3) {
        continue;
    }
    $ini = ip2long($p[0]) & 0xFFFFFFFF;
    $fin = ip2long($p[1]) & 0xFFFFFFFF;
    $cc = strtoupper($p[2]);
    if ($ini > $prevFin + 1 && $prevFin >= 0) {
        $rec[] = [$prevFin + 1, 'ZZ']; // hueco sin asignar
    }
    if ($rec && end($rec)[1] === $cc && $ini === $prevFin + 1) {
        $prevFin = $fin;

        continue; // tramo contiguo del mismo país: se fusiona
    }
    $rec[] = [$ini, $cc];
    $prevFin = $fin;
}
if ($prevFin < 0xFFFFFFFF) {
    $rec[] = [$prevFin + 1, 'ZZ'];
}
$h = fopen($out.'/ipv4.bin', 'wb');
foreach ($rec as [$i, $c]) {
    fwrite($h, pack('N', $i).$c);
}
fclose($h);
echo 'IPv4: '.count($rec)." tramos\n";

// ---- IPv6 (se usan los primeros 64 bits)
$rec = [];
$prevFin = null;
foreach (new SplFileObject($f6) as $l) {
    $p = str_getcsv(trim((string) $l));
    if (count($p) < 3) {
        continue;
    }
    $ini = substr(inet_pton($p[0]), 0, 8);
    $fin = substr(inet_pton($p[1]), 0, 8);
    $cc = strtoupper($p[2]);
    if ($prevFin !== null && strcmp($ini, $prevFin) > 0) {
        $sig = $prevFin;
        for ($i = 7; $i >= 0; $i--) { // prevFin + 1 en 64 bits
            $b = ord($sig[$i]);
            $sig[$i] = chr(($b + 1) & 0xFF);
            if ($b !== 255) {
                break;
            }
        }
        if (strcmp($sig, $ini) < 0 && ! ($rec && end($rec)[1] === 'ZZ')) {
            $rec[] = [$sig, 'ZZ'];
        }
    }
    if ($rec && end($rec)[1] === $cc) {
        $prevFin = $fin;

        continue;
    }
    $rec[] = [$ini, $cc];
    $prevFin = $fin;
}
$h = fopen($out.'/ipv6.bin', 'wb');
foreach ($rec as [$i, $c]) {
    fwrite($h, $i.$c);
}
fclose($h);
echo 'IPv6: '.count($rec)." tramos\n";

// ---- Mapa (proyección equirrectangular 1000x500, trazos simplificados)
$g = json_decode((string) file_get_contents($gj), true);
$mapa = [];
$proy = fn ($lon, $lat) => [round(($lon + 180) / 360 * 1000, 1), round((90 - $lat) / 180 * 500, 1)];
foreach ($g['features'] as $f) {
    $pr = $f['properties'];
    $cc = $pr['ISO_A2'] !== '-99' ? $pr['ISO_A2'] : ($pr['ISO_A2_EH'] !== '-99' ? $pr['ISO_A2_EH'] : null);
    if (! $cc || $cc === 'AQ') {
        continue; // sin código (territorios disputados) o Antártida
    }
    $polys = $f['geometry']['type'] === 'Polygon' ? [$f['geometry']['coordinates']] : $f['geometry']['coordinates'];
    $d = '';
    $mayor = 0;
    $cen = [0, 0];
    foreach ($polys as $poly) {
        foreach ($poly as $k => $anillo) {
            $pts = [];
            $ult = null;
            foreach ($anillo as [$lon, $lat]) {
                [$x, $y] = $proy($lon, $lat);
                if ($ult !== [$x, $y]) {
                    $pts[] = [$x, $y];
                }
                $ult = [$x, $y];
            }
            if (count($pts) < 3) {
                continue;
            }
            $d .= 'M'.implode('L', array_map(fn ($q) => $q[0].' '.$q[1], $pts)).'Z';
            if ($k === 0) { // anillo exterior: el mayor define el centroide aproximado
                $xs = array_column($pts, 0);
                $ys = array_column($pts, 1);
                $area = (max($xs) - min($xs)) * (max($ys) - min($ys));
                if ($area > $mayor) {
                    $mayor = $area;
                    $cen = [round((max($xs) + min($xs)) / 2, 1), round((max($ys) + min($ys)) / 2, 1)];
                }
            }
        }
    }
    $mapa[$cc] = ['n' => $pr['NAME_ES'] ?: $pr['NAME'], 'd' => $d, 'c' => $cen];
}
file_put_contents($out.'/mapa.json', json_encode($mapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo 'Mapa: '.count($mapa)." países\n";
