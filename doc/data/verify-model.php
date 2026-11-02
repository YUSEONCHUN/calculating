<?php
/**
 * 권리금 모델 검증 스크립트 (문서용 재현 도구)
 *
 *   php doc/data/verify-model.php
 *
 * doc/data/2026-09-권리금-적정성-조사.csv 의 20케이스 × 조사원 3명 = 60응답을 읽어
 * v1(계단형)·v2(곡선형)을 보정 전/후 계수로 각각 계산하고
 *   - 응답이 산출 범위(빠른매도~높은호가) 안에 든 비율
 *   - 응답 ÷ 적정호가 중앙값 (전체 / 소형 / 일반)
 *   - 케이스별·조사원별 적중
 * 을 출력한다. 계산식은 src/config.php 의 gwBaseQuote() / goodwill_curve.php 의
 * prescMultiplier() 와 동일하며, DB 보정(지역 피드백·설문)은 제외한 순수 기초 산출만 비교한다.
 * 보정 전 계수 = 지역 회수개월수 ÷ 1.15 (소수 1자리 반올림), 소형 규모 계수 0.88.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$csv = __DIR__ . '/2026-09-권리금-적정성-조사.csv';
$lines = file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
array_shift($lines);
$rows = array();
foreach ($lines as $l) { $rows[] = explode(',', $l); }

$REGION = array(
    '강남 등 서울 핵심 상권' => 'seoul_core', '서울 일반' => 'seoul',
    '수도권 (경기·인천)' => 'metro', '광역시 (부산·대구 등)' => 'bigcity', '지방 중소도시' => 'local',
);
$MONTHS = array(
    'seoul_core' => array(20.2, 25.8, 33.2), 'seoul' => array(16.9, 21.5, 27.7),
    'metro' => array(17.7, 21.0, 27.1), 'bigcity' => array(13.6, 20.2, 26.6),
    'local' => array(12.2, 18.2, 23.9),
);
$MULT = array(1000 => 1.00, 2500 => 1.15, 3500 => 1.25, 5000 => 1.35);

function stepMult($p, $u, $M) {
    if ($p < 1000) return $u;
    if ($p < 2500) return $M[1000];
    if ($p < 3500) return $M[2500];
    if ($p < 5000) return $M[3500];
    return $M[5000];
}
function lerpMult($p, $u, $M) {
    if ($p <= 1000) return $u + ($p / 1000) * ($M[1000] - $u);
    if ($p < 2500) return $M[1000] + ($p - 1000) / 1500 * ($M[2500] - $M[1000]);
    if ($p < 3500) return $M[2500] + ($p - 2500) / 1000 * ($M[3500] - $M[2500]);
    if ($p < 5000) return $M[3500] + ($p - 3500) / 1500 * ($M[5000] - $M[3500]);
    return $M[5000];
}
function quote($months, $mult, $presc, $otcDaily, $rent, $deposit) {
    $otc = $otcDaily * 0.4 * 25;
    $depAdj = $deposit > 30000 ? $deposit * 0.05 / 12 : 0;
    $profit = $presc + $otc - ($rent + $depAdj);
    if ($profit < 700) return array(3000, 5000, 8000, $profit);
    $r = $profit < 1000 ? 0.5 + ($profit - 700) / 300 * 0.5 : 1.0;
    return array(round($profit * $months[0] * $mult * $r), round($profit * $months[1] * $mult * $r),
                 round($profit * $months[2] * $mult * $r), $profit);
}
function med($a) { sort($a); $n = count($a); return $n % 2 ? $a[($n - 1) / 2] : ($a[$n / 2 - 1] + $a[$n / 2]) / 2; }

function run($rows, $REGION, $MONTHS, $MULT, $mode, $calibrated) {
    $scale = $calibrated ? 1.0 : 1 / 1.15;
    $u = $calibrated ? 0.50 : 0.88;
    $hit = 0; $tot = 0; $ratios = array(); $small = array(); $large = array(); $cases = array();
    foreach ($rows as $r) {
        $key = $REGION[$r[1]];
        $months = array(); foreach ($MONTHS[$key] as $m) { $months[] = $calibrated ? $m : round($m / 1.15, 1); }
        $presc = (float) $r[2]; $otcD = (float) $r[4]; $rent = (float) $r[5]; $dep = (float) $r[6];
        $mult = $mode === 'v1' ? stepMult($presc, $u, $MULT) : lerpMult($presc, $u, $MULT);
        list($q, $f, $h, $profit) = quote($months, $mult, $presc, $otcD, $rent, $dep);
        $ans = array((float) $r[10], (float) $r[11], (float) $r[12]);
        $cHit = 0;
        foreach ($ans as $a) {
            $tot++;
            if ($a >= $q && $a <= $h) { $hit++; $cHit++; }
            $ratios[] = $a / $f;
            if ($presc < 1000) { $small[] = $a / $f; } else { $large[] = $a / $f; }
        }
        $cases[] = array('no' => $r[0], 'region' => $r[1], 'presc' => $presc, 'q' => $q, 'f' => $f, 'h' => $h,
                         'hit' => $cHit, 'ans' => $ans, 'mult' => $mult);
    }
    return array('hit' => $hit, 'tot' => $tot, 'med' => med($ratios),
                 'small' => $small ? med($small) : null, 'large' => med($large), 'cases' => $cases);
}

$R = array();
foreach (array(array('v1', false), array('v1', true), array('v2', false), array('v2', true)) as $c) {
    $res = run($rows, $REGION, $MONTHS, $MULT, $c[0], $c[1]);
    $R[$c[0] . ($c[1] ? '-post' : '-pre')] = $res;
    printf("%s %-6s 적중 %2d/%d (%2.0f%%)  ratio중앙값 %.3f  소형 %s  일반 %.3f\n",
        $c[0], $c[1] ? '보정후' : '보정전', $res['hit'], $res['tot'], $res['hit'] / $res['tot'] * 100,
        $res['med'], $res['small'] === null ? '-' : number_format($res['small'], 3), $res['large']);
}

echo "\n[v1 보정후 — 케이스별]\n";
foreach ($R['v1-post']['cases'] as $c) {
    printf("%2s %-14s 조제%5d  %6d~%6d  응답 %s  적중 %d/3\n", $c['no'], $c['region'], $c['presc'],
        $c['q'], $c['h'], implode('/', array_map('intval', $c['ans'])), $c['hit']);
}

echo "\n[소형 3케이스 — v1 vs v2 보정후]\n";
foreach ($R['v1-post']['cases'] as $i => $c) {
    if ($c['presc'] >= 1000) continue;
    $v = $R['v2-post']['cases'][$i];
    printf("%2s 조제%5d  v1 계수 %.2f → %5d~%5d (적정 %5d)   v2 계수 %.2f → %5d~%5d (적정 %5d)   응답 %s\n",
        $c['no'], $c['presc'], $c['mult'], $c['q'], $c['h'], $c['f'],
        $v['mult'], $v['q'], $v['h'], $v['f'], implode('/', array_map('intval', $c['ans'])));
}

foreach (array('v1-pre' => '보정전', 'v1-post' => '보정후') as $kk => $lab) {
echo "\n[조사원별 적중 — v1 {$lab}]\n";
for ($s = 0; $s < 3; $s++) {
    $hit = 0; $rt = array();
    foreach ($R[$kk]['cases'] as $c) {
        $a = $c['ans'][$s];
        if ($a >= $c['q'] && $a <= $c['h']) $hit++;
        $rt[] = $a / $c['f'];
    }
    printf("조사원%d  적중 %2d/20  ratio중앙값 %.3f\n", $s + 1, $hit, med($rt));
}
}

echo "\n[v1 vs v2 — 20케이스 적정호가 차이]\n";
$maxd = 0; $maxno = '';
foreach ($R['v1-post']['cases'] as $i => $c) {
    $v = $R['v2-post']['cases'][$i];
    $d = abs($v['f'] - $c['f']) / $c['f'] * 100;
    if ($d > $maxd) { $maxd = $d; $maxno = $c['no'] . ' (조제 ' . (int) $c['presc'] . ')'; }
}
printf("최대 차이 %.1f%% — 케이스 %s\n", $maxd, $maxno);

echo "\n[0단계 모델 비교 — 1차 40응답(조사원 1·2)]\n";
$mA = array('small' => array(), 'large' => array(), 'hit' => 0, 'tot' => 0);
$mB = array('hit' => 0, 'tot' => 0);
foreach ($rows as $r) {
    $presc = (float) $r[2]; $otc = (float) $r[4] * 0.4 * 25;
    $gross = $presc + $otc;                       // 월 매출총이익 (월세 차감 전)
    $a12 = $gross * 12;                           // 모델 A: 일반 상가식 12개월분
    $key = $REGION[$r[1]];
    list($q, $f, $h) = quote($MONTHS[$key], stepMult($presc, 0.50, $MULT), $presc, (float) $r[4], (float) $r[5], (float) $r[6]);
    foreach (array(10, 11) as $i) {               // 1차 조사 = 조사원 1·2
        $ans = (float) $r[$i];
        $mA['tot']++; $mB['tot']++;
        if ($ans >= $gross * 10 && $ans <= $gross * 14) $mA['hit']++;  // A는 10~14개월을 범위로 간주
        if ($ans >= $q && $ans <= $h) $mB['hit']++;
        if ($presc < 1000) { $mA['small'][] = $a12 / $ans; } else { $mA['large'][] = $a12 / $ans; }
    }
}
printf("모델 A (매출총이익 × 12개월)  범위(10~14개월) 적중 %d/%d\n", $mA['hit'], $mA['tot']);
printf("  모델 ÷ 응답 중앙값 — 소형 %.2f배 (%.2f~%.2f) / 일반 %.2f배 (%.2f~%.2f)\n",
    med($mA['small']), min($mA['small']), max($mA['small']),
    med($mA['large']), min($mA['large']), max($mA['large']));
printf("모델 B (회수배수, 보정후 계수) 범위 적중 %d/%d\n", $mB['hit'], $mB['tot']);

echo "\n[3차 — 1차 기초값 위에 매물 조정을 얹었을 때 (조사 60응답)]\n";
// 3차는 1차와 같은 엔진(gwBaseQuote)으로 기초 시세를 내고 그 위에 매물 조건을 곱한다.
// 조사지에는 상권·임대차 항목이 없으므로 화면 기본값으로 가정한다:
//   상권 동네/주택가 0% · 병원 의존 복수 +10% · 잔여 계약 1~3년 0% · 임대료 부담(계산)
$hit3 = 0; $tot3 = 0; $r3 = array(); $rates = array();
foreach ($rows as $r) {
    $key = $REGION[$r[1]];
    $presc = (float) $r[2]; $otcD = (float) $r[4]; $rent = (float) $r[5]; $dep = (float) $r[6];
    list($q, $f, $h, $profit) = quote($MONTHS[$key], stepMult($presc, 0.50, $MULT), $presc, $otcD, $rent, $dep);
    $gross = $presc + $otcD * 0.4 * 25;                 // 월 매출총이익 (월세 차감 전)
    $rentRatio = $gross > 0 ? $rent / $gross : 0;
    $rate = 0.10;                                        // 병원 의존 복수 +10%
    if ($rentRatio > 0.5) { $rate -= 0.20; } elseif ($rentRatio > 0.3) { $rate -= 0.10; }
    $rates[] = $rate;
    $m = 1 + $rate;
    foreach (array(10, 11, 12) as $i) {
        $a = (float) $r[$i];
        $tot3++;
        if ($a >= $q * $m && $a <= $h * $m) { $hit3++; }
        $r3[] = $a / ($f * $m);
    }
}
printf("3차 기본 입력  적중 %d/%d (%2.0f%%)  ratio중앙값 %.3f  조정률 중앙값 %+.0f%%\n",
    $hit3, $tot3, $hit3 / $tot3 * 100, med($r3), med($rates) * 100);
printf("  (참고) 1차 보정후 적중 %d/%d\n", $R['v1-post']['hit'], $R['v1-post']['tot']);
