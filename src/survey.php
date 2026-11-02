<?php
// 적정성 설문 (조사원용, 링크 배포) — 주제: 권리금(goodwill) · 급여 시세(salary) · OTC 판매가(otc)
// 앵커링 방지 설계: ① 조건만 보고 추정액 기입 → ② 모델·시세 금액 공개(추정액 잠금) → ③ 낮음/적정/높음 판정
// 케이스 제시 순서는 응답자마다 무작위. 집계: 관리자 > 설문 집계(admin_survey.php)
// 권리금 기존 배포 링크(goodwill_survey.php)는 이 파일을 topic=goodwill 고정으로 include
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/survey_cases.php';

$TOPICS = gsTopics();
$topic = isset($FORCE_TOPIC) ? $FORCE_TOPIC : (isset($_GET['topic']) ? $_GET['topic'] : 'goodwill');
if (!isset($TOPICS[$topic])) {
    $topic = 'goodwill';
}
$TC = $TOPICS[$topic];
$CASES = gsTopicCases($topic);
$TYPES = gsTopicTypes($topic);

// ===== OTC 지역 케이스 (응답자 소재 지역의 시세 중앙값) =====
if ($topic === 'otc' && isset($_GET['api']) && $_GET['api'] === 'cases') {
    header('Content-Type: application/json; charset=utf-8');
    $sido = isset($_GET['sido']) ? trim($_GET['sido']) : '';
    echo json_encode(array('ok' => true, 'fairs' => gsOtcRegionalFairs($sido)), JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 제출 =====
if (isset($_GET['api']) && $_GET['api'] === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true);
    $rt = isset($body['resp_type']) && isset($TYPES[$body['resp_type']]) ? $body['resp_type'] : '';
    $answers = isset($body['answers']) && is_array($body['answers']) ? $body['answers'] : array();
    if ($rt === '' || count($answers) === 0) {
        echo json_encode(array('ok' => false, 'error' => '응답자 유형과 답변이 필요합니다.'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $pdo = getConnection();
        gsEnsureTable($pdo);
        $sub = bin2hex(random_bytes(12));
        $region = mb_substr(trim(isset($body['region']) ? $body['region'] : ''), 0, 20);
        // OTC: 응답자가 본 금액은 소재 지역 중앙값 — 서버에서 같은 방식으로 재계산해 저장 (클라이언트 값 신뢰 안 함)
        $regional = $topic === 'otc' ? gsOtcRegionalFairs($region) : null;
        $surveyor = mb_substr(trim(isset($body['surveyor']) ? $body['surveyor'] : ''), 0, 40);
        $stmt = $pdo->prepare(
            "INSERT INTO goodwill_survey (topic, submission, resp_type, region, surveyor, case_key, est_amount, model_amount, adj_amount, verdict)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $saved = 0;
        foreach ($answers as $a) {
            $ck = isset($a['case']) ? $a['case'] : '';
            $est = (int) (isset($a['est']) ? $a['est'] : 0);
            $verdict = isset($a['verdict']) && array_key_exists($a['verdict'], $TC['verdicts']) ? $a['verdict'] : '';
            if (!isset($CASES[$ck]) || $verdict === '' || $est < $TC['min'] || $est > $TC['max']) {
                continue; // 주제별 정상 범위 밖은 오입력으로 간주
            }
            // ③ 시세 공개 후 조정액 — 범위 밖·미입력은 NULL로 저장
            $adj = isset($a['adj']) ? (int) $a['adj'] : 0;
            if ($adj < $TC['min'] || $adj > $TC['max']) {
                $adj = null;
            }
            $model = $regional !== null && isset($regional[$ck]) ? (int) $regional[$ck]['fair'] : (int) $CASES[$ck]['fair'];
            $stmt->execute(array($topic, $sub, $rt, $region, $surveyor, $ck, $est, $model, $adj, $verdict));
            $saved++;
        }
        echo json_encode(array('ok' => $saved > 0, 'saved' => $saved), JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(array('ok' => false, 'error' => '저장에 실패했습니다. 잠시 후 다시 시도해주세요.'), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 화면 렌더용 케이스 (조건표 행 포함)
$CASES_OUT = array();
foreach ($CASES as $ck => $c) {
    $CASES_OUT[$ck] = array('fair' => $c['fair'], 'cond' => gsCondRows($topic, $c));
}

$SIDOS = array('서울특별시', '부산광역시', '대구광역시', '인천광역시', '광주광역시', '대전광역시', '울산광역시',
    '세종특별자치시', '경기도', '강원특별자치도', '충청북도', '충청남도', '전북특별자치도', '전라남도',
    '경상북도', '경상남도', '제주특별자치도');
$self = 'survey.php?topic=' . $topic;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($TC['title']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Malgun Gothic', -apple-system, sans-serif; margin: 0; background: #f3f5f9; color: #1e293b; line-height: 1.6; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 22px 16px 60px; }
        .brand { font-size: 0.78em; color: #94a3b8; font-weight: bold; letter-spacing: 0.05em; margin-bottom: 6px; }
        h1 { font-size: 1.25em; margin: 0 0 6px; }
        .lead { color: #64748b; font-size: 0.9em; margin: 0 0 18px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px 22px; margin-bottom: 14px; box-shadow: 0 2px 8px rgba(15,23,42,0.05); }
        label { display: block; font-weight: bold; font-size: 0.88em; color: #374151; margin: 12px 0 5px; }
        select, input[type=text], input[type=number] { width: 100%; padding: 11px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1em; background: #fff; }
        input:focus, select:focus { outline: none; border-color: #b45309; box-shadow: 0 0 0 3px rgba(180,83,9,0.12); }
        .type-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 6px; }
        .type-btn { padding: 13px 8px; border: 1.5px solid #d1d5db; border-radius: 10px; background: #fff; font-size: 0.9em; font-weight: bold; color: #374151; cursor: pointer; text-align: center; }
        .type-btn.on { border-color: #b45309; background: #fff7ed; color: #9a3412; }
        .progress { display: flex; gap: 5px; margin: 4px 0 14px; }
        .progress i { flex: 1; height: 6px; border-radius: 3px; background: #e2e8f0; }
        .progress i.done { background: #b45309; }
        .cond-tbl { width: 100%; border-collapse: collapse; font-size: 0.92em; margin: 8px 0 4px; }
        .cond-tbl td { padding: 7px 4px; border-bottom: 1px dashed #eef2f7; }
        .cond-tbl td:first-child { color: #64748b; width: 45%; }
        .cond-tbl td:last-child { text-align: right; font-weight: bold; font-variant-numeric: tabular-nums; }
        .step-title { font-size: 0.85em; font-weight: bold; color: #b45309; margin: 16px 0 4px; }
        .amt-row { display: flex; gap: 8px; align-items: center; }
        .amt-row input { text-align: right; font-size: 1.1em; font-weight: bold; }
        .amt-row .unit { color: #64748b; font-size: 0.9em; white-space: nowrap; }
        .btn { display: block; width: 100%; padding: 14px; border: none; border-radius: 10px; background: #b45309; color: #fff; font-size: 1em; font-weight: bold; cursor: pointer; margin-top: 14px; }
        .btn:disabled { background: #d6d9de; cursor: not-allowed; }
        .btn.ghost { background: #fff; border: 1.5px solid #d1d5db; color: #64748b; }
        .model-box { display: none; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; padding: 14px 16px; margin-top: 14px; text-align: center; }
        .model-box .mv { font-size: 1.5em; font-weight: 900; color: #0369a1; }
        .model-box .mk { font-size: 0.8em; color: #64748b; }
        .verdict-grid { display: none; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 12px; }
        .v-btn { padding: 14px 6px; border: 1.5px solid #d1d5db; border-radius: 10px; background: #fff; font-size: 0.95em; font-weight: bold; cursor: pointer; }
        .v-btn.v-lo { color: #1d4ed8; }
        .v-btn.v-mid { color: #047857; }
        .v-btn.v-hi { color: #b91c1c; }
        .v-btn.on { border-color: #b45309; background: #fff7ed; }
        .locked { background: #f8fafc !important; color: #94a3b8 !important; }
        .adj-wrap { display: none; }
        .adj-row { display: flex; gap: 8px; align-items: stretch; margin-top: 6px; }
        .adj-btn { flex: 0 0 48px; border: 1.5px solid #d1d5db; border-radius: 10px; background: #fff; font-size: 1.3em; font-weight: bold; color: #374151; cursor: pointer; }
        .adj-btn:active { background: #f1f5f9; }
        .adj-row input { flex: 1; text-align: right; font-size: 1.1em; font-weight: bold; min-width: 0; }
        .adj-row .unit { display: flex; align-items: center; color: #64748b; font-size: 0.9em; white-space: nowrap; }
        .adj-note { font-size: 0.8em; margin-top: 6px; font-weight: bold; }
        .adj-note.same { color: #047857; }
        .adj-note.diff { color: #b45309; }
        .adj-help { font-size: 0.76em; color: #9ca3af; margin-top: 4px; line-height: 1.5; }
        .done-box { text-align: center; padding: 40px 10px; }
        .done-box .big { font-size: 2.4em; }
        .note { font-size: 0.78em; color: #9ca3af; line-height: 1.6; margin-top: 14px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">💊 데일리팜 약국 툴 — <?= htmlspecialchars($TC['name']) ?> 조사</div>

    <!-- 0단계: 응답자 정보 -->
    <div class="card" id="step-intro">
        <h1><?= htmlspecialchars($TC['title']) ?></h1>
        <p class="lead"><?= $TC['lead'] ?></p>
        <label>응답자 유형 *</label>
        <div class="type-grid" id="type-grid">
            <?php foreach ($TYPES as $tk => $tn) : ?>
            <button type="button" class="type-btn" data-t="<?= $tk ?>"><?= htmlspecialchars($tn) ?></button>
            <?php endforeach; ?>
        </div>
        <label><?= $topic === 'otc' ? '약국 소재 지역 * <span style="font-weight:normal; color:#9ca3af;">— 이 지역 시세 기준으로 설문이 구성됩니다</span>' : '활동 지역 (선택)' ?></label>
        <select id="f-region"><option value=""><?= $topic === 'otc' ? '선택하세요' : '선택 안 함' ?></option>
            <?php foreach ($SIDOS as $s) : ?><option><?= $s ?></option><?php endforeach; ?>
        </select>
        <label>조사원 이름·코드 (선택 — 조사 관리용)</label>
        <input type="text" id="f-surveyor" maxlength="40" placeholder="예: 홍길동">
        <button type="button" class="btn" id="start-btn" disabled onclick="startSurvey()">설문 시작</button>
    </div>

    <!-- 케이스 진행 -->
    <div id="step-cases" style="display:none;">
        <div class="progress" id="progress"></div>
        <div class="card" id="case-card"></div>
    </div>

    <!-- 완료 -->
    <div class="card done-box" id="step-done" style="display:none;">
        <div class="big">🙏</div>
        <h1>응답이 저장되었습니다</h1>
        <p class="lead" id="done-msg">참여해 주셔서 감사합니다.</p>
        <button type="button" class="btn ghost" onclick="location.reload()">다음 응답자 설문 시작</button>
    </div>

    <p class="note"><?= htmlspecialchars($TC['note']) ?></p>
</div>

<script>
var CASES = <?= json_encode($CASES_OUT, JSON_UNESCAPED_UNICODE) ?>;
var UNIT = <?= json_encode($TC['unit'], JSON_UNESCAPED_UNICODE) ?>;
var USE_EOK = <?= $TC['eok'] ? 'true' : 'false' ?>;
var Q2 = <?= json_encode($TC['q2'], JSON_UNESCAPED_UNICODE) ?>;
var MODEL_LABEL = <?= json_encode($TC['model_label'], JSON_UNESCAPED_UNICODE) ?>;
var VERDICTS = <?= json_encode($TC['verdicts'], JSON_UNESCAPED_UNICODE) ?>; // 낮음→적정→높음 순서
var Q3 = <?= json_encode($TC['q3'], JSON_UNESCAPED_UNICODE) ?>;
var VERDICT_ADJ = <?= json_encode($TC['verdict_adj'], JSON_UNESCAPED_UNICODE) ?>; // 판정별 조정액 초기 배율
var ADJ_STEP_PCT = 0.05; // ± 클릭당 계산 결과의 5%
var SUBMIT_URL = <?= json_encode($self . '&api=submit') ?>;
var TOPIC = <?= json_encode($topic) ?>;
var CASES_URL = <?= json_encode($self . '&api=cases') ?>;
var ORDER = Object.keys(CASES);
// 응답자마다 케이스 순서 무작위
for (var i = ORDER.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = ORDER[i]; ORDER[i] = ORDER[j]; ORDER[j] = t; }

var RESP_TYPE = '', ANSWERS = [], IDX = 0, CUR_VERDICT = '';

function checkStart() {
    var ok = RESP_TYPE !== '' && (TOPIC !== 'otc' || document.getElementById('f-region').value !== '');
    document.getElementById('start-btn').disabled = !ok;
}
document.querySelectorAll('#type-grid .type-btn').forEach(function (b) {
    b.addEventListener('click', function () {
        document.querySelectorAll('#type-grid .type-btn').forEach(function (x) { x.classList.remove('on'); });
        b.classList.add('on');
        RESP_TYPE = b.getAttribute('data-t');
        checkStart();
    });
});
document.getElementById('f-region').addEventListener('change', checkStart);

// 조사원 이름은 기기에 기억 (연속 조사 편의)
try { document.getElementById('f-surveyor').value = localStorage.getItem('gs_surveyor') || ''; } catch (e) {}

function fmt(n) { return Number(n).toLocaleString('ko-KR'); }
function amtText(v) {
    if (USE_EOK && v >= 10000) { return (Math.round(v / 100) / 100) + '억원'; }
    return fmt(v) + UNIT;
}

function startSurvey() {
    try { localStorage.setItem('gs_surveyor', document.getElementById('f-surveyor').value.trim()); } catch (e) {}
    if (TOPIC === 'otc') {
        // 소재 지역의 시세 중앙값으로 케이스 교체 (지역 표본 30건 미만이면 전국 폴백 표시)
        var btn = document.getElementById('start-btn');
        btn.disabled = true; btn.textContent = '지역 시세 불러오는 중…';
        fetch(CASES_URL + '&sido=' + encodeURIComponent(document.getElementById('f-region').value))
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok && j.fairs) {
                    for (var ck in j.fairs) {
                        if (CASES[ck]) {
                            CASES[ck].fair = j.fairs[ck].fair;
                            CASES[ck].cond.forEach(function (row) {
                                if (row[0] === '기준 지역') { row[1] = j.fairs[ck].label; }
                            });
                        }
                    }
                }
                beginCases();
            })
            .catch(function () { beginCases(); }); // 실패 시 전국 기준으로 진행 (fail-safe)
        return;
    }
    beginCases();
}

function beginCases() {
    document.getElementById('step-intro').style.display = 'none';
    document.getElementById('step-cases').style.display = 'block';
    renderCase();
}

function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

function renderCase() {
    var pg = document.getElementById('progress');
    pg.innerHTML = ORDER.map(function (_, i) { return '<i class="' + (i < IDX ? 'done' : '') + '"></i>'; }).join('');
    var ck = ORDER[IDX];
    var c = CASES[ck];
    var box = document.getElementById('case-card');
    var condHtml = c.cond.map(function (row) {
        return '<tr><td>' + esc(row[0]) + '</td><td>' + esc(row[1]) + '</td></tr>';
    }).join('');
    var vKeys = Object.keys(VERDICTS);
    var vCls = ['v-lo', 'v-mid', 'v-hi'];
    var vHtml = vKeys.map(function (vk, i) {
        var sub = VERDICTS[vk];
        return '<button type="button" class="v-btn ' + vCls[i] + '" data-v="' + esc(vk) + '" onclick="pick(this)">'
            + esc(vk) + (sub ? '<br><small>' + esc(sub) + '</small>' : '') + '</button>';
    }).join('');
    box.innerHTML =
        '<h1 style="font-size:1.05em;">케이스 ' + (IDX + 1) + ' / ' + ORDER.length + '</h1>'
        + '<table class="cond-tbl">' + condHtml + '</table>'
        + '<div class="model-box" id="model-box" style="display:block;"><div class="mk">' + esc(MODEL_LABEL) + '</div>'
        + '<div class="mv">' + amtText(c.fair) + '</div>'
        + (USE_EOK && c.fair >= 10000 ? '<div class="mk">(' + fmt(c.fair) + '만원)</div>' : '')
        + '</div>'
        + '<div class="step-title">① ' + esc(Q2) + '</div>'
        + '<div class="verdict-grid" id="v-grid" style="display:grid;">' + vHtml + '</div>'
        + '<div class="adj-wrap" id="adj-wrap" style="display:block;">'
        + '<div class="step-title">② ' + esc(Q3) + ' <span style="font-weight:normal; color:#94a3b8;">— ±로 조정하거나 직접 입력하세요</span></div>'
        + '<div class="adj-row">'
        + '<button type="button" class="adj-btn" onclick="adjStep(-1)">−</button>'
        + '<input type="number" id="adj-amt" min="0" inputmode="numeric" value="' + Math.round(c.fair) + '">'
        + '<span class="unit">' + esc(UNIT) + '</span>'
        + '<button type="button" class="adj-btn" onclick="adjStep(1)">+</button>'
        + '</div>'
        + '<div class="adj-note" id="adj-note"></div>'
        + '<div class="adj-help">± 클릭당 계산 결과의 ' + Math.round(ADJ_STEP_PCT * 100) + '%씩 조정 · 의견 선택 시 자동 반영: ' + adjHelpText()
        + '<br>금액이 계산 결과 대비 ±10%를 벗어나면 의견 선택이 자동으로 바뀝니다</div>'
        + '<button type="button" class="btn" id="next-btn" disabled onclick="nextCase()">' + (IDX === ORDER.length - 1 ? '완료 — 응답 제출' : '다음 케이스') + '</button>'
        + '</div>';
    CUR_VERDICT = '';
    updateAdjNote();
    // 직접 입력 시 비교 문구만 갱신 — 입력값은 코드가 절대 고쳐 쓰지 않음
    document.getElementById('adj-amt').addEventListener('input', updateAdjNote);
}

function adjHelpText() {
    return Object.keys(VERDICTS).map(function (vk) {
        var m = Number(VERDICT_ADJ[vk] || 1);
        var pct = Math.round((m - 1) * 100);
        return esc(vk) + ' ' + (pct === 0 ? '그대로' : (pct > 0 ? '+' + pct + '%' : '−' + Math.abs(pct) + '%'));
    }).join(' · ');
}

// 금액 ↔ 판정 일관성: 조정액이 계산 결과 대비 ±10% 이내면 적정, 벗어나면 쌈/비쌈으로 선택이 자동 전환
var BAND_PCT = 0.10;

function verdictForAmount(v, model) {
    var keys = Object.keys(VERDICTS); // [쌈·낮음, 적정, 비쌈·높음]
    // 반올림 오차 없이 경계 포함 판정 — 금액 단위로 비교
    if (v > Math.round(model * (1 + BAND_PCT))) { return keys[0]; } // 내 금액이 계산보다 높음 = 계산값이 쌈(낮음)
    if (v < Math.round(model * (1 - BAND_PCT))) { return keys[2]; } // 내 금액이 계산보다 낮음 = 계산값이 비쌈(높음)
    return keys[1]; // ±10% 이내(경계 포함) = 적정
}

function syncVerdict(v, model) {
    CUR_VERDICT = verdictForAmount(v, model);
    document.querySelectorAll('#v-grid .v-btn').forEach(function (b) {
        b.classList.toggle('on', b.getAttribute('data-v') === CUR_VERDICT);
    });
    document.getElementById('next-btn').disabled = false;
}

function updateAdjNote() {
    var model = CASES[ORDER[IDX]].fair;
    var note = document.getElementById('adj-note');
    var v = Math.round(Number(document.getElementById('adj-amt').value));
    if (!(v > 0)) {
        note.textContent = '';
        CUR_VERDICT = '';
        document.querySelectorAll('#v-grid .v-btn').forEach(function (b) { b.classList.remove('on'); });
        document.getElementById('next-btn').disabled = true;
        return;
    }
    if (v === Math.round(model)) {
        note.className = 'adj-note same';
        note.textContent = '계산결과와 동일';
    } else {
        var pct = (v / model - 1) * 100;
        note.className = 'adj-note diff';
        note.textContent = '계산결과 대비 ' + (pct > 0 ? '+' : '−') + Math.abs(pct).toFixed(1) + '% (' + amtText(v) + ')';
    }
    // 입력·±로 금액이 바뀌었으면 판정도 금액 기준으로 맞춤 (모순 방지) — 최초 렌더(선택 전)에는 건드리지 않음
    if (CUR_VERDICT !== '' || v !== Math.round(model)) {
        syncVerdict(v, model);
    }
}

function adjStep(dir) {
    var model = CASES[ORDER[IDX]].fair;
    var input = document.getElementById('adj-amt');
    var step = Math.max(1, Math.round(model * ADJ_STEP_PCT));
    var v = Math.round(Number(input.value)) || Math.round(model);
    input.value = Math.max(0, v + dir * step);
    updateAdjNote();
}

function pick(btn) {
    // 의견 선택 시 조정액 자동 반영 (계산 결과 × 배율) — 이후 ±·직접 입력으로 미세 조정
    var model = CASES[ORDER[IDX]].fair;
    var mult = Number(VERDICT_ADJ[btn.getAttribute('data-v')] || 1);
    document.getElementById('adj-amt').value = Math.round(model * mult);
    CUR_VERDICT = btn.getAttribute('data-v');
    updateAdjNote(); // 배율 결과 기준으로 판정·하이라이트 동기화 (적정 클릭 = 계산값 그대로 → 적정 유지)
}

function nextCase() {
    if (CUR_VERDICT === '') { return; }
    var ck = ORDER[IDX];
    var amt = Math.round(Number(document.getElementById('adj-amt').value));
    ANSWERS.push({ 'case': ck, est: amt, verdict: CUR_VERDICT, adj: amt });
    IDX++;
    if (IDX < ORDER.length) { renderCase(); window.scrollTo(0, 0); }
    else { submitAll(); }
}

function submitAll() {
    var box = document.getElementById('case-card');
    box.innerHTML = '<p style="text-align:center; padding:30px 0; color:#64748b;">저장 중…</p>';
    fetch(SUBMIT_URL, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            resp_type: RESP_TYPE,
            region: document.getElementById('f-region').value,
            surveyor: document.getElementById('f-surveyor').value.trim(),
            answers: ANSWERS
        })
    }).then(function (r) { return r.json(); }).then(function (j) {
        document.getElementById('step-cases').style.display = 'none';
        var done = document.getElementById('step-done');
        done.style.display = 'block';
        if (!(j && j.ok)) {
            done.querySelector('h1').textContent = '저장에 실패했습니다';
            document.getElementById('done-msg').textContent = '네트워크 상태를 확인하고 다시 시도해 주세요. 응답 내용은 이 화면을 새로고침하기 전까지 유지됩니다.';
        }
    }).catch(function () {
        document.getElementById('step-cases').style.display = 'none';
        var done = document.getElementById('step-done');
        done.style.display = 'block';
        done.querySelector('h1').textContent = '저장에 실패했습니다';
        document.getElementById('done-msg').textContent = '네트워크 상태를 확인하고 다시 시도해 주세요.';
    });
}
</script>
</body>
</html>
