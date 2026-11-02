<?php
// 권리금 시세 산정 — 거래 데이터 기반 회수배수 모델 (클라이언트 계산 + 피드백 자동 보정)
// 모델: 월 환산수익 × (지역별 회수개월수 × 조제료 규모 계수), 개준모 362건 가중회귀 참고
// 보정: goodwill_feedback 의 제시값/계산값 비율 평균으로 지역별 가격을 자동 보정 (표본 30건+, ±10% 한도)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
authGuard(); // 입장 조건: 페이지별 최소 등급 (auth.php authPageLevels)

// 참고 데이터·가중치 — config.php 공용 로더 사용 (원본 권리금 시세와 계수를 동일하게 유지하고
// 계단형 vs 곡선형 '보간 방식'만 비교하기 위함. 계수가 갈리면 비교 자체가 성립하지 않는다)
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
    <title>권리금 시세 산정 (곡선형 비교)</title>
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
    <div style="background:#ecfdf5; border:1px solid #6ee7b7; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:0.85em; color:#065f46; line-height:1.6;">
        🧪 <strong>비교용 페이지 — 조제료 규모 계수를 곡선형(구간 선형보간)으로 계산합니다.</strong>
        원본은 <a href="goodwill.php" style="color:#047857; font-weight:bold;">권리금 시세 산정(계단형)</a>에서 그대로 유지됩니다. 결과 비교를 위해 의견 수집은 이 페이지에서 비활성화했습니다.
    </div>
    <h2 class="page-title">💰 권리금 시세 산정 <span style="font-size:0.55em; color:#0d9488; font-weight:bold; vertical-align:middle;">곡선형</span></h2>
    <p class="lead">
        실거래 회수배수 통계 기반으로 <strong>빠른매도 / 적정 호가 / 높은 호가</strong> 3단계 가격대를 즉시 산출합니다.
        5개 변수만 입력하면 되고, AI 분석이 없어 결과가 바로 나옵니다.
        정밀 수익성 분석과 개국 자금 계획이 필요하면 <a href="premium_curve.php" style="color:#b45309;">권리금 계산</a>을 이용하세요.
    </p>
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

        <div class="sec-label">호가 전략</div>
        <div class="ln quick">
            <span class="l-dot"></span>
            <span class="l-name">빠른 매도</span>
            <span class="l-meta"><span id="m-quick"></span> · 1~2개월 내 거래 목표</span>
            <span class="l-value" id="p-quick">-</span>
        </div>
        <div class="ln fair">
            <span class="l-dot"></span>
            <span class="l-name">적정 호가</span>
            <span class="l-tag">기준</span>
            <span class="l-meta"><span id="m-fair"></span> · 2~4개월 소요</span>
            <span class="l-value" id="p-fair">-</span>
        </div>
        <div class="ln high">
            <span class="l-dot"></span>
            <span class="l-name">높은 호가</span>
            <span class="l-meta"><span id="m-high"></span> · 6개월 이상 대기</span>
            <span class="l-value" id="p-high">-</span>
        </div>

        <div id="nego-box">
            <div class="sec-label">협상 시나리오</div>
            <div class="ln nego">
                <span class="l-name">추천 시작 호가</span>
                <span class="l-meta">적정과 높은 호가 사이에서 시작</span>
                <span class="l-value" id="p-ask">-</span>
            </div>
            <div class="ln nego">
                <span class="l-name">예상 거래가</span>
                <span class="l-meta">협상 후 빠른매도~적정 사이 마무리 예상</span>
                <span class="l-value" id="p-deal">-</span>
            </div>
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
            <a class="cta-btn" href="premium_curve.php">📊 권리금 계산기에서 자세히 계산하기 →</a>
        </div>
    </div>


    <div class="result-sheet" id="fb-card" style="border-top-color:#0f766e; display:none;">
    <!-- 비교용 페이지는 원본(goodwill.php)의 지역별 회수배수 보정(goodwill_feedback) 데이터를 오염시키지 않도록 의견 수집 UI를 숨김 (JS 참조 유지를 위해 DOM은 존치) -->
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
// [곡선형] 원본(goodwill.php)은 구간별 고정값(계단형)이라 구간 경계를 넘는 순간 배수가 뚝 뛰는 문제가 있음.
// 이 페이지는 같은 5개 값을 "1,000/2,500/3,500/5,000만원 지점의 기준점"으로 재해석해 그 사이를 선형보간한다.
// (관리자 설정값·구조는 원본과 완전히 동일 — 계산 공식만 다름)
var PRESC_MULT = <?= json_encode($gw_presc_mult) ?>;
function lerp(x, x0, y0, x1, y1) { return y0 + (x - x0) / (x1 - x0) * (y1 - y0); }
function prescMultiplier(p) {
    var m = PRESC_MULT;
    if (p <= 1000) return Number(m.under1000 + (p / 1000) * (m.b1000 - m.under1000)); // 0~1,000: 바닥값→1,000 기준점으로 접근
    if (p < 2500) return lerp(p, 1000, Number(m.b1000), 2500, Number(m.b2500));
    if (p < 3500) return lerp(p, 2500, Number(m.b2500), 3500, Number(m.b3500));
    if (p < 5000) return lerp(p, 3500, Number(m.b3500), 5000, Number(m.over5000));
    return Number(m.over5000); // 5,000 이상은 상한 고정 (원본과 동일한 정책 유지)
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
    var ask  = Math.round(fair + (high - fair) * Number(GW.ask_ratio));
    var deal = Math.round(quick + (fair - quick) * Number(GW.deal_ratio));

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
    document.getElementById('p-quick').textContent = fmtEok(quick);
    document.getElementById('p-fair').textContent = fmtEok(fair);
    document.getElementById('p-high').textContent = fmtEok(high);
    document.getElementById('m-quick').textContent = isCritical ? '시설양도비 하단' : '× ' + adjP30.toFixed(1) + '개월';
    document.getElementById('m-fair').textContent  = isCritical ? '시설양도비 중간' : '× ' + adjP50.toFixed(1) + '개월';
    document.getElementById('m-high').textContent  = isCritical ? '시설양도비 상단' : '× ' + adjP75.toFixed(1) + '개월';

    document.getElementById('nego-box').style.display = isCritical ? 'none' : '';
    document.getElementById('p-ask').textContent = fmtEok(ask);
    document.getElementById('p-deal').textContent = '~ ' + fmtEok(deal);

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
