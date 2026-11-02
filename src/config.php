<?php
// 외부 API(공공데이터·카카오·Gemini) 연쇄 호출 + 재시도가 기본 제한(30초)을 넘을 수 있어 연장
set_time_limit(300);

/**
 * 환경설정 값 로드 — 시스템 환경변수 우선, 없으면 같은 폴더의 .env 파일(키=값 형식)에서 읽음
 * .env 파일은 저장소에 올리지 마세요 (.gitignore 처리됨)
 */
function envValue($key, $default = '')
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    static $fileEnv = null;
    if ($fileEnv === null) {
        $fileEnv = array();
        $path = __DIR__ . '/.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                $pair = explode('=', $line, 2);
                $fileEnv[trim($pair[0])] = trim($pair[1]);
            }
        }
    }
    return isset($fileEnv[$key]) ? $fileEnv[$key] : $default;
}

// 대시보드 접근 비밀번호 — 환경변수 또는 .env의 DASHBOARD_PASSWORD (미설정 시 대시보드 접근 차단)
define('DASHBOARD_PASSWORD', envValue('DASHBOARD_PASSWORD'));

// MariaDB 접속 설정 — 비밀번호는 반드시 .env(또는 환경변수)에서 읽음, 나머지는 미설정 시 기본값 사용
define('DB_HOST', envValue('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) envValue('DB_PORT', '3306'));
define('DB_NAME', envValue('DB_NAME', 'calculator'));
define('DB_USER', envValue('DB_USER', 'calc_user'));
define('DB_PASS', envValue('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

// Gemini API 키 — .env의 GEMINI_API_KEY에 설정
define('GEMINI_API_KEY', envValue('GEMINI_API_KEY'));

// Gemini 1회 호출당 응답 토큰 상한 — .env의 GEMINI_MAX_TOKENS (미설정 시 4096)
define('GEMINI_MAX_TOKENS', max(256, (int) envValue('GEMINI_MAX_TOKENS', '4096')));

// 로컬 개발 환경에서만 외부 API의 SSL 인증서 검증을 생략 (.env에 DEV_INSECURE_SSL=1)
// ★운영 서버에서는 절대 설정하지 마세요 — 미설정 시 정상적으로 SSL 검증합니다
define('DEV_INSECURE_SSL', envValue('DEV_INSECURE_SSL', '') === '1');

/**
 * PDO 커넥션 반환
 */
function getConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

/**
 * 상품명 정규화 — pricecheck.php의 JS normName()과 동일 규칙 유지 필수
 * (공백·괄호·숫자·영문·기호 제거 → 한글 핵심만 남겨 표기 차이 흡수)
 * drug_master.norm_name 저장과 매칭 대조 양쪽에서 사용
 */
function pcNormalizeName($s)
{
    $t = preg_replace('/[\s()\[\]{}0-9a-zA-Z.,·*\-\/+%~"\']+/u', '', (string) $s);
    return mb_strtolower($t === null ? '' : $t, 'UTF-8');
}

/**
 * 정규화명에서 끝의 제형 접미어를 제거 — "텐텐츄정"→"텐텐" 처럼
 * 제형 표기 차이만 있는 같은 상품을 대조하기 위한 비교용 키 (긴 접미어 우선)
 * "베아제정(10정)"→정규화 "베아제정정" 같은 포장 수량 잔재 대비 최대 2회 반복 제거
 */
function pcStripDoseForm($norm)
{
    static $forms = array(
        '마이크로그램', '이알서방정', '밀리그램', '밀리그람', '밀리리터', // 단위 꼬리 (숫자는 정규화에서 이미 제거됨)
        '츄어블정', '연질캡슐', '장용캡슐', '서방정', '발포정', '현탁액', '내복액',
        '그램', '그람', '츄정', '캡슐', '캅셀', '시럽', '과립', '스프레이', '크림', '연고',
        '정', '액', '산', '환', '겔',
    );
    for ($pass = 0; $pass < 3; $pass++) {
        $stripped = false;
        foreach ($forms as $f) {
            $fl = mb_strlen($f, 'UTF-8');
            if (mb_strlen($norm, 'UTF-8') > $fl && mb_substr($norm, -$fl, null, 'UTF-8') === $f) {
                $norm = mb_substr($norm, 0, mb_strlen($norm, 'UTF-8') - $fl, 'UTF-8');
                $stripped = true;
                break;
            }
        }
        if (!$stripped) {
            break;
        }
    }
    return $norm;
}

/**
 * 관리자 설정 전체 로드 (요청당 1회 캐시) — admin_settings 테이블의 참고 데이터·가중치 오버라이드
 * DB 미연결·테이블 미생성 시 빈 배열 반환 → 각 페이지의 하드코딩 기본값으로 동작 (fail-safe)
 */
function adminSettingsAll()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = array();
    try {
        $pdo = getConnection();
        $stmt = $pdo->query('SELECT setting_key, value_json FROM admin_settings');
        foreach ($stmt->fetchAll() as $row) {
            $v = json_decode($row['value_json'], true);
            if ($v !== null) {
                $cache[$row['setting_key']] = $v;
            }
        }
    } catch (Exception $e) {
        // 설정 없이 기본값으로 동작
    }
    return $cache;
}

/**
 * 관리자 설정 단건 조회 — 미설정 시 $default 반환
 */
function adminSetting($key, $default)
{
    $all = adminSettingsAll();
    return isset($all[$key]) ? $all[$key] : $default;
}

/**
 * 시/도 명칭 정규화 — 공공데이터 API는 개편 후 공식 명칭만 인식
 * (전라북도→전북특별자치도, 강원도→강원특별자치도, 서울시→서울특별시 등: 옛/약식 표기는 0건 반환)
 */
function normalizeSido($sido)
{
    $map = array(
        '서울' => '서울특별시', '서울시' => '서울특별시',
        '부산' => '부산광역시', '부산시' => '부산광역시',
        '대구' => '대구광역시', '대구시' => '대구광역시',
        '인천' => '인천광역시', '인천시' => '인천광역시',
        '광주' => '광주광역시', '광주시' => '광주광역시',
        '대전' => '대전광역시', '대전시' => '대전광역시',
        '울산' => '울산광역시', '울산시' => '울산광역시',
        '세종' => '세종특별자치시', '세종시' => '세종특별자치시',
        '경기' => '경기도',
        '강원' => '강원특별자치도', '강원도' => '강원특별자치도',
        '충북' => '충청북도', '충남' => '충청남도',
        '전북' => '전북특별자치도', '전라북도' => '전북특별자치도',
        '전남' => '전라남도',
        '경북' => '경상북도', '경남' => '경상남도',
        '제주' => '제주특별자치도', '제주도' => '제주특별자치도',
    );
    $sido = trim($sido);
    return isset($map[$sido]) ? $map[$sido] : $sido;
}

/**
 * 시/도 공식 명칭 → 법정동코드 시도 접두(2자리). paytable(salary_table)의 법정동코드1 매칭용
 */
function sidoBjdPrefix($sidoName)
{
    $map = array(
        '서울특별시' => 11, '부산광역시' => 26, '대구광역시' => 27, '인천광역시' => 28,
        '광주광역시' => 29, '대전광역시' => 30, '울산광역시' => 31, '세종특별자치시' => 36,
        '경기도' => 41, '강원특별자치도' => 51, '충청북도' => 43, '충청남도' => 44,
        '전북특별자치도' => 52, '전라남도' => 46, '경상북도' => 47, '경상남도' => 48,
        '제주특별자치도' => 50,
    );
    return isset($map[$sidoName]) ? $map[$sidoName] : 0;
}

/**
 * 시/도 공식 명칭 → 약사회 지부명 접두 (otc_prices.jibu_name 매칭용, 예: 서울시약사회 → '서울')
 */
function sidoShortName($sidoName)
{
    $map = array(
        '서울특별시' => '서울', '부산광역시' => '부산', '대구광역시' => '대구', '인천광역시' => '인천',
        '광주광역시' => '광주', '대전광역시' => '대전', '울산광역시' => '울산', '세종특별자치시' => '세종',
        '경기도' => '경기', '강원특별자치도' => '강원', '충청북도' => '충북', '충청남도' => '충남',
        '전북특별자치도' => '전북', '전라남도' => '전남', '경상북도' => '경북', '경상남도' => '경남',
        '제주특별자치도' => '제주',
    );
    return isset($map[$sidoName]) ? $map[$sidoName] : '';
}

/**
 * 적정성 설문 보정 계수 — 관리자 > 설문 집계의 원클릭 반영 (admin_settings 'survey_calibration')
 * 적용처: goodwill(권리금 시세·곡선형), salary(regionAverageSalaryInfo), otc(OTC 실측 시세 병기)
 * 반환: array('factor' => float, 'resp' => int, 'applied_at' => string) | null (미반영)
 */
function surveyCalibration($topic)
{
    $all = adminSetting('survey_calibration', array());
    if (!isset($all[$topic]['factor'])) {
        return null;
    }
    $f = (float) $all[$topic]['factor'];
    if ($f < 0.85 || $f > 1.15 || abs($f - 1.0) < 0.001) {
        return null; // 반영 폭 상한(±15%) 밖·무의미한 값은 무시 (fail-safe)
    }
    return array(
        'factor' => round($f, 3),
        'resp' => (int) (isset($all[$topic]['resp']) ? $all[$topic]['resp'] : 0),
        'applied_at' => isset($all[$topic]['applied_at']) ? (string) $all[$topic]['applied_at'] : '',
    );
}

/**
 * 권리금 지역 보정 계수 — goodwill.php·goodwill_curve.php·listings.php 공용
 * 소스 2종 합산: ① goodwill_feedback (사용자 제시값 ÷ 모델가) ② listing_reports (실매물 호가 ÷ 모델가, 테스트 제외)
 * 지역별 합산 표본이 $min_n 이상일 때만 ±$limit 한도 내 보정 (fail-safe: DB 오류 시 보정 없음)
 */
function goodwillRegionCalib($min_n, $limit)
{
    $calib = array();
    foreach (array('seoul_core', 'seoul', 'metro', 'bigcity', 'local') as $rg) {
        $calib[$rg] = array('factor' => 1.0, 'n' => 0, 'min_n' => $min_n);
    }
    try {
        $pdo = getConnection();
        $sum = array(); // region => array(n, ratio_sum)
        foreach ($pdo->query(
            "SELECT region, COUNT(*) AS n, SUM(suggested_manwon / calc_fair) AS rs
               FROM goodwill_feedback
              WHERE is_critical = 0 AND suggested_manwon IS NOT NULL AND calc_fair > 0
                AND suggested_manwon BETWEEN calc_fair * 0.2 AND calc_fair * 3
              GROUP BY region"
        )->fetchAll() as $row) {
            $sum[$row['region']] = array((int) $row['n'], (float) $row['rs']);
        }
        try {
            foreach ($pdo->query(
                "SELECT region, COUNT(*) AS n, SUM(ask / model_fair) AS rs
                   FROM listing_reports
                  WHERE is_test = 0 AND kind = 'exist' AND ask > 0 AND model_fair > 0
                    AND ask BETWEEN model_fair * 0.2 AND model_fair * 3
                  GROUP BY region"
            )->fetchAll() as $row) {
                $r = $row['region'];
                $prev = isset($sum[$r]) ? $sum[$r] : array(0, 0.0);
                $sum[$r] = array($prev[0] + (int) $row['n'], $prev[1] + (float) $row['rs']);
            }
        } catch (Exception $e) {
            // listing_reports 미생성(구설치) — 피드백 소스만 사용
        }
        foreach ($sum as $rg => $d) {
            if (!isset($calib[$rg])) {
                continue;
            }
            $calib[$rg]['n'] = $d[0];
            if ($d[0] >= $min_n) {
                $avg = $d[1] / $d[0];
                $calib[$rg]['factor'] = round(min(1 + $limit, max(1 - $limit, $avg)), 3);
            }
        }
    } catch (Exception $e) {
        // DB 미연결 — 보정 없이 기본 모델
    }
    return $calib;
}

/** ===== 권리금 시세 엔진 (1번 모델) 공용 계수 — goodwill.php와 동일 키·기본값 ===== */
function gwRegionMonths()
{
    // 2026-09 적정성 조사 반영 (조사원 3명 × 20케이스 = 60응답): 기존 표가 전 지역·전 규모에서
    // 일관되게 약 15% 낮게 나와 p30/p50/p75를 일괄 ×1.15 상향 (지역 간 상대 관계는 유지)
    return adminSetting('goodwill_region_months', array(
        'seoul_core' => array('p30' => 20.2, 'p50' => 25.8, 'p75' => 33.2),
        'seoul'      => array('p30' => 16.9, 'p50' => 21.5, 'p75' => 27.7),
        'metro'      => array('p30' => 17.7, 'p50' => 21.0, 'p75' => 27.1),
        'bigcity'    => array('p30' => 13.6, 'p50' => 20.2, 'p75' => 26.6),
        'local'      => array('p30' => 12.2, 'p50' => 18.2, 'p75' => 23.9),
    ));
}

function gwPrescMult()
{
    // under1000: 조사에서 소형 약국(월 조제료 1,000만원 미만)은 모델 대비 0.65배로 평가돼
    // 0.88 → 0.50 하향 (표본 3케이스 9응답 — 방향은 3명 모두 일치, 표본 축적 후 재검토 대상)
    // b3500·over5000은 이번 조사에 표본이 없어 기존 상대 관계 유지
    return adminSetting('goodwill_presc_mult', array(
        'under1000' => 0.50, 'b1000' => 1.00, 'b2500' => 1.15, 'b3500' => 1.25, 'over5000' => 1.35,
    ));
}

function gwWeights()
{
    return adminSetting('goodwill_weights', array(
        'otc_margin' => 0.4, 'work_days' => 25, 'deposit_threshold' => 30000, 'deposit_rate' => 0.05,
        'critical_profit' => 700, 'caution_profit' => 1000,
        'crit_quick' => 3000, 'crit_fair' => 5000, 'crit_high' => 8000,
        'doctor65' => 0.7, 'doctor75' => 0.5, 'ask_ratio' => 0.4, 'deal_ratio' => 0.6,
        'calib_min_n' => 30, 'calib_limit' => 0.10,
    ));
}

/** 주소 시도·시군구 → 시세 지역 키 (강남·서초·송파는 서울 핵심 상권으로 분류) */
function gwRegionKeyFromAddr($sido, $sigungu = '')
{
    $sido = normalizeSido(trim((string) $sido));
    if ($sido === '서울특별시') {
        foreach (array('강남구', '서초구', '송파구') as $core) {
            if (strpos((string) $sigungu, $core) !== false) {
                return 'seoul_core';
            }
        }
        return 'seoul';
    }
    if ($sido === '경기도' || $sido === '인천광역시') {
        return 'metro';
    }
    if (in_array($sido, array('부산광역시', '대구광역시', '광주광역시', '대전광역시', '울산광역시', '세종특별자치시'), true)) {
        return 'bigcity';
    }
    return 'local';
}

/**
 * 권리금 시세 기초 산출 (goodwill.php 계산식의 PHP 포트 — 회수배수 모델)
 * 월 환산수익 × 지역 회수개월수(p30/50/75) × 조제료 규모 계수 → 빠른매도/적정/높은호가.
 * 저수익 임계 할인, 의사 고령 할인, 지역 피드백·설문 보정까지 goodwill.php와 동일 순서로 적용.
 * $prescProfit: 월 조제 수익(조제료 규모 계수 판정에도 사용), $otcProfit: 월 OTC 마진
 */
function gwBaseQuote($regionKey, $prescProfit, $otcProfit, $rent, $deposit, $doctorAge = 'none')
{
    $monthsTable = gwRegionMonths();
    $multTable = gwPrescMult();
    $weights = gwWeights();
    if (!isset($monthsTable[$regionKey])) {
        $regionKey = 'seoul';
    }
    $regionMonths = $monthsTable[$regionKey]; // 이 지역의 p30/p50/p75 회수개월수

    // 1. 월 환산수익 (보증금 기준 초과분의 연 환산 기회비용을 월세에 가산)
    $depositAdj = $deposit > (float) $weights['deposit_threshold'] ? $deposit * (float) $weights['deposit_rate'] / 12 : 0;
    $profit = $prescProfit + $otcProfit - ($rent + $depositAdj);

    // 2. 조제료 규모 계수 (조제료가 클수록 회수기간을 길게 인정 → 권리금 상향)
    if ($prescProfit < 1000) {
        $prescMult = (float) $multTable['under1000'];
    } elseif ($prescProfit < 2500) {
        $prescMult = (float) $multTable['b1000'];
    } elseif ($prescProfit < 3500) {
        $prescMult = (float) $multTable['b2500'];
    } elseif ($prescProfit < 5000) {
        $prescMult = (float) $multTable['b3500'];
    } else {
        $prescMult = (float) $multTable['over5000'];
    }

    // 3. 3단계 가격 + 저수익 임계점 처리
    //    임계 미달이면 시설양도비 수준 고정, 임계 부근이면 회수배수를 최대 50%까지 깎는다.
    $criticalProfit = (float) $weights['critical_profit'];
    $cautionProfit = (float) $weights['caution_profit'];
    $warning = null;
    $cautionMult = 1.0; // 저수익 할인 배율 (1.0 = 할인 없음)
    if ($profit < $criticalProfit) {
        $quick = (float) $weights['crit_quick'];
        $fair = (float) $weights['crit_fair'];
        $high = (float) $weights['crit_high'];
        $warning = 'critical';
    } else {
        if ($profit < $cautionProfit) {
            $cautionMult = 0.5 + ($profit - $criticalProfit) / ($cautionProfit - $criticalProfit) * 0.5;
            $warning = 'caution';
        }
        $quick = $profit * $regionMonths['p30'] * $prescMult * $cautionMult;
        $fair = $profit * $regionMonths['p50'] * $prescMult * $cautionMult;
        $high = $profit * $regionMonths['p75'] * $prescMult * $cautionMult;
    }

    // 4. 의사 고령 할인 (시설양도비 구간은 이미 바닥이라 미적용)
    $doctorMult = $doctorAge === 'over75' ? (float) $weights['doctor75'] : ($doctorAge === 'over65' ? (float) $weights['doctor65'] : 1.0);
    if ($warning !== 'critical' && $doctorMult < 1.0) {
        $quick *= $doctorMult;
        $fair *= $doctorMult;
        $high *= $doctorMult;
    }

    // 5. 지역 피드백·실매물 보정 + 적정성 설문 보정 (시설양도비 구간 제외)
    $calibFactor = 1.0;
    $surveyFactor = 1.0;
    if ($warning !== 'critical') {
        $calib = goodwillRegionCalib(max(1, (int) $weights['calib_min_n']), max(0, (float) $weights['calib_limit']));
        if (isset($calib[$regionKey]['factor'])) {
            $calibFactor = (float) $calib[$regionKey]['factor'];
        }
        $surveyCal = surveyCalibration('goodwill');
        if ($surveyCal !== null) {
            $surveyFactor = (float) $surveyCal['factor'];
        }
        $quick *= $calibFactor * $surveyFactor;
        $fair *= $calibFactor * $surveyFactor;
        $high *= $calibFactor * $surveyFactor;
    }

    // quick/fair/high = 빠른매도/중간/높은호가, profit = 월 환산수익,
    // presc_mult = 조제료 규모 계수, months_fair = 실제 적용된 적정 회수개월수,
    // warning = null|'caution'|'critical', caution_mult = 저수익 할인 배율
    return array(
        'quick' => (int) round($quick), 'fair' => (int) round($fair), 'high' => (int) round($high),
        'profit' => (int) round($profit),
        'presc_mult' => $prescMult, 'months_fair' => round($regionMonths['p50'] * $prescMult, 1),
        'warning' => $warning, 'caution_mult' => $cautionMult, 'doctor_mult' => $doctorMult,
        'calib_factor' => $calibFactor, 'survey_factor' => $surveyFactor, 'region' => $regionKey,
    );
}

/**
 * 지역 평균 급여 (근무약사, 세전 월급 원) — 우선순위:
 *  1) salary_table 실측 시군구 중앙값 (법정동코드 앞 5자리 매칭, 표본 3건+; 개편 전·후 코드 병행)
 *  2) salary_table 실측 시도 중앙값 (표본 5건+; 구코드 42/45 강원/전북 데이터도 함께 매칭)
 *  3) 전국 실측 중앙값 (표본 5건+)
 *  4) 관리자 설정(salary_region_avg) → 5) 기본 600만원
 * 급여 적정성 설문 보정(surveyCalibration 'salary')이 반영 중이면 결과에 계수를 곱해 반환
 * 반환: array('salary' => int, 'n' => int, 'source' => string)
 */
function regionAverageSalaryInfo($address)
{
    $info = regionAverageSalaryInfoRaw($address);
    $cal = surveyCalibration('salary');
    if ($cal !== null && isset($info['salary']) && $info['salary'] > 0) {
        $info['salary'] = (int) round($info['salary'] * $cal['factor']);
        $info['source'] .= ' · 설문보정 ×' . number_format($cal['factor'], 2);
    }
    return $info;
}

function regionAverageSalaryInfoRaw($address)
{
    $region = parseRegion($address);
    $sidoName = $region[0];
    $sigunguName = $region[1];

    try {
        $pdo = getConnection();

        // 1) 시군구 단위 — locationCode 기반 (시도,시군구)명 → 법정동 시군구 코드(신·구 병행) 매칭
        if ($sigunguName !== '') {
            require_once __DIR__ . '/region_codes.php';
            $codes = regionCodesForName($sidoName, $sigunguName);
            if (count($codes) > 0) {
                $in = implode(',', array_map('intval', $codes));
                $stmt = $pdo->query(
                    "SELECT pharmacist_monthly FROM salary_table
                      WHERE pharmacist_monthly BETWEEN 1500000 AND 20000000
                        AND sigungu_code IN ({$in})
                      ORDER BY pharmacist_monthly"
                );
                $vals = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (count($vals) >= 3) {
                    $mid = (int) $vals[(int) floor(count($vals) / 2)];
                    return array('salary' => $mid, 'n' => count($vals), 'source' => '실측·' . $sidoName . ' ' . $sigunguName);
                }
            }
        }

        // 2) 시도 단위 폴백
        $prefix = sidoBjdPrefix($sidoName);
        if ($prefix > 0) {
            // 개편 전 코드 병행 매칭 (강원 51↔42, 전북 52↔45)
            $prefixes = array($prefix);
            if ($prefix === 51) { $prefixes[] = 42; }
            if ($prefix === 52) { $prefixes[] = 45; }
            $in = implode(',', $prefixes);
            $stmt = $pdo->query(
                "SELECT pharmacist_monthly FROM salary_table
                  WHERE pharmacist_monthly BETWEEN 1500000 AND 20000000
                    AND FLOOR(sido_code / 1000) IN ({$in})
                  ORDER BY pharmacist_monthly"
            );
            $vals = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($vals) >= 5) {
                $mid = (int) $vals[(int) floor(count($vals) / 2)];
                return array('salary' => $mid, 'n' => count($vals), 'source' => '실측·' . $sidoName);
            }
        }
        // 전국 폴백
        $stmt = $pdo->query(
            'SELECT pharmacist_monthly FROM salary_table
              WHERE pharmacist_monthly BETWEEN 1500000 AND 20000000
              ORDER BY pharmacist_monthly'
        );
        $vals = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($vals) >= 5) {
            $mid = (int) $vals[(int) floor(count($vals) / 2)];
            return array('salary' => $mid, 'n' => count($vals), 'source' => '실측·전국');
        }
    } catch (Exception $e) {
        // DB 미연결·테이블 미생성 → 설정/기본값으로
    }

    $map = adminSetting('salary_region_avg', array());
    if (isset($map[$sidoName]) && (int) $map[$sidoName] > 0) {
        return array('salary' => (int) $map[$sidoName], 'n' => 0, 'source' => '설정·' . $sidoName);
    }
    if (isset($map['기본값']) && (int) $map['기본값'] > 0) {
        return array('salary' => (int) $map['기본값'], 'n' => 0, 'source' => '설정·기본값');
    }
    return array('salary' => 6000000, 'n' => 0, 'source' => '기본값');
}

/**
 * 지역 종업원(비약사) 평균 급여 (세전 월급 원) — salary_table 실측 (staff_monthly)
 * 시군구 중앙값(표본 3건+) → 시도(5건+) → 전국 순 폴백, 실측 없으면 null 반환
 * 반환: array('salary' => int, 'n' => int, 'source' => string) | null
 */
function regionStaffSalaryInfo($address)
{
    $region = parseRegion($address);
    $sidoName = $region[0];
    $sigunguName = $region[1];

    try {
        $pdo = getConnection();
        $range = 'staff_monthly BETWEEN 800000 AND 10000000';

        if ($sigunguName !== '') {
            require_once __DIR__ . '/region_codes.php';
            $codes = regionCodesForName($sidoName, $sigunguName);
            if (count($codes) > 0) {
                $in = implode(',', array_map('intval', $codes));
                $vals = $pdo->query(
                    "SELECT staff_monthly FROM salary_table WHERE {$range} AND sigungu_code IN ({$in}) ORDER BY staff_monthly"
                )->fetchAll(PDO::FETCH_COLUMN);
                if (count($vals) >= 3) {
                    return array('salary' => (int) $vals[(int) floor(count($vals) / 2)], 'n' => count($vals), 'source' => '실측·' . $sidoName . ' ' . $sigunguName);
                }
            }
        }

        $prefix = sidoBjdPrefix($sidoName);
        if ($prefix > 0) {
            $prefixes = array($prefix);
            if ($prefix === 51) { $prefixes[] = 42; }
            if ($prefix === 52) { $prefixes[] = 45; }
            $in = implode(',', $prefixes);
            $vals = $pdo->query(
                "SELECT staff_monthly FROM salary_table WHERE {$range} AND FLOOR(sido_code / 1000) IN ({$in}) ORDER BY staff_monthly"
            )->fetchAll(PDO::FETCH_COLUMN);
            if (count($vals) >= 5) {
                return array('salary' => (int) $vals[(int) floor(count($vals) / 2)], 'n' => count($vals), 'source' => '실측·' . $sidoName);
            }
        }

        $vals = $pdo->query(
            "SELECT staff_monthly FROM salary_table WHERE {$range} ORDER BY staff_monthly"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (count($vals) >= 5) {
            return array('salary' => (int) $vals[(int) floor(count($vals) / 2)], 'n' => count($vals), 'source' => '실측·전국');
        }
    } catch (Exception $e) {
        // DB 미연결·테이블 미생성 → 실측 없음
    }
    return null;
}

/**
 * 주소에서 시/도, 시/군/구만 추출 (개인정보 최소화 — 상세 주소는 저장하지 않음)
 */
function parseRegion($address)
{
    $parts = preg_split('/\s+/', trim($address));
    $sido = normalizeSido(isset($parts[0]) ? $parts[0] : '');
    $sigungu = isset($parts[1]) ? $parts[1] : '';
    // "성남시 분당구"처럼 시 아래 구/군이 있는 경우 합침
    if (isset($parts[2]) && preg_match('/(구|군)$/u', $parts[2]) && preg_match('/시$/u', $sigungu)) {
        $sigungu .= ' ' . $parts[2];
    }
    // 부천시는 일반구 폐지(2016) — 옛 "부천시 원미구" 주소로 조회하면 폐지 이전 표기 데이터만 잡히므로 시 단위로 통일
    if (strpos($sigungu, '부천시') === 0) {
        $sigungu = '부천시';
    }
    return array($sido, $sigungu);
}

/**
 * Gemini 모델 시도 계획 — ai_status.php 점검 캐시(5분) 기준으로 라우팅.
 * 기본 모델(flash)이 최근 점검에서 down이면 예비(lite)로만 진행(25초 탐침도 생략),
 * 다음 점검에서 기본 모델이 살아나면 자동으로 기본 모델 우선으로 복귀한다.
 */
function aiModelPlan()
{
    $flashDown = false;
    try {
        $pdo = getConnection();
        $row = $pdo->query("SELECT payload FROM ai_status_cache WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $p = json_decode($row['payload'], true);
            // 신선도는 payload의 시각 기준 — 프로브뿐 아니라 실호출 피드백(aiSetModelState)도 갱신함.
            // 300초 지나면 기본 순서로 복귀해 1차 25초 탐침이 스스로 재판정 (트래픽만으로도 자가 치유)
            $fresh = isset($p['checked_at']) && time() - (int) $p['checked_at'] <= 300;
            $flashDown = $fresh && isset($p['models']['flash']['state']) && $p['models']['flash']['state'] === 'down';
        }
    } catch (Exception $e) {
        $flashDown = false; // 캐시 없음 — 기본 순서로 (1차 25초 탐침이 안전망)
    }
    $GLOBALS['__ai_plan_has_flash'] = !$flashDown; // logApiUsage의 실호출 피드백 판단용
    return $flashDown
        ? array('gemini-flash-lite-latest' => 3)
        : array('gemini-flash-latest' => 1, 'gemini-flash-lite-latest' => 3);
}

/**
 * 점검 캐시의 특정 모델 상태를 실호출 결과로 즉시 갱신.
 * checked_at(프로브 시각) 컬럼은 건드리지 않아 5분 주기 재점검(복구 판정)은 그대로 동작하고,
 * payload 안의 표시용 시각만 갱신해 네비 램프가 최신 상태를 보여준다.
 */
function aiSetModelState($key, $state, $ms = null)
{
    try {
        $pdo = getConnection();
        $row = $pdo->query("SELECT payload FROM ai_status_cache WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $p = json_decode($row['payload'], true);
        if (!is_array($p)) {
            return;
        }
        if (!isset($p['models'][$key])) {
            $p['models'][$key] = array('state' => '', 'ms' => 0, 'http' => 0);
        }
        // 상태가 같아도 시각은 갱신 — 지속 장애 중 라우팅 신선도가 유지되어
        // 예비 직행이 끊기지 않는다 (복구 판정은 5분 주기 프로브 담당)
        $p['models'][$key]['state'] = $state;
        if ($ms !== null) {
            $p['models'][$key]['ms'] = (int) $ms;
        }
        // 종합 판정 재계산 (ai_status.php와 동일 규칙)
        $f = isset($p['models']['flash']['state']) ? $p['models']['flash']['state'] : 'down';
        $l = isset($p['models']['lite']['state']) ? $p['models']['lite']['state'] : 'down';
        $p['overall'] = ($f === 'ok' && ($l === 'ok' || $l === 'slow')) ? 'ok'
                      : (($l === 'ok' || $l === 'slow' || $f === 'slow') ? 'degraded' : 'down');
        $p['checked_at'] = time(); // 표시용 (프로브 주기와 무관)
        $pdo->prepare("UPDATE ai_status_cache SET payload = ? WHERE id = 1")
            ->execute(array(json_encode($p, JSON_UNESCAPED_UNICODE)));
    } catch (Exception $e) {
        // 상태 갱신 실패는 무시 — 분석 동작에 영향 없음
    }
}

/**
 * Gemini API 토큰 사용량 기록 (대시보드 통계용) — 실패해도 페이지 동작에는 영향 없음
 * + 실호출 결과를 점검 캐시에 피드백: 기본 모델을 시도했는데 예비로 넘어갔으면
 *   기본 모델 down 처리(다음 요청부터 즉시 예비 직행), 기본 모델 성공이면 ok 유지
 */
function logApiUsage($page, $model, $usage)
{
    if ($model === 'gemini-flash-latest') {
        aiSetModelState('flash', 'ok');
    } elseif ($model === 'gemini-flash-lite-latest' && !empty($GLOBALS['__ai_plan_has_flash'])) {
        aiSetModelState('flash', 'down'); // 계획상 기본 모델 먼저였는데 예비가 쓰임 = 기본 모델 실패
    }
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO api_usage (page, model, prompt_tokens, output_tokens, total_tokens) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            mb_substr($page, 0, 40),
            mb_substr($model, 0, 60),
            (int) (isset($usage['promptTokenCount']) ? $usage['promptTokenCount'] : 0),
            (int) (isset($usage['candidatesTokenCount']) ? $usage['candidatesTokenCount'] : 0),
            (int) (isset($usage['totalTokenCount']) ? $usage['totalTokenCount'] : 0),
        ));
    } catch (Exception $e) {
        error_log('api_usage insert failed: ' . $e->getMessage());
    }
}

/**
 * 급여 조회 기록 저장 (통계용) — 실패해도 페이지 동작에는 영향 없음
 */
function saveSalaryQuery(array $d)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO salary_queries
                (user_type, job, experience, region_sido, region_sigungu, commercial_trait, employ_type,
                 weekly_hours, night_hours, current_salary, desired_salary, market_wage, recommended_wage)
             VALUES
                (:user_type, :job, :experience, :region_sido, :region_sigungu, :commercial_trait, :employ_type,
                 :weekly_hours, :night_hours, :current_salary, :desired_salary, :market_wage, :recommended_wage)'
        );
        $stmt->execute($d);
    } catch (Exception $e) {
        error_log('salary_queries insert failed: ' . $e->getMessage());
    }
}
