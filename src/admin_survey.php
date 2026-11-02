<?php
// 관리자 — 적정성 설문 집계 (survey.php 응답 분석) : 권리금·급여 시세·OTC 판매가 3주제
// 핵심 지표: ratio = 응답자 조정액 ÷ 모델·시세 금액 — 유형별 중앙값이 곧 유형별 보정 계수
$ADMIN_PAGE = 'survey';
require __DIR__ . '/admin_common.php';
require_once __DIR__ . '/survey_cases.php';

$flash = '';
$flash_err = '';

function gsMedian($arr)
{
    if (count($arr) === 0) {
        return null;
    }
    sort($arr);
    $n = count($arr);
    return $n % 2 ? $arr[($n - 1) / 2] : ($arr[$n / 2 - 1] + $arr[$n / 2]) / 2;
}

function gsQuartile($arr, $q)
{
    if (count($arr) === 0) {
        return null;
    }
    sort($arr);
    $pos = (count($arr) - 1) * $q;
    $lo = (int) floor($pos);
    $hi = (int) ceil($pos);
    return $arr[$lo] + ($arr[$hi] - $arr[$lo]) * ($pos - $lo);
}

$TOPICS = gsTopics();
$topic = isset($_GET['topic']) && isset($TOPICS[$_GET['topic']]) ? $_GET['topic'] : 'goodwill';
$TC = $TOPICS[$topic];
$CASES = gsTopicCases($topic);
$TYPES = gsTopicTypes($topic);
$VKEYS = array_keys($TC['verdicts']); // [낮음, 적정, 높음] 순서 고정
$unit = $TC['unit'];

$rows = array();
$subs = array();
try {
    $pdo = getConnection();
    gsEnsureTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gs_action']) && $_POST['gs_action'] === 'del_sub') {
        $sid = preg_replace('/[^a-f0-9]/', '', isset($_POST['submission']) ? $_POST['submission'] : '');
        if ($sid !== '') {
            $st = $pdo->prepare('DELETE FROM goodwill_survey WHERE submission = ?');
            $st->execute(array($sid));
            $flash = '응답자 1명의 응답(' . $st->rowCount() . '건)을 삭제했습니다.';
        }
    }

    $st = $pdo->prepare("SELECT * FROM goodwill_survey WHERE topic = ? ORDER BY id");
    $st->execute(array($topic));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare(
        "SELECT submission, resp_type, region, surveyor, COUNT(*) n, MIN(created_at) at
           FROM goodwill_survey WHERE topic = ? GROUP BY submission, resp_type, region, surveyor ORDER BY at DESC LIMIT 60"
    );
    $st->execute(array($topic));
    $subs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $flash_err = 'DB 오류: ' . htmlspecialchars($e->getMessage());
}

// ===== 집계 계산 =====
$byType = array();   // type => array(ratios[], verdicts{}, band_in, n, subs{})
$byTypeCase = array(); // type|case => ratios[]
$vZero = array();
foreach ($VKEYS as $vk) {
    $vZero[$vk] = 0;
}
foreach ($rows as $r) {
    $t = $r['resp_type'];
    if (!isset($byType[$t])) {
        $byType[$t] = array('ratios' => array(), 'adj_ratios' => array(), 'v' => $vZero, 'band' => 0, 'n' => 0, 'subs' => array());
    }
    $ratio = $r['model_amount'] > 0 ? $r['est_amount'] / $r['model_amount'] : null;
    if ($ratio !== null && $ratio > 0.05 && $ratio < 20) { // 극단 오입력 컷
        $byType[$t]['ratios'][] = $ratio;
        $byTypeCase[$t . '|' . $r['case_key']][] = $ratio;
    }
    // ③ 시세 공개 후 조정액 ratio (앵커링 있음 — 협상 범위 참고용, 보정 계수에는 미사용)
    $adj = isset($r['adj_amount']) ? (int) $r['adj_amount'] : 0;
    if ($adj > 0 && $r['model_amount'] > 0) {
        $ar = $adj / $r['model_amount'];
        if ($ar > 0.05 && $ar < 20) {
            $byType[$t]['adj_ratios'][] = $ar;
        }
    }
    if (isset($byType[$t]['v'][$r['verdict']])) {
        $byType[$t]['v'][$r['verdict']]++;
    }
    $c = isset($CASES[$r['case_key']]) ? $CASES[$r['case_key']] : null;
    if ($c && $r['est_amount'] >= $c['quick'] && $r['est_amount'] <= $c['high']) {
        $byType[$t]['band']++;
    }
    $byType[$t]['n']++;
    $byType[$t]['subs'][$r['submission']] = 1;
}

$total_resp = count($subs);
$total_ans = count($rows);
$survey_url = 'survey.php?topic=' . $topic;

// ===== 원클릭 계산기 반영 =====
// 안전장치: 전체 응답자 15명+ AND 유형별 5명+ 인 "자격 유형" 2개+ 일 때만 반영 가능
// 계수 = 자격 유형별 ratio 중앙값들의 중앙값 (유형 균형 — 특정 유형 응답 쏠림 방지), ±15% 클램프
$MIN_RESP_TOTAL = 15;
$MIN_RESP_TYPE = 5;
$CAL_LIMIT = 0.15;

$type_meds = array();
foreach ($TYPES as $tk => $tn) {
    if (isset($byType[$tk]) && count($byType[$tk]['subs']) >= $MIN_RESP_TYPE && count($byType[$tk]['ratios']) > 0) {
        $type_meds[$tk] = gsMedian($byType[$tk]['ratios']);
    }
}
$raw_factor = count($type_meds) > 0 ? gsMedian(array_values($type_meds)) : null;
$preview_factor = $raw_factor !== null ? round(min(1 + $CAL_LIMIT, max(1 - $CAL_LIMIT, $raw_factor)), 3) : null;
$eligible = $total_resp >= $MIN_RESP_TOTAL && count($type_meds) >= 2 && $preview_factor !== null;
$block_reasons = array();
if ($total_resp < $MIN_RESP_TOTAL) {
    $block_reasons[] = '전체 응답자 ' . $MIN_RESP_TOTAL . '명 이상 필요 (현재 ' . $total_resp . '명)';
}
if (count($type_meds) < 2) {
    $block_reasons[] = '유형별 ' . $MIN_RESP_TYPE . '명 이상인 응답자 유형 2개 이상 필요 (현재 ' . count($type_meds) . '개)';
}

function gsCalRead($pdo)
{
    $row = $pdo->query("SELECT value_json FROM admin_settings WHERE setting_key = 'survey_calibration'")->fetch(PDO::FETCH_ASSOC);
    $map = $row ? json_decode($row['value_json'], true) : null;
    return is_array($map) ? $map : array();
}

function gsCalWrite($pdo, $map)
{
    $stmt = $pdo->prepare(
        "INSERT INTO admin_settings (setting_key, value_json) VALUES ('survey_calibration', ?)
         ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)"
    );
    $stmt->execute(array(json_encode($map, JSON_UNESCAPED_UNICODE)));
}

$applied = null; // 현재 주제의 반영 상태
try {
    if (isset($pdo)) {
        adminEnsureTable($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gs_action']) && $_POST['gs_action'] === 'cal_apply') {
            if ($eligible) {
                $map = gsCalRead($pdo);
                $kst = new DateTime('now', new DateTimeZone('Asia/Seoul'));
                $map[$topic] = array(
                    'factor' => $preview_factor,
                    'resp' => $total_resp,
                    'applied_at' => $kst->format('Y-m-d H:i'),
                );
                gsCalWrite($pdo, $map);
                $flash = htmlspecialchars($TC['name']) . ' 보정 계수 ×' . number_format($preview_factor, 3) . ' 를 계산기에 반영했습니다.';
            } else {
                $flash_err = '반영 조건 미충족: ' . implode(' / ', $block_reasons);
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gs_action']) && $_POST['gs_action'] === 'cal_clear') {
            $map = gsCalRead($pdo);
            unset($map[$topic]);
            gsCalWrite($pdo, $map);
            $flash = htmlspecialchars($TC['name']) . ' 보정을 해제했습니다. 계산기는 기본 모델·시세로 동작합니다.';
        }
        $map = gsCalRead($pdo);
        if (isset($map[$topic]['factor'])) {
            $applied = $map[$topic];
        }
    }
} catch (Exception $e) {
    $flash_err = '보정 설정 처리 오류: ' . htmlspecialchars($e->getMessage());
}

// 주제별 반영 대상 설명
$apply_targets = array(
    'goodwill' => '권리금 시세 · 권리금 시세(곡선형)의 산출액(빠른매도·적정·높은호가)에 계수를 곱합니다. 시설양도비 구간(임계점 미달)은 제외됩니다.',
    'salary' => '구직자 급여 · 채용 분석이 사용하는 지역 급여 시세 기준값(salary_table 중앙값)에 계수를 곱합니다.',
    'otc' => 'OTC 판매가 계산기의 실측 시세 패널에 체감 보정 중앙값을 병기합니다 (권장 판매가 자체는 바꾸지 않습니다).',
);

// 주제별 밴드·해석 설명
if ($topic === 'goodwill') {
    $band_desc = '조정액이 빠른매도~높은호가 내';
    $hint = '<b>읽는 법</b> — ratio 중앙값 1.15 = 그 유형은 모델보다 평균 15% 높게 봄 → 유형별 보정 계수로 사용 가능.
        예상 패턴은 매도 &gt; 중개사 ≈ 일반 &gt; 매수이며, <b>중개사</b>를 기준(1.0 근처)으로 삼으면 나머지 유형의 상대 프리미엄/디스카운트가 나옵니다.
        "비쌈" 비율이 높고 ratio&lt;1이면 모델 상향이 아니라 <b>하향</b> 신호입니다. 유형당 5명 이상부터 방향성, 15명 이상부터 크기를 신뢰하세요.';
} elseif ($topic === 'salary') {
    $band_desc = '조정액이 시세 중앙값 ±15% 내';
    $hint = '<b>읽는 법</b> — ratio 중앙값 1.10 = 그 유형은 시세 중앙값보다 10% 높게 봄.
        예상 패턴은 근무약사·구직 &gt; 개국약사(고용주)이며, 격차가 크면 시세 표에 <b>희망급여/제시급여 구분 표기</b>가 필요하다는 신호입니다.
        "낮음" 비율이 높으면 조사 데이터가 시장 체감보다 낮게 적재된 것 — 다음 조사 회차에서 세전/세후·근무시간 통제를 강화하세요. 유형당 5명 이상부터 방향성을 신뢰하세요.';
} else {
    $band_desc = '조정액이 시세 중앙값 ±15% 내';
    $hint = '<b>읽는 법</b> — ratio 중앙값 1.10 = 그 유형은 전국 시세 중앙값보다 10% 높게 봄.
        예상 패턴은 약사 &gt; 소비자(소비자는 낮게 추정)이며, 격차가 크면 판매가 진단의 권장가에 <b>지역·상권 보정</b>이 필요하다는 신호입니다.
        특정 품목만 ratio가 튀면 그 품목의 조사 중앙값(포장·함량 혼입)을 점검하세요. 유형당 5명 이상부터 방향성을 신뢰하세요.';
}

$hint .= ' 설문은 계산 결과를 보여준 상태에서 ±로 조정하는 방식이라 ratio가 1 근처로 보수적으로 나옵니다 — 절대 크기보다 <b>유형 간 상대 비교</b>(누가 더 높게/낮게 보나)와 방향에 초점을 두세요.';

adminPageOpen(
    '설문 집계',
    '<a href="' . $survey_url . '" target="_blank">' . htmlspecialchars($TC['title']) . '</a> 응답을 유형별로 집계합니다.
     <b>ratio = 응답자 조정액 ÷ 모델·시세 금액</b> — 1보다 크면 시세보다 높게, 작으면 낮게 보는 것.
     조사원에게는 <code>' . htmlspecialchars($survey_url) . '</code> 링크를 전달하세요.
     현장 배포·부착용 <a href="survey_qr.html" target="_blank">QR 안내지 인쇄</a> (총 4장: 안내지 1 + 주제별 포스터 3)',
    '설문 집계'
);
?>

<style>
    .gs-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .gs-tab { padding: 9px 18px; border-radius: 20px; border: 1.5px solid #d1d5db; background: #fff; color: #374151; font-weight: bold; font-size: 0.88em; text-decoration: none; }
    .gs-tab.on { border-color: #b45309; background: #fff7ed; color: #9a3412; }
    .gs-tbl { width: 100%; border-collapse: collapse; font-size: 0.87em; }
    .gs-tbl th { text-align: center; padding: 8px 8px; color: #6b7280; font-size: 0.88em; border-bottom: 2px solid #f3f4f6; white-space: nowrap; }
    .gs-tbl td { padding: 8px; border-bottom: 1px solid #f9fafb; text-align: center; font-variant-numeric: tabular-nums; }
    .gs-tbl td:first-child, .gs-tbl th:first-child { text-align: left; }
    .gs-ratio { font-weight: 900; font-size: 1.05em; }
    .gs-ratio.hi { color: #b91c1c; }
    .gs-ratio.lo { color: #1d4ed8; }
    .gs-ratio.mid { color: #047857; }
    .vbar { display: inline-flex; width: 120px; height: 12px; border-radius: 6px; overflow: hidden; vertical-align: middle; background: #f3f4f6; }
    .vbar i { height: 100%; }
    .dim { color: #9ca3af; font-size: 0.9em; }
    .tbl-scroll { overflow-x: auto; }
    .hint { font-size: 0.82em; color: #6b7280; background: #f8fafc; border: 1px dashed #d4dbe5; border-radius: 8px; padding: 10px 14px; line-height: 1.7; margin-top: 12px; }
</style>

<div class="gs-tabs">
    <?php foreach ($TOPICS as $tk => $t) : ?>
    <a class="gs-tab <?= $tk === $topic ? 'on' : '' ?>" href="?topic=<?= $tk ?>"><?= htmlspecialchars($t['name']) ?></a>
    <?php endforeach; ?>
</div>

<div class="result-card">
    <div class="grp-head"><h3>👥 유형별 요약 — <?= htmlspecialchars($TC['name']) ?></h3>
        <span class="badge on">응답자 <?= number_format($total_resp) ?>명 · 케이스 응답 <?= number_format($total_ans) ?>건</span>
    </div>
    <?php if ($total_ans === 0) : ?>
    <p class="grp-desc">아직 응답이 없습니다. 조사원에게 <a href="<?= $survey_url ?>" target="_blank">설문 링크</a>를 전달하세요.</p>
    <?php else : ?>
    <div class="tbl-scroll"><table class="gs-tbl">
        <tr><th>응답자 유형</th><th>응답자</th><th>ratio 중앙값</th><th>IQR (25~75%)</th>
            <th>판정 분포 (<?= implode('·', $VKEYS) ?>)</th>
            <th>시세 밴드 안<br><span class="dim">(<?= $band_desc ?>)</span></th></tr>
        <?php foreach ($TYPES as $tk => $tn) :
            if (!isset($byType[$tk])) {
                continue;
            }
            $d = $byType[$tk];
            $med = gsMedian($d['ratios']);
            $q1 = gsQuartile($d['ratios'], 0.25);
            $q3 = gsQuartile($d['ratios'], 0.75);
            $vt = max(1, array_sum($d['v']));
            $cls = $med === null ? '' : ($med >= 1.1 ? 'hi' : ($med <= 0.9 ? 'lo' : 'mid'));
        ?>
        <tr>
            <td><b><?= htmlspecialchars($tn) ?></b></td>
            <td><?= count($d['subs']) ?>명 <span class="dim">(<?= $d['n'] ?>건)</span></td>
            <td><span class="gs-ratio <?= $cls ?>"><?= $med !== null ? number_format($med, 2) : '-' ?></span></td>
            <td class="dim"><?= $q1 !== null ? number_format($q1, 2) . ' ~ ' . number_format($q3, 2) : '-' ?></td>
            <td>
                <span class="vbar">
                    <i style="width:<?= round($d['v'][$VKEYS[0]] / $vt * 100) ?>%; background:#93c5fd;"></i>
                    <i style="width:<?= round($d['v'][$VKEYS[1]] / $vt * 100) ?>%; background:#6ee7b7;"></i>
                    <i style="width:<?= round($d['v'][$VKEYS[2]] / $vt * 100) ?>%; background:#fca5a5;"></i>
                </span>
                <span class="dim"><?= $d['v'][$VKEYS[0]] ?>·<?= $d['v'][$VKEYS[1]] ?>·<?= $d['v'][$VKEYS[2]] ?></span>
            </td>
            <td><?= round($d['band'] / max(1, $d['n']) * 100) ?>%</td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <div class="hint"><?= $hint ?></div>
    <?php endif; ?>
</div>

<div class="result-card">
    <div class="grp-head"><h3>⚙ 계산기 원클릭 반영</h3>
        <?php if ($applied !== null) : ?>
        <span class="badge on">반영 중 ×<?= number_format((float) $applied['factor'], 3) ?></span>
        <?php else : ?>
        <span class="badge">미반영</span>
        <?php endif; ?>
    </div>
    <p class="grp-desc"><?= htmlspecialchars($apply_targets[$topic]) ?></p>

    <?php if ($applied !== null) : ?>
    <p style="font-size:0.88em;">현재 <b>×<?= number_format((float) $applied['factor'], 3) ?></b> 반영 중
        <span class="dim">(응답자 <?= (int) $applied['resp'] ?>명 기준 · <?= htmlspecialchars(isset($applied['applied_at']) ? $applied['applied_at'] : '') ?> 반영)</span></p>
    <?php endif; ?>

    <?php if (count($type_meds) > 0) : ?>
    <p style="font-size:0.88em; margin:6px 0;">자격 유형(응답자 <?= $MIN_RESP_TYPE ?>명+) ratio 중앙값:
        <?php $parts = array(); foreach ($type_meds as $tk => $m) { $parts[] = htmlspecialchars($TYPES[$tk]) . ' <b>' . number_format($m, 2) . '</b>'; } echo implode(' · ', $parts); ?>
        → 반영 계수(유형 균형 중앙값, ±<?= round($CAL_LIMIT * 100) ?>% 한도): <b class="gs-ratio <?= $preview_factor >= 1.1 ? 'hi' : ($preview_factor <= 0.9 ? 'lo' : 'mid') ?>">×<?= number_format($preview_factor, 3) ?></b>
        <?php if ($raw_factor !== null && round($raw_factor, 3) != $preview_factor) : ?><span class="dim">(원값 <?= number_format($raw_factor, 3) ?> → 한도 적용)</span><?php endif; ?>
    </p>
    <?php endif; ?>

    <div style="display:flex; gap:10px; align-items:center; margin-top:10px; flex-wrap:wrap;">
        <?php if ($eligible) : ?>
        <form method="post" style="display:inline;" onsubmit="return confirm('<?= htmlspecialchars($TC['name']) ?> 보정 계수 ×<?= number_format($preview_factor, 3) ?> 를 계산기에 반영할까요?\n반영 즉시 사용자 화면의 산출값이 바뀝니다.');">
            <input type="hidden" name="gs_action" value="cal_apply">
            <button type="submit" class="btn-save" style="padding:9px 20px;">계산기에 반영 (×<?= number_format($preview_factor, 3) ?>)</button>
        </form>
        <?php else : ?>
        <button type="button" class="btn-save" style="padding:9px 20px; opacity:0.45; cursor:not-allowed;" disabled>계산기에 반영</button>
        <span class="dim" style="font-size:0.85em;">🔒 <?= htmlspecialchars(implode(' / ', $block_reasons)) ?></span>
        <?php endif; ?>
        <?php if ($applied !== null) : ?>
        <form method="post" style="display:inline;" onsubmit="return confirm('보정을 해제할까요? 계산기는 기본 모델·시세로 되돌아갑니다.');">
            <input type="hidden" name="gs_action" value="cal_clear">
            <button type="submit" class="btn-reset" style="padding:9px 16px; color:#b91c1c;">보정 해제</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="hint" style="margin-top:12px;">
        반영은 자동이 아니라 이 버튼으로만 이뤄집니다. 응답이 더 쌓이면 계수가 달라질 수 있으니, 조사 회차가 끝날 때마다 다시 확인 후 재반영하세요.
        반영된 계수는 <code>관리자 설정(survey_calibration)</code>에 저장되며 언제든 해제할 수 있습니다.
    </div>
</div>

<?php if ($total_ans > 0) : ?>
<div class="result-card">
    <div class="grp-head"><h3>📐 케이스 × 유형 — ratio 중앙값</h3></div>
    <p class="grp-desc"><?php if ($topic === 'goodwill') : ?>특정 케이스(규모·지역)에서만 격차가 벌어지는지 확인합니다. C4는 소형(두 모델 격차 큰 케이스), C1·C5는 대형입니다.<?php elseif ($topic === 'salary') : ?>특정 지역에서만 시세와 체감의 격차가 벌어지는지 확인합니다. 모델가는 salary_table 지역 중앙값입니다.<?php else : ?>특정 품목에서만 시세와 체감의 격차가 벌어지는지 확인합니다. 모델가는 otc_prices 전국 중앙값입니다.<?php endif; ?></p>
    <div class="tbl-scroll"><table class="gs-tbl">
        <tr><th>케이스</th><th>조건 요약</th><th>시세·모델가</th>
            <?php foreach ($TYPES as $tk => $tn) : ?><th><?= mb_substr($tn, 0, 2) ?></th><?php endforeach; ?></tr>
        <?php foreach ($CASES as $ck => $c) : ?>
        <tr>
            <td><b><?= $ck ?></b></td>
            <td style="text-align:left;" class="dim"><?= htmlspecialchars(gsCaseSummary($topic, $c)) ?></td>
            <td><?= number_format($c['fair']) ?><?= $unit ?></td>
            <?php foreach ($TYPES as $tk => $tn) :
                $m = gsMedian(isset($byTypeCase[$tk . '|' . $ck]) ? $byTypeCase[$tk . '|' . $ck] : array());
                $cls = $m === null ? '' : ($m >= 1.1 ? 'hi' : ($m <= 0.9 ? 'lo' : 'mid'));
            ?>
            <td><span class="gs-ratio <?= $cls ?>" style="font-size:0.95em;"><?= $m !== null ? number_format($m, 2) : '<span class="dim">-</span>' ?></span></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>

<div class="result-card">
    <div class="grp-head"><h3>🧾 최근 응답자 (60명까지)</h3></div>
    <div class="tbl-scroll"><table class="gs-tbl">
        <tr><th>응답 시각</th><th>유형</th><th>지역</th><th>조사원</th><th>케이스</th><th></th></tr>
        <?php foreach ($subs as $s) : ?>
        <tr>
            <td class="dim"><?= htmlspecialchars(substr($s['at'], 0, 16)) ?></td>
            <td><?= htmlspecialchars(isset($TYPES[$s['resp_type']]) ? $TYPES[$s['resp_type']] : $s['resp_type']) ?></td>
            <td class="dim"><?= $s['region'] !== '' ? htmlspecialchars($s['region']) : '-' ?></td>
            <td class="dim"><?= $s['surveyor'] !== '' ? htmlspecialchars($s['surveyor']) : '-' ?></td>
            <td><?= (int) $s['n'] ?>건</td>
            <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('이 응답자의 응답 전체를 삭제할까요?');">
                    <input type="hidden" name="gs_action" value="del_sub">
                    <input type="hidden" name="submission" value="<?= htmlspecialchars($s['submission']) ?>">
                    <button type="submit" class="btn-reset" style="padding:4px 10px; font-size:0.78em; color:#b91c1c;">삭제</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php endif; ?>

<?php adminPageClose(); ?>
