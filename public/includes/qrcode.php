<?php
/**
 * Nik Smart Count - Warehousing
 * Pure-PHP QR Code generator (ISO/IEC 18004), no external service and no
 * network call. Byte mode, automatic version selection, error correction
 * L/M/Q/H, standard masking. Output is an inline SVG so it prints sharply
 * at any size and needs no image library.
 *
 * Usage:
 *   echo nik_qr_svg('text to encode', ['size_mm' => 24]);
 */

/** Error-correction codewords per block, indexed [level][version]. */
function nik_qr_ecc_per_block(): array {
    static $t = null;
    if ($t !== null) return $t;
    $t = [
        'L' => [0,7,10,15,20,26,18,20,24,30,18,20,24,26,30,22,24,28,30,28,28,28,28,30,30,26,28,30,30,30,30,30,30,30,30,30,30,30,30,30,30],
        'M' => [0,10,16,26,18,24,16,18,22,22,26,30,22,22,24,24,28,28,26,26,26,26,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28],
        'Q' => [0,13,22,18,26,18,24,18,22,20,24,28,26,24,20,30,24,28,28,26,30,28,30,30,30,30,28,30,30,30,30,30,30,30,30,30,30,30,30,30,30],
        'H' => [0,17,28,22,16,22,28,26,26,24,28,24,28,22,24,24,30,28,28,26,28,30,24,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30],
    ];
    return $t;
}

/** Number of error-correction blocks, indexed [level][version]. */
function nik_qr_block_count(): array {
    static $t = null;
    if ($t !== null) return $t;
    $t = [
        'L' => [0,1,1,1,1,1,2,2,2,2,4,4,4,4,4,6,6,6,6,7,8,8,9,9,10,12,12,12,13,14,15,16,17,18,19,19,20,21,22,24,25],
        'M' => [0,1,1,1,2,2,4,4,4,5,5,5,8,9,9,10,10,11,13,14,16,17,17,18,20,21,23,25,26,28,29,31,33,35,37,38,40,43,45,47,49],
        'Q' => [0,1,1,2,2,4,4,6,6,8,8,8,10,12,16,12,17,16,18,21,20,23,23,25,27,29,34,34,35,38,40,43,45,48,51,53,56,59,62,65,68],
        'H' => [0,1,1,2,4,4,4,5,6,8,8,11,11,16,16,18,16,19,21,25,25,25,34,30,32,35,37,40,42,45,48,51,54,57,60,63,66,70,74,77,81],
    ];
    return $t;
}

/** Number of data bits available for payload in a version. */
function nik_qr_raw_data_modules(int $ver): int {
    $result = (16 * $ver + 128) * $ver + 64;
    if ($ver >= 2) {
        $numAlign = intdiv($ver, 7) + 2;
        $result -= (25 * $numAlign - 10) * $numAlign - 55;
        if ($ver >= 7) $result -= 36;
    }
    return $result;
}

/** Data codewords (bytes) that fit in one version at one EC level. */
function nik_qr_data_capacity(int $ver, string $ecl): int {
    $ecc = nik_qr_ecc_per_block();
    $blocks = nik_qr_block_count();
    $raw = intdiv(nik_qr_raw_data_modules($ver), 8);
    return $raw - $ecc[$ecl][$ver] * $blocks[$ecl][$ver];
}

/** Alignment-pattern centre coordinates for a version. */
function nik_qr_alignment_positions(int $ver): array {
    if ($ver === 1) return [];
    $numAlign = intdiv($ver, 7) + 2;
    $step = $ver === 32 ? 26 : intdiv($ver * 4 + $numAlign * 2 + 1, $numAlign * 2 - 2) * 2;
    $result = [6];
    for ($pos = $ver * 4 + 10; count($result) < $numAlign; $pos -= $step) {
        $result = array_merge([$result[0]], [$pos], array_slice($result, 1));
    }
    return $result;
}

/** GF(256) multiply using precomputed log/exp tables (primitive 0x11D). */
function nik_qr_gf_multiply(int $x, int $y): int {
    static $exp = null, $log = null;
    if ($exp === null) {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x2 = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x2;
            $log[$x2] = $i;
            $x2 <<= 1;
            if ($x2 & 0x100) $x2 ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    }
    if ($x === 0 || $y === 0) return 0;
    return $exp[$log[$x] + $log[$y]];
}

/** Reed-Solomon generator polynomial for the given degree. */
function nik_qr_rs_divisor(int $degree): array {
    static $cache = [];
    if (isset($cache[$degree])) return $cache[$degree];
    $result = array_fill(0, $degree, 0);
    $result[$degree - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        for ($j = 0; $j < $degree; $j++) {
            $result[$j] = nik_qr_gf_multiply($result[$j], $root);
            if ($j + 1 < $degree) $result[$j] ^= $result[$j + 1];
        }
        $root = nik_qr_gf_multiply($root, 0x02);
    }
    return $cache[$degree] = $result;
}

/** Reed-Solomon remainder (the EC codewords) for one block. */
function nik_qr_rs_remainder(array $data, array $divisor): array {
    $result = array_fill(0, count($divisor), 0);
    foreach ($data as $byte) {
        $factor = $byte ^ $result[0];
        array_shift($result);
        $result[] = 0;
        foreach ($divisor as $i => $coefficient) {
            $result[$i] ^= nik_qr_gf_multiply($coefficient, $factor);
        }
    }
    return $result;
}

/**
 * Encode a UTF-8 string into a QR matrix.
 *
 * @return array{version:int,ecl:string,size:int,modules:array<int,array<int,int>>}
 */
function nik_qr_matrix(string $text, string $ecl = 'M'): array {
    $ecl = strtoupper($ecl);
    if (!in_array($ecl, ['L', 'M', 'Q', 'H'], true)) $ecl = 'M';
    if ($text === '') throw new InvalidArgumentException('QR text is empty.');
    $bytes = array_values(unpack('C*', $text));

    $version = 0;
    foreach (range(1, 40) as $v) {
        $countBits = $v <= 9 ? 8 : 16;
        if (4 + $countBits + count($bytes) * 8 <= nik_qr_data_capacity($v, $ecl) * 8) {
            $version = $v;
            break;
        }
    }
    if ($version === 0) throw new InvalidArgumentException('QR payload is too long.');

    $bits = [];
    $push = static function (int $value, int $length) use (&$bits): void {
        for ($i = $length - 1; $i >= 0; $i--) $bits[] = ($value >> $i) & 1;
    };
    $push(0b0100, 4);                     // byte mode
    $push(count($bytes), $version <= 9 ? 8 : 16);
    foreach ($bytes as $byte) $push($byte, 8);

    $capacityBits = nik_qr_data_capacity($version, $ecl) * 8;
    $push(0, min(4, $capacityBits - count($bits)));
    while (count($bits) % 8 !== 0) $bits[] = 0;
    for ($padIndex = 0; count($bits) < $capacityBits; $padIndex++) $push($padIndex % 2 === 0 ? 0xEC : 0x11, 8);

    $dataCodewords = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $value = 0;
        for ($j = 0; $j < 8; $j++) $value = ($value << 1) | $bits[$i + $j];
        $dataCodewords[] = $value;
    }

    $eccTable = nik_qr_ecc_per_block();
    $blockTable = nik_qr_block_count();
    $numBlocks = $blockTable[$ecl][$version];
    $blockEccLen = $eccTable[$ecl][$version];
    $rawCodewords = intdiv(nik_qr_raw_data_modules($version), 8);
    $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
    $shortBlockLen = intdiv($rawCodewords, $numBlocks);

    $divisor = nik_qr_rs_divisor($blockEccLen);
    $blocks = [];
    $k = 0;
    for ($i = 0; $i < $numBlocks; $i++) {
        $dataLen = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
        $dat = array_slice($dataCodewords, $k, $dataLen);
        $k += $dataLen;
        $ecc = nik_qr_rs_remainder($dat, $divisor);
        if ($i < $numShortBlocks) $dat[] = 0; // placeholder keeps interleaving aligned
        $blocks[] = array_merge($dat, $ecc);
    }
    $codewords = [];
    $blockLen = count($blocks[0]);
    for ($i = 0; $i < $blockLen; $i++) {
        for ($j = 0; $j < $numBlocks; $j++) {
            if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) $codewords[] = $blocks[$j][$i];
        }
    }

    $size = $version * 4 + 17;
    $modules = array_fill(0, $size, array_fill(0, $size, 0));
    $isFunction = array_fill(0, $size, array_fill(0, $size, 0));

    $setFunction = static function (int $x, int $y, bool $dark) use (&$modules, &$isFunction, $size): void {
        if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) return;
        $modules[$y][$x] = $dark ? 1 : 0;
        $isFunction[$y][$x] = 1;
    };

    // Alignment patterns
    $align = nik_qr_alignment_positions($version);
    $last = count($align) - 1;
    foreach ($align as $i => $ax) {
        foreach ($align as $j => $ay) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) continue;
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $setFunction($ax + $dx, $ay + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
    }
    // Timing patterns
    for ($i = 0; $i < $size; $i++) {
        $setFunction(6, $i, $i % 2 === 0);
        $setFunction($i, 6, $i % 2 === 0);
    }
    // Finder patterns + separators
    foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $setFunction($cx + $dx, $cy + $dy, $dist !== 2 && $dist !== 4);
            }
        }
    }
    // Dark module + reserved format area
    $setFunction(8, $size - 8, true);

    $eclBits = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2][$ecl];
    $drawFormat = static function (int $mask) use (&$modules, &$isFunction, $size, $eclBits): void {
        $data = ($eclBits << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $get = static fn(int $index): bool => (($bits >> $index) & 1) !== 0;
        $put = static function (int $x, int $y, bool $dark) use (&$modules, &$isFunction): void {
            $modules[$y][$x] = $dark ? 1 : 0;
            $isFunction[$y][$x] = 1;
        };
        for ($i = 0; $i <= 5; $i++) $put(8, $i, $get($i));
        $put(8, 7, $get(6));
        $put(8, 8, $get(7));
        $put(7, 8, $get(8));
        for ($i = 9; $i < 15; $i++) $put(14 - $i, 8, $get($i));
        for ($i = 0; $i < 8; $i++) $put($size - 1 - $i, 8, $get($i));
        for ($i = 8; $i < 15; $i++) $put(8, $size - 15 + $i, $get($i));
        $put(8, $size - 8, true);
    };
    $drawFormat(0);

    if ($version >= 7) {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        $bits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = (($bits >> $i) & 1) !== 0;
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $setFunction($a, $b, $bit);
            $setFunction($b, $a, $bit);
        }
    }

    // Data placement (zig-zag, two columns at a time, right to left)
    $bitIndex = 0;
    $bitCount = count($codewords) * 8;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5;
        for ($vert = 0; $vert < $size; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                $upward = (($right + 1) & 2) === 0;
                $y = $upward ? $size - 1 - $vert : $vert;
                if ($isFunction[$y][$x] || $bitIndex >= $bitCount) continue;
                $modules[$y][$x] = ($codewords[$bitIndex >> 3] >> (7 - ($bitIndex & 7))) & 1;
                $bitIndex++;
            }
        }
    }

    // XOR is its own inverse, so the same routine both applies and removes a mask.
    $applyMask = static function (int $mask) use (&$modules, &$isFunction, $size): void {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($isFunction[$y][$x]) continue;
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($invert) $modules[$y][$x] ^= 1;
            }
        }
    };

    $penalty = static function () use (&$modules, $size): int {
        $score = 0;
        $patternA = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $patternB = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        for ($y = 0; $y < $size; $y++) {
            $run = 1;
            for ($x = 1; $x < $size; $x++) {
                if ($modules[$y][$x] === $modules[$y][$x - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) $score += 3 + ($run - 5);
                    $run = 1;
                }
            }
            if ($run >= 5) $score += 3 + ($run - 5);
        }
        for ($x = 0; $x < $size; $x++) {
            $run = 1;
            for ($y = 1; $y < $size; $y++) {
                if ($modules[$y][$x] === $modules[$y - 1][$x]) {
                    $run++;
                } else {
                    if ($run >= 5) $score += 3 + ($run - 5);
                    $run = 1;
                }
            }
            if ($run >= 5) $score += 3 + ($run - 5);
        }
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $modules[$y][$x];
                if ($c === $modules[$y][$x + 1] && $c === $modules[$y + 1][$x] && $c === $modules[$y + 1][$x + 1]) $score += 3;
            }
        }
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x <= $size - 11; $x++) {
                $slice = array_slice($modules[$y], $x, 11);
                if ($slice === $patternA || $slice === $patternB) $score += 40;
            }
        }
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y <= $size - 11; $y++) {
                $slice = [];
                for ($k = 0; $k < 11; $k++) $slice[] = $modules[$y + $k][$x];
                if ($slice === $patternA || $slice === $patternB) $score += 40;
            }
        }
        $dark = 0;
        foreach ($modules as $row) $dark += array_sum($row);
        $total = $size * $size;
        $score += intdiv(abs($dark * 20 - $total * 10), $total) * 10;
        return $score;
    };

    $bestMask = 0;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $applyMask($mask);
        $drawFormat($mask);
        $score = $penalty();
        $applyMask($mask);
        if ($score < $bestScore) {
            $bestScore = $score;
            $bestMask = $mask;
        }
    }
    $applyMask($bestMask);
    $drawFormat($bestMask);

    return ['version' => $version, 'ecl' => $ecl, 'size' => $size, 'modules' => $modules];
}

/**
 * Render a QR code as an inline SVG (no external files, prints sharply).
 *
 * @param array{size_mm?:float,quiet?:int,level?:string,color?:string,alt?:string,class?:string} $options
 */
function nik_qr_svg(string $text, array $options = []): string {
    $sizeMm = max(10.0, min(80.0, (float)($options['size_mm'] ?? 24)));
    $quiet = max(0, min(8, (int)($options['quiet'] ?? 4)));
    $level = (string)($options['level'] ?? 'M');
    $color = (string)($options['color'] ?? '#0f172a');
    if (!preg_match('/^#[0-9a-f]{3,8}$/i', $color)) $color = '#0f172a';
    $alt = (string)($options['alt'] ?? 'QR code');

    $qr = nik_qr_matrix($text, $level);
    $size = $qr['size'];
    $total = $size + $quiet * 2;

    $path = '';
    foreach ($qr['modules'] as $y => $row) {
        $x = 0;
        while ($x < $size) {
            if ($row[$x] !== 1) {
                $x++;
                continue;
            }
            $start = $x;
            while ($x < $size && $row[$x] === 1) $x++;
            $path .= 'M' . ($start + $quiet) . ' ' . ($y + $quiet) . 'h' . ($x - $start) . 'v1h-' . ($x - $start) . 'z';
        }
    }

    $class = isset($options['class']) ? ' class="' . htmlspecialchars((string)$options['class'], ENT_QUOTES) . '"' : '';
    return '<svg' . $class . ' xmlns="http://www.w3.org/2000/svg" width="' . rtrim(rtrim(number_format($sizeMm, 2, '.', ''), '0'), '.') . 'mm"'
        . ' height="' . rtrim(rtrim(number_format($sizeMm, 2, '.', ''), '0'), '.') . 'mm"'
        . ' viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges"'
        . ' role="img" aria-label="' . htmlspecialchars($alt, ENT_QUOTES) . '">'
        . '<title>' . htmlspecialchars($alt, ENT_QUOTES) . '</title>'
        . '<rect width="' . $total . '" height="' . $total . '" fill="#ffffff"/>'
        . '<path fill="' . htmlspecialchars($color, ENT_QUOTES) . '" d="' . $path . '"/>'
        . '</svg>';
}

/** Shorten a text value without cutting a multi-byte character. */
function nik_qr_trim(string $value, int $max): string {
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') return '';
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8') > $max ? rtrim(mb_substr($value, 0, $max - 1, 'UTF-8')) . '…' : $value;
    }
    return strlen($value) > $max ? substr($value, 0, $max - 1) . '…' : $value;
}

/**
 * Human-readable shop + invoice summary that appears on the phone screen when
 * the invoice QR is scanned. Kept compact (<= 180 bytes, EC level M) so the
 * symbol stays small, sharp and easy for any phone camera to read in print.
 *
 * @param array{name?:string,phones?:string,address?:string,invoice?:string,total?:string,brand?:string} $data
 */
function nik_qr_summary_text(array $data, int $maxBytes = 240): string {
    $brand = trim((string)($data['brand'] ?? (defined('APP_BRAND_NAME') ? APP_BRAND_NAME : 'Nik Smart Count - Warehousing')));
    $name = nik_qr_trim((string)($data['name'] ?? ''), 34);
    $phones = nik_qr_trim((string)($data['phones'] ?? ''), 38);
    $addressRaw = trim(preg_replace('/\s+/u', ' ', (string)($data['address'] ?? '')) ?? '');
    $invoice = nik_qr_trim((string)($data['invoice'] ?? ''), 26);
    $total = nik_qr_trim((string)($data['total'] ?? ''), 20);

    $bill = '';
    if ($invoice !== '') {
        $bill = 'بل: ' . $invoice . ($total !== '' ? ' | ' . $total : '');
    } elseif ($total !== '') {
        $bill = 'مبلغ: ' . $total;
    }

    // Shorten the address first; the invoice line and the brand always survive.
    foreach ([46, 40, 34, 28, 22, 16, 10, 0] as $addressLimit) {
        $lines = [];
        if ($name !== '') $lines[] = $name;
        if ($phones !== '') $lines[] = 'تلفن: ' . $phones;
        if ($addressLimit > 0 && $addressRaw !== '') $lines[] = 'آدرس: ' . nik_qr_trim($addressRaw, $addressLimit);
        if ($bill !== '') $lines[] = $bill;
        $lines[] = $brand;
        $text = implode("\n", $lines);
        if (strlen($text) <= $maxBytes) return $text;
    }

    // Last resort: keep the two lines that carry meaning.
    return implode("\n", array_values(array_filter([$bill, $brand], static fn($line) => $line !== '')));
}
