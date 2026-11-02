<?php
// 권리금 시세 산정 — 거래 데이터 기반 회수배수 모델 (클라이언트 계산 + 피드백 자동 보정)
// 모델: 월 환산수익 × (지역별 회수개월수 × 조제료 규모 계수), 개준모 362건 가중회귀 참고
// 보정: goodwill_feedback 의 제시값/계산값 비율 평균으로 지역별 가격을 자동 보정 (표본 30건+, ±10% 한도)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
authGuard(); // 입장 조건: 페이지별 최소 등급 (auth.php authPageLevels)

// 참고 데이터·가중치 — 관리자(참고 데이터 관리 / 가중치 관리)에서 설정 가능, 미설정 시 아래 기본값
// 계수 3종은 config.php 공용 로더 사용 (premium.php 기초 산출과 단일 소스)
$gw_months = gwRegionMonths();
$gw_presc_mult = gwPrescMult();
$gw = gwWeights();
$calib_min_n = max(1, (int) $gw['calib_min_n']);
$calib_limit = max(0, (float) $gw['calib_limit']);

// 지역 보정: 실사용 피드백 + 실매물 호가 합산 (config.php 공용 — 표본 가드·한도 동일)
$calib = goodwillRegionCalib($calib_min_n, $calib_limit);

// 적정성 설문 보정 (관리자 > 설문 집계에서 원클릭 반영, ±15% 한도) — 피드백 보정과 별개로 곱해짐
$survey_cal = surveyCalibration('goodwill');

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>권리금 계산 v1 (계단형)</title>
    <style>
        body { font-family: 'Malgun Gothic', sans-serif; padding: 20px; max-width: 860px; margin: 0 auto; background-color: #f8fafc; }
        .search-box { margin-bottom: 20px; padding: 25px; border: 1px solid #ddd; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .nav-links.side-nav { position: fixed; left: 0; top: 0; bottom: 0; width: 200px; background: #1e293b; padding: 16px 12px; overflow-y: auto; z-index: 100; margin: 0; }
        .side-nav .sn-home { display: block; color: #fff; font-weight: bold; text-decoration: none; font-size: 0.9em; padding: 9px 10px; border-radius: 8px; background: rgba(255,255,255,0.1); margin-bottom: 6px; }
        .side-nav .sn-home:hover { background: rgba(255,255,255,0.2); }
        .side-nav .sn-cat { font-size: 0.7em; color: #94a3b8; font-weight: bold; margin: 14px 0 4px; padding: 0 10px; }
        .side-nav a { display: block; color: #cbd5e1; font-size: 0.84em; padding: 7px 10px; border-radius: 7px; text-decoration: none; margin-bottom: 1px; }
        .side-nav a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .side-nav .sn-on { display: block; font-size: 0.84em; padding: 7px 10px; border-radius: 7px; margin-bottom: 1px; background: #0f766e; color: #fff; font-weight: bold; }
        @media (min-width: 1120px) { body { margin-left: max(236px, calc(50% - 320px)); margin-right: auto; } }
        @media (max-width: 1119px) {
            .nav-links.side-nav { position: static; width: auto; border-radius: 12px; margin-bottom: 18px; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0 14px; align-items: start; }
            .side-nav .sn-home { grid-column: 1 / -1; }
        }

        h2.page-title { margin-top: 0; color: #0f766e; }
        .lead { color: #5f6368; font-size: 0.95em; margin-bottom: 0; line-height: 1.6; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; }
        .form-grid .full { grid-column: 1 / -1; }
        @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
        label { display: block; font-weight: bold; font-size: 0.85em; color: #374151; margin-bottom: 5px; }
        label .hint { font-weight: normal; color: #9ca3af; font-size: 0.9em; }
        input[type=text], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 1em; background: #fff; }
        input[type=text]:focus, select:focus { outline: none; border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.12); }
        .suffix-wrap { position: relative; }
        .suffix-wrap .suffix { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85em; pointer-events: none; }
        .suffix-wrap input { padding-right: 46px; text-align: right; }

        .btn-calc { width: 100%; margin-top: 18px; padding: 13px; background: #0f766e; color: #fff; border: none; border-radius: 8px; font-size: 1.05em; font-weight: bold; cursor: pointer; }
        .btn-calc:hover { background: #115e59; }

        /* ===== 결과: 명세서형 리스트 스타일 (박스 카드 없음) ===== */
        .result-sheet { background: #fff; border: 1px solid #e2e8f0; border-top: 3px solid #0f172a; padding: 26px 30px 22px; margin-bottom: 18px; }
        .sheet-head { display: flex; justify-content: space-between; align-items: baseline; padding-bottom: 12px; border-bottom: 1px solid #0f172a; margin-bottom: 4px; }
        .sheet-head .sh-title { font-size: 1.15em; font-weight: bold; color: #0f172a; letter-spacing: 0.02em; }
        .sheet-head .sh-sub { font-size: 0.76em; color: #94a3b8; letter-spacing: 0.08em; }
        .sec-label { font-size: 0.74em; font-weight: bold; color: #0f766e; letter-spacing: 0.14em; margin: 26px 0 4px; }

        /* 최소~최대 범위 헤드라인 */
        .range-head { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; flex-wrap: wrap;
                      margin: 18px 0 4px; padding: 16px 18px; background: #f0fdfa; border: 1px solid #99f6e4; }
        .range-head .rh-label { font-size: 0.82em; font-weight: bold; color: #0f766e; }
        .range-head .rh-value { font-size: 1.45em; font-weight: bold; color: #0f766e; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .range-head .rs { color: #5eead4; font-weight: normal; margin: 0 4px; }
        @media (max-width: 480px) { .range-head .rh-value { font-size: 1.2em; } }

        .ln { display: flex; align-items: baseline; gap: 12px; padding: 13px 2px; border-bottom: 1px solid #eef2f7; }
        .ln .l-dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 auto; align-self: center; }
        .ln .l-name { font-weight: bold; color: #1e293b; font-size: 0.95em; flex: 0 0 auto; }
        .ln .l-tag { font-size: 0.68em; font-weight: bold; color: #0f766e; border: 1px solid #99f6e4; background: #f0fdfa; border-radius: 3px; padding: 1px 6px; flex: 0 0 auto; }
        .ln .l-meta { font-size: 0.78em; color: #94a3b8; flex: 1; min-width: 0; }
        .ln .l-value { font-size: 1.35em; font-weight: bold; color: #0f172a; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .ln.quick .l-dot { background: #059669; }
        .ln.fair .l-dot { background: #d97706; }
        .ln.high .l-dot { background: #e11d48; }

        @media (max-width: 640px) {
            .ln { flex-wrap: wrap; }
            .ln .l-meta { flex-basis: 100%; order: 5; padding-left: 21px; margin-top: 2px; }
        }

        .ln.nego { border-bottom: 1px solid #eef2f7; padding: 15px 2px; }
        .ln.nego .l-name { color: #0f766e; }
        .ln.nego .l-value { color: #0f766e; font-size: 1.5em; }
        .nego-note { font-size: 0.8em; color: #64748b; margin: 12px 0 0; line-height: 1.65; }

        /* 범위 내 위치 기준 — 최소가 / 최대가로 가는 조건 */
        .pos-lead { font-size: 0.85em; color: #64748b; line-height: 1.65; margin: 6px 0 16px; }
        .pos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 640px) { .pos-grid { grid-template-columns: 1fr; } }
        .pos-col { border: 1px solid #e2e8f0; border-top: 3px solid; padding: 14px 16px 12px; background: #fff; }
        .pos-min { border-top-color: #059669; }
        .pos-max { border-top-color: #e11d48; }
        .pos-head { display: flex; align-items: center; gap: 7px; font-size: 0.87em; font-weight: bold; color: #0f172a; margin-bottom: 9px; }
        .pos-dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 auto; }
        .pos-min .pos-dot { background: #059669; }
        .pos-max .pos-dot { background: #e11d48; }
        .pos-list { margin: 0; }
        .pos-item { display: flex; align-items: flex-start; gap: 8px; font-size: 0.83em; color: #334155;
                    line-height: 1.55; padding: 5px 0; cursor: pointer; }
        .pos-item input { margin: 2px 0 0; flex: 0 0 auto; cursor: pointer; }
        .pos-min .pos-item input { accent-color: #059669; }
        .pos-max .pos-item input { accent-color: #e11d48; }
        .pos-item input:checked + span { color: #0f172a; font-weight: bold; }
        /* 반대 조건 선택으로 자동 해제된 항목 — 잠깐 표시해 이유를 알 수 있게 */
        .pos-item.pos-drop { animation: posDrop 0.9s ease-out; border-radius: 4px; }
        @keyframes posDrop {
            0%, 45% { background: #fef3c7; }
            100% { background: transparent; }
        }
        @media (prefers-reduced-motion: reduce) { .pos-item.pos-drop { animation: none; } }
        .pos-foot { font-size: 0.78em; color: #94a3b8; margin: 10px 0 0; padding-top: 9px; border-top: 1px solid #f1f5f9; line-height: 1.55; }

        /* 체크 결과 — 범위 위 예상 구간 표시 */
        .pos-scale { margin-top: 18px; padding: 16px 18px 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .ps-top { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 11px; }
        .ps-label { font-size: 0.82em; font-weight: bold; color: #475569; }
        .ps-value { font-size: 1.15em; font-weight: bold; color: #0f172a; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ps-value .rs { color: #94a3b8; font-weight: normal; margin: 0 3px; }
        .ps-track { position: relative; height: 16px; border-radius: 3px;
                    background: linear-gradient(90deg, #a7f3d0 0%, #fde68a 50%, #fecdd3 100%); }
        .ps-band { position: absolute; top: -3px; bottom: -3px; border-radius: 3px; background: #0f766e;
                   opacity: 0.85; transition: left 0.2s ease, width 0.2s ease; min-width: 3px; }
        .ps-ends { display: flex; justify-content: space-between; font-size: 0.74em; color: #94a3b8; margin-top: 7px; font-variant-numeric: tabular-nums; }
        .ps-note { font-size: 0.78em; color: #94a3b8; margin: 9px 0 0; line-height: 1.55; }
        .ps-note a { color: #0f766e; }

        .warn-box { padding: 11px 14px; border-left: 3px solid; margin: 14px 0 0; font-size: 0.88em; line-height: 1.65; background: #f8fafc; color: #334155; }
        .warn-critical { border-color: #be123c; }
        .warn-caution { border-color: #d97706; }
        .warn-doctor { border-color: #ea580c; }
        .warn-box strong { display: block; margin-bottom: 3px; color: #0f172a; }

        .detail-table { width: 100%; border-collapse: collapse; font-size: 0.87em; }
        .detail-table td { padding: 9px 2px; border-bottom: 1px solid #eef2f7; color: #64748b; }
        .detail-table td:last-child { text-align: right; font-weight: bold; color: #1e293b; font-variant-numeric: tabular-nums; }
        .detail-table tr.sum td { border-top: 1px solid #0f172a; border-bottom: none; color: #0f766e; font-weight: bold; }

        .foot-note { font-size: 0.8em; color: #9ca3af; margin: 20px 0 30px; line-height: 1.7; }
        #result-wrap { display: none; }

        /* 정밀 계산기 안내 CTA */
        .gw-cta { margin-top: 26px; padding: 16px 18px; background: #f0fdfa; border: 1px solid #99f6e4;
                  display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
        .gw-cta .cta-text { font-size: 0.85em; color: #134e4a; line-height: 1.6; flex: 1; min-width: 200px; }
        .gw-cta .cta-btn { display: inline-block; padding: 11px 20px; background: #0f766e; color: #fff;
                           font-size: 0.9em; font-weight: bold; text-decoration: none; white-space: nowrap; }
        .gw-cta .cta-btn:hover { background: #115e59; }

        /* 피드백 — 명세서 스타일과 동일 톤 */
        .fb-lead { font-size: 0.85em; color: #64748b; margin: 6px 0 16px; }
        .fb-row { margin-bottom: 16px; }
        .fb-label { font-weight: bold; font-size: 0.85em; color: #1e293b; margin-bottom: 7px; }
        .fb-label .hint { font-weight: normal; color: #94a3b8; }
        .fb-pills { display: flex; gap: 0; flex-wrap: wrap; border: 1px solid #cbd5e1; width: fit-content; }
        .fb-pill { padding: 9px 20px; border: none; border-right: 1px solid #cbd5e1; background: #fff; color: #475569;
                   font-size: 0.9em; cursor: pointer; font-weight: bold; }
        .fb-pill:last-child { border-right: none; }
        .fb-pill:hover { background: #f0fdfa; color: #0f766e; }
        .fb-pill.on { background: #0f766e; color: #fff; }
        .fb-value-row { display: flex; align-items: center; gap: 0; border: 1px solid #cbd5e1; width: fit-content; }
        .fb-step { width: 46px; height: 46px; border: none; background: #f8fafc; font-size: 1.25em; font-weight: bold;
                   color: #475569; cursor: pointer; flex: 0 0 auto; }
        .fb-step:first-child { border-right: 1px solid #cbd5e1; }
        .fb-step:nth-of-type(2) { border-left: 1px solid #cbd5e1; }
        .fb-step:hover { background: #f0fdfa; color: #0f766e; }
        .fb-value-wrap { position: relative; width: 200px; }
        .fb-value-wrap input { width: 100%; padding: 12px 46px 12px 10px; border: none; box-sizing: border-box;
                               font-size: 1.15em; font-weight: bold; text-align: right; background: #fff; color: #0f172a;
                               font-variant-numeric: tabular-nums; }
        .fb-value-wrap .suffix { position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
                                 color: #94a3b8; font-size: 0.8em; pointer-events: none; }
        .fb-delta { font-size: 0.82em; color: #0f766e; font-weight: bold; min-width: 70px; margin-left: 12px; }
        .fb-note { font-size: 0.78em; color: #94a3b8; margin-top: 6px; }
        .btn-fb { padding: 11px 28px; background: #0f172a; color: #fff; border: none; font-size: 0.95em;
                  font-weight: bold; cursor: pointer; }
        .btn-fb:hover { background: #1e293b; }
        .btn-fb:disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; }
        #fb-status { margin-left: 10px; font-size: 0.85em; }
        .fb-done { text-align: center; padding: 18px 0; color: #0f766e; font-weight: bold; }
    </style>
    <link rel="stylesheet" href="theme.css?v=2">
</head>
<body>

<div class="search-box">
    <?php require_once __DIR__ . '/nav.php'; renderSideNav(); ?>
    <h2 class="page-title">💰 권리금 계산<span style="display:inline-block; vertical-align:middle; margin-left:8px; padding:2px 9px; border-radius:999px; font-size:0.5em; font-weight:bold; letter-spacing:0.02em; background:#fef3c7; color:#92400e; border:1px solid #fde68a;">v1 · 계단형</span></h2>
    <p class="lead">
        실거래 회수배수 통계 기반으로 <strong>최소(빠른 매도) ~ 최대(높은 호가)</strong> 권리금 범위를 즉시 산출합니다.
        5개 변수만 입력하면 되고, AI 분석이 없어 결과가 바로 나옵니다.
        정밀 수익성 분석과 개국 자금 계획이 필요하면 <a href="premium.php" style="color:#b45309;">권리금 계산 v3</a>를 이용하세요.
    </p><div style="margin:12px 0 0; padding:10px 14px; background:#fffbeb; border-left:3px solid #f59e0b; font-size:0.84em; color:#334155; line-height:1.7;"><b>v1 — 조제료 규모 계수를 계단형으로 적용</b><br>월 조제료를 5개 구간(1,000만 미만 / 1,000~2,500 / 2,500~3,500 / 3,500~5,000 / 5,000 이상)으로 나눠 구간별 고정 계수를 곱합니다. 기준이 단순해 설명하기 쉬운 대신, <b>구간 경계에서 금액이 한 번에 뛰는 단점</b>이 있습니다. 경계를 없앤 버전은 <a href="goodwill_curve.php" style="color:#0d9488; font-weight:bold;">권리금 계산 v2</a>에서 비교해 보세요.</div>
</div>

<div class="search-box">
    <div class="form-grid">
        <div>
            <label for="region">지역</label>
            <select id="region">
                <option value="seoul_core">강남 등 서울 핵심 상권</option>
                <option value="seoul">서울 일반</option>
                <option value="metro" selected>수도권 (경기·인천)</option>
                <option value="bigcity">광역시 (부산·대구 등)</option>
                <option value="local">지방 중소도시</option>
            </select>
        </div>
        <div>
            <label for="doctor_age">메인 병원 의사 고령 여부 <span class="hint">처방 최다 병원 기준</span></label>
            <select id="doctor_age">
                <option value="none">해당 없음 (60대 중반 미만)</option>
                <option value="over65">65세 이상 의사 있음</option>
                <option value="over75">75세 이상 의사 있음</option>
            </select>
        </div>
        <div>
            <label for="presc">월 조제료 <span class="hint">월 평균</span></label>
            <div class="suffix-wrap"><input type="text" id="presc" inputmode="numeric" value="1,400"><span class="suffix">만원</span></div>
        </div>
        <div>
            <label for="otc_daily">일반약 일매출 <span class="hint">하루 기준 (월 아님)</span></label>
            <div class="suffix-wrap"><input type="text" id="otc_daily" inputmode="numeric" value="50"><span class="suffix">만원</span></div>
        </div>
        <div>
            <label for="rent">월세</label>
            <div class="suffix-wrap"><input type="text" id="rent" inputmode="numeric" value="300"><span class="suffix">만원</span></div>
        </div>
        <div>
            <label for="deposit">보증금</label>
            <div class="suffix-wrap"><input type="text" id="deposit" inputmode="numeric" value="5,000"><span class="suffix">만원</span></div>
        </div>
    </div>
    <button type="button" class="btn-calc" onclick="calc()">권리금 산정</button>
</div>

<div id="result-wrap">
    <div class="result-sheet">
        <div class="sheet-head">
            <span class="sh-title">권리금 산정 명세</span>
            <span class="sh-sub">GOODWILL ESTIMATE</span>
        </div>
        <div id="warn-area"></div>

        <div class="range-head">
            <span class="rh-label">권리금 범위 (최소 ~ 최대)</span>
            <span class="rh-value" id="p-range">-</span>
        </div>

        <div id="pos-area">
        <div class="sec-label">내 약국은 범위의 어디쯤인가</div>
        <p class="pos-lead">같은 수익이라도 아래 조건에 따라 실제 거래가는 범위의 최소 쪽과 최대 쪽으로 갈립니다.
            해당하는 항목이 많은 쪽으로 기울여 보고, 어느 쪽도 뚜렷하지 않으면 범위 가운데를 기준으로 삼으세요.<br>
            임대차·처방 추이·시설 상태처럼 <strong>서로 반대되는 조건</strong>은 한쪽을 선택하면 반대쪽이 자동으로 해제됩니다.</p>

        <div class="pos-grid">
            <div class="pos-col pos-min">
                <div class="pos-head"><span class="pos-dot"></span>최소가 쪽으로 가는 조건</div>
                <div class="pos-list">
                    <label class="pos-item" title="임대차 조건 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="min" data-pair="lease" onchange="posToggle(this)"><span>잔여 임대차가 1년 미만이거나 재계약이 불확실하다</span></label>
                    <label class="pos-item"><input type="checkbox" data-side="min" onchange="posToggle(this)"><span>메인 병원 의사가 고령이거나 이전·폐업 가능성이 있다</span></label>
                    <label class="pos-item" title="처방 구조 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="min" data-pair="hospital" onchange="posToggle(this)"><span>처방이 한 병원에 몰려 있다 (단일 문전 의존)</span></label>
                    <label class="pos-item" title="처방 추이 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="min" data-pair="trend" onchange="posToggle(this)"><span>최근 1년간 처방전 수가 줄고 있다</span></label>
                    <label class="pos-item" title="경쟁 환경 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="min" data-pair="compete" onchange="posToggle(this)"><span>인근에 약국이 새로 들어설 예정이거나 이미 늘었다</span></label>
                    <label class="pos-item" title="매도 시급성 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="min" data-pair="urgency" onchange="posToggle(this)"><span>매도자가 급하다 (건강·이주·자금 사정, 이미 폐문)</span></label>
                    <label class="pos-item" title="시설 상태 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="min" data-pair="facility" onchange="posToggle(this)"><span>시설이 노후해 인수 후 추가 투자가 필요하다</span></label>
                    <label class="pos-item" title="임대료 부담 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="min" data-pair="rent" onchange="posToggle(this)"><span>월세 부담이 커서 수익이 임대료에 눌린다</span></label>
                </div>
                <p class="pos-foot">1~2개월 안에 정리해야 하는 매물은 최소가 근처에서 거래됩니다.</p>
            </div>
            <div class="pos-col pos-max">
                <div class="pos-head"><span class="pos-dot"></span>최대가 쪽으로 가는 조건</div>
                <div class="pos-list">
                    <label class="pos-item" title="임대차 조건 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="max" data-pair="lease" onchange="posToggle(this)"><span>잔여 임대차가 3년 이상이고 승계가 확정적이다</span></label>
                    <label class="pos-item" title="처방 구조 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="max" data-pair="hospital" onchange="posToggle(this)"><span>복수 병원에서 처방이 분산돼 리스크가 낮다</span></label>
                    <label class="pos-item" title="처방 추이 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="max" data-pair="trend" onchange="posToggle(this)"><span>처방전 수가 늘고 있거나 인근에 병원이 들어올 예정이다</span></label>
                    <label class="pos-item" title="경쟁 환경 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="max" data-pair="compete" onchange="posToggle(this)"><span>대체 입지가 없는 문전·독점 자리다</span></label>
                    <label class="pos-item" title="시설 상태 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="max" data-pair="facility" onchange="posToggle(this)"><span>최근 리모델링해 바로 영업할 수 있다</span></label>
                    <label class="pos-item"><input type="checkbox" data-side="max" onchange="posToggle(this)"><span>직원이 승계돼 인수 직후에도 운영이 끊기지 않는다</span></label>
                    <label class="pos-item" title="매도 시급성 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="max" data-pair="urgency" onchange="posToggle(this)"><span>매도자가 급하지 않아 시간을 두고 기다릴 수 있다</span></label>
                    <label class="pos-item" title="임대료 부담 — 반대쪽 항목과 동시 선택 불가"><input type="checkbox" data-side="max" data-pair="rent" onchange="posToggle(this)"><span>월세가 수익 대비 낮아 부담이 적다</span></label>
                </div>
                <p class="pos-foot">6개월 이상 기다릴 수 있는 매물은 최대가까지 호가를 유지합니다.</p>
            </div>
        </div>

        <div class="pos-scale">
            <div class="ps-top">
                <span class="ps-label">체크한 조건 기준 예상 구간</span>
                <span class="ps-value" id="ps-value">-</span>
            </div>
            <div class="ps-track"><div class="ps-band" id="ps-band"></div></div>
            <div class="ps-ends"><span id="ps-min">-</span><span id="ps-max">-</span></div>
            <p class="ps-note" id="ps-note">해당하는 조건을 체크하면 예상 구간이 좁혀집니다.
                <a href="#" onclick="posReset(); return false;">전체 해제</a></p>
        </div>
        </div>

        <div id="nego-box">
            <p class="nego-note"><strong>협상에서 쓰는 법</strong> — 매도 측은 보통 최대가에 가깝게 시작해 협상 과정에서 가운데 아래로 수렴합니다.
                매수 측이라면 위 <strong>최소가 조건</strong> 중 해당하는 항목을 근거로 제시할 때 하향 협상의 명분이 생기고,
                매도 측이라면 <strong>최대가 조건</strong>을 자료(임대차 계약서·처방 통계·리모델링 내역)로 입증할수록 호가를 지킬 수 있습니다.</p>
        </div>

        <div class="sec-label">계산 근거</div>
        <table class="detail-table">
            <tr><td>월 조제료</td><td id="d-presc">-</td></tr>
            <tr><td>일반약 월수익 (일매출 × 마진 × 근무일)</td><td id="d-otc">-</td></tr>
            <tr><td>실질 월세 <span id="d-dep-note" style="font-weight:normal;color:#94a3b8;"></span></td><td id="d-rent">-</td></tr>
            <tr class="sum"><td>월 환산수익</td><td id="d-profit">-</td></tr>
        </table>
        <table class="detail-table" style="margin-top:18px;">
            <tr><td>지역 기준 회수개월수 (빠른/적정/높은)</td><td id="d-base-months">-</td></tr>
            <tr><td>조제료 규모 계수</td><td id="d-presc-mult">-</td></tr>
            <tr><td>의사 고령 할인</td><td id="d-doctor">-</td></tr>
            <tr><td>실사용 피드백 보정 <span style="font-weight:normal;color:#94a3b8;">(제시값 표본 <?= $calib_min_n ?>건부터, ±<?= round($calib_limit * 100) ?>% 한도)</span></td><td id="d-calib">-</td></tr>
            <?php if ($survey_cal !== null) : ?>
            <tr><td>적정성 설문 보정 <span style="font-weight:normal;color:#94a3b8;">(응답자 <?= $survey_cal['resp'] ?>명 기준)</span></td><td id="d-scal">-</td></tr>
            <?php endif; ?>
            <tr class="sum"><td>적용 회수개월수</td><td id="d-adj-months">-</td></tr>
        </table>

        <div class="gw-cta">
            <div class="cta-text">이 결과는 회수배수 기반 <strong>약식 시세</strong>입니다.<br>
                수익성 분석·개국 자금 계획·매물 조건 실측 검증까지 필요하다면 정밀 계산기를 이용해보세요.</div>
            <a class="cta-btn" href="premium.php">📊 권리금 계산기에서 자세히 계산하기 →</a>
        </div>
    </div>


    <div class="result-sheet" id="fb-card" style="border-top-color:#0f766e;">
        <div class="sheet-head" style="border-bottom-color:#0f766e;">
            <span class="sh-title">이 호가, 어떻게 보세요?</span>
            <span class="sh-sub">FEEDBACK</span>
        </div>
        <div id="fb-form">
            <p class="fb-lead">의견을 모아 지역별 회수배수를 자동 보정합니다. 익명으로 수집되며, 하루 1건(당일 재제출 시 갱신)입니다.</p>
            <div class="fb-row">
                <div class="fb-label">응답자</div>
                <div class="fb-pills">
                    <button type="button" class="fb-pill" data-role="매도" onclick="fbSetRole(this)">매도 약사</button>
                    <button type="button" class="fb-pill" data-role="매수" onclick="fbSetRole(this)">매수 약사</button>
                </div>
            </div>
            <div class="fb-row">
                <div class="fb-label">적정 호가에 대한 의견</div>
                <div class="fb-pills">
                    <button type="button" class="fb-pill" data-verdict="-1" onclick="fbSetVerdict(this)">싸다</button>
                    <button type="button" class="fb-pill on" data-verdict="0" onclick="fbSetVerdict(this)">적정하다</button>
                    <button type="button" class="fb-pill" data-verdict="1" onclick="fbSetVerdict(this)">비싸다</button>
                </div>
            </div>
            <div class="fb-row">
                <div class="fb-label">내가 생각하는 적정 권리금 <span class="hint">— ± 클릭당 계산결과의 2%씩 조정</span></div>
                <div style="display:flex; align-items:center;">
                    <div class="fb-value-row">
                        <button type="button" class="fb-step" onclick="fbStep(-1)">−</button>
                        <div class="fb-value-wrap">
                            <input type="text" id="fb-value" readonly>
                            <span class="suffix">만원</span>
                        </div>
                        <button type="button" class="fb-step" onclick="fbStep(1)">＋</button>
                    </div>
                    <span class="fb-delta" id="fb-delta"></span>
                </div>
                <div class="fb-note">의견 선택 시 자동 반영: 비싸다 −30% · 적정 그대로 · 싸다 +20%</div>
            </div>
            <button type="button" class="btn-fb" id="fb-submit" onclick="fbSubmit()" disabled>의견 보내기</button>
            <span id="fb-status"></span>
        </div>
        <div class="fb-done" id="fb-done" style="display:none;">🙏 의견 감사합니다 — 지역 회수배수 보정 데이터로 활용됩니다.</div>
    </div>
</div>

<p class="foot-note">
    ※ 모델: 월 환산수익 × 지역별 회수개월수(실거래 362건 가중회귀 분포의 30/50/75 백분위) × 조제료 규모 계수.
    조제료를 수익 지표로 그대로 사용하는 권리금 산정용 약식 모델이며, 실제 순수익과 다릅니다.<br>
    ※ 추정값이며 실제 거래는 1인약국 여부·병원 안정성·건물 형태·임대차 조건 등에 따라 ±30% 변동할 수 있습니다.
    입력값은 이 브라우저에만 저장됩니다.
</p>

<script>
var FORM_KEY = 'goodwill_form_v1';

// 지역별 실사용 피드백 보정계수 (서버에서 goodwill_feedback 집계 주입: 최소 표본·한도는 관리자 설정)
var CALIB = <?= json_encode($calib) ?>;
// 적정성 설문 보정 (관리자 반영 시에만 값 존재, ±15% 한도)
var SURVEY_CAL = <?= json_encode($survey_cal) ?>;

// 지역별 회수개월수 (실거래 가중회귀 분포: p30 / p50 / p75) — 관리자(참고 데이터)에서 설정
var REGION_MONTHS = <?= json_encode($gw_months) ?>;

// 계산 가중치 — 관리자(가중치 관리)에서 설정
var GW = <?= json_encode($gw) ?>;

// 조제료 규모 계수 (조제료가 클수록 회수기간을 길게 인정 → 권리금 상향) — 관리자(참고 데이터)에서 설정
var PRESC_MULT = <?= json_encode($gw_presc_mult) ?>;
function prescMultiplier(p) {
    if (p < 1000) return Number(PRESC_MULT.under1000);
    if (p < 2500) return Number(PRESC_MULT.b1000);
    if (p < 3500) return Number(PRESC_MULT.b2500);
    if (p < 5000) return Number(PRESC_MULT.b3500);
    return Number(PRESC_MULT.over5000);
}

function num(id) {
    var v = document.getElementById(id).value.replace(/[^0-9.]/g, '');
    return parseFloat(v) || 0;
}

function fmtEok(manwon) {
    if (manwon >= 10000) {
        var eok = manwon / 10000;
        return (eok >= 10 ? eok.toFixed(1) : eok.toFixed(2)) + '억원';
    }
    return Math.round(manwon).toLocaleString('ko-KR') + '만원';
}

function fmtMan(manwon) {
    return Math.round(manwon).toLocaleString('ko-KR') + '만원';
}

function calc() {
    var region = document.getElementById('region').value;
    var doctorAge = document.getElementById('doctor_age').value;
    var presc = num('presc');
    var otcDaily = num('otc_daily');
    var rent = num('rent');
    var deposit = num('deposit');

    // 1. 월 환산수익 (계수: 관리자 가중치 설정)
    var depositAdj = deposit > Number(GW.deposit_threshold) ? deposit * Number(GW.deposit_rate) / 12 : 0; // 보증금 기준 초과 시 연 환산 기회비용을 월세에 가산
    var adjustedRent = rent + depositAdj;
    var otcMonthly = otcDaily * Number(GW.otc_margin) * Number(GW.work_days);
    var profit = presc + otcMonthly - adjustedRent;

    // 2. 회수개월수 (지역 × 조제료 계수)
    var rm = REGION_MONTHS[region];
    var pm = prescMultiplier(presc);
    var adjP30 = rm.p30 * pm, adjP50 = rm.p50 * pm, adjP75 = rm.p75 * pm;

    // 3. 3단계 가격 + 임계점 처리 (임계값·시설양도비: 관리자 가중치 설정)
    var critP = Number(GW.critical_profit), cautP = Number(GW.caution_profit);
    var quick, fair, high, warning = null, ratio = 1;
    if (profit < critP) {
        quick = Number(GW.crit_quick); fair = Number(GW.crit_fair); high = Number(GW.crit_high);
        warning = 'critical';
    } else if (profit < cautP) {
        ratio = 0.5 + (profit - critP) / (cautP - critP) * 0.5;
        quick = Math.round(profit * adjP30 * ratio);
        fair  = Math.round(profit * adjP50 * ratio);
        high  = Math.round(profit * adjP75 * ratio);
        warning = 'caution';
    } else {
        quick = Math.round(profit * adjP30);
        fair  = Math.round(profit * adjP50);
        high  = Math.round(profit * adjP75);
    }

    // 4. 의사 고령 할인 (시설양도비 수준은 이미 바닥이라 미적용)
    var doctorMult = doctorAge === 'over75' ? Number(GW.doctor75) : (doctorAge === 'over65' ? Number(GW.doctor65) : 1.0);
    if (warning !== 'critical' && doctorMult < 1.0) {
        quick = Math.round(quick * doctorMult);
        fair  = Math.round(fair * doctorMult);
        high  = Math.round(high * doctorMult);
    }

    // 5. 실사용 피드백 보정 (표본 30건+ 지역만, 시설양도비 구간 제외)
    var cal = CALIB[region] || { factor: 1.0, n: 0 };
    if (warning !== 'critical' && cal.factor !== 1.0) {
        quick = Math.round(quick * cal.factor);
        fair  = Math.round(fair * cal.factor);
        high  = Math.round(high * cal.factor);
    }

    // 5-1. 적정성 설문 보정 (관리자 반영 시, 시설양도비 구간 제외)
    if (warning !== 'critical' && SURVEY_CAL && SURVEY_CAL.factor) {
        quick = Math.round(quick * SURVEY_CAL.factor);
        fair  = Math.round(fair * SURVEY_CAL.factor);
        high  = Math.round(high * SURVEY_CAL.factor);
    }
    var scalEl = document.getElementById('d-scal');
    if (scalEl) {
        scalEl.textContent = (warning === 'critical') ? '- (시설양도비 구간)' : '× ' + Number(SURVEY_CAL.factor).toFixed(3);
    }

    // 6. 협상 시나리오
    // (협상 시나리오 금액은 범위 내 위치 설명으로 대체 — ask_ratio/deal_ratio 미사용)
    // fair(중간값)는 화면에 직접 표시하지 않고 피드백·보정 기준값으로만 사용한다

    // === 렌더링 ===
    var warnArea = document.getElementById('warn-area');
    var warnHtml = '';
    if (warning === 'critical') {
        warnHtml += '<div class="warn-box warn-critical"><strong>⚠ 임계점 미달 — 월 환산수익 ' + critP.toLocaleString('ko-KR') + '만원 미만</strong>'
            + '매수자 회수 부담이 커 일반 권리금 거래가 어렵습니다. <strong>시설양도비 수준(' + Number(GW.crit_quick).toLocaleString('ko-KR') + '~' + Number(GW.crit_high).toLocaleString('ko-KR') + '만원)</strong>에서 거래되는 경우가 많고, 매수자가 잘 나타나지 않을 가능성도 높습니다.</div>';
    } else if (warning === 'caution') {
        warnHtml += '<div class="warn-box warn-caution"><strong>수익 임계점 부근 (' + critP.toLocaleString('ko-KR') + '~' + cautP.toLocaleString('ko-KR') + '만원)</strong>'
            + '수익이 매도 임계점에 가까워 일반 회수배수에서 <strong>' + Math.round((1 - ratio) * 100) + '% 할인</strong>된 가격대를 적용했습니다.</div>';
    }
    if (warning !== 'critical' && doctorMult < 1.0) {
        warnHtml += '<div class="warn-box warn-doctor"><strong>메인 병원 의사 고령 반영</strong>'
            + (doctorAge === 'over75' ? '75세 이상' : '65세 이상') + ' 의사가 있어 매수자의 회수기간 내 폐업 리스크를 고려해 권리금을 <strong>'
            + Math.round((1 - doctorMult) * 100) + '% 하향</strong> 조정했습니다.</div>';
    }
    warnArea.innerHTML = warnHtml;

    var isCritical = (warning === 'critical');
    document.getElementById('p-range').innerHTML = fmtEok(quick) + '<span class="rs">~</span>' + fmtEok(high);

    // 시설양도비 구간에서는 범위 내 위치 판단이 의미가 없으므로 조건 설명을 감춘다
    document.getElementById('pos-area').style.display = isCritical ? 'none' : '';
    document.getElementById('nego-box').style.display = isCritical ? 'none' : '';
    posQuick = quick; posFair = fair; posHigh = high;
    posUpdate();

    document.getElementById('d-presc').textContent = fmtMan(presc);
    document.getElementById('d-otc').textContent = '+ ' + fmtMan(otcMonthly);
    document.getElementById('d-rent').textContent = '− ' + fmtMan(adjustedRent);
    document.getElementById('d-dep-note').textContent = depositAdj > 0
        ? '(보증금 3억 초과분 기회비용 월 ' + fmtMan(depositAdj) + ' 포함)' : '';
    document.getElementById('d-profit').textContent = fmtMan(profit);

    document.getElementById('d-base-months').textContent = rm.p30.toFixed(1) + ' / ' + rm.p50.toFixed(1) + ' / ' + rm.p75.toFixed(1) + '개월';
    document.getElementById('d-presc-mult').textContent = '× ' + pm.toFixed(2);
    document.getElementById('d-doctor').textContent = doctorMult < 1.0
        ? '× ' + doctorMult.toFixed(1) + ' (' + Math.round((1 - doctorMult) * 100) + '% 하향)' : '없음';
    document.getElementById('d-adj-months').textContent = isCritical
        ? '미적용 (시설양도비 고정)' : adjP30.toFixed(1) + ' / ' + adjP50.toFixed(1) + ' / ' + adjP75.toFixed(1) + '개월';
    document.getElementById('d-calib').textContent = isCritical
        ? '미적용 (시설양도비 고정)'
        : (cal.n >= cal.min_n ? '× ' + cal.factor.toFixed(3) + ' (' + cal.n + '건 반영)' : '없음 (표본 ' + cal.n + '/' + cal.min_n + '건)');

    document.getElementById('result-wrap').style.display = 'block';

    // 피드백 상태 초기화 — 화면에 표시된 적정 호가를 기준값으로 사용
    lastCalc = {
        region: region, doctor_age: doctorAge,
        presc: Math.round(presc), otc_daily: Math.round(otcDaily),
        rent: Math.round(rent), deposit: Math.round(deposit),
        calc_quick: quick, calc_fair: fair, calc_high: high,
        is_critical: isCritical ? 1 : 0, correction: cal.n >= 30 ? cal.factor : 1.0
    };
    fbReset();
    saveForm();
}

// === 범위 내 위치 — 체크한 조건으로 예상 구간 좁히기 ===
// 최소가/최대가 조건 체크 수의 차이로 구간 중심을 정하고, 체크가 많을수록(=정보가 많을수록) 폭을 좁힌다.
// 중심 t: 0 = 최소가(빠른 매도), 0.5 = 중간 호가, 1 = 최대가(높은 호가)
var posQuick = 0, posFair = 0, posHigh = 0;

// t(0~1) → 금액. 0.5가 정확히 중간 호가가 되도록 quick~fair, fair~high 두 구간으로 보간
function posPrice(t) {
    if (t <= 0.5) { return posQuick + (posFair - posQuick) * (t / 0.5); }
    return posFair + (posHigh - posFair) * ((t - 0.5) / 0.5);
}

// 서로 반대되는 조건(data-pair 동일, 좌우 반대)은 동시에 성립할 수 없으므로
// 한쪽을 체크하면 반대쪽을 자동 해제한다. 해제된 항목은 잠깐 강조해 왜 풀렸는지 보이게 한다.
function posToggle(el) {
    var pair = el.getAttribute('data-pair');
    if (el.checked && pair) {
        var side = el.getAttribute('data-side');
        Array.prototype.forEach.call(
            document.querySelectorAll('.pos-item input[data-pair="' + pair + '"]'),
            function (other) {
                if (other === el || other.getAttribute('data-side') === side || !other.checked) { return; }
                other.checked = false;
                var lb = other.closest('.pos-item');
                if (lb) {
                    lb.classList.remove('pos-drop');
                    void lb.offsetWidth; // 리플로우로 애니메이션 재시작
                    lb.classList.add('pos-drop');
                    setTimeout(function () { lb.classList.remove('pos-drop'); }, 900);
                }
            }
        );
    }
    posUpdate();
}

function posUpdate() {
    var band = document.getElementById('ps-band');
    if (!band || posHigh <= 0) { return; }
    var boxes = document.querySelectorAll('.pos-item input[type=checkbox]');
    var minChecked = 0, maxChecked = 0, conditionCount = 0;
    Array.prototype.forEach.call(boxes, function (box) {
        var side = box.getAttribute('data-side');
        conditionCount += (side === 'min' || side === 'max') ? 1 : 0;
        if (!box.checked) { return; }
        if (side === 'min') { minChecked++; } else if (side === 'max') { maxChecked++; }
    });
    var perSide = Math.max(1, conditionCount / 2);                  // 한쪽 조건 개수
    var lean = (maxChecked - minChecked) / perSide;                 // -1(전부 최소) ~ +1(전부 최대)
    var center = Math.min(1, Math.max(0, 0.5 + lean * 0.5));        // 구간 중심 (0=최소가, 1=최대가)
    var answered = conditionCount > 0 ? (minChecked + maxChecked) / conditionCount : 0;
    var halfWidth = 0.5 * (1 - answered);                          // 체크가 없으면 전체 범위, 많을수록 좁아짐
    var loPoint = Math.min(1, Math.max(0, center - halfWidth));
    var hiPoint = Math.min(1, Math.max(0, center + halfWidth));

    var loPrice = posPrice(loPoint), hiPrice = posPrice(hiPoint);
    document.getElementById('ps-value').innerHTML = (Math.round(loPrice) === Math.round(hiPrice))
        ? fmtEok(loPrice)
        : fmtEok(loPrice) + '<span class="rs">~</span>' + fmtEok(hiPrice);
    document.getElementById('ps-min').textContent = '최소 ' + fmtEok(posQuick);
    document.getElementById('ps-max').textContent = fmtEok(posHigh) + ' 최대';

    // 막대는 중심값(center)이 아니라 실제 금액에 비례해 배치해야 좌우 눈금과 일치한다
    var fullSpan = posHigh - posQuick;
    var leftPct = fullSpan > 0 ? (loPrice - posQuick) / fullSpan * 100 : 0;
    var widthPct = fullSpan > 0 ? (hiPrice - loPrice) / fullSpan * 100 : 100;
    band.style.left = Math.max(0, Math.min(100, leftPct)) + '%';
    band.style.width = Math.max(0.8, Math.min(100 - leftPct, widthPct)) + '%';

    var note = document.getElementById('ps-note');
    var noteText;
    if (minChecked + maxChecked === 0) {
        noteText = '해당하는 조건을 체크하면 예상 구간이 좁혀집니다.';
    } else if (minChecked > maxChecked) {
        noteText = '최소가 조건 ' + minChecked + '개 · 최대가 조건 ' + maxChecked + '개 — 범위 <strong>아래쪽</strong>에서 거래될 가능성이 큽니다.';
    } else if (maxChecked > minChecked) {
        noteText = '최소가 조건 ' + minChecked + '개 · 최대가 조건 ' + maxChecked + '개 — 범위 <strong>위쪽</strong> 호가를 유지할 여지가 있습니다.';
    } else {
        noteText = '최소가 조건 ' + minChecked + '개 · 최대가 조건 ' + maxChecked + '개 — 양쪽이 비슷해 <strong>가운데</strong>를 기준으로 봅니다.';
    }
    note.innerHTML = noteText + ' <a href="#" onclick="posReset(); return false;">전체 해제</a>';
}

function posReset() {
    Array.prototype.forEach.call(document.querySelectorAll('.pos-item input[type=checkbox]'), function (b) {
        b.checked = false;
    });
    posUpdate();
}

// === 피드백 (매도/매수 성향 + 적정성 의견 + 제시 권리금) ===
var lastCalc = null;
var fbRole = null;
var fbVerdict = 0;      // -1 싸다 / 0 적정 / 1 비싸다
var fbValue = 0;        // 제시 권리금 (만원)
var fbBase = 0;         // 최초 계산된 적정 호가 (± 2% 스텝의 기준)

function fbReset() {
    fbBase = lastCalc.calc_fair;
    fbVerdict = 0;
    fbValue = fbBase;
    var pills = document.querySelectorAll('#fb-form .fb-pill[data-verdict]');
    for (var i = 0; i < pills.length; i++) {
        pills[i].className = pills[i].getAttribute('data-verdict') === '0' ? 'fb-pill on' : 'fb-pill';
    }
    document.getElementById('fb-form').style.display = 'block';
    document.getElementById('fb-done').style.display = 'none';
    document.getElementById('fb-status').textContent = '';
    fbRender();
    fbUpdateSubmit();
}

function fbRender() {
    document.getElementById('fb-value').value = Math.round(fbValue).toLocaleString('ko-KR');
    var diff = fbValue - fbBase;
    var pct = fbBase > 0 ? Math.round(diff / fbBase * 100) : 0;
    document.getElementById('fb-delta').textContent =
        diff === 0 ? '계산결과와 동일' : (diff > 0 ? '+' : '') + pct + '%';
}

function fbSetRole(btn) {
    fbRole = btn.getAttribute('data-role');
    var pills = document.querySelectorAll('#fb-form .fb-pill[data-role]');
    for (var i = 0; i < pills.length; i++) {
        pills[i].className = pills[i] === btn ? 'fb-pill on' : 'fb-pill';
    }
    fbUpdateSubmit();
}

function fbSetVerdict(btn) {
    fbVerdict = parseInt(btn.getAttribute('data-verdict'), 10);
    var pills = document.querySelectorAll('#fb-form .fb-pill[data-verdict]');
    for (var i = 0; i < pills.length; i++) {
        pills[i].className = pills[i] === btn ? 'fb-pill on' : 'fb-pill';
    }
    // 의견에 따라 제시값 자동 완성: 비싸다 −30% / 적정 그대로 / 싸다 +20%
    fbValue = Math.round(fbBase * (fbVerdict === 1 ? 0.7 : (fbVerdict === -1 ? 1.2 : 1.0)));
    fbRender();
}

function fbStep(dir) {
    var step = Math.max(1, Math.round(fbBase * 0.02)); // 최초 계산결과의 2%
    fbValue = Math.max(0, fbValue + dir * step);
    fbRender();
}

function fbUpdateSubmit() {
    document.getElementById('fb-submit').disabled = (fbRole === null);
    document.getElementById('fb-status').textContent = fbRole === null ? '응답자를 선택하면 보낼 수 있습니다.' : '';
    document.getElementById('fb-status').style.color = '#9ca3af';
}

function fbSubmit() {
    if (fbRole === null || lastCalc === null) return;
    var btn = document.getElementById('fb-submit');
    var st = document.getElementById('fb-status');
    btn.disabled = true;
    st.textContent = '전송 중…';
    st.style.color = '#9ca3af';

    var params = 'role=' + encodeURIComponent(fbRole)
        + '&verdict=' + fbVerdict
        + '&suggested_manwon=' + Math.round(fbValue)
        + '&region=' + encodeURIComponent(lastCalc.region)
        + '&doctor_age=' + encodeURIComponent(lastCalc.doctor_age)
        + '&presc=' + lastCalc.presc
        + '&otc_daily=' + lastCalc.otc_daily
        + '&rent=' + lastCalc.rent
        + '&deposit=' + lastCalc.deposit
        + '&calc_quick=' + lastCalc.calc_quick
        + '&calc_fair=' + lastCalc.calc_fair
        + '&calc_high=' + lastCalc.calc_high
        + '&is_critical=' + lastCalc.is_critical
        + '&correction=' + lastCalc.correction;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'goodwill_feedback.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.onload = function () {
        var ok = false;
        try { ok = JSON.parse(xhr.responseText).ok === true; } catch (e) {}
        if (ok) {
            document.getElementById('fb-form').style.display = 'none';
            document.getElementById('fb-done').style.display = 'block';
        } else {
            btn.disabled = false;
            st.textContent = '저장에 실패했습니다. 잠시 후 다시 시도해주세요.';
            st.style.color = '#be123c';
        }
    };
    xhr.onerror = function () {
        btn.disabled = false;
        st.textContent = '전송에 실패했습니다. 잠시 후 다시 시도해주세요.';
        st.style.color = '#be123c';
    };
    xhr.send(params);
}

// === 입력 콤마 처리 & localStorage 저장 ===
var moneyIds = ['presc', 'otc_daily', 'rent', 'deposit'];

function attachComma(id) {
    var el = document.getElementById(id);
    el.addEventListener('input', function () {
        var digits = el.value.replace(/[^0-9]/g, '');
        el.value = digits === '' ? '' : parseInt(digits, 10).toLocaleString('ko-KR');
    });
}

function saveForm() {
    try {
        var data = {
            region: document.getElementById('region').value,
            doctor_age: document.getElementById('doctor_age').value
        };
        for (var i = 0; i < moneyIds.length; i++) {
            data[moneyIds[i]] = document.getElementById(moneyIds[i]).value;
        }
        localStorage.setItem(FORM_KEY, JSON.stringify(data));
    } catch (e) {}
}

function loadForm() {
    try {
        var raw = localStorage.getItem(FORM_KEY);
        if (!raw) return;
        var data = JSON.parse(raw);
        if (data.region) document.getElementById('region').value = data.region;
        if (data.doctor_age) document.getElementById('doctor_age').value = data.doctor_age;
        for (var i = 0; i < moneyIds.length; i++) {
            if (data[moneyIds[i]] !== undefined) document.getElementById(moneyIds[i]).value = data[moneyIds[i]];
        }
    } catch (e) {}
}

for (var i = 0; i < moneyIds.length; i++) attachComma(moneyIds[i]);
loadForm();
</script>

</body>
</html>
