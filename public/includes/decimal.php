<?php
/** Small fixed-decimal arithmetic. No float arithmetic is used for FX or allocations. */
function dec_clean($value) {
    if (!is_int($value) && !is_float($value) && !is_string($value)) throw new InvalidArgumentException('Enter a valid decimal number.');
    if (is_float($value)) $value = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
    $s = strtr(trim((string)$value), array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
    $s = str_replace([',', '٬'], '', $s);
    $s = str_replace('٫', '.', $s);
    if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/D', $s)) throw new InvalidArgumentException('Enter a valid decimal number.');
    if (strlen($s) > 40) throw new InvalidArgumentException('Number is too large.');
    return $s;
}
function dec_parts($value) {
    $s = dec_clean($value); $neg = $s[0] === '-'; $s = ltrim($s, '+-');
    $p = explode('.', $s, 2); $scale = strlen($p[1] ?? '');
    return [$neg, ltrim($p[0] . ($p[1] ?? ''), '0') ?: '0', $scale];
}
function dec_cmp_digits($a, $b) {
    $a = ltrim((string)$a, '0') ?: '0'; $b = ltrim((string)$b, '0') ?: '0';
    return strlen($a) === strlen($b) ? strcmp($a, $b) : (strlen($a) <=> strlen($b));
}
function dec_add_digits($a, $b) {
    $length=max(strlen($a),strlen($b));$a=str_pad($a,$length,'0',STR_PAD_LEFT);$b=str_pad($b,$length,'0',STR_PAD_LEFT);
    $out='';$carry=0;
    for($i=$length-1;$i>=0;$i--){$n=(int)$a[$i]+(int)$b[$i]+$carry;$out=($n%10).$out;$carry=intdiv($n,10);}
    return ltrim(($carry?(string)$carry:'').$out,'0')?:'0';
}
function dec_sub_digits($a, $b) {
    $b = str_pad($b, strlen($a), '0', STR_PAD_LEFT); $out = ''; $borrow = 0;
    for ($i = strlen($a)-1; $i >= 0; $i--) { $d = (int)$a[$i] - (int)$b[$i] - $borrow; $borrow = $d < 0 ? 1 : 0; $out = ($d + 10 * $borrow) . $out; }
    return ltrim($out, '0') ?: '0';
}
function dec_mul_digits($a, $b) {
    $out = array_fill(0, strlen($a) + strlen($b), 0);
    for ($i = strlen($a)-1; $i >= 0; $i--) for ($j = strlen($b)-1; $j >= 0; $j--) {
        $p = (int)$a[$i] * (int)$b[$j] + $out[$i+$j+1];
        $out[$i+$j+1] = $p % 10; $out[$i+$j] += intdiv($p, 10);
    }
    return ltrim(implode('', $out), '0') ?: '0';
}
function dec_div_digits($a, $b) {
    if (dec_cmp_digits($b, '0') === 0) throw new InvalidArgumentException('Division by zero.');
    $q = ''; $r = '0';
    foreach (str_split($a) as $d) {
        $r = ltrim($r . $d, '0') ?: '0'; $n = 0;
        while (dec_cmp_digits($r, $b) >= 0) { $r = dec_sub_digits($r, $b); $n++; }
        $q .= (string)$n;
    }
    return [ltrim($q, '0') ?: '0', $r];
}
function dec_inc_digits($digits) {
    for ($i = strlen($digits)-1; $i >= 0; $i--) { if ($digits[$i] !== '9') { $digits[$i] = (string)((int)$digits[$i]+1); return $digits; } $digits[$i] = '0'; }
    return '1' . $digits;
}
function dec_format_digits($digits, $scale, $negative = false) {
    $digits = str_pad($digits, $scale+1, '0', STR_PAD_LEFT);
    $text = $scale ? substr($digits, 0, -$scale) . '.' . substr($digits, -$scale) : $digits;
    return ($negative && trim($digits, '0') !== '' ? '-' : '') . $text;
}
function dec_round($value, $scale = 2) {
    [$negative, $digits, $oldScale] = dec_parts($value);
    if ($oldScale > $scale) {
        $digits = str_pad($digits, $oldScale+1, '0', STR_PAD_LEFT);
        $cut = strlen($digits) - $oldScale + $scale;
        $up = (int)$digits[$cut] >= 5; $digits = substr($digits, 0, $cut) ?: '0';
        if ($up) $digits = dec_inc_digits($digits);
    } else $digits .= str_repeat('0', $scale-$oldScale);
    return dec_format_digits($digits, $scale, $negative);
}
function dec_mul($a, $b, $scale = 2) {
    [$na,$a,$sa] = dec_parts($a); [$nb,$b,$sb] = dec_parts($b);
    return dec_round(dec_format_digits(dec_mul_digits($a,$b), $sa+$sb, $na !== $nb), $scale);
}
function dec_div($a, $b, $scale = 2) {
    [$na,$a,$sa] = dec_parts($a); [$nb,$b,$sb] = dec_parts($b);
    [$q,$r] = dec_div_digits($a . str_repeat('0', $sb+$scale), $b . str_repeat('0', $sa));
    if (dec_cmp_digits(dec_mul_digits($r, '2'), $b . str_repeat('0', $sa)) >= 0) $q = dec_inc_digits($q);
    return dec_format_digits($q, $scale, $na !== $nb);
}
function dec_units($value, $scale = 2) {
    $s = str_replace('.', '', dec_round($value, $scale));
    if (dec_cmp_digits(ltrim($s, '-'), '9000000000000000') > 0) throw new InvalidArgumentException('Amount exceeds the supported safe limit.');
    return (int)$s;
}
function dec_add($a, $b, $scale = 2) { return dec_format_digits((string)abs($n = dec_units($a,$scale)+dec_units($b,$scale)), $scale, $n < 0); }
function dec_sub($a, $b, $scale = 2) { $b=dec_round($b,$scale); return dec_add($a, $b[0]==='-'?substr($b,1):'-'.$b, $scale); }
/** Largest remainder, stable by line order: allocated cents always add up exactly. */
function money_allocate($total, array $weights) {
    $cents = dec_units($total); $sum='0';
    foreach($weights as $weight) {
        if(!preg_match('/^\d+$/D',(string)$weight))throw new InvalidArgumentException('Invalid allocation weight.');
        $sum=dec_add_digits($sum,(string)$weight);
    }
    if ($cents < 0 || !$weights || $sum === '0') throw new InvalidArgumentException('Invalid allocation weights.');
    $parts=[]; $rem=[];
    foreach ($weights as $i=>$weight) {
        if ($weight < 0) throw new InvalidArgumentException('Invalid allocation weight.');
        [$q,$r] = dec_div_digits(dec_mul_digits((string)$cents,(string)$weight), (string)$sum);
        $parts[$i]=(int)$q; $rem[$i]=$r;
    }
    uksort($rem, function($a,$b) use($rem) { $cmp=dec_cmp_digits($rem[$b],$rem[$a]); return $cmp ?: ($a <=> $b); });
    $left=$cents-array_sum($parts);
    foreach (array_keys($rem) as $i) { if ($left-- <= 0) break; $parts[$i]++; }
    ksort($parts);
    return array_map(function($n){return dec_format_digits((string)$n,2);},$parts);
}
