<?php
/**
 * Scannable 1D barcodes for SKU labels (Code 128, subsets B and C).
 *
 * Code 128 is used because it encodes any printable ASCII SKU/serial compactly
 * and is readable by ordinary phone scanners. Persian text is never encoded in
 * a 1D barcode; those labels use the QR helper instead.
 */

/** The official Code 128 element widths for values 0-106. */
function nik_code128_patterns(): array {
    static $patterns = null;
    if ($patterns !== null) return $patterns;
    $patterns = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112',
    ];
    return $patterns;
}

/** Integer values of a Code 128 symbol, including start, checksum and stop. */
function nik_code128_values(string $text): array {
    $values = [];
    $numeric = preg_match('/^[0-9]{4,}$/D', $text) === 1;
    if ($numeric) {
        if (strlen($text) % 2 === 1) {
            // Odd digit count: one Start B character, then latch to subset C.
            $values[] = 104;
            $values[] = ord($text[0]) - 32;
            $values[] = 99; // Code C latch.
            $digits = substr($text, 1);
        } else {
            $values[] = 105; // Start C: two digits per symbol.
            $digits = $text;
        }
        foreach (str_split($digits, 2) as $pair) $values[] = (int)$pair;
    } else {
        $values[] = 104; // Start B: full printable ASCII.
        foreach (str_split($text) as $char) $values[] = ord($char) - 32;
    }
    $sum = $values[0];
    $count = count($values);
    for ($i = 1; $i < $count; $i++) $sum += $i * $values[$i];
    $values[] = $sum % 103;
    $values[] = 106; // Stop.
    return $values;
}

/** Total element modules of the symbol, including quiet zones (0 when unsupported). */
function nik_barcode_modules(string $text): int {
    $text = trim($text);
    if (!nik_barcode_supported($text)) return 0;
    $patterns = nik_code128_patterns();
    $modules = 20; // 10 quiet modules on each side.
    foreach (nik_code128_values($text) as $value) {
        $pattern = $patterns[$value] ?? '';
        foreach (str_split($pattern) as $width) $modules += (int)$width;
    }
    return $modules;
}

/** True when the text can be represented by Code 128 (printable ASCII). */
function nik_barcode_supported(string $text): bool {
    return $text !== '' && strlen($text) <= 48 && preg_match('/^[\x20-\x7E]+$/D', $text) === 1;
}

/**
 * Renders a Code 128 barcode as inline SVG.
 *
 * Options: size_mm (total width), height_mm, quiet (modules), color,
 * show_text, alt, class.
 */
function nik_barcode_svg(string $text, array $options = []): string {
    $text = trim($text);
    if (!nik_barcode_supported($text)) return '';
    $patterns = nik_code128_patterns();
    $values = nik_code128_values($text);

    $quiet = max(6, min(20, (int)($options['quiet'] ?? 10)));
    $widthMm = max(20.0, min(120.0, (float)($options['size_mm'] ?? 45)));
    $heightMm = max(6.0, min(30.0, (float)($options['height_mm'] ?? 12)));
    $color = (string)($options['color'] ?? '#0f172a');
    if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) $color = '#0f172a';

    $bars = [];       // [start module, width in modules]
    $x = $quiet;
    foreach ($values as $value) {
        $pattern = $patterns[$value] ?? '';
        if ($pattern === '') continue;
        $bar = true;
        foreach (str_split($pattern) as $width) {
            $w = (int)$width;
            if ($bar) $bars[] = [$x, $w];
            $x += $w;
            $bar = !$bar;
        }
    }
    $totalModules = $x + $quiet;
    $path = '';
    foreach ($bars as [$start, $w]) {
        $path .= 'M' . $start . ' 0h' . $w . 'v1h-' . $w . 'z';
    }

    $class = isset($options['class']) ? ' class="' . htmlspecialchars((string)$options['class'], ENT_QUOTES) . '"' : '';
    $alt = (string)($options['alt'] ?? ('Barcode ' . $text));
    $svg = '<svg' . $class . ' xmlns="http://www.w3.org/2000/svg" width="' . rtrim(rtrim(number_format($widthMm, 2, '.', ''), '0'), '.') . 'mm"'
        . ' height="' . rtrim(rtrim(number_format($heightMm, 2, '.', ''), '0'), '.') . 'mm"'
        . ' viewBox="0 0 ' . $totalModules . ' 1" preserveAspectRatio="none"'
        . ' shape-rendering="crispEdges" role="img" aria-label="' . htmlspecialchars($alt, ENT_QUOTES) . '">'
        . '<title>' . htmlspecialchars($alt, ENT_QUOTES) . '</title>'
        . '<rect width="' . $totalModules . '" height="1" fill="#ffffff"/>'
        . '<path fill="' . htmlspecialchars($color, ENT_QUOTES) . '" d="' . $path . '"/>'
        . '</svg>';
    return $svg;
}
