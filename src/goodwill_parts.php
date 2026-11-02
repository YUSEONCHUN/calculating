<?php
// 권리금 구성 계산 — 총 권리금을 바닥권리금 / 영업권리금 / 시설권리금(재고 포함)으로 분해
// 총액은 검증된 실거래 회수배수 모델(config.php gwBaseQuote)을 사용하고,
// 시설권리금은 사용자가 입력한 시설 실액(경과연수 감가) + 재고 평가액으로 확정한 뒤
// 나머지를 상권 특성별 비중으로 바닥 : 영업에 배분한다.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
authGuard();
require_once __DIR__ . '/feedback_widget.php';

$gw_months = gwRegionMonths();
$gw_presc_mult = gwPrescMult();
$gw = gwWeights();
$calib = goodwillRegionCalib(max(1, (int) $gw['calib_min_n']), max(0, (float) $gw['calib_limit']));
$survey_cal = surveyCalibration('goodwill');
$survey_factor = $survey_cal !== null ? (float) $survey_cal['factor'] : 1.0;

// 시설 항목 그룹 — '대략적인 금액'을 그룹 단위로 입력받고 경과연수로 감가
// life: 내용연수(년), 잔존율 = max(floor_rate, 1 - 경과/내용연수)
$FACILITY_GROUPS = adminSetting('goodwill_facility_groups', array(
    'interior' => array('name' => '인테리어·내장 공사', 'life' => 5, 'hint' => '바닥·벽·천장·조명·조제실 구획'),
    'sign'     => array('name' => '간판·자동문·외부 시설', 'life' => 7, 'hint' => '채널사인·돌출간판·자동문'),
    'dispense' => array('name' => '조제 설비', 'life' => 8, 'hint' => 'ATC·산제분포기·조제대·약장·의약품 냉장고'),
    'it'       => array('name' => '전산·POS', 'life' => 5, 'hint' => 'PC·청구 프로그램·POS·CCTV'),
    'store'    => array('name' => '판매 집기', 'life' => 5, 'hint' => 'OTC 진열장·카운터·대기 의자'),
    'hvac'     => array('name' => '냉난방·기타 설비', 'life' => 8, 'hint' => '시스템 에어컨·정수기·금고·소방'),
));
$FACILITY_FLOOR = 0.10; // 잔존율 하한 (완전 감가 후에도 남는 사용가치)

// 상권 특성별 바닥권리금 비중 — 입지 자체가 수익을 만드는 상권일수록 바닥 비중이 크다
$TRAIT_FLOOR_SHARE = adminSetting('goodwill_floor_share', array(
    'munjeon'     => array('name' => '대형병원 문전 / 클리닉 밀집', 'share' => 0.70),
    'yoyang'      => array('name' => '요양·실버 상권 (요양병원 인근)', 'share' => 0.60),
    'apartment'   => array('name' => '대단지 아파트 상가', 'share' => 0.55),
    'downtown'    => array('name' => '지하철역·번화가', 'share' => 0.50),
    'office'      => array('name' => '오피스 상권', 'share' => 0.45),
    'residential' => array('name' => '동네·주택가', 'share' => 0.40),
    'academy'     => array('name' => '학원가·대학가', 'share' => 0.35),
    'outskirt'    => array('name' => '한적한 외곽', 'share' => 0.30),
));

$region_opts = array(
    'seoul_core' => '강남 등 서울 핵심 상권', 'seoul' => '서울 일반', 'metro' => '수도권 (경기·인천)',
    'bigcity' => '광역시 (부산·대구 등)', 'local' => '지방 중소도시',
);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>권리금 구성 계산 (바닥·영업·시설)</title>
    <style>
        body { font-family: 'Malgun Gothic', sans-serif; padding: 20px; max-width: 860px; margin: 0 auto; background-color: #f8fafc; }
        .search-box { margin-bottom: 20px; padding: 25px; border: 1px solid #ddd; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .nav-links.side-nav { position: fixed; left: 0; top: 0; bottom: 0; width: 200px; background: #1e293b; padding: 16px 12px; overflow-y: auto; z-index: 100; margin: 0; }
        .side-nav .sn-home { display: block; color: #fff; font-weight: bold; text-decoration: none; font-size: 0.9em; padding: 9px 10px; border-radius: 8px; background: rgba(255,255,255,0.1); margin-bottom: 6px; }
        .side-nav .sn-home:hover { background: rgba(255,255,255,0.2); }
        .side-nav .sn-cat { font-size: 0.7em; color: #94a3b8; font-weight: bold; margin: 14px 0 4px; padding: 0 10px; }
        .side-nav a { display: block; color: #cbd5e1; font-size: 0.84em; padding: 7px 10px; border-radius: 7px; text-decoration: none; margin-bottom: 1px; }
        .side-nav a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .side-nav .sn-on { display: block; font-size: 0.84em; padding: 7px 10px; border-radius: 7px; margin-bottom: 1px; background: #7c3aed; color: #fff; font-weight: bold; }
        @media (min-width: 1120px) { body { margin-left: max(236px, calc(50% - 320px)); margin-right: auto; } }
        @media (max-width: 1119px) {
            .nav-links.side-nav { position: static; width: auto; border-radius: 12px; margin-bottom: 18px; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0 14px; align-items: start; }
            .side-nav .sn-home { grid-column: 1 / -1; }
        }

        h2.page-title { margin-top: 0; color: #6d28d9; }
        .lead { color: #5f6368; font-size: 0.95em; margin-bottom: 0; line-height: 1.6; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; }
        .form-grid .full { grid-column: 1 / -1; }
        @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
        label { display: block; font-weight: bold; font-size: 0.85em; color: #374151; margin-bottom: 5px; }
        label .hint { font-weight: normal; color: #9ca3af; font-size: 0.9em; }
        input[type=text], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 1em; background: #fff; }
        input[type=text]:focus, select:focus { outline: none; border-color: #6d28d9; box-shadow: 0 0 0 3px rgba(109,40,217,0.12); }
        .suffix-wrap { position: relative; }
        .suffix-wrap .suffix { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85em; pointer-events: none; }
        .suffix-wrap input { padding-right: 46px; text-align: right; }

        .sec-title { font-size: 0.95em; font-weight: bold; color: #1e293b; margin: 26px 0 4px; padding-top: 18px; border-top: 1px solid #e2e8f0; }
        .sec-desc { font-size: 0.83em; color: #94a3b8; margin: 0 0 14px; line-height: 1.6; }

        /* 시설 입력 표 */
        .fac-table { width: 100%; border-collapse: collapse; font-size: 0.87em; }
        .fac-table th { text-align: left; font-size: 0.82em; color: #6b7280; font-weight: bold; padding: 0 6px 7px; border-bottom: 1px solid #e5e7eb; }
        .fac-table th.num, .fac-table td.num { text-align: right; }
        .fac-table td { padding: 7px 6px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .fac-table td.nm { color: #1e293b; font-weight: bold; }
        .fac-table td.nm small { display: block; font-weight: normal; color: #9ca3af; font-size: 0.86em; margin-top: 1px; }
        .fac-table input, .fac-table select { padding: 7px 8px; font-size: 0.95em; border: 1px solid #d1d5db; border-radius: 6px; width: 100%; box-sizing: border-box; text-align: right; background: #fff; }
        .fac-table select { text-align: left; }
        .fac-table td.res { color: #6d28d9; font-weight: bold; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .fac-table tr.sum td { border-top: 1px solid #0f172a; border-bottom: none; padding-top: 11px; font-weight: bold; color: #1e293b; }
        .fac-w { width: 120px; }
        .fac-y { width: 96px; }
        @media (max-width: 640px) {
            .fac-table th.hide-m, .fac-table td.hide-m { display: none; }
            .fac-w, .fac-y { width: 84px; }
        }

        .btn-calc { width: 100%; margin-top: 20px; padding: 13px; background: #6d28d9; color: #fff; border: none; border-radius: 8px; font-size: 1.05em; font-weight: bold; cursor: pointer; }
        .btn-calc:hover { background: #5b21b6; }

        /* ===== 결과 ===== */
        #result-wrap { display: none; }
        .result-sheet { background: #fff; border: 1px solid #e2e8f0; border-top: 3px solid #0f172a; padding: 26px 30px 22px; margin-bottom: 18px; }
        .sheet-head { display: flex; justify-content: space-between; align-items: baseline; padding-bottom: 12px; border-bottom: 1px solid #0f172a; margin-bottom: 4px; }
        .sheet-head .sh-title { font-size: 1.15em; font-weight: bold; color: #0f172a; letter-spacing: 0.02em; }
        .sheet-head .sh-sub { font-size: 0.76em; color: #94a3b8; letter-spacing: 0.08em; }
        .sec-label { font-size: 0.74em; font-weight: bold; color: #6d28d9; letter-spacing: 0.14em; margin: 26px 0 4px; }

        .ln { display: flex; align-items: baseline; gap: 12px; padding: 13px 2px; border-bottom: 1px solid #eef2f7; }
        .ln .l-dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 auto; align-self: center; }
        .ln .l-name { font-weight: bold; color: #1e293b; font-size: 0.95em; flex: 0 0 auto; }
        .ln .l-meta { font-size: 0.78em; color: #94a3b8; flex: 1; min-width: 0; }
        .ln .l-value { font-size: 1.35em; font-weight: bold; color: #0f172a; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .ln .l-value.rng { font-size: 1.12em; }
        .ln .l-value .rs { color: #94a3b8; font-weight: normal; margin: 0 3px; }
        .ln.floor .l-dot { background: #0284c7; }
        .ln.biz .l-dot { background: #d97706; }
        .ln.fac .l-dot { background: #059669; }
        .ln.total { border-bottom: none; border-top: 2px solid #0f172a; padding-top: 16px; }
        .ln.total .l-name { color: #6d28d9; }
        .ln.total .l-value { color: #6d28d9; font-size: 1.6em; }
        .ln.total .l-value.rng { font-size: 1.3em; }
        @media (max-width: 640px) {
            .ln { flex-wrap: wrap; }
            .ln .l-meta { flex-basis: 100%; order: 5; padding-left: 21px; margin-top: 2px; }
        }

        /* 구성 비중 막대 */
        .share-bar { display: flex; height: 26px; border-radius: 5px; overflow: hidden; margin: 16px 0 8px; background: #f1f5f9; }
        .share-bar span { display: flex; align-items: center; justify-content: center; font-size: 0.72em; font-weight: bold; color: #fff; white-space: nowrap; overflow: hidden; }
        .sb-floor { background: #0284c7; }
        .sb-biz { background: #d97706; }
        .sb-fac { background: #059669; }
        .share-legend { display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.78em; color: #64748b; }
        .share-legend i { display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 5px; }

        .warn-box { padding: 11px 14px; border-left: 3px solid; margin: 16px 0 0; font-size: 0.88em; line-height: 1.65; background: #f8fafc; color: #334155; }
        .warn-critical { border-color: #be123c; }
        .warn-caution { border-color: #d97706; }
        .warn-over { border-color: #7c3aed; }
        .warn-box strong { display: block; margin-bottom: 3px; color: #0f172a; }

        .detail-table { width: 100%; border-collapse: collapse; font-size: 0.87em; }
        .detail-table td { padding: 9px 2px; border-bottom: 1px solid #eef2f7; color: #64748b; }
        .detail-table td:last-child { text-align: right; font-weight: bold; color: #1e293b; font-variant-numeric: tabular-nums; }
        .detail-table tr.sum td { border-top: 1px solid #0f172a; border-bottom: none; color: #6d28d9; font-weight: bold; }

        .nego-note { font-size: 0.8em; color: #64748b; margin: 14px 0 0; line-height: 1.65; }
        .foot-note { font-size: 0.8em; color: #9ca3af; margin: 20px 0 30px; line-height: 1.7; }
        .gw-cta { margin-top: 26px; padding: 16px 18px; background: #f5f3ff; border: 1px solid #ddd6fe;
                  display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
        .gw-cta .cta-text { font-size: 0.85em; color: #4c1d95; line-height: 1.6; flex: 1; min-width: 200px; }
        .gw-cta .cta-btn { display: inline-block; padding: 11px 20px; background: #6d28d9; color: #fff;
                           font-size: 0.9em; font-weight: bold; text-decoration: none; white-space: nowrap; }
        .gw-cta .cta-btn:hover { background: #5b21b6; }
    </style>
    <link rel="stylesheet" href="theme.css?v=2">
    <style>:root { --accent: #6d28d9; --ring: rgba(109,40,217,0.14); }</style>
</head>
<body>

<div class="search-box">
    <?php require_once __DIR__ . '/nav.php'; renderSideNav(); ?>
    <h2 class="page-title">권리금 구성 계산</h2>
    <p class="lead">총 권리금을 <strong>바닥권리금 · 영업권리금 · 시설권리금</strong>으로 나눠 산정합니다.
        총액은 「권리금 시세」와 같은 실거래 회수배수 모델로 구하고, <strong>시설권리금은 입력하신 시설 금액</strong>(경과연수 감가)과 재고 평가액으로 확정한 뒤
        나머지를 상권 특성에 따라 바닥 : 영업으로 배분합니다.<br>
        매도자가 <em>"시설비만 얼마"</em>라고 주장할 때 그 금액이 전체에서 차지하는 몫을 바로 확인할 수 있습니다.</p>

    <div class="form-grid" style="margin-top:20px;">
        <div>
            <label for="region">지역 <span class="hint">— 회수배수 기준</span></label>
            <select id="region">
                <?php foreach ($region_opts as $rk => $rn) : ?>
                <option value="<?= $rk ?>"<?= $rk === 'metro' ? ' selected' : '' ?>><?= htmlspecialchars($rn) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="trait">상권 특성 <span class="hint">— 바닥 : 영업 배분 기준</span></label>
            <select id="trait">
                <?php foreach ($TRAIT_FLOOR_SHARE as $tk => $tv) : ?>
                <option value="<?= $tk ?>"<?= $tk === 'residential' ? ' selected' : '' ?>><?= htmlspecialchars($tv['name']) ?> (바닥 <?= round($tv['share'] * 100) ?>%)</option>
                <?php endforeach; ?>
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
        <div class="full">
            <label for="doctor_age">메인 병원 의사 고령 여부</label>
            <select id="doctor_age">
                <option value="none">해당 없음 (60대 중반 미만)</option>
                <option value="over65">65세 이상 의사 있음</option>
                <option value="over75">75세 이상 의사 있음</option>
            </select>
        </div>
    </div>

    <div class="sec-title">🔧 시설권리금 — 시설 금액 입력</div>
    <p class="sec-desc">항목별 <strong>대략적인 금액</strong>(설치 당시 기준)과 경과연수를 넣으면 잔존가치로 환산합니다.
        모르는 항목은 비워 두세요. 잔존율은 내용연수 정액 감가이며 최저 <?= round($FACILITY_FLOOR * 100) ?>%까지 인정합니다.</p>

    <table class="fac-table">
        <tr>
            <th>항목</th>
            <th class="num fac-w">설치 금액</th>
            <th class="num fac-y">경과연수</th>
            <th class="num hide-m">잔존율</th>
            <th class="num">잔존가치</th>
        </tr>
        <?php foreach ($FACILITY_GROUPS as $fk => $fv) : ?>
        <tr>
            <td class="nm"><?= htmlspecialchars($fv['name']) ?><small><?= htmlspecialchars($fv['hint']) ?> · 내용연수 <?= (int) $fv['life'] ?>년</small></td>
            <td class="num"><input type="text" id="fac_<?= $fk ?>" inputmode="numeric" value="0" oninput="calc()"></td>
            <td class="num"><input type="text" id="age_<?= $fk ?>" inputmode="numeric" value="0" oninput="calc()"></td>
            <td class="num hide-m" id="rate_<?= $fk ?>">-</td>
            <td class="num res" id="val_<?= $fk ?>">0만원</td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td class="nm">재고 <small>매입가 기준 — 평가율 적용</small></td>
            <td class="num"><input type="text" id="stock" inputmode="numeric" value="0" oninput="calc()"></td>
            <td class="num"><select id="stock_rate" onchange="calc()">
                <option value="0.9" selected>90%</option>
                <option value="0.8">80%</option>
                <option value="0.7">70%</option>
                <option value="0.6">60%</option>
                <option value="1">100%</option>
            </select></td>
            <td class="num hide-m" id="rate_stock">-</td>
            <td class="num res" id="val_stock">0만원</td>
        </tr>
        <tr class="sum">
            <td colspan="4">시설권리금 합계 (재고 포함)</td>
            <td class="num res" id="fac_total">0만원</td>
        </tr>
    </table>

    <button type="button" class="btn-calc" onclick="calc(true)">권리금 구성 계산하기</button>
</div>

<div id="result-wrap">
    <div class="result-sheet">
        <div class="sheet-head">
            <span class="sh-title">권리금 구성 명세</span>
            <span class="sh-sub" id="sh-sub">-</span>
        </div>

        <div class="sec-label">구성 항목 — 최소 ~ 최대</div>
        <div class="ln floor">
            <span class="l-dot"></span><span class="l-name">바닥권리금</span>
            <span class="l-meta" id="m-floor">-</span>
            <span class="l-value rng" id="p-floor">-</span>
        </div>
        <div class="ln biz">
            <span class="l-dot"></span><span class="l-name">영업권리금</span>
            <span class="l-meta" id="m-biz">-</span>
            <span class="l-value rng" id="p-biz">-</span>
        </div>
        <div class="ln fac">
            <span class="l-dot"></span><span class="l-name">시설권리금</span>
            <span class="l-meta" id="m-fac">-</span>
            <span class="l-value" id="p-fac">-</span>
        </div>
        <div class="ln total">
            <span class="l-name">총 권리금</span>
            <span class="l-meta" id="m-total">-</span>
            <span class="l-value rng" id="p-total">-</span>
        </div>

        <div class="share-bar" id="share-bar"></div>
        <div class="share-legend">
            <span><i class="sb-floor"></i>바닥 <b id="sh-floor">-</b></span>
            <span><i class="sb-biz"></i>영업 <b id="sh-biz">-</b></span>
            <span><i class="sb-fac"></i>시설 <b id="sh-fac">-</b></span>
            <span style="color:#94a3b8;">— 중간 호가 기준 비중</span>
        </div>

        <div id="warn-area"></div>

        <div class="sec-label">협상 참고 — 총액 기준 3단계</div>
        <table class="detail-table">
            <tr><td>빠른 매도 (총액)</td><td id="d-quick">-</td></tr>
            <tr><td>중간 호가 (총액)</td><td id="d-fair">-</td></tr>
            <tr><td>높은 호가 (총액)</td><td id="d-high">-</td></tr>
        </table>
        <p class="nego-note" style="margin-top:10px;">위 3단계는 <a href="goodwill.php" style="color:#6d28d9;">권리금 시세</a>와 <strong>같은 계산·같은 값</strong>입니다.
            같은 조건을 넣으면 두 페이지 결과가 일치하며, 이 페이지는 그 총액을 구성별로 나눠 보여줍니다.</p>
        <p class="nego-note">시설권리금은 입력하신 실액으로 고정되므로, 호가가 달라지면 <strong>바닥·영업권리금이 조정</strong>됩니다.
            매도자가 시설비를 별도로 요구한다면 그 금액이 위 시설권리금 잔존가치와 얼마나 차이 나는지 확인하세요.</p>

        <div class="sec-label">산출 근거</div>
        <table class="detail-table">
            <tr><td>월 환산수익 (조제료 + OTC 마진 − 월세·보증금 환산)</td><td id="d-profit">-</td></tr>
            <tr><td>지역 기준 회수개월수 (빠른/적정/높은)</td><td id="d-base-months">-</td></tr>
            <tr><td>조제료 규모 계수</td><td id="d-pm">-</td></tr>
            <tr><td>적용 회수개월수 (적정)</td><td id="d-adj-months">-</td></tr>
            <tr><td>지역 보정 (실사용 피드백·실매물)</td><td id="d-cal">-</td></tr>
            <tr><td>설문 보정</td><td id="d-scal">-</td></tr>
            <tr class="sum"><td>총 권리금 (최소 ~ 최대)</td><td id="d-total">-</td></tr>
        </table>

        <div class="gw-cta">
            <span class="cta-text"><strong>매물 조건까지 반영하려면</strong><br>
                병원 의존도·잔여 임대차·임대료 부담까지 반영한 정밀 산정과 개국 자금 계획은 권리금 계산기에서 확인하세요.</span>
            <a class="cta-btn" href="premium.php">권리금 계산기 →</a>
        </div>
    </div>

    <p class="foot-note">
        · 바닥권리금은 <strong>입지 자체의 가치</strong>(그 자리에서 영업하면 확보되는 기본 수익력), 영업권리금은 <strong>현 운영자의 영업력</strong>(단골·거래처·운영 노하우), 시설권리금은 <strong>인수하는 시설·재고의 잔존가치</strong>입니다.<br>
        · 총액은 실거래 회수배수 모델 기준이며, 시설권리금은 입력값으로 확정한 뒤 나머지를 상권 특성별 비중으로 배분한 <strong>약식 배분</strong>입니다. 실제 계약서상 배분은 당사자 합의로 정해집니다.<br>
        · 재고는 통상 별도 정산하는 경우가 많습니다. 이 계산기는 요청에 따라 시설권리금에 포함해 표시합니다.
    </p>
</div>

<?php renderFeedbackWidget('goodwill_parts'); ?>

<script>
var REGION_MONTHS = <?= json_encode($gw_months) ?>;
var PRESC_MULT = <?= json_encode($gw_presc_mult) ?>;
var GW = <?= json_encode($gw) ?>;
var CALIB = <?= json_encode($calib) ?>;
var SURVEY_FACTOR = <?= json_encode($survey_factor) ?>;
var FACILITY = <?= json_encode($FACILITY_GROUPS) ?>;
var FAC_FLOOR = <?= json_encode($FACILITY_FLOOR) ?>;
var FLOOR_SHARE = <?= json_encode($TRAIT_FLOOR_SHARE) ?>;
var REGION_NAMES = <?= json_encode($region_opts) ?>;

function num(id) {
    var el = document.getElementById(id);
    if (!el) { return 0; }
    var v = String(el.value).replace(/[^0-9.]/g, '');
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
// 최소 ~ 최대 범위 표기 (두 값이 같으면 단일 표기)
function fmtRange(lo, hi) {
    if (Math.round(lo) === Math.round(hi)) { return fmtEok(lo); }
    return fmtEok(lo) + '<span class="rs">~</span>' + fmtEok(hi);
}
function prescMultiplier(p) {
    if (p < 1000) return Number(PRESC_MULT.under1000);
    if (p < 2500) return Number(PRESC_MULT.b1000);
    if (p < 3500) return Number(PRESC_MULT.b2500);
    if (p < 5000) return Number(PRESC_MULT.b3500);
    return Number(PRESC_MULT.over5000);
}

// 시설권리금 — 항목별 잔존가치 합 + 재고 평가액 (표에 즉시 반영)
function facilityTotal() {
    var sum = 0;
    for (var k in FACILITY) {
        if (!FACILITY.hasOwnProperty(k)) { continue; }
        var cost = num('fac_' + k);
        var age = num('age_' + k);
        var life = Number(FACILITY[k].life);
        var rate = Math.max(FAC_FLOOR, 1 - (life > 0 ? age / life : 1));
        if (cost <= 0) { rate = 0; }
        var val = cost * rate;
        document.getElementById('rate_' + k).textContent = cost > 0 ? Math.round(rate * 100) + '%' : '-';
        document.getElementById('val_' + k).textContent = fmtMan(val);
        sum += val;
    }
    var stock = num('stock');
    var sRate = parseFloat(document.getElementById('stock_rate').value) || 0;
    var stockVal = stock * sRate;
    document.getElementById('rate_stock').textContent = stock > 0 ? Math.round(sRate * 100) + '%' : '-';
    document.getElementById('val_stock').textContent = fmtMan(stockVal);
    sum += stockVal;
    document.getElementById('fac_total').textContent = fmtMan(sum);
    return { total: sum, stock: stockVal, fixture: sum - stockVal };
}

function calc(show) {
    var facility = facilityTotal();
    if (!show && document.getElementById('result-wrap').style.display !== 'block') { return; }

    var region = document.getElementById('region').value;
    var traitKey = document.getElementById('trait').value;
    var doctorAge = document.getElementById('doctor_age').value;
    var presc = num('presc'), otcDaily = num('otc_daily'), rent = num('rent'), deposit = num('deposit');

    // 1. 월 환산수익 (권리금 시세와 동일)
    var depositAdj = deposit > Number(GW.deposit_threshold) ? deposit * Number(GW.deposit_rate) / 12 : 0;
    var otcMonthly = otcDaily * Number(GW.otc_margin) * Number(GW.work_days);
    var profit = presc + otcMonthly - (rent + depositAdj);

    // 2. 회수개월수 (지역 × 규모 계수)
    var rm = REGION_MONTHS[region];
    var pm = prescMultiplier(presc);
    var adjP30 = rm.p30 * pm, adjP50 = rm.p50 * pm, adjP75 = rm.p75 * pm;

    // 3. 총액 3단계 + 저수익 임계 처리
    var critP = Number(GW.critical_profit), cautP = Number(GW.caution_profit);
    var quick, fair, high, warning = null, ratio = 1;
    if (profit < critP) {
        quick = Number(GW.crit_quick); fair = Number(GW.crit_fair); high = Number(GW.crit_high);
        warning = 'critical';
    } else {
        if (profit < cautP) {
            ratio = 0.5 + (profit - critP) / (cautP - critP) * 0.5;
            warning = 'caution';
        }
        quick = profit * adjP30 * ratio;
        fair = profit * adjP50 * ratio;
        high = profit * adjP75 * ratio;
    }

    // 4. 의사 고령 할인 + 5. 지역·설문 보정 (시설양도비 구간 제외)
    var doctorMult = doctorAge === 'over75' ? Number(GW.doctor75) : (doctorAge === 'over65' ? Number(GW.doctor65) : 1.0);
    var cal = CALIB[region] || { factor: 1.0, n: 0 };
    var calFactor = 1.0, surveyF = 1.0;
    if (warning !== 'critical') {
        calFactor = Number(cal.factor);
        surveyF = Number(SURVEY_FACTOR) || 1.0;
        var mult = doctorMult * calFactor * surveyF;
        quick *= mult; fair *= mult; high *= mult;
    }
    quick = Math.round(quick); fair = Math.round(fair); high = Math.round(high);

    // ===== 3분류 배분 — 시설은 입력 실액으로 확정, 나머지를 상권 비중으로 바닥:영업 배분 =====
    // 총액이 빠른매도~높은호가 범위이므로 바닥·영업도 범위로 산출한다 (시설은 입력 실액 고정)
    var facVal = Math.round(facility.total);
    var floorShare = Number(FLOOR_SHARE[traitKey].share);
    var overFacility = (facVal > quick); // 시설이 최소 호가를 넘으면 그 구간의 영업권리금은 0

    // 한 호가 단계를 바닥 / 영업 / 시설로 쪼갠다 (시설을 먼저 확보하고 잔여를 상권 비중으로 배분)
    function splitTier(tier) {
        var rest = Math.max(tier - facVal, 0);
        var floorPart = Math.round(rest * floorShare);
        return { floor: floorPart, biz: rest - floorPart, total: rest + facVal };
    }
    var atQuick = splitTier(quick), atFair = splitTier(fair), atHigh = splitTier(high);

    // ===== 렌더링 =====
    document.getElementById('sh-sub').textContent = REGION_NAMES[region] + ' · ' + FLOOR_SHARE[traitKey].name;

    document.getElementById('p-floor').innerHTML = fmtRange(atQuick.floor, atHigh.floor);
    document.getElementById('m-floor').textContent = '입지 자체의 가치 — 잔여분의 ' + Math.round(floorShare * 100) + '% (상권 특성 기준)';
    document.getElementById('p-biz').innerHTML = fmtRange(atQuick.biz, atHigh.biz);
    document.getElementById('m-biz').textContent = '영업력·단골·거래처 — 잔여분의 ' + Math.round((1 - floorShare) * 100) + '%';
    document.getElementById('p-fac').textContent = fmtEok(facVal);
    document.getElementById('m-fac').textContent = '시설 잔존가치 ' + fmtMan(facility.fixture) + ' + 재고 ' + fmtMan(facility.stock) + ' — 호가와 무관하게 고정';
    document.getElementById('p-total').innerHTML = fmtRange(atQuick.total, atHigh.total);
    document.getElementById('m-total').textContent = warning === 'critical'
        ? '시설양도비 수준 (수익 임계점 미달)'
        : '실거래 회수배수 기준 — 빠른 매도 ~ 높은 호가';

    // 구성 비중 막대 (중간 호가 기준 — 금액이 아닌 비중이라 대표값 하나로 표시)
    var bar = document.getElementById('share-bar');
    var floorVal = atFair.floor, bizVal = atFair.biz, totalVal = atFair.total;
    var pf = totalVal > 0 ? floorVal / totalVal * 100 : 0;
    var pb = totalVal > 0 ? bizVal / totalVal * 100 : 0;
    var pc = totalVal > 0 ? facVal / totalVal * 100 : 0;
    bar.innerHTML = '<span class="sb-floor" style="width:' + pf + '%">' + (pf >= 12 ? Math.round(pf) + '%' : '') + '</span>'
        + '<span class="sb-biz" style="width:' + pb + '%">' + (pb >= 12 ? Math.round(pb) + '%' : '') + '</span>'
        + '<span class="sb-fac" style="width:' + pc + '%">' + (pc >= 12 ? Math.round(pc) + '%' : '') + '</span>';
    document.getElementById('sh-floor').textContent = Math.round(pf) + '%';
    document.getElementById('sh-biz').textContent = Math.round(pb) + '%';
    document.getElementById('sh-fac').textContent = Math.round(pc) + '%';

    // 경고
    var warnHtml = '';
    if (warning === 'critical') {
        warnHtml += '<div class="warn-box warn-critical"><strong>⚠ 수익 임계점 미달 — 월 환산수익 ' + Number(GW.critical_profit).toLocaleString('ko-KR') + '만원 미만</strong>'
            + '영업권리금이 성립하기 어려운 구간입니다. 총액이 시설양도비 수준으로 산정되며, 사실상 <strong>시설권리금 위주의 거래</strong>가 됩니다.</div>';
    } else if (warning === 'caution') {
        warnHtml += '<div class="warn-box warn-caution"><strong>수익 임계점 부근</strong>'
            + '회수배수에서 <strong>' + Math.round((1 - ratio) * 100) + '% 할인</strong>된 총액을 적용했습니다. 영업권리금 비중이 낮게 나오는 것이 정상입니다.</div>';
    }
    if (overFacility) {
        warnHtml += '<div class="warn-box warn-over"><strong>시설 잔존가치가 최소 호가를 넘습니다</strong>'
            + '입력하신 시설·재고 평가액(' + fmtMan(facVal) + ')이 수익 기준 빠른 매도가(' + fmtMan(quick) + ')보다 큽니다. '
            + '시설에 과투자되었거나 수익 대비 시설이 과대평가된 경우로, 최소 구간에서는 <strong>영업권리금 없이 시설비만</strong> 인정하는 협상이 현실적입니다.</div>';
    }
    if (warning !== 'critical' && doctorMult < 1.0) {
        warnHtml += '<div class="warn-box warn-caution"><strong>메인 병원 의사 고령 반영</strong>'
            + (doctorAge === 'over75' ? '75세 이상' : '65세 이상') + ' 의사가 있어 회수기간 내 폐업 리스크를 고려해 총액을 <strong>'
            + Math.round((1 - doctorMult) * 100) + '% 하향</strong> 조정했습니다. 시설권리금은 그대로이므로 영업·바닥권리금이 줄어듭니다.</div>';
    }
    document.getElementById('warn-area').innerHTML = warnHtml;

    // 협상 3단계 (총액)
    document.getElementById('d-quick').textContent = fmtEok(Math.max(quick, facVal));
    document.getElementById('d-fair').textContent = fmtEok(Math.max(fair, facVal));
    document.getElementById('d-high').textContent = fmtEok(Math.max(high, facVal));

    // 산출 근거
    document.getElementById('d-profit').textContent = fmtMan(profit);
    document.getElementById('d-base-months').textContent = rm.p30.toFixed(1) + ' / ' + rm.p50.toFixed(1) + ' / ' + rm.p75.toFixed(1) + '개월';
    document.getElementById('d-pm').textContent = '× ' + pm.toFixed(2);
    document.getElementById('d-adj-months').textContent = warning === 'critical' ? '- (시설양도비 구간)' : adjP50.toFixed(1) + '개월';
    document.getElementById('d-cal').textContent = '× ' + calFactor.toFixed(3) + (cal.n ? ' (표본 ' + cal.n + '건)' : '');
    document.getElementById('d-scal').textContent = '× ' + surveyF.toFixed(3);
    document.getElementById('d-total').innerHTML = fmtRange(atQuick.total, atHigh.total);

    document.getElementById('result-wrap').style.display = 'block';
    if (show) {
        document.getElementById('result-wrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// 천 단위 콤마 — 금액 입력칸
['presc', 'otc_daily', 'rent', 'deposit', 'stock'].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) { return; }
    el.addEventListener('input', function () {
        var v = this.value.replace(/[^0-9]/g, '');
        this.value = v ? Number(v).toLocaleString('ko-KR') : '';
        calc();
    });
});
['region', 'trait', 'doctor_age'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', function () { calc(); });
});

facilityTotal();
</script>

</body>
</html>
