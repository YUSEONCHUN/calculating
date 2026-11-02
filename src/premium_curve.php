<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
authGuard(); // 입장 조건: 페이지별 최소 등급 (auth.php authPageLevels)
require_once __DIR__ . '/feedback_widget.php';
require_once __DIR__ . '/public_api.php';

// 1. API 설정 (Gemini Flash 최신버전)
$api_key = GEMINI_API_KEY; // .env의 GEMINI_API_KEY에서 로드
// 사용 모델과 모델별 최대 시도 횟수 — 기본 모델이 과부하면 재시도 후 예비 모델로 전환
$api_models = aiModelPlan(); // 점검 캐시 기준 — 기본 모델 장애 시 예비 직행, 회복 시 자동 복귀

$api_response = '';
$current_year = date('Y');

// [권리금 산정 기준 상수] — 관리자(가중치 관리 > 권리금 계산)에서 조정 가능
$PW = adminSetting('premium_weights', array(
    'margin_dispense' => 0.25, 'default_otc_margin_pct' => 30, 'base_months' => 12,
    'range_pct' => 15, 'inventory_months' => 1.5, 'reserve_rent_months' => 3,
));
define('MARGIN_DISPENSE', (float) $PW['margin_dispense']);       // 조제 매출 마진율 추정
define('DEFAULT_OTC_MARGIN_PCT', (float) $PW['default_otc_margin_pct']); // OTC 마진율 기본값 (%) — 폼에서 조정 가능
define('BASE_MONTHS', (float) $PW['base_months']);               // 기준 권리금 = 월 매출총이익 × 개월 수
define('PREMIUM_RANGE', (float) $PW['range_pct'] / 100);         // 적정 범위 ± 비율

// [개국 필요자금 추정 상수]
define('INVENTORY_MONTHS', (float) $PW['inventory_months']);     // 초기 의약품 재고 = 월 매출원가 × 개월 수
define('RESERVE_RENT_MONTHS', (float) $PW['reserve_rent_months']); // 예비 운전자금 = 월 임대료 × 개월 수

// 폼 데이터 수신 (금액 단위: 만원)
$full_address = trim(isset($_POST['full_address']) ? $_POST['full_address'] : '');
$commercial_trait = isset($_POST['commercial_trait']) ? $_POST['commercial_trait'] : '동네/주택가 (처방 및 매약 보통, 업무강도 보통)';
$hospital_dependency = isset($_POST['hospital_dependency']) ? $_POST['hospital_dependency'] : '복수 병원 인근 (처방 분산, 안정적)';
$doctor_age = isset($_POST['doctor_age']) ? $_POST['doctor_age'] : 'none'; // 메인 병원 의사 고령 여부 (권리금 시세와 동일 기준)
if (!in_array($doctor_age, array('none', 'over65', 'over75'), true)) {
    $doctor_age = 'none';
}
$dispense_sales = (int) (isset($_POST['dispense_sales']) ? $_POST['dispense_sales'] : 4000);
$otc_sales = (int) (isset($_POST['otc_sales']) ? $_POST['otc_sales'] : 1000);
$otc_margin_pct = (float) (isset($_POST['otc_margin']) ? $_POST['otc_margin'] : DEFAULT_OTC_MARGIN_PCT);
$otc_margin_pct = max(0, min(100, $otc_margin_pct)); // 0~100% 범위로 제한
$rx_count = (int) (isset($_POST['rx_count']) ? $_POST['rx_count'] : 2500);
$monthly_rent = (int) (isset($_POST['monthly_rent']) ? $_POST['monthly_rent'] : 300);
$deposit = (int) (isset($_POST['deposit']) ? $_POST['deposit'] : 5000);
$store_size = (int) (isset($_POST['store_size']) ? $_POST['store_size'] : 15);
$lease_remaining = isset($_POST['lease_remaining']) ? $_POST['lease_remaining'] : '1~3년';
$asking_premium = trim(isset($_POST['asking_premium']) ? $_POST['asking_premium'] : '');

// 카카오 주소검색이 전달하는 행정구역 (공공데이터 실측 조회용)
$sido_in = trim(isset($_POST['sido']) ? $_POST['sido'] : '');
$sigungu_in = trim(isset($_POST['sigungu']) ? $_POST['sigungu'] : '');
$bname_in = trim(isset($_POST['bname']) ? $_POST['bname'] : '');

// 상권 특성·병원 의존도 옵션 목록 — AI 입력 검증의 제안값도 이 목록에서 선택됨
$trait_options = array(
    '대형병원 문전/클리닉 밀집 (처방 다발, 업무강도 높음)',
    '요양·실버 상권 (요양병원 인근, 장기 처방 수요)',
    '대단지 아파트 상가 (입주민 고정 수요)',
    '지하철역/번화가 (유동인구 다수, 매약 중심, 업무강도 높음)',
    '오피스 상권 (직장인 대상, 평일 주간 집중)',
    '쇼핑몰·대형마트 입점 (특수상권, 주말 가족 고객)',
    '동네/주택가 (처방 및 매약 보통, 업무강도 보통)',
    '학원가·대학가 (젊은 층 매약 중심, 방학 비수기)',
    '한적한 외곽 (방문객 적음, 업무강도 낮음)',
);
$dependency_options = array(
    '복수 병원 인근 (처방 분산, 안정적)',
    '단일 병원 문전 (해당 병원 이전·폐업 리스크)',
    '병원 무관 (매약 중심 상권)',
);

// 개국 자금 계획 입력 (금액 단위: 만원) — 조달 자금은 자기자본과 대출 2종류로 입력
$own_capital = (int) (isset($_POST['own_capital']) ? $_POST['own_capital'] : 20000);
$loan_amount = (int) (isset($_POST['loan_amount']) ? $_POST['loan_amount'] : 10000);
$interior_cost = (int) (isset($_POST['interior_cost']) ? $_POST['interior_cost'] : 3000);
$loan_rate = (float) (isset($_POST['loan_rate']) ? $_POST['loan_rate'] : 4.5);
$loan_rate = max(0, min(20, $loan_rate));
$loan_years = (int) (isset($_POST['loan_years']) ? $_POST['loan_years'] : 5);
if (!in_array($loan_years, array(3, 5, 7, 10), true)) {
    $loan_years = 5;
}

/**
 * 만원 단위 금액을 "N억 N,NNN만원" 형태로 표시
 */
function formatManwon($mw)
{
    $mw = (int) round($mw);
    if ($mw >= 10000) {
        $eok = intdiv($mw, 10000);
        $rest = $mw % 10000;
        return $rest > 0 ? $eok . '억 ' . number_format($rest) . '만원' : $eok . '억원';
    }
    return number_format($mw) . '만원';
}

/**
 * 상권·병원 의존도·잔여 계약·임대료 부담 조정률 계산
 * (입력 검증에서 제안값으로 재계산할 수 있도록 함수로 분리)
 */
function computePremiumAdjustments($trait, $dependency, $lease, $rentRatio, $doctorAge = 'none')
{
    $adjustments = array();

    // 조정률은 관리자(가중치 관리 > 권리금 계산 조정 계수)에서 설정 가능
    $R = adminSetting('premium_adjust_rates', array());
    $rate = function ($key, $default) use ($R) {
        return isset($R[$key]) ? (float) $R[$key] : $default;
    };

    // 상권 조정 — 매출 안정성·양도 가치 기준
    if (strpos($trait, '문전') !== false) {
        $adjustments[] = array('label' => '상권 조정 (병원 문전, 처방 안정)', 'rate' => $rate('trait_munjeon', 0.20));
    } elseif (strpos($trait, '요양') !== false || strpos($trait, '실버') !== false) {
        $adjustments[] = array('label' => '상권 조정 (요양·실버, 장기 처방 반복 수요)', 'rate' => $rate('trait_yoyang', 0.15));
    } elseif (strpos($trait, '아파트') !== false) {
        $adjustments[] = array('label' => '상권 조정 (대단지 아파트, 고정 배후 수요)', 'rate' => $rate('trait_apartment', 0.10));
    } elseif (strpos($trait, '번화가') !== false) {
        $adjustments[] = array('label' => '상권 조정 (번화가, 유동인구)', 'rate' => $rate('trait_downtown', 0.10));
    } elseif (strpos($trait, '오피스') !== false) {
        $adjustments[] = array('label' => '상권 조정 (오피스, 평일 안정·주말 공백)', 'rate' => $rate('trait_office', 0.05));
    } elseif (strpos($trait, '쇼핑몰') !== false || strpos($trait, '마트') !== false) {
        $adjustments[] = array('label' => '상권 조정 (쇼핑몰·마트 입점, 집객력·임대조건 의존)', 'rate' => $rate('trait_mall', 0.05));
    } elseif (strpos($trait, '학원가') !== false || strpos($trait, '대학가') !== false) {
        $adjustments[] = array('label' => '상권 조정 (학원가·대학가, 방학 비수기 상쇄)', 'rate' => $rate('trait_academy', 0.0));
    } elseif (strpos($trait, '외곽') !== false) {
        $adjustments[] = array('label' => '상권 조정 (한적한 외곽)', 'rate' => $rate('trait_outskirt', -0.20));
    } else {
        $adjustments[] = array('label' => '상권 조정 (동네/주택가)', 'rate' => $rate('trait_residential', 0.0));
    }

    // 병원 의존도 조정 (단일 병원 문전은 병원 이전·폐업 리스크)
    if (strpos($dependency, '복수') === 0) {
        $adjustments[] = array('label' => '병원 의존도 조정 (복수 병원, 리스크 분산)', 'rate' => $rate('dep_multi', 0.10));
    } elseif (strpos($dependency, '단일') === 0) {
        $adjustments[] = array('label' => '병원 의존도 조정 (단일 병원 의존 리스크)', 'rate' => $rate('dep_single', -0.05));
    } else {
        $adjustments[] = array('label' => '병원 의존도 조정 (병원 무관, 매약 중심)', 'rate' => $rate('dep_none', -0.10));
    }

    // 메인 병원 의사 고령 조정 (매수자의 회수기간 내 폐업 리스크 — 권리금 시세와 동일 기준)
    if ($doctorAge === 'over75') {
        $adjustments[] = array('label' => '병원 의사 고령 조정 (75세 이상 — 회수기간 내 폐업 리스크)', 'rate' => $rate('doctor75', -0.50));
    } elseif ($doctorAge === 'over65') {
        $adjustments[] = array('label' => '병원 의사 고령 조정 (65세 이상 — 폐업 리스크)', 'rate' => $rate('doctor65', -0.30));
    }

    // 잔여 임대차 기간 조정
    if ($lease === '3년 이상') {
        $adjustments[] = array('label' => '잔여 계약 조정 (3년 이상 안정)', 'rate' => $rate('lease_3y', 0.10));
    } elseif ($lease === '1년 미만') {
        $adjustments[] = array('label' => '잔여 계약 조정 (1년 미만, 갱신 불확실)', 'rate' => $rate('lease_under1y', -0.20));
    } elseif ($lease === '재계약 협의 필요') {
        $adjustments[] = array('label' => '잔여 계약 조정 (재계약 협의 필요)', 'rate' => $rate('lease_renegotiate', -0.05));
    } else {
        $adjustments[] = array('label' => '잔여 계약 조정 (1~3년)', 'rate' => $rate('lease_mid', 0.0));
    }

    // [곡선형] 임대료 부담 조정 — 원본(premium.php)은 30%/50%에서 계단식으로 뚝 끊겨
    // 월세 몇 만원 차이로 조정률이 갑자기 -10%p씩 뛰는 문제가 있음.
    // 같은 두 기준값(rent_over30, rent_over50)을 30%→40%→50% 세 지점의 곡선 기준점으로 재해석해
    // 0 → rent_over30 → rent_over50 을 매끄럽게 잇는다 (40%는 두 기준의 중간점, 별도 설정 불필요).
    // 30% 이하는 원본과 동일하게 조정 없음, 50% 이상은 원본과 동일하게 rent_over50 상한 고정.
    $rent_over30 = $rate('rent_over30', -0.10);
    $rent_over50 = $rate('rent_over50', -0.20);
    $rent_mid = 0.4;
    if ($rentRatio > 0.5) {
        $adjustments[] = array('label' => '임대료 부담 조정 (매출총이익의 50% 초과 — 상한)', 'rate' => $rent_over50);
    } elseif ($rentRatio > 0.3) {
        $rent_adj = ($rentRatio <= $rent_mid)
            ? ($rentRatio - 0.3) / ($rent_mid - 0.3) * $rent_over30
            : $rent_over30 + ($rentRatio - $rent_mid) / (0.5 - $rent_mid) * ($rent_over50 - $rent_over30);
        $adjustments[] = array('label' => '임대료 부담 조정 (매출총이익의 ' . round($rentRatio * 100) . '% — 곡선 보간)', 'rate' => $rent_adj);
    }

    $total = 0.0;
    foreach ($adjustments as $adj) {
        $total += $adj['rate'];
    }
    return array($adjustments, $total);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // [권리금 자체 계산 — PHP 결정적 산출]
    // 1) 월 매출총이익 추정 (OTC 마진율은 폼 입력값 반영)
    $gross_profit = (int) round($dispense_sales * MARGIN_DISPENSE + $otc_sales * ($otc_margin_pct / 100));
    $otc_margin_disp = rtrim(rtrim(number_format($otc_margin_pct, 1), '0'), '.');

    // 2) 기준 권리금 = 월 매출총이익 × 12개월
    $base_premium = $gross_profit * BASE_MONTHS;

    // 3) 조정 계수 (함수 계산 — 입력 검증 재계산에도 동일 함수 사용)
    $rent_ratio = $gross_profit > 0 ? $monthly_rent / $gross_profit : 0;
    $adjResult = computePremiumAdjustments($commercial_trait, $hospital_dependency, $lease_remaining, $rent_ratio, $doctor_age);
    $adjustments = $adjResult[0];
    $total_rate = $adjResult[1];
    $fair_premium = (int) round($base_premium * (1 + $total_rate));
    $premium_low = (int) round($fair_premium * (1 - PREMIUM_RANGE));
    $premium_high = (int) round($fair_premium * (1 + PREMIUM_RANGE));
    $payback_months = $gross_profit > 0 ? (int) ceil($fair_premium / $gross_profit) : 0;

    // 5) 매도 희망 권리금 평가 (입력 시)
    $asking_eval = '';
    $asking_num = (int) $asking_premium;
    if ($asking_num > 0) {
        if ($asking_num > $premium_high) {
            $over = round(($asking_num / $fair_premium - 1) * 100);
            $asking_eval = '적정 범위 상회 (기준가 대비 +' . $over . '%)';
        } elseif ($asking_num < $premium_low) {
            $under = round((1 - $asking_num / $fair_premium) * 100);
            $asking_eval = '적정 범위 하회 (기준가 대비 -' . $under . '%)';
        } else {
            $asking_eval = '적정 범위 내';
        }
    }

    // 6) 개국 필요자금 계획 (권리금 기준가 사용)
    $cogs_month = max(0, $dispense_sales + $otc_sales - $gross_profit); // 월 매출원가
    $inventory_cost = (int) round($cogs_month * INVENTORY_MONTHS);
    $reserve_cost = $monthly_rent * RESERVE_RENT_MONTHS;
    $total_capital_needed = $fair_premium + $deposit + $interior_cost + $inventory_cost + $reserve_cost;

    // 총 조달 자금 = 자기자본 + 대출 금액 (계산치), 필요자금과 비교해 과부족 판정
    $total_funding = $own_capital + $loan_amount;
    $shortfall = $total_capital_needed - $total_funding; // 양수: 부족, 음수: 여유
    $equity_ratio = $total_funding > 0
        ? (int) round($own_capital / $total_funding * 100)
        : 0;

    // 원리금균등 상환 계산 (입력한 대출 금액 기준)
    $monthly_payment = 0;
    $total_interest = 0;
    if ($loan_amount > 0) {
        $n = $loan_years * 12;
        if ($loan_rate > 0) {
            $r = $loan_rate / 100 / 12;
            $monthly_payment = (int) round($loan_amount * $r * pow(1 + $r, $n) / (pow(1 + $r, $n) - 1));
            $total_interest = $monthly_payment * $n - $loan_amount;
        } else {
            $monthly_payment = (int) round($loan_amount / $n);
        }
    }
    $payment_ratio = ($gross_profit > 0 && $monthly_payment > 0)
        ? round($monthly_payment / $gross_profit * 100)
        : 0;

    // [공공데이터 실측 조회] — 키 설정 시 병·의원/약국/상권 데이터로 입력 검증
    $region = parseRegion($full_address);
    $sido = $sido_in !== '' ? $sido_in : $region[0];
    $sigungu = $sigungu_in !== '' ? $sigungu_in : $region[1];
    $data_key = envValue('DATA_GO_KR_KEY');
    $kakao_key = envValue('KAKAO_REST_KEY');

    // 병의원·약국·카카오 지오코딩 3건 동시 호출 후 좌표 확보 시 상권정보 연계 (fetchLocationBundle — 병렬)
    $real_err = '';
    $biz_err = '';
    list($real_data, $biz_data) = fetchLocationBundle(
        $data_key, $kakao_key, $sido, $sigungu, $bname_in, $full_address, $real_err, $biz_err
    );

    $real_prompt = '';
    if ($real_data !== null) {
        $hp_ratio = $real_data['pharm_total'] > 0 ? round($real_data['hosp_total'] / $real_data['pharm_total'], 1) : 0;
        $real_prompt .= "- {$sido} {$sigungu}: 병·의원 " . number_format($real_data['hosp_total']) . "곳 / 약국 " . number_format($real_data['pharm_total']) . "곳 (비율 {$hp_ratio}:1)\n";
        if ($bname_in !== '') {
            $real_prompt .= "- '{$bname_in}' 동 단위: 병·의원 " . $real_data['hosp_dong'] . "곳, 약국 " . $real_data['pharm_dong'] . "곳\n";
            if (count($real_data['dong_hosp_names']) > 0) {
                $real_prompt .= "- 동 내 주요 의료기관: " . implode(', ', $real_data['dong_hosp_names']) . "\n";
            }
        }
    }
    if ($biz_data !== null) {
        $bizParts = array();
        foreach (array_slice($biz_data['types'], 0, 6, true) as $tn => $tc) {
            $bizParts[] = $tn . ' ' . $tc . '곳';
        }
        $real_prompt .= "- 반경 500m 상가 업소: " . number_format($biz_data['total']) . "곳"
            . (count($bizParts) > 0 ? " (주요 업종: " . implode(', ', $bizParts) . ")" : "") . "\n";
    }

    // 2. 시스템 프롬프트 — 수치 계산은 PHP가 완료, AI는 정성 분석 담당
    $systemPrompt = "너는 대한민국 약국 양수도(권리금) 전문 컨설팅 시스템이다.
[Calculated Data]는 시스템이 산출한 확정 수치이므로 재계산하거나 다른 금액을 제시하지 마라.
너의 역할은 상권 분석, 리스크 평가, 협상 전략 서술이다.

[Calculated Data (시스템 확정 산출치)]
- 월 매출총이익 추정: " . formatManwon($gross_profit) . " (조제 마진 " . (MARGIN_DISPENSE * 100) . "% + OTC 마진 {$otc_margin_disp}%)
- 기준 권리금 (" . BASE_MONTHS . "개월분): " . formatManwon($base_premium) . "
- 조정 후 적정 권리금: " . formatManwon($fair_premium) . " (범위 " . formatManwon($premium_low) . " ~ " . formatManwon($premium_high) . ")
- 투자 회수 기간: 약 {$payback_months}개월
" . ($asking_num > 0 ? "- 매도자 희망 권리금: " . formatManwon($asking_num) . " → 평가: {$asking_eval}\n" : "") . "
[Funding Data (개국 필요자금 계획, 시스템 확정 산출치)]
- 총 필요자금: " . formatManwon($total_capital_needed) . " (권리금 기준가 + 보증금 + 인테리어 + 초기 재고 + 예비 운전자금)
- 조달 계획: 자기자본 " . formatManwon($own_capital) . " + 대출 " . formatManwon($loan_amount) . " = 총 조달 자금 " . formatManwon($total_funding) . " (자기자본 비율 {$equity_ratio}%)
- 필요자금 대비: " . ($shortfall > 0
        ? "부족 " . formatManwon($shortfall)
        : ($shortfall < 0 ? "여유 " . formatManwon(-$shortfall) : "정확히 일치")) . "
- 대출 상환: " . ($loan_amount > 0
        ? "연 {$loan_rate}%, {$loan_years}년 원리금균등 → 월 상환 " . formatManwon($monthly_payment) . ", 총 이자 " . formatManwon($total_interest) . ", 월 상환액이 월 매출총이익의 {$payment_ratio}%"
        : "대출 없음 (자기자본만으로 조달)") . "

[작성 지침]
1. location_analysis: 제공된 주소의 지리적 특성, 상권, 배후 수요, 인근 경쟁 약국 예상 수준을 분석하라.
2. risk_assessment: 병원 의존도, 잔여 임대차 기간, 임대료 수준, 처방전 추이 관점에서 권리금 회수 리스크를 평가하라. 단일 병원 의존 시 병원 이전·폐업 리스크를 반드시 짚어라.
3. negotiation_strategy: 양수인(인수자) 입장에서 위 적정 범위를 기준으로 한 협상 전략을 제시하라. 희망 권리금이 있으면 그 금액에 대한 대응 전략을 포함하라.
4. checklist: 계약 전 확인해야 할 실사 항목(처방전 통계 검증, 임대차 승계 조건, 병원 임대차 잔여기간, 시설·재고 포함 여부 등)을 서술하라.
5. funding_advice: [Funding Data]를 바탕으로 자기자본 비율과 월 상환 부담의 적정성을 평가하고,
   월 상환액이 월 매출총이익의 40%를 초과하면 위험 신호로 경고하라.
   조달 자금이 필요자금보다 부족하면 부족분 마련 방안(대출 증액, 자기자본 보강, 필요자금 축소)을 우선 제시하고, 여유가 있으면 그 활용 방향을 조언하라.
   약사 대상 대출(약사회 신용보증, 의약품도매상 담보 지원), 소상공인 정책자금, 창업 지원 프로그램 등 활용 가능한 조달 방안을 제안하라. 수치는 재계산하지 마라.

출력은 반드시 요구된 JSON 스키마 구조만 반환하라.";

    if ($real_prompt !== '') {
        $systemPrompt .= "\n\n[Real Data — 공공데이터 실측치 (입력 검증의 근거로 사용하라)]\n" . $real_prompt;
    }
    $systemPrompt .= "\n\n[입력 검증 지침 (추가 출력 필드)]
6. input_validation: " . ($real_prompt !== '' ? "위 실측치를 근거로" : "주소의 지역 특성 지식을 근거로") . " 사용자가 선택한 상권 특성('{$commercial_trait}')과 병원 의존도('{$hospital_dependency}')가 실제 입지와 부합하는지 검증하라. 불일치가 있으면 무엇이 어떻게 다른지 구체적으로 지적하고, 부합하면 부합한다고 확인하라.
7. suggested_trait: 실제 입지에 가장 적절한 상권 특성을 아래 목록에서 골라 '정확히 그대로' 반환하라. 현재 선택이 타당하면 현재 값을 그대로 반환하라.
   - " . implode("\n   - ", $trait_options) . "
8. suggested_dependency: 병원 의존도를 아래 목록에서 같은 방식으로 반환하라.
   - " . implode("\n   - ", $dependency_options);

    // 3. 유저 프롬프트
    $userPrompt = "
[Input Variables]
- Full Address (약국 주소): {$full_address}
- Commercial Trait (상권 특성): {$commercial_trait}
- Hospital Dependency (병원 의존도): {$hospital_dependency}
- Main Doctor Age (메인 병원 의사 고령 여부): " . ($doctor_age === 'over75' ? '75세 이상 의사 있음 (폐업 리스크 하향 조정이 계산에 반영됨)' : ($doctor_age === 'over65' ? '65세 이상 의사 있음 (폐업 리스크 하향 조정이 계산에 반영됨)' : '해당 없음')) . "
- Monthly Dispense Sales (월 조제 매출): " . formatManwon($dispense_sales) . "
- Monthly OTC Sales (월 OTC/매약 매출): " . formatManwon($otc_sales) . "
- OTC Margin (OTC 마진율): {$otc_margin_disp}%
- Monthly Rx Count (월 처방전 건수): " . number_format($rx_count) . "건
- Monthly Rent (월 임대료): " . formatManwon($monthly_rent) . "
- Deposit (보증금): " . formatManwon($deposit) . "
- Store Size (면적): {$store_size}평
- Lease Remaining (잔여 임대차 기간): {$lease_remaining}
" . ($asking_num > 0 ? "- Asking Premium (매도 희망 권리금): " . formatManwon($asking_num) . "\n" : "");

    // 4. Gemini 데이터 페이로드 구성 (Schema 강제 적용)
    $postData = array(
        'systemInstruction' => array(
            'parts' => array(array('text' => $systemPrompt))
        ),
        'contents' => array(
            array(
                'role' => 'user',
                'parts' => array(array('text' => $userPrompt))
            )
        ),
        'generationConfig' => array(
            'temperature' => 0,
            'maxOutputTokens' => GEMINI_MAX_TOKENS, // 1회 응답 토큰 상한 (.env GEMINI_MAX_TOKENS)
            'responseMimeType' => 'application/json',
            'responseSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'location_analysis' => array('type' => 'string'),
                    'risk_assessment' => array('type' => 'string'),
                    'negotiation_strategy' => array('type' => 'string'),
                    'checklist' => array('type' => 'string'),
                    'funding_advice' => array('type' => 'string'),
                    'input_validation' => array('type' => 'string'),
                    'suggested_trait' => array('type' => 'string'),
                    'suggested_dependency' => array('type' => 'string')
                ),
                'required' => array(
                    'location_analysis',
                    'risk_assessment',
                    'negotiation_strategy',
                    'checklist',
                    'funding_advice',
                    'input_validation',
                    'suggested_trait',
                    'suggested_dependency'
                )
            )
        )
    );

    $jsonData = json_encode($postData, JSON_UNESCAPED_UNICODE);

    // 5. Gemini API 호출 — 과부하 시 백오프 재시도, 실패하면 예비 모델로 전환
    $result = null;
    $used_model = '';
    $last_error = '';
    $is_overload = false;

    foreach ($api_models as $model => $maxTries) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
        for ($try = 1; $try <= $maxTries; $try++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-goog-api-key: ' . $api_key
            ));
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, $model === 'gemini-flash-latest' ? 25 : 45); // 1차 모델 무응답 시 빠르게 예비 모델로

            // 로컬 개발용 (운영 배포 시 제거 권장)
            if (DEV_INSECURE_SSL) { // 로컬 개발 환경 한정 — 운영에서는 SSL 검증 수행
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_errno($ch) ? curl_error($ch) : '';
            if (PHP_VERSION_ID < 80000) { curl_close($ch); } // PHP 8.0+ 불필요(8.5부터 deprecated), 7.x 호환 유지

            if ($curlErr !== '') {
                $last_error = 'Curl error: ' . $curlErr;
                $is_overload = true;
            } else {
                $decoded = json_decode($response, true);
                if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                    $result = $decoded;
                    $used_model = $model;
                    break 2;
                }
                $apiErrMsg = isset($decoded['error']['message']) ? $decoded['error']['message'] : '';
                $is_overload = in_array($httpCode, array(429, 500, 502, 503), true)
                    || stripos($apiErrMsg, 'high demand') !== false
                    || stripos($apiErrMsg, 'overloaded') !== false
                    || stripos($apiErrMsg, 'try again') !== false
                    || stripos($apiErrMsg, 'RESOURCE_EXHAUSTED') !== false
                    || stripos($apiErrMsg, 'UNAVAILABLE') !== false;
                $last_error = $apiErrMsg !== ''
                    ? 'API Error: ' . $apiErrMsg
                    : '알 수 없는 응답 형식 (HTTP ' . $httpCode . ')';
                if (!$is_overload) {
                    break 2;
                }
            }
            if ($try < $maxTries) {
                sleep($try * 2);
            }
        }
    }

    // 토큰 사용량 기록 (대시보드 통계)
    if ($result !== null) {
        logApiUsage(basename(__FILE__), $used_model,
            isset($result['usageMetadata']) ? $result['usageMetadata'] : array());
    }

    $model_notice = ($used_model === 'gemini-flash-lite-latest')
        ? "<div class='fallback-note'>⚠ 기본 모델이 혼잡하여 예비 모델(gemini-flash-lite)로 분석된 결과입니다.</div>"
        : '';

    if ($result === null) {
        if ($is_overload) {
            $api_response = "<div class='retry-box'>⏳ AI 서버가 일시적으로 혼잡합니다. 자동 재시도(예비 모델 포함)까지 모두 실패했으니 잠시 후 다시 시도해주세요.<br><small>" . htmlspecialchars($last_error) . "</small></div>";
        } else {
            $api_response = "<p style='color:red;'>" . htmlspecialchars($last_error) . "</p>";
        }
    } elseif (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $raw_json = $result['candidates'][0]['content']['parts'][0]['text'];
        $parsed_json = json_decode($raw_json, true);

        if ($parsed_json) {
            $funding_advice = nl2br(htmlspecialchars(isset($parsed_json['funding_advice']) ? $parsed_json['funding_advice'] : '내용 없음'));
            $location_analysis = nl2br(htmlspecialchars(isset($parsed_json['location_analysis']) ? $parsed_json['location_analysis'] : '분석 내용 없음'));
            $risk_assessment = nl2br(htmlspecialchars(isset($parsed_json['risk_assessment']) ? $parsed_json['risk_assessment'] : '분석 내용 없음'));
            $strategy = nl2br(htmlspecialchars(isset($parsed_json['negotiation_strategy']) ? $parsed_json['negotiation_strategy'] : '제안 내용 없음'));
            $checklist = nl2br(htmlspecialchars(isset($parsed_json['checklist']) ? $parsed_json['checklist'] : '내용 없음'));

            // 조정 내역 행
            $adjRows = '';
            foreach ($adjustments as $adj) {
                $pct = round($adj['rate'] * 100);
                $sign = $pct > 0 ? '+' : '';
                $cls = $pct > 0 ? 'plus' : ($pct < 0 ? 'minus' : '');
                $adjRows .= "<li><span class='label'>" . htmlspecialchars($adj['label']) . "</span> <span class='value ref {$cls}'>{$sign}{$pct}%</span></li>";
            }

            // 공공데이터 실측 패널
            $realdata_html = '';
            if ($real_data !== null || $biz_data !== null) {
                $rows = '';
                if ($real_data !== null) {
                    $hp_ratio = $real_data['pharm_total'] > 0 ? round($real_data['hosp_total'] / $real_data['pharm_total'], 1) : 0;
                    $rows .= "<li><span class='label'>" . htmlspecialchars($sido . ' ' . $sigungu) . " 병·의원 / 약국</span><span class='value ref'>"
                        . number_format($real_data['hosp_total']) . "곳 / " . number_format($real_data['pharm_total']) . "곳 (비율 {$hp_ratio}:1)</span></li>";
                    if ($bname_in !== '') {
                        $rows .= "<li><span class='label'>'" . htmlspecialchars($bname_in) . "' 동 단위</span><span class='value ref'>병·의원 "
                            . number_format($real_data['hosp_dong']) . "곳 · 약국 " . number_format($real_data['pharm_dong']) . "곳</span></li>";
                    }
                }
                if ($biz_data !== null) {
                    $chips = array();
                    foreach (array_slice($biz_data['types'], 0, 4, true) as $tn => $tc) {
                        $chips[] = htmlspecialchars($tn) . ' ' . number_format($tc);
                    }
                    $rows .= "<li><span class='label'>반경 500m 상가</span><span class='value ref'>" . number_format($biz_data['total']) . "곳"
                        . (count($chips) > 0 ? " (" . implode(' · ', $chips) . ")" : "") . "</span></li>";
                }
                $realdata_html = "<div class='real-data-box'><strong>📡 공공데이터 실측 (입력 검증 근거)</strong><ul class='salary-list'>{$rows}</ul>"
                    . "<p class='src-note'>출처: 국립중앙의료원·소상공인시장진흥공단 (공공데이터포털)</p></div>";
            }

            // 입력 검증 — AI 제안값과 비교, 불일치 시 수정 반영 재계산
            $sug_trait = trim(isset($parsed_json['suggested_trait']) ? $parsed_json['suggested_trait'] : '');
            $sug_dep = trim(isset($parsed_json['suggested_dependency']) ? $parsed_json['suggested_dependency'] : '');
            $validation_txt = nl2br(htmlspecialchars(isset($parsed_json['input_validation']) ? $parsed_json['input_validation'] : ''));
            $trait_ok = ($sug_trait === '' || $sug_trait === $commercial_trait);
            $dep_ok = ($sug_dep === '' || $sug_dep === $hospital_dependency);

            if ($trait_ok && $dep_ok) {
                $validation_html = "<div class='validate-box good'><strong>✓ 입력 검증:</strong> 선택하신 상권 특성·병원 의존도가 "
                    . ($real_data !== null ? "실측 데이터와" : "지역 특성과") . " 부합합니다."
                    . ($validation_txt !== '' ? "<p style='margin:8px 0 0;'>{$validation_txt}</p>" : "") . "</div>";
            } else {
                $adj2 = computePremiumAdjustments(
                    $trait_ok ? $commercial_trait : $sug_trait,
                    $dep_ok ? $hospital_dependency : $sug_dep,
                    $lease_remaining,
                    $rent_ratio,
                    $doctor_age
                );
                $fair2 = (int) round($base_premium * (1 + $adj2[1]));
                $low2 = (int) round($fair2 * (1 - PREMIUM_RANGE));
                $high2 = (int) round($fair2 * (1 + PREMIUM_RANGE));
                $chg = '';
                if (!$trait_ok) {
                    $chg .= "<li>상권 특성: <s>" . htmlspecialchars($commercial_trait) . "</s><br>→ <strong>" . htmlspecialchars($sug_trait) . "</strong></li>";
                }
                if (!$dep_ok) {
                    $chg .= "<li>병원 의존도: <s>" . htmlspecialchars($hospital_dependency) . "</s><br>→ <strong>" . htmlspecialchars($sug_dep) . "</strong></li>";
                }
                $validation_html = "<div class='validate-box alt'><strong>⚠ 입력 검증 — 실제 입지와 다른 항목이 있습니다</strong>"
                    . ($validation_txt !== '' ? "<p style='margin:8px 0 0;'>{$validation_txt}</p>" : "")
                    . "<ul class='validate-changes'>{$chg}</ul>"
                    . "<div class='validate-corrected'>수정 반영 시: 조정 합계 " . ($total_rate >= 0 ? '+' : '') . round($total_rate * 100) . "% → <strong>"
                    . ($adj2[1] >= 0 ? '+' : '') . round($adj2[1] * 100) . "%</strong> · 적정 권리금 기준가 " . formatManwon($fair_premium)
                    . " → <strong>" . formatManwon($fair2) . "</strong> (범위 " . formatManwon($low2) . " ~ " . formatManwon($high2) . ")</div>"
                    . "</div>";
            }

            // 개국 필요자금 계획서 섹션 — 조달 자금은 자기자본 + 대출 2종류, 총 조달은 계산치
            $shortRow = '';
            if ($shortfall > 0) {
                $shortRow = "<li><span class='label'>필요자금 대비</span> <span class='value ref minus'>⚠ " . formatManwon($shortfall) . " 부족</span></li>";
            } elseif ($shortfall < 0) {
                $shortRow = "<li><span class='label'>필요자금 대비</span> <span class='value ref plus'>" . formatManwon(-$shortfall) . " 여유</span></li>";
            } else {
                $shortRow = "<li><span class='label'>필요자금 대비</span> <span class='value ref plus'>정확히 일치</span></li>";
            }

            $loanRows = '';
            if ($loan_amount > 0) {
                $ratioWarn = $payment_ratio > 40
                    ? " <span class='minus'>⚠ 위험 수준</span>"
                    : ($payment_ratio > 25 ? " (주의)" : " (양호)");
                $loanRows = "
                    <li><span class='label'>대출 조건</span> <span class='value ref'>연 {$loan_rate}% · {$loan_years}년 원리금균등</span></li>
                    <li><span class='label'>월 상환액 (월 매출총이익의 {$payment_ratio}%{$ratioWarn})</span> <span class='value ref'>" . formatManwon($monthly_payment) . "</span></li>
                    <li><span class='label'>총 이자 비용 ({$loan_years}년 누적)</span> <span class='value ref minus'>" . formatManwon($total_interest) . "</span></li>";
            }

            $funding_html = "
                <div class='funding-section'>
                    <div class='funding-title'>🏦 개국 필요자금 계획서</div>
                    <ul class='salary-list'>
                        <li><span class='label'>권리금 (기준가)</span> <span class='value ref'>" . formatManwon($fair_premium) . "</span></li>
                        <li><span class='label'>임대 보증금</span> <span class='value ref'>" . formatManwon($deposit) . "</span></li>
                        <li><span class='label'>인테리어·시설비 (입력)</span> <span class='value ref'>" . formatManwon($interior_cost) . "</span></li>
                        <li><span class='label'>초기 의약품 재고 (월 매출원가 × " . INVENTORY_MONTHS . "개월)</span> <span class='value ref'>" . formatManwon($inventory_cost) . "</span></li>
                        <li><span class='label'>예비 운전자금 (임대료 " . RESERVE_RENT_MONTHS . "개월분)</span> <span class='value ref'>" . formatManwon($reserve_cost) . "</span></li>
                        <li class='funding-total'><span class='label'>총 필요자금</span> <span class='value strong-green'>" . formatManwon($total_capital_needed) . "</span></li>
                    </ul>
                    <ul class='salary-list funding-loan'>
                        <li><span class='label'>① 자기자본 (총 조달의 {$equity_ratio}%)</span> <span class='value ref plus'>" . formatManwon($own_capital) . "</span></li>
                        <li><span class='label'>② 대출 금액</span> <span class='value ref'>" . formatManwon($loan_amount) . "</span></li>
                        <li class='funding-total'><span class='label'>총 조달 자금 (① + ②, 자동 계산)</span> <span class='value strong-green'>" . formatManwon($total_funding) . "</span></li>
                        {$shortRow}
                        {$loanRows}
                    </ul>
                </div>";

            $askingRow = '';
            if ($asking_num > 0) {
                $isOver = strpos($asking_eval, '상회') !== false;
                $askingRow = "<li><span class='label'>매도 희망 권리금 (" . formatManwon($asking_num) . ") 평가</span> <span class='value ref " . ($isOver ? 'minus' : 'plus') . "'>" . htmlspecialchars($asking_eval) . "</span></li>";
            }

            $api_response = $model_notice . "
            <div class='report-card'>
                <div class='report-header'>약국 권리금 산정 리포트 ({$current_year}년 기준)</div>

                <div class='negotiation-focus'>
                    <span class='focus-title'>💰 적정 권리금 범위</span>
                    <div class='focus-amount'>" . formatManwon($premium_low) . " ~ " . formatManwon($premium_high) . "</div>
                    <div class='focus-sub'>기준가 " . formatManwon($fair_premium) . " · 투자 회수 약 " . number_format($payback_months) . "개월</div>
                </div>

                <ul class='salary-list'>
                    <li><span class='label'>월 매출총이익 추정 (조제 " . (MARGIN_DISPENSE * 100) . "% + OTC {$otc_margin_disp}%)</span> <span class='value ref'>" . formatManwon($gross_profit) . "</span></li>
                    <li><span class='label'>기준 권리금 (월 매출총이익 × " . BASE_MONTHS . "개월)</span> <span class='value ref'>" . formatManwon($base_premium) . "</span></li>
                    {$adjRows}
                    <li><span class='label'>조정 합계</span> <span class='value ref'>" . ($total_rate >= 0 ? '+' : '') . round($total_rate * 100) . "%</span></li>
                    {$askingRow}
                </ul>

                {$realdata_html}
                {$validation_html}

                {$funding_html}

                <div class='funding-advice-box'>
                    <strong>💳 자금 조달 조언 (AI)</strong>
                    <p style='margin-top:10px;'>{$funding_advice}</p>
                </div>

                <div class='analysis-box'>
                    <span class='label'>🏢 상권 분석:</span>
                    <p style='margin-top:10px;'>{$location_analysis}</p>
                </div>

                <div class='risk-box'>
                    <strong>⚠ 리스크 평가</strong>
                    <p style='margin-top:10px;'>{$risk_assessment}</p>
                </div>

                <div class='checklist-box'>
                    <strong>📋 계약 전 실사 체크리스트</strong>
                    <p style='margin-top:10px;'>{$checklist}</p>
                </div>

                <div class='insight-box'>
                    <strong>[협상 전략]</strong><br>
                    {$strategy}
                </div>
            </div>";
        } else {
            $api_response = "<p style='color:red;'>JSON 파싱 오류. 원본 출력:</p><br>" . nl2br(htmlspecialchars($raw_json));
        }
    } else {
        $api_response = "<p style='color:red;'>알 수 없는 응답 형식입니다. 잠시 후 다시 시도해주세요.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>약국 권리금 계산기 (곡선형 비교)</title>
    <script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
    <style>
        body { font-family: 'Malgun Gothic', sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; background-color: #f8fafc; }
        .search-box { margin-bottom: 20px; padding: 25px; border: 1px solid #ddd; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 15px; display: flex; align-items: center; }
        label { display: inline-block; width: 160px; font-weight: bold; color: #333; }
        input[type="text"], input[type="number"], select { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { padding: 12px 20px; cursor: pointer; background-color: #0f9d58; color: white; border: none; border-radius: 4px; font-weight: bold; width: 100%; font-size: 16px; margin-top: 10px;}
        button:hover { background-color: #0b8043; }
        .btn-postcode { background-color: #6c757d; margin-left: 10px; width: auto; font-size: 14px; margin-top:0;}
        .unit { margin-left: 8px; color: #80868b; font-size: 0.9em; flex: 0 0 auto; }
        .funding-zone { background: #f0faf4; border: 1px solid #ceead6; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
        .funding-zone-title { font-weight: bold; color: #0b8043; font-size: 0.95em; margin-bottom: 12px; }
        .funding-sum { background: #fff; border: 1px solid #ceead6; border-left: 4px solid #0f9d58; border-radius: 6px;
                       padding: 10px 14px; margin-bottom: 15px; font-size: 0.95em; color: #1e4620; }
        .funding-sum strong { color: #0b8043; font-size: 1.15em; }
        .funding-sum-detail { font-size: 0.85em; color: #5f6368; }

        .btn-avg { width: auto; flex: 0 0 auto; margin: 0 0 0 8px; padding: 8px 10px; font-size: 0.8em; background: #6c757d; }
        .btn-avg:hover { background: #5a6268; }
        #avg-modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); z-index: 1000;
                     align-items: center; justify-content: center; }
        .avg-box { background: #fff; border-radius: 12px; padding: 24px; width: 480px; max-width: 92vw;
                   max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .avg-title { font-weight: bold; color: #0b8043; font-size: 1.05em; margin-bottom: 8px; }
        .avg-desc { font-size: 0.82em; color: #80868b; margin: 0 0 14px; line-height: 1.5; }
        .avg-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .avg-cell { display: flex; flex-direction: column; }
        .avg-label { font-size: 0.75em; color: #5f6368; margin-bottom: 3px; }
        .avg-month-input { width: 100%; padding: 7px 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 0.9em; }
        .avg-summary { margin-top: 14px; padding: 10px 12px; background: #f0faf4; border: 1px solid #ceead6;
                       border-radius: 6px; font-size: 0.9em; color: #1e4620; }
        .avg-summary strong { color: #0b8043; font-size: 1.15em; }
        .avg-actions { display: flex; gap: 8px; margin-top: 14px; }
        .btn-avg-apply { flex: 2; margin: 0; padding: 10px; font-size: 0.95em; }
        .btn-avg-close { flex: 1; margin: 0; padding: 10px; font-size: 0.95em; background: #6c757d; }
        .btn-avg-close:hover { background: #5a6268; }
        .nav-links.side-nav { position: fixed; left: 0; top: 0; bottom: 0; width: 200px; background: #1e293b; padding: 16px 12px; overflow-y: auto; z-index: 100; margin: 0; }
        .side-nav .sn-home { display: block; color: #fff; font-weight: bold; text-decoration: none; font-size: 0.9em; padding: 9px 10px; border-radius: 8px; background: rgba(255,255,255,0.1); margin-bottom: 6px; }
        .side-nav .sn-home:hover { background: rgba(255,255,255,0.2); }
        .side-nav .sn-cat { font-size: 0.7em; color: #94a3b8; font-weight: bold; margin: 14px 0 4px; padding: 0 10px; }
        .side-nav a { display: block; color: #cbd5e1; font-size: 0.84em; padding: 7px 10px; border-radius: 7px; text-decoration: none; margin-bottom: 1px; }
        .side-nav a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .side-nav .sn-on { display: block; font-size: 0.84em; padding: 7px 10px; border-radius: 7px; margin-bottom: 1px; background: #0f9d58; color: #fff; font-weight: bold; }
        @media (min-width: 1120px) { body { margin-left: max(236px, calc(50% - 290px)); margin-right: auto; } }
        @media (max-width: 1119px) {
            .nav-links.side-nav { position: static; width: auto; border-radius: 12px; margin-bottom: 18px; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0 14px; align-items: start; }
            .side-nav .sn-home { grid-column: 1 / -1; }
        }

        .result-box { margin-top: 30px; }
        .report-card { background: #fff; border: 1px solid #0f9d58; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .report-header { background: #0f9d58; color: #fff; padding: 15px 20px; font-size: 1.2em; font-weight: bold; text-align: center;}

        .analysis-box { background-color: #f1f3f4; padding: 20px; border-top: 1px solid #e8eaed; color: #3c4043; line-height: 1.6;}
        .analysis-box p { margin: 5px 0 0; font-size: 0.95em; }

        .negotiation-focus { background-color: #e6f4ea; padding: 30px 20px; text-align: center; border-bottom: 1px solid #ceead6; }
        .focus-title { display: block; font-size: 1.1em; font-weight: bold; color: #0b8043; margin-bottom: 10px; }
        .focus-amount { font-size: 1.8em; font-weight: 900; color: #0f9d58; }
        .focus-sub { margin-top: 8px; color: #5f6368; font-size: 0.9em; }

        .salary-list { list-style: none; padding: 0; margin: 0; background-color: #fff; }
        .salary-list li { padding: 12px 20px; border-bottom: 1px solid #f1f3f4; display: flex; justify-content: space-between; align-items: center; }
        .label { font-weight: bold; color: #5f6368; font-size: 0.95em; }
        .value.ref { color: #80868b; font-size: 1em; }
        .value.ref.plus { color: #0f9d58; font-weight: bold; }
        .value.ref.minus { color: #d93025; font-weight: bold; }

        .risk-box { background: #fef7e0; padding: 20px; border-top: 1px solid #feefc3; color: #7c5e10; line-height: 1.6; font-size: 0.95em; }
        .funding-section { border-top: 2px solid #0f9d58; }
        .funding-title { background: #e6f4ea; color: #0b8043; font-weight: bold; padding: 12px 20px; font-size: 1.05em; }
        .funding-loan { border-top: 1px dashed #ceead6; }
        .funding-total { background: #f0faf4; }
        .value.strong-green { color: #0b8043; font-weight: 900; font-size: 1.1em; }
        .minus { color: #d93025; font-weight: bold; }
        .funding-advice-box { background: #f0faf4; padding: 20px; border-top: 1px solid #ceead6; color: #1e4620; line-height: 1.6; font-size: 0.95em; }
        .checklist-box { background: #e8f0fe; padding: 20px; border-top: 1px solid #d2e3fc; color: #174ea6; line-height: 1.6; font-size: 0.95em; }
        .insight-box { background: #fff; padding: 25px 20px; color: #3c4043; line-height: 1.6; border-top: 1px solid #e8eaed; }

        .retry-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; color: #92400e; line-height: 1.6; text-align: center; }
        .retry-box small { color: #b45309; font-size: 0.8em; }
        .fallback-note { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 10px 14px; color: #92400e; font-size: 0.85em; margin-bottom: 12px; }

        .real-data-box { background: #f0f9ff; border-top: 1px solid #bae6fd; padding: 16px 20px; }
        .real-data-box strong { color: #075985; }
        .real-data-box .salary-list li { padding: 8px 0 8px 0; border-bottom: 1px solid #e0f2fe; }
        .real-data-box .label { color: #0369a1; font-size: 0.9em; }
        .src-note { margin: 8px 0 0; font-size: 0.78em; color: #64748b; }
        .validate-box { padding: 16px 20px; font-size: 0.92em; line-height: 1.6; }
        .validate-box.good { background: #f0fdf4; border-top: 1px solid #bbf7d0; color: #15803d; }
        .validate-box.alt { background: #fef3c7; border-top: 1px solid #fcd34d; color: #92400e; }
        .validate-changes { margin: 10px 0 0; padding-left: 18px; }
        .validate-changes li { margin-bottom: 6px; }
        .validate-changes s { color: #b45309; opacity: 0.7; }
        .validate-corrected { margin-top: 12px; padding: 10px 12px; background: #fff; border: 1px dashed #fcd34d; border-radius: 6px; }

        #loading-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); z-index: 1000;
                           align-items: center; justify-content: center; }
        .loading-box { background: #fff; border-radius: 12px; padding: 30px 40px; text-align: center;
                       box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .loading-box p { margin: 16px 0 0; color: #3c4043; font-weight: bold; line-height: 1.6; }
        .loading-box small { display: block; margin-top: 6px; color: #80868b; font-weight: normal; font-size: 0.82em; }
        .spinner { width: 44px; height: 44px; margin: 0 auto; border: 4px solid #e6f4ea; border-top-color: #0f9d58;
                   border-radius: 50%; animation: spin 0.9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
    <link rel="stylesheet" href="theme.css?v=2">
    <style>:root { --accent: #0f9d58; --ring: rgba(15, 157, 88, 0.14); }</style>
</head>
<body>

<div class="search-box">
    <?php require_once __DIR__ . '/nav.php'; renderSideNav(); ?>
    <div style="background:#ecfdf5; border:1px solid #6ee7b7; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:0.85em; color:#065f46; line-height:1.6;">
        🧪 <strong>비교용 페이지 — 임대료 부담 조정을 곡선형(구간 선형보간)으로 계산합니다.</strong>
        원본은 <a href="premium.php" style="color:#047857; font-weight:bold;">권리금 계산(계단형)</a>에서 그대로 유지됩니다.
    </div>
    <h2 style="margin-top:0; color:#0b8043;">약국 권리금 계산기 <span style="font-size:0.55em; color:#0d9488; font-weight:bold; vertical-align:middle;">곡선형</span></h2>
    <p style="color:#5f6368; font-size:0.95em; margin-bottom:20px;">매출·상권·임대차 조건을 기반으로 적정 권리금 범위를 산출하고, AI가 상권·리스크 분석을 더해드립니다.</p>

    <form method="POST" action="" onsubmit="return validatePremiumForm()">
        <div class="form-group">
            <label for="full_address">약국 상세 주소</label>
            <input type="text" id="full_address" name="full_address" value="<?= htmlspecialchars($full_address) ?>" placeholder="주소 검색 버튼 클릭" readonly required>
            <button type="button" class="btn-postcode" onclick="execDaumPostcode()">카카오 주소 검색</button>
        </div>
        <input type="hidden" id="sido" name="sido" value="<?= htmlspecialchars($sido_in) ?>">
        <input type="hidden" id="sigungu" name="sigungu" value="<?= htmlspecialchars($sigungu_in) ?>">
        <input type="hidden" id="bname" name="bname" value="<?= htmlspecialchars($bname_in) ?>">

        <div class="form-group">
            <label for="dispense_sales">월 조제 매출</label>
            <input type="number" id="dispense_sales" name="dispense_sales" min="0" value="<?= htmlspecialchars($dispense_sales) ?>" required>
            <span class="unit">만원</span>
            <button type="button" class="btn-avg" onclick="openAvgModal('dispense_sales', '월 조제 매출')">📊 평균 계산</button>
        </div>
        <div class="form-group">
            <label for="otc_sales">월 OTC(매약) 매출</label>
            <input type="number" id="otc_sales" name="otc_sales" min="0" value="<?= htmlspecialchars($otc_sales) ?>" required>
            <span class="unit">만원</span>
            <button type="button" class="btn-avg" onclick="openAvgModal('otc_sales', '월 OTC(매약) 매출')">📊 평균 계산</button>
        </div>
        <div class="form-group">
            <label for="monthly_rent">월 임대료</label>
            <input type="number" id="monthly_rent" name="monthly_rent" min="0" value="<?= htmlspecialchars($monthly_rent) ?>" required>
            <span class="unit">만원</span>
        </div>
        <div class="form-group">
            <label for="asking_premium">매도 희망 권리금</label>
            <input type="number" id="asking_premium" name="asking_premium" min="0" value="<?= htmlspecialchars($asking_premium) ?>" placeholder="선택 입력 — 적정성 평가에 사용">
            <span class="unit">만원</span>
        </div>

        <p class="prefill-note">💡 아래 <strong>상세 조건</strong>에는 표준값이 미리 채워져 있어 그대로 계산해도 됩니다. 상권 특성·병원 의존도는 입력한 주소를 근거로 AI가 자동 검증·보정합니다.</p>

        <details class="adv">
            <summary>상세 조건 <span class="adv-hint">상권 · 병원 · 임대차 — 아는 값만 수정하세요</span></summary>
            <div class="adv-body">
        <div class="form-group">
            <label for="commercial_trait">상권 특성</label>
            <select id="commercial_trait" name="commercial_trait" required>
                <option value="대형병원 문전/클리닉 밀집 (처방 다발, 업무강도 높음)" <?= ($commercial_trait == '대형병원 문전/클리닉 밀집 (처방 다발, 업무강도 높음)') ? 'selected' : '' ?>>대형병원 문전/클리닉 (처방 다발) — 조정 +20%</option>
                <option value="요양·실버 상권 (요양병원 인근, 장기 처방 수요)" <?= ($commercial_trait == '요양·실버 상권 (요양병원 인근, 장기 처방 수요)') ? 'selected' : '' ?>>요양·실버 상권 (장기 처방 수요) — 조정 +15%</option>
                <option value="대단지 아파트 상가 (입주민 고정 수요)" <?= ($commercial_trait == '대단지 아파트 상가 (입주민 고정 수요)') ? 'selected' : '' ?>>대단지 아파트 상가 (고정 수요) — 조정 +10%</option>
                <option value="지하철역/번화가 (유동인구 다수, 매약 중심, 업무강도 높음)" <?= ($commercial_trait == '지하철역/번화가 (유동인구 다수, 매약 중심, 업무강도 높음)') ? 'selected' : '' ?>>지하철역/번화가 (매약 중심) — 조정 +10%</option>
                <option value="오피스 상권 (직장인 대상, 평일 주간 집중)" <?= ($commercial_trait == '오피스 상권 (직장인 대상, 평일 주간 집중)') ? 'selected' : '' ?>>오피스 상권 (평일 집중, 주말 공백) — 조정 +5%</option>
                <option value="쇼핑몰·대형마트 입점 (특수상권, 주말 가족 고객)" <?= ($commercial_trait == '쇼핑몰·대형마트 입점 (특수상권, 주말 가족 고객)') ? 'selected' : '' ?>>쇼핑몰·대형마트 입점 (특수상권) — 조정 +5%</option>
                <option value="동네/주택가 (처방 및 매약 보통, 업무강도 보통)" <?= ($commercial_trait == '동네/주택가 (처방 및 매약 보통, 업무강도 보통)') ? 'selected' : '' ?>>동네/주택가 — 기준 (0%)</option>
                <option value="학원가·대학가 (젊은 층 매약 중심, 방학 비수기)" <?= ($commercial_trait == '학원가·대학가 (젊은 층 매약 중심, 방학 비수기)') ? 'selected' : '' ?>>학원가·대학가 (방학 비수기) — 조정 0%</option>
                <option value="한적한 외곽 (방문객 적음, 업무강도 낮음)" <?= ($commercial_trait == '한적한 외곽 (방문객 적음, 업무강도 낮음)') ? 'selected' : '' ?>>한적한 외곽 — 조정 -20%</option>
            </select>
        </div>

        <div class="form-group">
            <label for="hospital_dependency">병원 의존도</label>
            <select id="hospital_dependency" name="hospital_dependency" required>
                <option value="복수 병원 인근 (처방 분산, 안정적)" <?= ($hospital_dependency == '복수 병원 인근 (처방 분산, 안정적)') ? 'selected' : '' ?>>복수 병원 인근 (처방 분산, 안정적)</option>
                <option value="단일 병원 문전 (해당 병원 이전·폐업 리스크)" <?= ($hospital_dependency == '단일 병원 문전 (해당 병원 이전·폐업 리스크)') ? 'selected' : '' ?>>단일 병원 문전 (이전·폐업 리스크)</option>
                <option value="병원 무관 (매약 중심 상권)" <?= ($hospital_dependency == '병원 무관 (매약 중심 상권)') ? 'selected' : '' ?>>병원 무관 (매약 중심 상권)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="doctor_age">메인 병원 의사 고령 여부</label>
            <select id="doctor_age" name="doctor_age">
                <option value="none" <?= ($doctor_age === 'none') ? 'selected' : '' ?>>해당 없음 (60대 중반 미만)</option>
                <option value="over65" <?= ($doctor_age === 'over65') ? 'selected' : '' ?>>65세 이상 의사 있음</option>
                <option value="over75" <?= ($doctor_age === 'over75') ? 'selected' : '' ?>>75세 이상 의사 있음</option>
            </select>
        </div>

        <div class="form-group">
            <label for="otc_margin">OTC 마진율</label>
            <input type="number" id="otc_margin" name="otc_margin" min="0" max="100" step="0.5" value="<?= htmlspecialchars($otc_margin_pct) ?>" required>
            <span class="unit">% (일반의약품·건기식 평균 마진, 기본 30%)</span>
        </div>
        <div class="form-group">
            <label for="rx_count">월 평균 처방전 건수</label>
            <input type="number" id="rx_count" name="rx_count" min="0" value="<?= htmlspecialchars($rx_count) ?>" required>
            <span class="unit">건</span>
        </div>
        <div class="form-group">
            <label for="deposit">보증금</label>
            <input type="number" id="deposit" name="deposit" min="0" value="<?= htmlspecialchars($deposit) ?>" required>
            <span class="unit">만원</span>
        </div>
        <div class="form-group">
            <label for="store_size">약국 면적</label>
            <input type="number" id="store_size" name="store_size" min="1" value="<?= htmlspecialchars($store_size) ?>" required>
            <span class="unit">평</span>
        </div>
        <div class="form-group">
            <label for="lease_remaining">잔여 임대차 기간</label>
            <select id="lease_remaining" name="lease_remaining" required>
                <option value="3년 이상" <?= ($lease_remaining == '3년 이상') ? 'selected' : '' ?>>3년 이상</option>
                <option value="1~3년" <?= ($lease_remaining == '1~3년') ? 'selected' : '' ?>>1~3년</option>
                <option value="1년 미만" <?= ($lease_remaining == '1년 미만') ? 'selected' : '' ?>>1년 미만</option>
                <option value="재계약 협의 필요" <?= ($lease_remaining == '재계약 협의 필요') ? 'selected' : '' ?>>재계약 협의 필요</option>
            </select>
        </div>
            </div>
        </details>

        <details class="adv">
            <summary>🏦 개국 자금 계획 <span class="adv-hint">필요자금·대출 상환 분석 — 선택 입력</span></summary>
            <div class="adv-body">
            <div class="form-group">
                <label for="own_capital">① 자기자본</label>
                <input type="number" id="own_capital" name="own_capital" min="0" value="<?= htmlspecialchars($own_capital) ?>" required oninput="updateFundingTotal()">
                <span class="unit">만원</span>
            </div>
            <div class="form-group">
                <label for="loan_amount">② 대출 금액</label>
                <input type="number" id="loan_amount" name="loan_amount" min="0" value="<?= htmlspecialchars($loan_amount) ?>" required oninput="updateFundingTotal()">
                <span class="unit">만원</span>
            </div>
            <div class="funding-sum" id="funding-sum"></div>
            <div class="form-group">
                <label for="interior_cost">인테리어·시설비</label>
                <input type="number" id="interior_cost" name="interior_cost" min="0" value="<?= htmlspecialchars($interior_cost) ?>" required>
                <span class="unit">만원</span>
            </div>
            <div class="form-group">
                <label for="loan_rate">대출 예상 금리</label>
                <input type="number" id="loan_rate" name="loan_rate" min="0" max="20" step="0.1" value="<?= htmlspecialchars($loan_rate) ?>" required>
                <span class="unit">% (연)</span>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="loan_years">대출 상환 기간</label>
                <select id="loan_years" name="loan_years" required>
                    <?php foreach (array(3, 5, 7, 10) as $ly): ?>
                    <option value="<?= $ly ?>" <?= ($loan_years === $ly) ? 'selected' : '' ?>><?= $ly ?>년 (원리금균등)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            </div>
        </details>

        <button type="submit">권리금 산정하기</button>
    </form>
</div>

<?php if (!empty($api_response)): ?>
    <div class="result-box">
        <?= $api_response ?>
    </div>
<?php endif; ?>

<?php if (!empty($api_response) && strpos($api_response, 'report-card') !== false) {
    echo renderFeedbackWidget('premium_curve', isset($sido) ? $sido : '', isset($sigungu) ? $sigungu : '', '적정 권리금', '만원'); // 원본(premium) 통계와 섞이지 않도록 별도 tool 키 사용
} ?>

<div id="loading-overlay">
    <div class="loading-box">
        <div class="spinner"></div>
        <p>AI가 상권과 권리금을 분석하고 있습니다...
            <small>상권 분석과 리스크 평가에 10~20초 정도 걸릴 수 있어요</small>
        </p>
    </div>
</div>

<div id="avg-modal" onclick="if (event.target === this) closeAvgModal()">
    <div class="avg-box">
        <div class="avg-title" id="avg-modal-title">월 매출 — 최근 1년 월별 입력</div>
        <p class="avg-desc">최근 1년간 월별 매출을 입력하세요 (만원 단위). 빈 칸은 제외하고 입력된 달만으로 평균을 계산합니다.</p>
        <div class="avg-grid">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <div class="avg-cell">
                <span class="avg-label"><?= $m ?>개월 전</span>
                <input type="number" class="avg-month-input" min="0" placeholder="만원" oninput="updateAvgSummary()">
            </div>
            <?php endfor; ?>
        </div>
        <div class="avg-summary" id="avg-summary">입력 0개월 · 합계 0만원 · 월평균 <strong>0만원</strong></div>
        <div class="avg-actions">
            <button type="button" class="btn-avg-apply" onclick="applyAvg()">✔ 평균값 적용</button>
            <button type="button" class="btn-avg-close" onclick="closeAvgModal()">닫기</button>
        </div>
    </div>
</div>

<script>
    var HAS_RESULT = <?= !empty($api_response) ? 'true' : 'false' ?>;

    // ===== 입력값 자동 저장/복원 (localStorage) — 새로고침·페이지 이동 후에도 유지 =====
    (function() {
        var FORM_KEY = 'premium_form_v1';
        var form = document.querySelector('form');
        if (!form) return;

        function collect() {
            var data = {};
            Array.prototype.forEach.call(form.elements, function(el) {
                if (!el.name) return;
                if (el.type === 'checkbox') data[el.name] = el.checked;
                else data[el.name] = el.value;
            });
            return data;
        }

        window.__saveForm = function() {
            try { localStorage.setItem(FORM_KEY, JSON.stringify(collect())); } catch (e) {}
        };

        window.__restoreForm = function() {
            var raw;
            try { raw = localStorage.getItem(FORM_KEY); } catch (e) { return; }
            if (!raw) return;
            var data;
            try { data = JSON.parse(raw); } catch (e) { return; }
            Array.prototype.forEach.call(form.elements, function(el) {
                if (!el.name || !(el.name in data)) return;
                if (el.type === 'checkbox') el.checked = !!data[el.name];
                else el.value = data[el.name];
            });
        };

        form.addEventListener('input', window.__saveForm);
        form.addEventListener('change', window.__saveForm);

        // 접힌 상세 조건 안의 필수값이 비어 있으면 해당 섹션을 자동으로 펼쳐 브라우저 검증 말풍선이 보이게 함
        form.addEventListener('invalid', function(e) {
            var d = e.target.closest ? e.target.closest('details') : null;
            if (d) d.open = true;
        }, true);
    })();

    document.addEventListener('DOMContentLoaded', function() {
        // 결과 페이지(POST)는 서버가 값을 채워주므로 그대로 두고, 새로 열었을 때(GET)만 복원
        if (!HAS_RESULT) __restoreForm();
        __saveForm();
        updateFundingTotal();
    });

    // ===== 총 조달 자금 실시간 계산 (자기자본 + 대출) =====
    function formatManwonJs(mw) {
        mw = Math.round(mw);
        if (mw >= 10000) {
            var eok = Math.floor(mw / 10000);
            var rest = mw % 10000;
            return rest > 0 ? eok + '억 ' + rest.toLocaleString('ko-KR') + '만원' : eok + '억원';
        }
        return mw.toLocaleString('ko-KR') + '만원';
    }

    function updateFundingTotal() {
        var own = parseInt(document.getElementById('own_capital').value || '0', 10);
        var loan = parseInt(document.getElementById('loan_amount').value || '0', 10);
        document.getElementById('funding-sum').innerHTML =
            '💵 총 조달 자금: <strong>' + formatManwonJs(own + loan) + '</strong>'
            + ' <span class="funding-sum-detail">(자기자본 ' + formatManwonJs(own) + ' + 대출 ' + formatManwonJs(loan) + ')</span>';
    }

    function validatePremiumForm() {
        var dispense = parseInt(document.getElementById('dispense_sales').value || '0', 10);
        var otc = parseInt(document.getElementById('otc_sales').value || '0', 10);
        if (dispense + otc <= 0) {
            alert('조제 매출 또는 매약 매출 중 하나는 0보다 커야 합니다.');
            return false;
        }
        document.getElementById('loading-overlay').style.display = 'flex';
        return true;
    }

    window.addEventListener('pageshow', function() {
        document.getElementById('loading-overlay').style.display = 'none';
    });

    // ===== 최근 1년 월 매출 평균 계산 모달 =====
    // 월별 입력값은 대상 필드별(조제/OTC)로 저장해뒀다가 다시 열면 복원 — 재계산 시 다시 입력할 필요 없음
    var avgTargetId = null;
    var avgStore = {};
    try { avgStore = JSON.parse(localStorage.getItem('premium_avg_v1') || '{}'); } catch (e) { avgStore = {}; }

    function openAvgModal(targetId, title) {
        avgTargetId = targetId;
        document.getElementById('avg-modal-title').textContent = title + ' — 최근 1년 월별 입력';
        var saved = avgStore[targetId] || [];
        document.querySelectorAll('.avg-month-input').forEach(function(i, idx) {
            i.value = (saved[idx] !== undefined && saved[idx] !== null) ? saved[idx] : '';
        });
        updateAvgSummary();
        document.getElementById('avg-modal').style.display = 'flex';
    }

    function closeAvgModal() {
        document.getElementById('avg-modal').style.display = 'none';
    }

    function persistAvgInputs() {
        if (!avgTargetId) return;
        var vals = [];
        document.querySelectorAll('.avg-month-input').forEach(function(i) {
            vals.push(i.value === '' ? null : parseFloat(i.value));
        });
        avgStore[avgTargetId] = vals;
        try { localStorage.setItem('premium_avg_v1', JSON.stringify(avgStore)); } catch (e) {}
    }

    function collectAvg() {
        var count = 0, sum = 0;
        document.querySelectorAll('.avg-month-input').forEach(function(i) {
            if (i.value !== '' && !isNaN(parseFloat(i.value))) {
                count++;
                sum += parseFloat(i.value);
            }
        });
        return { count: count, sum: sum, avg: count > 0 ? Math.round(sum / count) : 0 };
    }

    function updateAvgSummary() {
        var r = collectAvg();
        document.getElementById('avg-summary').innerHTML =
            '입력 ' + r.count + '개월 · 합계 ' + r.sum.toLocaleString('ko-KR') + '만원 · 월평균 <strong>'
            + r.avg.toLocaleString('ko-KR') + '만원</strong>';
        persistAvgInputs(); // 입력할 때마다 대상 필드별로 저장
    }

    function applyAvg() {
        var r = collectAvg();
        if (r.count === 0) {
            alert('최소 1개월 이상의 매출을 입력해주세요.');
            return;
        }
        if (avgTargetId) {
            document.getElementById(avgTargetId).value = r.avg;
            if (window.__saveForm) window.__saveForm(); // 프로그램적 값 변경도 저장
        }
        closeAvgModal();
    }

    function execDaumPostcode() {
        new daum.Postcode({
            oncomplete: function(data) {
                var addr = '';
                var extraAddr = '';

                if (data.userSelectedType === 'R') {
                    addr = data.roadAddress;
                } else {
                    addr = data.jibunAddress;
                }

                if(data.userSelectedType === 'R'){
                    if(data.bname !== '' && /[동|로|가]$/g.test(data.bname)){
                        extraAddr += data.bname;
                    }
                    if(data.buildingName !== '' && data.apartment === 'Y'){
                        extraAddr += (extraAddr !== '' ? ', ' + data.buildingName : data.buildingName);
                    }
                    if(extraAddr !== ''){
                        extraAddr = ' (' + extraAddr + ')';
                    }
                }

                document.getElementById('full_address').value = addr + extraAddr;
                document.getElementById('sido').value = data.sido || '';
                document.getElementById('sigungu').value = data.sigungu || '';
                document.getElementById('bname').value = data.bname || '';
                if (window.__saveForm) window.__saveForm();
            }
        }).open();
    }
</script>
</body>
</html>
