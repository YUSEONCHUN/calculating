<?php
// 관리자 공통 모듈 — 참고 데이터/가중치 설정 페이지의 인증·정의·저장·렌더링
// 사용: admin_data.php / admin_weights.php 에서 $ADMIN_PAGE 지정 후 require
// 저장소: admin_settings (setting_key, value_json) — 미설정 키는 각 페이지의 하드코딩 기본값 사용
require_once __DIR__ . '/config.php';

// ===== 세션 (dashboard.php 와 동일한 저장 경로 폴백) =====
$sessDir = __DIR__ . '/.sessions';
if (!is_dir($sessDir)) {
    @mkdir($sessDir, 0700, true);
}
if (is_dir($sessDir) && is_writable($sessDir)) {
    session_save_path($sessDir);
} elseif (is_writable(sys_get_temp_dir())) {
    session_save_path(sys_get_temp_dir());
}
session_start();

$self = basename($_SERVER['SCRIPT_NAME']);

if (isset($_GET['logout'])) {
    unset($_SESSION['dash_auth']);
    header('Location: ' . $self);
    exit;
}

if (DASHBOARD_PASSWORD === '') {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><title>관리자 설정 필요</title></head>'
        . '<body style="font-family: \'Malgun Gothic\', sans-serif; background:#f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0;">'
        . '<div style="background:#fff; border:1px solid #fecaca; border-radius:12px; padding:32px 40px; max-width:420px; color:#b91c1c; line-height:1.7;">'
        . '<strong>⚠ 관리자 비밀번호가 설정되지 않았습니다.</strong><br>'
        . '<span style="color:#6b7280; font-size:0.9em;">.env의 <code>DASHBOARD_PASSWORD</code>를 설정한 뒤 다시 접속하세요. (대시보드와 동일 비밀번호)</span>'
        . '</div></body></html>';
    exit;
}

$login_error = '';
if (isset($_POST['dash_password'])) {
    if (hash_equals(DASHBOARD_PASSWORD, (string) $_POST['dash_password'])) {
        $_SESSION['dash_auth'] = true;
        header('Location: ' . $self);
        exit;
    }
    $login_error = '비밀번호가 올바르지 않습니다.';
}

if (empty($_SESSION['dash_auth'])) {
    ?>
<!DOCTYPE html>
<html lang="ko">
<head><meta charset="UTF-8"><title>관리자 로그인</title>
<style>
    body { font-family: 'Malgun Gothic', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .login-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 36px 40px; width: 340px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
    h1 { font-size: 1.15em; color: #7c2d12; margin: 0 0 6px; }
    p { font-size: 0.85em; color: #6b7280; margin: 0 0 18px; }
    input[type=password] { width: 100%; padding: 11px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 1em; }
    button { width: 100%; margin-top: 12px; padding: 11px; background: #7c2d12; color: #fff; border: none; border-radius: 6px; font-weight: bold; font-size: 0.95em; cursor: pointer; }
    button:hover { background: #92400e; }
    .err { color: #b91c1c; font-size: 0.82em; margin-top: 10px; }
    a { color: #9ca3af; font-size: 0.8em; text-decoration: none; display: block; text-align: center; margin-top: 14px; }
</style>
    <link rel="stylesheet" href="theme.css?v=2"></head>
<body>
<form class="login-box" method="post">
    <h1>🔧 관리자 — 설정 관리</h1>
    <p>대시보드와 동일한 비밀번호를 입력하세요.</p>
    <input type="password" name="dash_password" placeholder="비밀번호" autofocus>
    <button type="submit">로그인</button>
    <?php if ($login_error !== ''): ?><div class="err"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
    <a href="index.php">← 홈으로</a>
</form>
</body></html>
    <?php
    exit;
}

// ===== 설정 그룹 정의 =====
// default 의 형태(스칼라 맵 / 2단 맵)가 곧 편집 폼의 형태가 됨. 기본값은 각 페이지의 하드코딩 값과 동일해야 함.
$EQUIP_LABELS = array(
    'atc' => '전자동 정제분포기(ATC)', 'powder' => '산제 분포기/분쇄기', 'counter_table' => '조제대',
    'cabinet' => '약장/조제 선반', 'pill_counter' => '정제 계수기', 'syrup' => '시럽 분주기',
    'fridge' => '의약품 전용 냉장고', 'label' => '라벨 프린터',
    'partition' => '조제실 구획/칸막이 공사', 'narcotics_safe' => '마약류 보관 금고', 'thermo' => '온습도계/냉암소 설비',
    'pharm_sw' => '청구 프로그램 설치비', 'pc_set' => 'PC·스캐너·프린터 세트', 'pos' => 'POS/카드 단말기',
    'cctv' => 'CCTV', 'barcode' => '바코드 스캐너', 'queue' => '순번 대기기',
    'sales_counter' => '판매 카운터', 'display' => 'OTC 진열장/곤돌라 (평당)', 'chairs' => '대기 의자',
    'signage_in' => '안내 사인물/POP',
    'interior' => '내부 인테리어 (평당)', 'sign' => '간판', 'hvac' => '시스템 냉난방기', 'door' => '자동문',
    'bp' => '혈압계', 'water' => '정수기', 'safe' => '금고 (현금)', 'fire' => '소방 설비',
);

$ADMIN_GROUPS = array(
    // ---------- 참고 데이터 ----------
    'data' => array(
        array(
            'key' => 'goodwill_region_months',
            'title' => '권리금 시세 — 지역별 회수개월수 (개월)',
            'used' => '권리금 시세',
            'desc' => '실거래 회수배수 분포의 30/50/75 백분위. 권리금 = 월 환산수익 × 회수개월수 × 조제료 계수. 자체 거래 데이터가 쌓이면 이 표를 갱신하세요.
                      <b>2026-09 적정성 조사(조사원 3명 × 20케이스) 반영으로 전 지역 ×1.15 상향</b>했습니다.',
            'labels' => array('seoul_core' => '강남 등 서울 핵심', 'seoul' => '서울 일반', 'metro' => '수도권', 'bigcity' => '광역시', 'local' => '지방 중소도시'),
            'col_labels' => array('p30' => '빠른매도 (p30)', 'p50' => '적정 (p50)', 'p75' => '높은호가 (p75)'),
            'default' => array(
                'seoul_core' => array('p30' => 20.2, 'p50' => 25.8, 'p75' => 33.2),
                'seoul'      => array('p30' => 16.9, 'p50' => 21.5, 'p75' => 27.7),
                'metro'      => array('p30' => 17.7, 'p50' => 21.0, 'p75' => 27.1),
                'bigcity'    => array('p30' => 13.6, 'p50' => 20.2, 'p75' => 26.6),
                'local'      => array('p30' => 12.2, 'p50' => 18.2, 'p75' => 23.9),
            ),
        ),
        array(
            'key' => 'goodwill_presc_mult',
            'title' => '권리금 시세 — 조제료 규모 계수',
            'used' => '권리금 시세',
            'desc' => '월 조제료 구간별 회수개월수 보정 계수. 조제료가 클수록 회수기간을 길게 인정해 권리금이 비선형으로 커집니다.
                      <b>2026-09 조사 반영으로 1,000만원 미만 구간을 0.88 → 0.50 하향</b>(소형 약국은 조사 응답이 모델의 0.65배). 3,500만원 이상 구간은 조사 표본이 없어 유지.',
            'labels' => array(
                'under1000' => '1,000만원 미만', 'b1000' => '1,000~2,500만원', 'b2500' => '2,500~3,500만원',
                'b3500' => '3,500~5,000만원', 'over5000' => '5,000만원 이상',
            ),
            'default' => array('under1000' => 0.50, 'b1000' => 1.00, 'b2500' => 1.15, 'b3500' => 1.25, 'over5000' => 1.35),
        ),
    ),

    // ---------- 계산 가중치 ----------
    'weights' => array(
        array(
            'key' => 'premium_weights',
            'title' => '권리금 계산 — 기본 계수',
            'used' => '권리금 계산',
            'desc' => '권리금 기초 산출은 <b>권리금 시세와 동일한 실거래 회수배수 모델</b>을 사용합니다(지역 회수개월수·조제료 규모 계수는 「참고 데이터」·「권리금 시세 가중치」에서 설정).
                      여기서는 매출 마진율과 개국 자금 계획 계수를 설정합니다. 기준 권리금 개월수·적정 범위는 회수배수 모델로 대체되어 현재 계산에 쓰이지 않습니다.',
            'labels' => array(
                'margin_dispense' => '조제 매출 마진율 (0.25 = 25%)', 'default_otc_margin_pct' => 'OTC 마진율 폼 기본값 (%)',
                'base_months' => '기준 권리금 개월수 (미사용 — 곡선형 비교용)', 'range_pct' => '적정 범위 ± (%) (미사용 — 곡선형 비교용)',
                'inventory_months' => '초기 재고 개월수 (월 매출원가 ×)', 'reserve_rent_months' => '예비 운전자금 개월수 (임대료 ×)',
            ),
            'default' => array(
                'margin_dispense' => 0.25, 'default_otc_margin_pct' => 30, 'base_months' => 12,
                'range_pct' => 15, 'inventory_months' => 1.5, 'reserve_rent_months' => 3,
            ),
        ),
        array(
            'key' => 'premium_adjust_rates',
            'title' => '권리금 계산 — 조정 계수 (비율, +가산/−감산)',
            'used' => '권리금 계산',
            'desc' => '기초 권리금 시세(회수배수 모델)에 곱해지는 매물별 조정률 합산의 구성 요소. 0.20 = +20%, -0.05 = −5%.
                      ※ 의사 고령 계수는 기초 산출 엔진(권리금 시세 가중치)에서 반영되므로 여기 값은 곡선형 비교용으로만 남아 있습니다.',
            'labels' => array(
                'trait_munjeon' => '상권: 병원 문전', 'trait_yoyang' => '상권: 요양·실버', 'trait_apartment' => '상권: 대단지 아파트',
                'trait_downtown' => '상권: 번화가', 'trait_office' => '상권: 오피스', 'trait_mall' => '상권: 쇼핑몰·마트',
                'trait_academy' => '상권: 학원가·대학가', 'trait_residential' => '상권: 동네/주택가', 'trait_outskirt' => '상권: 한적한 외곽',
                'dep_multi' => '병원 의존: 복수 병원', 'dep_single' => '병원 의존: 단일 병원', 'dep_none' => '병원 의존: 병원 무관',
                'doctor65' => '의사 고령: 65세 이상', 'doctor75' => '의사 고령: 75세 이상',
                'lease_3y' => '잔여 계약: 3년 이상', 'lease_mid' => '잔여 계약: 1~3년', 'lease_renegotiate' => '잔여 계약: 재계약 협의', 'lease_under1y' => '잔여 계약: 1년 미만',
                'rent_over30' => '임대료 부담: 총이익의 30% 초과', 'rent_over50' => '임대료 부담: 총이익의 50% 초과',
            ),
            'default' => array(
                'trait_munjeon' => 0.20, 'trait_yoyang' => 0.15, 'trait_apartment' => 0.10,
                'trait_downtown' => 0.10, 'trait_office' => 0.05, 'trait_mall' => 0.05,
                'trait_academy' => 0.0, 'trait_residential' => 0.0, 'trait_outskirt' => -0.20,
                'dep_multi' => 0.10, 'dep_single' => -0.05, 'dep_none' => -0.10,
                'doctor65' => -0.30, 'doctor75' => -0.50,
                'lease_3y' => 0.10, 'lease_mid' => 0.0, 'lease_renegotiate' => -0.05, 'lease_under1y' => -0.20,
                'rent_over30' => -0.10, 'rent_over50' => -0.20,
            ),
        ),
        array(
            'key' => 'goodwill_weights',
            'title' => '권리금 시세 — 계산 가중치',
            'used' => '권리금 시세',
            'desc' => '월 환산수익 = 조제료 + 일매출×OTC마진×근무일 − (월세 + 보증금 기회비용). 임계점·할인·협상 시나리오·피드백 보정 한도를 관리합니다.',
            'labels' => array(
                'otc_margin' => 'OTC 마진율 (0.4 = 40%)', 'work_days' => '월 근무일수 (일)',
                'deposit_threshold' => '보증금 기회비용 기준 (만원)', 'deposit_rate' => '보증금 연 환산율 (0.05 = 5%)',
                'critical_profit' => '거래 임계점 — 월수익 (만원)', 'caution_profit' => '할인 구간 상한 — 월수익 (만원)',
                'crit_quick' => '시설양도비 하단 (만원)', 'crit_fair' => '시설양도비 중간 (만원)', 'crit_high' => '시설양도비 상단 (만원)',
                'doctor65' => '의사 65세+ 계수 (0.7 = 30% 하향)', 'doctor75' => '의사 75세+ 계수 (0.5 = 50% 하향)',
                'ask_ratio' => '추천 시작 호가 위치 (적정~높은 사이 비율)', 'deal_ratio' => '예상 거래가 위치 (빠른~적정 사이 비율)',
                'calib_min_n' => '피드백 보정 최소 표본 (건)', 'calib_limit' => '피드백 보정 한도 (0.10 = ±10%)',
            ),
            'default' => array(
                'otc_margin' => 0.4, 'work_days' => 25, 'deposit_threshold' => 30000, 'deposit_rate' => 0.05,
                'critical_profit' => 700, 'caution_profit' => 1000,
                'crit_quick' => 3000, 'crit_fair' => 5000, 'crit_high' => 8000,
                'doctor65' => 0.7, 'doctor75' => 0.5, 'ask_ratio' => 0.4, 'deal_ratio' => 0.6,
                'calib_min_n' => 30, 'calib_limit' => 0.10,
            ),
        ),
    ),
);

// ===== 저장/초기화 처리 =====
function adminEnsureTable($pdo)
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_settings (
          setting_key VARCHAR(60) PRIMARY KEY,
          value_json  TEXT NOT NULL,
          updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );
}

function adminParseNum($raw, $fallback)
{
    $s = str_replace(array(',', ' '), '', (string) $raw);
    if ($s === '' || !is_numeric($s)) {
        return $fallback;
    }
    $f = (float) $s;
    return $f == (int) $f ? (int) $f : $f;
}

$flash = '';
$flash_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_key']) && isset($ADMIN_GROUPS[$ADMIN_PAGE])) {
    $gk = $_POST['group_key'];
    $group = null;
    foreach ($ADMIN_GROUPS[$ADMIN_PAGE] as $g) {
        if ($g['key'] === $gk) {
            $group = $g;
            break;
        }
    }
    if ($group !== null) {
        try {
            $pdo = getConnection();
            adminEnsureTable($pdo);
            if (isset($_POST['reset'])) {
                $stmt = $pdo->prepare('DELETE FROM admin_settings WHERE setting_key = ?');
                $stmt->execute(array($gk));
                $flash = '「' . $group['title'] . '」 설정을 기본값으로 되돌렸습니다.';
            } else {
                $posted = isset($_POST['f']) && is_array($_POST['f']) ? $_POST['f'] : array();
                $value = array();
                foreach ($group['default'] as $k => $v) {
                    if (is_array($v)) {
                        $value[$k] = array();
                        foreach ($v as $k2 => $v2) {
                            $value[$k][$k2] = adminParseNum(isset($posted[$k][$k2]) ? $posted[$k][$k2] : null, $v2);
                        }
                    } else {
                        $value[$k] = adminParseNum(isset($posted[$k]) ? $posted[$k] : null, $v);
                    }
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO admin_settings (setting_key, value_json) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)'
                );
                $stmt->execute(array($gk, json_encode($value, JSON_UNESCAPED_UNICODE)));
                $flash = '「' . $group['title'] . '」 저장 완료 — 각 계산기에 즉시 반영됩니다.';
            }
        } catch (Exception $e) {
            $flash_err = '저장 실패: DB 연결을 확인하세요. (' . htmlspecialchars($e->getMessage()) . ')';
        }
    }
}

// 저장 후 현재 상태 로드 (오버라이드 + 수정 시각)
$overrides = array();
$updated_map = array();
try {
    $pdo = getConnection();
    adminEnsureTable($pdo);
    foreach ($pdo->query('SELECT setting_key, value_json, updated_at FROM admin_settings')->fetchAll() as $row) {
        $v = json_decode($row['value_json'], true);
        if ($v !== null) {
            $overrides[$row['setting_key']] = $v;
            $updated_map[$row['setting_key']] = $row['updated_at'];
        }
    }
} catch (Exception $e) {
    $flash_err = 'DB에 연결할 수 없어 설정을 저장/조회할 수 없습니다. 기본값이 표시됩니다.';
}

/** 그룹 1개를 편집 폼으로 렌더링 */
function renderGroup($group, $overrides, $updated_map)
{
    $gk = $group['key'];
    $current = isset($overrides[$gk]) ? $overrides[$gk] : $group['default'];
    $isOverridden = isset($overrides[$gk]);
    $labels = isset($group['labels']) ? $group['labels'] : array();
    $colLabels = isset($group['col_labels']) ? $group['col_labels'] : array();

    $badge = $isOverridden
        ? "<span class='badge on'>수정됨 · " . htmlspecialchars(substr($updated_map[$gk], 0, 16)) . "</span>"
        : "<span class='badge'>기본값 사용 중</span>";

    echo "<div class='result-card'>";
    echo "<div class='grp-head'><h3>" . htmlspecialchars($group['title']) . "</h3>{$badge}</div>";
    echo "<div class='grp-used'>적용 대상: " . htmlspecialchars($group['used']) . "</div>";
    echo "<p class='grp-desc'>" . htmlspecialchars($group['desc']) . "</p>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='group_key' value='" . htmlspecialchars($gk) . "'>";

    // 2단 맵(그리드) 여부 판별
    $first = reset($group['default']);
    if (is_array($first)) {
        $cols = array_keys($first);
        echo "<table class='set-table'><tr><th></th>";
        foreach ($cols as $c) {
            echo "<th>" . htmlspecialchars(isset($colLabels[$c]) ? $colLabels[$c] : $c) . "</th>";
        }
        echo "</tr>";
        foreach ($group['default'] as $k => $row) {
            $label = isset($labels[$k]) ? $labels[$k] : $k;
            echo "<tr><td class='row-label'>" . htmlspecialchars($label) . "</td>";
            foreach ($cols as $c) {
                $val = isset($current[$k][$c]) ? $current[$k][$c] : $row[$c];
                $def = $row[$c];
                $mark = ($val != $def) ? ' changed' : '';
                echo "<td><input type='text' inputmode='decimal' class='set-in{$mark}' name='f[" . htmlspecialchars($k) . "][" . htmlspecialchars($c) . "]' value='" . htmlspecialchars((string) $val) . "' title='기본값: {$def}'></td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<table class='set-table narrow'>";
        foreach ($group['default'] as $k => $def) {
            $label = isset($labels[$k]) ? $labels[$k] : $k;
            $val = isset($current[$k]) ? $current[$k] : $def;
            $mark = ($val != $def) ? ' changed' : '';
            echo "<tr><td class='row-label'>" . htmlspecialchars($label) . "</td>"
                . "<td><input type='text' inputmode='decimal' class='set-in{$mark}' name='f[" . htmlspecialchars($k) . "]' value='" . htmlspecialchars((string) $val) . "' title='기본값: {$def}'></td></tr>";
        }
        echo "</table>";
    }

    echo "<div class='grp-actions'>"
        . "<button type='submit' class='btn-save'>저장</button>"
        . "<button type='submit' name='reset' value='1' class='btn-reset' onclick=\"return confirm('이 그룹을 기본값으로 되돌릴까요?');\">기본값으로 초기화</button>"
        . "</div>";
    echo "</form></div>";
}

/** 페이지 셸 (헤더 + 사이드바) — 사이드바 메뉴는 nav.php 에서 공용 관리 */
function adminPageOpen($pageTitle, $lead, $activeLabel)
{
    require_once __DIR__ . '/nav.php';
    ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        body { font-family: 'Malgun Gothic', sans-serif; padding: 20px; max-width: 860px; margin: 0 auto; background-color: #f8fafc; }
        .search-box { margin-bottom: 20px; padding: 25px; border: 1px solid #ddd; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .nav-links.side-nav { position: fixed; left: 0; top: 0; bottom: 0; width: 200px; background: #1e293b; padding: 16px 12px; overflow-y: auto; z-index: 100; margin: 0; }
        .side-nav .sn-home { display: block; color: #fff; font-weight: bold; text-decoration: none; font-size: 0.9em; padding: 9px 10px; border-radius: 8px; background: rgba(255,255,255,0.1); margin-bottom: 6px; }
        .side-nav .sn-home:hover { background: rgba(255,255,255,0.2); }
        .side-nav .sn-cat { font-size: 0.7em; color: #94a3b8; font-weight: bold; margin: 14px 0 4px; padding: 0 10px; }
        .side-nav a { display: block; color: #cbd5e1; font-size: 0.84em; padding: 7px 10px; border-radius: 7px; text-decoration: none; margin-bottom: 1px; }
        .side-nav a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .side-nav .sn-on { display: block; font-size: 0.84em; padding: 7px 10px; border-radius: 7px; margin-bottom: 1px; background: #7c2d12; color: #fff; font-weight: bold; }
        @media (min-width: 1120px) { body { margin-left: max(236px, calc(50% - 320px)); margin-right: auto; } }
        @media (max-width: 1119px) {
            .nav-links.side-nav { position: static; width: auto; border-radius: 12px; margin-bottom: 18px; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0 14px; align-items: start; }
            .side-nav .sn-home { grid-column: 1 / -1; }
        }
        h2.page-title { margin-top: 0; color: #7c2d12; }
        .lead { color: #5f6368; font-size: 0.95em; margin-bottom: 0; line-height: 1.6; }
        .lead a { color: #7c2d12; }

        .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9em; font-weight: bold; }
        .flash.ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; }
        .flash.err { background: #fff1f2; border: 1px solid #fda4af; color: #be123c; }

        .result-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 22px 25px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .grp-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .grp-head h3 { margin: 0; font-size: 1.02em; color: #1e293b; }
        .badge { font-size: 0.72em; color: #6b7280; background: #f3f4f6; border-radius: 999px; padding: 3px 10px; font-weight: bold; }
        .badge.on { background: #fef3c7; color: #92400e; }
        .grp-used { font-size: 0.76em; color: #b45309; font-weight: bold; margin-top: 6px; }
        .grp-desc { font-size: 0.85em; color: #6b7280; line-height: 1.6; margin: 8px 0 14px; }

        .set-table { width: 100%; border-collapse: collapse; font-size: 0.88em; }
        .set-table th { text-align: center; padding: 6px; color: #6b7280; font-size: 0.85em; border-bottom: 2px solid #f3f4f6; }
        .set-table td { padding: 5px 6px; border-bottom: 1px solid #f9fafb; }
        .set-table .row-label { color: #374151; font-weight: bold; width: 45%; }
        .set-table input { width: 100%; max-width: 140px; padding: 7px 9px; border: 1px solid #d1d5db; border-radius: 6px;
                           box-sizing: border-box; font-size: 1em; text-align: right; background: #fff; }
        .set-table input:focus { outline: none; border-color: #7c2d12; box-shadow: 0 0 0 3px rgba(124,45,18,0.1); }
        .set-table input.changed { background: #fffbeb; border-color: #f59e0b; font-weight: bold; }
        .set-table.narrow td:last-child { text-align: right; }

        .grp-actions { margin-top: 14px; display: flex; gap: 8px; }
        .btn-save { padding: 9px 24px; background: #7c2d12; color: #fff; border: none; border-radius: 7px; font-weight: bold; font-size: 0.9em; cursor: pointer; }
        .btn-save:hover { background: #92400e; }
        .btn-reset { padding: 9px 16px; background: #fff; color: #6b7280; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.85em; cursor: pointer; }
        .btn-reset:hover { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }

        .top-links { text-align: right; font-size: 0.8em; margin-bottom: 8px; }
        .top-links a { color: #9ca3af; text-decoration: none; margin-left: 12px; }
        .top-links a:hover { color: #4b5563; }
        .foot-note { font-size: 0.8em; color: #9ca3af; margin: 20px 0 30px; line-height: 1.7; }
    </style>
</head>
<body>

<div class="top-links"><a href="<?= htmlspecialchars(basename($_SERVER['SCRIPT_NAME'])) ?>?logout=1">로그아웃</a></div>

<div class="search-box">
    <?php require_once __DIR__ . '/nav.php'; renderSideNav(); ?>
    <h2 class="page-title"><?= htmlspecialchars($pageTitle) ?></h2>
    <p class="lead"><?= $lead ?></p>
</div>
    <?php
    global $flash, $flash_err;
    if ($flash !== '') {
        echo "<div class='flash ok'>✓ " . htmlspecialchars($flash) . "</div>";
    }
    if ($flash_err !== '') {
        echo "<div class='flash err'>⚠ " . $flash_err . "</div>";
    }
}

function adminPageClose()
{
    ?>
<p class="foot-note">
    ※ 저장 즉시 각 계산기에 반영됩니다 (별도 배포 불필요). 「기본값으로 초기화」는 저장된 오버라이드를 삭제하고 코드의 기본값으로 되돌립니다.<br>
    ※ 수정된 값은 노란 배경으로 표시됩니다. 입력칸에 마우스를 올리면 기본값이 보입니다. 비율 항목은 소수(0.15 = 15%)로 입력하세요.
</p>
</body>
</html>
    <?php
}
