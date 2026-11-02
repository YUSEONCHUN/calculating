<?php
// 매물 비교 — 중개사·컨설팅에서 받은 약국 매물 조건을 권리금 시세 모델로 평가·비교 (매수 약사용)
// 계산 로직은 goodwill.php(권리금 시세)와 동일: 지역 회수개월수 × 조제료 계수 + 피드백·설문 보정
// 매물 데이터는 개인 검토 자료라 서버에 저장하지 않고 브라우저(localStorage)에만 보관
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
authGuard(); // 입장 조건: 페이지별 최소 등급 (auth.php authPageLevels)

// ===== 반경 500m 약국 조회 API (경쟁 약국 확인용) =====
// 카카오 지오코딩(주소→좌표) + 카카오 로컬 카테고리 검색(PM9=약국, radius 500m)
if (isset($_GET['api']) && $_GET['api'] === 'nearby') {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/public_api.php';
    $addr = trim(isset($_GET['addr']) ? $_GET['addr'] : '');
    $kakao = envValue('KAKAO_REST_KEY');
    if ($addr === '') {
        echo json_encode(array('ok' => false, 'error' => '주소를 입력해 주세요.'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($kakao === '') {
        echo json_encode(array('ok' => false, 'error' => '서버에 카카오 키(KAKAO_REST_KEY)가 설정되지 않았습니다.'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $err = '';
    $coords = fetchKakaoCoords($kakao, $addr, $err);
    if ($coords === null) {
        echo json_encode(array('ok' => false, 'error' => $err), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $res = fetchParallel(array('p' => array(
        'url' => 'https://dapi.kakao.com/v2/local/search/category.json?category_group_code=PM9&radius=500&size=15&sort=distance'
            . '&x=' . $coords['x'] . '&y=' . $coords['y'],
        'headers' => array('Authorization: KakaoAK ' . $kakao),
        'timeout' => 10,
    )));
    $r = $res['p'];
    $j = $r['ok'] && $r['code'] === 200 ? json_decode($r['body'], true) : null;
    if (!isset($j['meta']['total_count'])) {
        echo json_encode(array('ok' => false, 'error' => '약국 검색에 실패했습니다. 잠시 후 다시 시도해 주세요.'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $names = array();
    foreach (array_slice(isset($j['documents']) ? $j['documents'] : array(), 0, 5) as $doc) {
        if (isset($doc['place_name'])) {
            $names[] = $doc['place_name'] . (isset($doc['distance']) ? '(' . $doc['distance'] . 'm)' : '');
        }
    }
    echo json_encode(array('ok' => true, 'count' => (int) $j['meta']['total_count'], 'names' => $names), JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 매물 상담기록 서버 동기화 =====
// 브라우저 저장분을 익명 토큰(client_id) 기준으로 미러링 — 시세 보정 원천 (전화번호·연락처는 받지 않음)
function lrEnsureTable($pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS listing_reports (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id VARCHAR(32) NOT NULL,
        local_id VARCHAR(10) NOT NULL,
        kind VARCHAR(6) NOT NULL DEFAULT 'exist',
        name VARCHAR(40) NOT NULL,
        source VARCHAR(40) NULL,
        cdate DATE NULL,
        region VARCHAR(12) NOT NULL,
        doctor VARCHAR(8) NOT NULL DEFAULT 'none',
        presc INT UNSIGNED NOT NULL,
        otc_daily DECIMAL(8,1) NOT NULL DEFAULT 0,
        rent INT UNSIGNED NOT NULL DEFAULT 0,
        deposit INT UNSIGNED NOT NULL DEFAULT 0,
        ask INT UNSIGNED NOT NULL DEFAULT 0,
        rating VARCHAR(6) NOT NULL DEFAULT '',
        model_fair INT UNSIGNED NOT NULL DEFAULT 0,
        model_profit INT NOT NULL DEFAULT 0,
        checks_json TEXT NULL,
        notes_json TEXT NULL,
        is_test TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_client (client_id, local_id),
        KEY idx_region (region, kind)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if (isset($_GET['api']) && $_GET['api'] === 'sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true);
    $client = preg_replace('/[^a-z0-9]/', '', isset($body['client']) ? (string) $body['client'] : '');
    $items = isset($body['listings']) && is_array($body['listings']) ? array_slice($body['listings'], 0, 50) : array();
    if ($client === '' || strlen($client) < 8) {
        echo json_encode(array('ok' => false));
        exit;
    }
    try {
        $pdo = getConnection();
        lrEnsureTable($pdo);
        $regions = array('seoul_core' => 1, 'seoul' => 1, 'metro' => 1, 'bigcity' => 1, 'local' => 1);
        $keep = array();
        // 구설치 보강 — rating 컬럼 (schema.sql 미적용 상태 대비)
        try {
            $col = $pdo->query("SHOW COLUMNS FROM listing_reports LIKE 'rating'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE listing_reports ADD COLUMN rating VARCHAR(6) NOT NULL DEFAULT '' AFTER ask");
            }
        } catch (Exception $e) {
        }
        $up = $pdo->prepare(
            "INSERT INTO listing_reports
               (client_id, local_id, kind, name, source, cdate, region, doctor, presc, otc_daily, rent, deposit, ask, rating, model_fair, model_profit, checks_json, notes_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE kind=VALUES(kind), name=VALUES(name), source=VALUES(source), cdate=VALUES(cdate),
               region=VALUES(region), doctor=VALUES(doctor), presc=VALUES(presc), otc_daily=VALUES(otc_daily),
               rent=VALUES(rent), deposit=VALUES(deposit), ask=VALUES(ask), rating=VALUES(rating), model_fair=VALUES(model_fair),
               model_profit=VALUES(model_profit), checks_json=VALUES(checks_json), notes_json=VALUES(notes_json)"
        );
        $saved = 0;
        foreach ($items as $it) {
            $lid = preg_replace('/[^a-z0-9]/', '', isset($it['id']) ? (string) $it['id'] : '');
            $name = mb_substr(trim(isset($it['name']) ? (string) $it['name'] : ''), 0, 40);
            $region = isset($it['region']) && isset($regions[$it['region']]) ? $it['region'] : '';
            $presc = (int) (isset($it['presc']) ? $it['presc'] : 0);
            if ($lid === '' || $name === '' || $region === '' || $presc <= 0 || $presc > 100000) {
                continue;
            }
            $cdate = isset($it['cdate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $it['cdate']) ? $it['cdate'] : null;
            $up->execute(array(
                $client, $lid,
                (isset($it['kind']) && $it['kind'] === 'new') ? 'new' : 'exist',
                $name,
                mb_substr(trim(isset($it['source']) ? (string) $it['source'] : ''), 0, 40),
                $cdate, $region,
                in_array(isset($it['doctor']) ? $it['doctor'] : '', array('over65', 'over75'), true) ? $it['doctor'] : 'none',
                $presc,
                round(max(0, min(9999, (float) (isset($it['otc']) ? $it['otc'] : 0))), 1),
                max(0, min(100000, (int) (isset($it['rent']) ? $it['rent'] : 0))),
                max(0, min(10000000, (int) (isset($it['deposit']) ? $it['deposit'] : 0))),
                max(0, min(10000000, (int) (isset($it['ask']) ? $it['ask'] : 0))),
                in_array(isset($it['rating']) ? $it['rating'] : '', array('great', 'good', 'soso', 'bad', 'out'), true) ? $it['rating'] : '',
                max(0, min(10000000, (int) (isset($it['model_fair']) ? $it['model_fair'] : 0))),
                max(-1000000, min(10000000, (int) (isset($it['model_profit']) ? $it['model_profit'] : 0))),
                json_encode(isset($it['checks']) && is_array($it['checks']) ? $it['checks'] : array(), JSON_UNESCAPED_UNICODE),
                json_encode(isset($it['notes']) && is_array($it['notes']) ? $it['notes'] : array(), JSON_UNESCAPED_UNICODE),
            ));
            $keep[] = $lid;
            $saved++;
        }
        // 브라우저에서 삭제된 매물은 서버에서도 제거 (미러링)
        if (count($keep) > 0) {
            $ph = implode(',', array_fill(0, count($keep), '?'));
            $st = $pdo->prepare("DELETE FROM listing_reports WHERE client_id = ? AND local_id NOT IN ($ph)");
            $st->execute(array_merge(array($client), $keep));
        } else {
            $st = $pdo->prepare('DELETE FROM listing_reports WHERE client_id = ?');
            $st->execute(array($client));
        }
        echo json_encode(array('ok' => true, 'saved' => $saved));
    } catch (Exception $e) {
        echo json_encode(array('ok' => false));
    }
    exit;
}

// 내 매물 로드 — 서버(listing_reports)에 동기화된 이 브라우저(client)의 매물을 반환 (localStorage 유실 시 복원·DB가 소스)
if (isset($_GET['api']) && $_GET['api'] === 'load') {
    header('Content-Type: application/json; charset=utf-8');
    $client = preg_replace('/[^a-z0-9]/', '', isset($_GET['client']) ? (string) $_GET['client'] : '');
    $out = array();
    if ($client !== '' && strlen($client) >= 8) {
        try {
            $pdo = getConnection();
            $st = $pdo->prepare(
                "SELECT local_id, kind, name, source, cdate, region, doctor, presc, otc_daily, rent, deposit, ask, rating, checks_json, notes_json
                   FROM listing_reports WHERE client_id = ? ORDER BY id LIMIT 50"
            );
            $st->execute(array($client));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $checks = json_decode($r['checks_json'], true);
                $notes = json_decode($r['notes_json'], true);
                $out[] = array(
                    'id' => $r['local_id'],
                    'kind' => $r['kind'], 'name' => $r['name'], 'source' => $r['source'], 'phone' => '',
                    'cdate' => $r['cdate'], 'region' => $r['region'], 'doctor' => $r['doctor'],
                    'presc' => (int) $r['presc'], 'otc' => (float) $r['otc_daily'],
                    'rent' => (int) $r['rent'], 'deposit' => (int) $r['deposit'], 'ask' => (int) $r['ask'],
                    'rating' => isset($r['rating']) ? $r['rating'] : '',
                    'memo' => '', 'checks' => is_array($checks) ? $checks : array(), 'notes' => is_array($notes) ? $notes : array(),
                );
            }
        } catch (Exception $e) {
            // 테이블 미생성 — 빈 목록
        }
    }
    echo json_encode(array('ok' => true, 'listings' => $out), JSON_UNESCAPED_UNICODE);
    exit;
}

// 예시 매물 (스키마 시드 가데이터) — 빈 화면에서 둘러보기용, 브라우저에 demo 플래그로 로드되어 동기화 제외
if (isset($_GET['api']) && $_GET['api'] === 'demo') {
    header('Content-Type: application/json; charset=utf-8');
    $out = array();
    try {
        $pdo = getConnection();
        $rows = $pdo->query(
            "SELECT local_id, kind, name, source, cdate, region, doctor, presc, otc_daily, rent, deposit, ask, rating, checks_json, notes_json
               FROM listing_reports WHERE client_id = 'seedtest0000000000' AND is_test = 1 ORDER BY id LIMIT 30"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $i => $r) {
            $checks = json_decode($r['checks_json'], true);
            $notes = json_decode($r['notes_json'], true);
            $out[] = array(
                'id' => 'd' . ($i + 1), 'demo' => true,
                'kind' => $r['kind'], 'name' => $r['name'], 'source' => $r['source'], 'phone' => '',
                'cdate' => $r['cdate'], 'region' => $r['region'], 'doctor' => $r['doctor'],
                'presc' => (int) $r['presc'], 'otc' => (float) $r['otc_daily'],
                'rent' => (int) $r['rent'], 'deposit' => (int) $r['deposit'], 'ask' => (int) $r['ask'],
                'rating' => isset($r['rating']) ? $r['rating'] : '',
                'memo' => '', 'checks' => is_array($checks) ? $checks : array(), 'notes' => is_array($notes) ? $notes : array(),
            );
        }
    } catch (Exception $e) {
        // 테이블 미생성 등 — 빈 목록
    }
    echo json_encode(array('ok' => true, 'listings' => $out), JSON_UNESCAPED_UNICODE);
    exit;
}

$gw_months = adminSetting('goodwill_region_months', array(
    'seoul_core' => array('p30' => 19.1, 'p50' => 24.3, 'p75' => 31.5),
    'seoul'      => array('p30' => 12.8, 'p50' => 16.3, 'p75' => 21.0),
    'metro'      => array('p30' => 14.9, 'p50' => 17.7, 'p75' => 22.8),
    'bigcity'    => array('p30' => 14.3, 'p50' => 21.3, 'p75' => 28.0),
    'local'      => array('p30' => 10.6, 'p50' => 15.8, 'p75' => 20.8),
));
$gw_presc_mult = adminSetting('goodwill_presc_mult', array(
    'under1000' => 0.75, 'b1000' => 1.0, 'b2500' => 1.1, 'b3500' => 1.2, 'over5000' => 1.25,
));
$gw = adminSetting('goodwill_weights', array(
    'otc_margin' => 0.35, 'work_days' => 26, 'deposit_threshold' => 5000, 'deposit_rate' => 0.05,
    'critical_profit' => 700, 'caution_profit' => 1000,
    'crit_quick' => 3000, 'crit_fair' => 5000, 'crit_high' => 8000,
    'doctor65' => 0.7, 'doctor75' => 0.5, 'ask_ratio' => 0.4, 'deal_ratio' => 0.6,
    'calib_min_n' => 30, 'calib_limit' => 0.10,
));
$calib_min_n = max(1, (int) $gw['calib_min_n']);
$calib_limit = max(0, (float) $gw['calib_limit']);

// 지역 보정: 실사용 피드백 + 실매물 호가 합산 (config.php 공용 — 표본 가드·한도 동일)
$calib = goodwillRegionCalib($calib_min_n, $calib_limit);
$survey_cal = surveyCalibration('goodwill');

// 매물 확인 체크리스트 — 매물 유형별 (exist: 기존 약국 인수 / new: 신규 자리 새 개국)
// 항목: [질문, 왜 물어야 하는지, 답 기록 예시] — 확인 체크 + 들은 내용 기록 입력칸이 함께 붙음
$CHECK_SETS = array(
    'exist' => array(
        '💰 수익 검증 (시세 계산 입력값의 근거 확인)' => array(
            'presc_proof'   => array('월 조제료를 최근 6개월 자료로 확인', '건강보험 청구내역·매출장 원본 — 구두 금액과 다르면 계산 결과 자체가 달라집니다', '예: 최근 6개월 평균 1,950만원'),
            'presc_detail'  => array('처방전 수와 처방전당 단가 확인', '조제료 총액만이 아니라 건수 추이(감소세 여부)까지', '예: 일 120건 · 건당 9,500원'),
            'otc_proof'     => array('OTC 일매출을 포스 자료로 확인', '월별 편차·계절성 포함', '예: 포스 월평균 520만원'),
            'sales_mix'     => array('카드·현금 매출 비중 확인', '신고 매출과 실제 매출의 차이 여부', '예: 카드 85% · 현금 15%'),
        ),
        '🏥 처방 원천 (권리금의 지속성)' => array(
            'doctor_age'    => array('메인 병·의원 의사 연령·진료과 확인', '65세 이상이면 시세 모델도 권리금을 30~50% 할인합니다', '예: 내과 58세 · 정형 45세'),
            'hosp_lease'    => array('메인 병원의 임대차 잔여기간·이전 계획 확인', '병원이 떠나면 처방전도 떠납니다', '예: 잔여 4년 · 이전 계획 없음'),
            'concentration' => array('단일 병원 처방 의존율 확인', '한 병원이 70%+ 면 리스크 — 처방전 출처 분포 요청', '예: 내과 1곳 80%'),
        ),
        '🏢 임대차·건물 (월세·보증금 입력값 검증)' => array(
            'lease_terms'   => array('임대차 승계 조건·잔여기간 확인', '월세·보증금이 시세 계산의 차감 항목 — 인상 예정이면 반영', '예: 승계 가능 · 잔여 3년'),
            'rent_raise'    => array('임대료 인상 예정·연 인상률 확인', '재계약 시점과 인상 상한', '예: 재계약 시 연 3%'),
            'mgmt_fee'      => array('관리비·부대비용 확인', '월세 외 고정비 — 월 환산수익에서 추가 차감', '예: 월 25만원'),
            'registry'      => array('등기부등본으로 건물주·권리관계 확인', '근저당 과다·신탁 건물 여부', '예: 근저당 5억 · 특이사항 없음'),
        ),
        '📄 권리금 조건' => array(
            'tax_invoice'   => array('권리금 세금계산서 발행 여부 확인', '발행 시 부가세 10%·상대 소득세 — 실부담이 달라집니다', '예: 발행 예정 (부가세 별도)'),
            'included'      => array('시설·집기 포함 범위 목록 확인', '조제대·전산·자동화기기 포함 여부를 서면으로', '예: 조제대·ATC 포함 · 포스 제외'),
            'inventory'     => array('재고 인수 조건·정산 방식 확인', '별도 정산인지 권리금 포함인지, 부실 재고 제외 기준', '예: 별도 정산 약 3,000만원'),
            'staff'         => array('직원 승계 여부·근속 확인', '퇴직금 승계 부담 여부', '예: 1명 승계 · 근속 5년'),
        ),
        '🧾 기타 실사' => array(
            'penalty'       => array('행정처분 이력 확인', '업무정지 이력은 양수인에게 승계될 수 있습니다', '예: 최근 3년 없음'),
            'competition'   => array('주변 경쟁 약국·개국 예정 확인', '인근 신축 상가·병원 이전 계획', '예: 반경 300m 약국 2곳'),
            'redevelop'     => array('재건축·재개발 등 상권 변화 확인', '정비구역 지정 여부', '예: 해당 없음'),
        ),
    ),
    'new' => array(
        '💰 수익 추정 (실적이 없으므로 근거 검증)' => array(
            'presc_basis'   => array('예상 월 조제료의 산출 근거 확인', '인근·입점 병의원 처방전 수 × 단가 — 컨설팅 추정치의 근거 자료를 요청하세요', '예: 내과 일 100건 기준 추정'),
            'hosp_confirm'  => array('병·의원 입점·개원 확정 여부 확인', '분양·임대차 계약 완료인지, 개원 예정일 — 확정 전이면 수익 추정 전체가 가정입니다', '예: 내과 계약 완료 · 10월 개원'),
            'presc_detail'  => array('예상 처방전 수와 처방전당 단가 확인', '진료과별 평균 처방전 수와 비교해 과대 추정 여부 검증', '예: 일 100건 · 건당 9,000원 가정'),
            'rx_share'      => array('인근 약국과의 처방전 분산 확인', '기존 약국이 가져갈 몫을 빼고 추정했는지', '예: 300m 내 약국 1곳 · 분산 20% 가정'),
        ),
        '🏥 처방 원천 (입점 병원의 지속성)' => array(
            'doctor_age'    => array('개원(예정) 의사 연령·진료과 확인', '65세 이상이면 시세 모델도 권리금을 30~50% 할인합니다', '예: 내과 42세'),
            'hosp_lease'    => array('병원의 임대차 기간·계약 조건 확인', '병원 계약이 짧으면 처방 기반이 불안정합니다', '예: 5년 계약 · 렌트프리 6개월'),
            'concentration' => array('단일 병원 의존 구조인지 확인', '입점 병원 1곳뿐이면 개원 지연·폐업 리스크가 그대로 전가됩니다', '예: 입점 2과 예정'),
        ),
        '🏢 임대차·건물' => array(
            'lease_terms'   => array('임대 조건·계약기간·렌트프리 확인', '신규 상가는 렌트프리·인테리어 지원 협상 여지가 큽니다', '예: 5년 · 렌트프리 3개월'),
            'rent_raise'    => array('임대료 인상 예정·연 인상률 확인', '재계약 시점과 인상 상한', '예: 연 2% 상한'),
            'mgmt_fee'      => array('관리비·부대비용 확인', '신축 상가는 관리비가 높은 경우가 많습니다', '예: 월 40만원'),
            'registry'      => array('등기부등본으로 건물주·권리관계 확인', '근저당 과다·신탁 건물 여부 — 신축 분양 상가 특히 주의', '예: 신탁 등기 · 확인 필요'),
            'exclusive'     => array('상가 내 약국 독점 조항 확인', '같은 건물에 약국 추가 입점을 막는 조항이 계약서에 있는지', '예: 독점 조항 있음 (계약서 7조)'),
        ),
        '📄 권리금·시설 조건' => array(
            'floor_premium' => array('바닥권리금·분양 프리미엄 확인', '신규 자리도 바닥권리금을 요구하는 사례가 많습니다 — 근거를 물어보세요', '예: 바닥권리금 5,000만원 요구'),
            'interior'      => array('인테리어·시설 조건 확인', '지원 여부, 원상복구 의무, 기존 시설 유무', '예: 지원 없음 · 원상복구 조항 있음'),
            'tax_invoice'   => array('권리금·시설비 세금계산서 발행 여부 확인', '발행 시 부가세 10% — 실부담이 달라집니다', '예: 발행 가능'),
        ),
        '🧾 기타 실사' => array(
            'competition'   => array('주변 경쟁 약국·개국 예정 확인', '인근 상가 약국 자리 분양 여부까지', '예: 옆 건물 약국 자리 분양 중'),
            'redevelop'     => array('재건축·재개발 등 상권 변화 확인', '정비구역 지정 여부', '예: 해당 없음'),
            'vacancy'       => array('상가 공실률·활성화 확인', '공실 많은 상가는 병원 개원 지연·이탈 위험이 큽니다', '예: 공실 30% · 2층 이상 비어있음'),
        ),
    ),
);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>매물 비교</title>
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
        h2.page-title { margin-top: 0; color: #0e7490; }
        .lead { color: #5f6368; font-size: 0.95em; margin-bottom: 0; line-height: 1.6; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; }
        .form-grid .full { grid-column: 1 / -1; }
        @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
        label { display: block; font-weight: bold; font-size: 0.85em; color: #374151; margin-bottom: 5px; }
        label .hint { font-weight: normal; color: #9ca3af; font-size: 0.9em; }
        input[type=text], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 1em; background: #fff; }
        input[type=text]:focus, select:focus { outline: none; border-color: #0e7490; box-shadow: 0 0 0 3px rgba(14,116,144,0.12); }
        .suffix-wrap { position: relative; }
        .suffix-wrap .suffix { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85em; pointer-events: none; }
        .suffix-wrap input { padding-right: 46px; text-align: right; }
        .btn-calc { width: 100%; margin-top: 18px; padding: 13px; background: #0e7490; color: #fff; border: none; border-radius: 8px; font-size: 1.05em; font-weight: bold; cursor: pointer; }
        .btn-calc:hover { background: #155e75; }
        .btn-ghost { width: 100%; margin-top: 8px; padding: 11px; background: #fff; color: #64748b; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.92em; font-weight: bold; cursor: pointer; display: none; }

        /* 상담 모드 (질문 가이드 위저드) */
        .mode-tabs { display: flex; gap: 8px; margin-bottom: 18px; }
        .mode-tab { flex: 1; padding: 11px 8px; border: 1.5px solid #d1d5db; border-radius: 10px; background: #fff; font-size: 0.92em; font-weight: bold; color: #64748b; cursor: pointer; }
        .mode-tab small { display: block; font-weight: normal; font-size: 0.78em; color: #9ca3af; margin-top: 2px; }
        .mode-tab.on { border-color: #0e7490; background: #ecfeff; color: #155e75; }
        .wiz-progress { display: flex; gap: 5px; margin-bottom: 14px; }
        .wiz-progress i { flex: 1; height: 6px; border-radius: 3px; background: #e2e8f0; }
        .wiz-progress i.done { background: #0e7490; }
        .wiz-step-head { margin-bottom: 4px; }
        .wiz-step-head .st { font-size: 1.02em; font-weight: bold; color: #0f172a; }
        .wiz-step-head .sd { font-size: 0.82em; color: #94a3b8; margin-top: 2px; line-height: 1.5; }
        .wiz-item { padding: 13px 0; border-bottom: 1px dashed #eef2f7; }
        .wiz-item:last-child { border-bottom: none; }
        .wiz-q { font-size: 0.92em; font-weight: bold; color: #1e293b; }
        .wiz-q .req { color: #dc2626; }
        .wiz-h { font-size: 0.78em; color: #94a3b8; line-height: 1.55; margin: 3px 0 8px; }
        .wiz-ck { display: inline-flex; align-items: center; gap: 7px; margin-top: 8px; padding: 7px 12px; border: 1.5px solid #d1d5db; border-radius: 8px; background: #fff; font-size: 0.82em; font-weight: bold; color: #64748b; cursor: pointer; user-select: none; }
        .wiz-ck input { accent-color: #0e7490; width: 15px; height: 15px; margin: 0; }
        .wiz-ck.on { border-color: #0e7490; background: #ecfeff; color: #155e75; }
        .wiz-nav { display: flex; gap: 10px; margin-top: 18px; }
        .wiz-btn { flex: 1; padding: 13px; border-radius: 8px; font-size: 1em; font-weight: bold; cursor: pointer; }
        .wiz-btn.main { background: #0e7490; color: #fff; border: none; }
        .wiz-btn.main:hover { background: #155e75; }
        .wiz-btn.ghost { background: #fff; color: #64748b; border: 1.5px solid #d1d5db; flex: 0 0 30%; }
        .wiz-btn.ghost:disabled { opacity: 0.4; cursor: not-allowed; }

        /* 매물 카드 */
        .lst-card { background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #0e7490; border-radius: 10px; padding: 18px 20px; margin-bottom: 14px; }
        .lst-head { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
        .lst-name { font-size: 1.06em; font-weight: bold; color: #0f172a; }
        .lst-src { font-size: 0.78em; color: #94a3b8; }
        .lst-badge { font-size: 0.72em; font-weight: bold; border-radius: 4px; padding: 2px 8px; }
        .b-cheap { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .b-fair { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .b-upper { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .b-high { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .b-crit { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
        .lst-nums { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px 16px; margin: 12px 0 4px; }
        .lst-num .k { font-size: 0.72em; color: #94a3b8; }
        .lst-num .v { font-size: 1.02em; font-weight: bold; color: #0f172a; font-variant-numeric: tabular-nums; }
        .lst-num .v.accent { color: #0e7490; }
        .lst-nums.sub { border-top: 1px dashed #eef2f7; padding-top: 8px; margin-top: 8px; }
        .lst-nums.sub .k { font-size: 0.7em; }
        .lst-nums.sub .v { font-size: 0.9em; color: #475569; }
        .invest-line { margin-top: 10px; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 8px 12px; font-size: 0.86em; color: #134e4a; }
        .invest-line b { font-size: 1.12em; color: #0f766e; }
        .invest-line .iv-sub { font-size: 0.84em; color: #64748b; }
        .lst-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; align-items: center; }
        .lst-actions button { padding: 7px 14px; border-radius: 7px; border: 1px solid #d1d5db; background: #fff; font-size: 0.8em; font-weight: bold; color: #374151; cursor: pointer; }
        .lst-actions button:hover { background: #f8fafc; }
        .lst-actions .danger { color: #b91c1c; }
        .lst-call { display: inline-block; padding: 7px 14px; border-radius: 7px; border: 1px solid #99f6e4; background: #f0fdfa; font-size: 0.8em; font-weight: bold; color: #0f766e; text-decoration: none; }
        .rate-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 12px; padding-top: 10px; border-top: 1px dashed #eef2f7; }
        .rate-label { font-size: 0.74em; color: #94a3b8; font-weight: bold; margin-right: 2px; }
        .rate-btn { padding: 6px 11px; border-radius: 14px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 0.78em; color: #94a3b8; cursor: pointer; }
        .rate-btn.on { font-weight: bold; }
        .lst-card.excluded { opacity: 0.55; border-left-color: #cbd5e1; }
        .lst-call:hover { background: #ccfbf1; }
        .ck-progress { flex: 1; min-width: 120px; display: flex; align-items: center; gap: 8px; }
        .ck-bar { flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
        .ck-bar i { display: block; height: 100%; background: #0e7490; }
        .ck-pct { font-size: 0.74em; color: #64748b; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .lst-memo { font-size: 0.82em; color: #64748b; margin-top: 8px; background: #f8fafc; border-radius: 6px; padding: 7px 10px; }
        .lst-warn { font-size: 0.8em; color: #b45309; margin-top: 8px; }

        /* 체크리스트 */
        .ck-wrap { display: none; margin-top: 14px; border-top: 1px dashed #e2e8f0; padding-top: 12px; }
        .ck-group { font-size: 0.8em; font-weight: bold; color: #0e7490; margin: 12px 0 4px; }
        .ck-item { padding: 8px 2px 10px; border-bottom: 1px solid #f8fafc; }
        .ck-row { display: flex; gap: 9px; align-items: flex-start; cursor: pointer; }
        .ck-row input { margin-top: 3px; accent-color: #0e7490; width: 16px; height: 16px; flex: 0 0 auto; }
        .ck-item .t { font-size: 0.86em; color: #1e293b; font-weight: bold; }
        .ck-item .h { font-size: 0.76em; color: #94a3b8; line-height: 1.5; }
        .ck-item.done .t { color: #94a3b8; text-decoration: line-through; }
        .ck-note { width: 100%; margin-top: 7px; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 0.82em; box-sizing: border-box; background: #fafbfc; }
        .ck-note:focus { outline: none; border-color: #0e7490; background: #fff; }
        /* 항목별 구조화 기록 필드 */
        .nf-wrap { margin-top: 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 9px 12px; }
        @media (max-width: 560px) { .nf-wrap { grid-template-columns: 1fr; } }
        .nf-field.full { grid-column: 1 / -1; }
        .nf-field .nl { font-size: 0.73em; color: #64748b; font-weight: bold; margin-bottom: 3px; display: block; }
        .nf-field input[type=text], .nf-field select { width: 100%; padding: 8px 10px; font-size: 0.85em; border: 1px solid #e2e8f0; border-radius: 7px; box-sizing: border-box; background: #fafbfc; }
        .nf-field input:focus, .nf-field select:focus { outline: none; border-color: #0e7490; background: #fff; }
        .nf-suffix { position: relative; }
        .nf-suffix .sfx { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.75em; pointer-events: none; }
        .nf-suffix input { padding-right: 38px; text-align: right; }
        .nf-multi { display: flex; flex-wrap: wrap; gap: 6px; }
        .nf-multi label { border: 1px solid #d1d5db; border-radius: 14px; padding: 4px 11px; font-size: 0.78em; cursor: pointer; color: #64748b; user-select: none; }
        .nf-multi label.on { border-color: #0e7490; background: #ecfeff; color: #155e75; font-weight: bold; }
        .nf-multi input { display: none; }
        .nf-range-row { display: flex; align-items: center; gap: 10px; }
        .nf-range-row input[type=range] { flex: 1; accent-color: #0e7490; }
        .nf-range-out { font-size: 0.82em; font-weight: bold; color: #155e75; white-space: nowrap; min-width: 150px; text-align: right; }
        .nf-cost-row, .nf-people-row { display: flex; gap: 6px; margin-bottom: 6px; align-items: center; }
        .nf-cost-row input, .nf-people-row input, .nf-cost-row select { padding: 7px 9px; font-size: 0.83em; border: 1px solid #e2e8f0; border-radius: 7px; background: #fafbfc; box-sizing: border-box; min-width: 0; }
        .nf-cost-row .c-name { flex: 1.2; } .nf-cost-row .c-amt { flex: 1; text-align: right; } .nf-cost-row select { flex: 0 0 62px; }
        .nf-people-row input { flex: 1; }
        .nf-mini-btn { padding: 6px 11px; font-size: 0.75em; border: 1px solid #d1d5db; background: #fff; border-radius: 6px; cursor: pointer; color: #374151; white-space: nowrap; }
        .nf-mini-btn.del { color: #b91c1c; padding: 6px 9px; flex: 0 0 auto; }
        .nf-total { font-size: 0.82em; font-weight: bold; color: #0e7490; margin-top: 2px; }
        .nf-addr-row { display: flex; gap: 6px; }
        .nf-addr-row input { flex: 1; }
        .nf-addr-result { font-size: 0.8em; color: #155e75; font-weight: bold; margin-top: 5px; line-height: 1.5; }
        .nf-addr-result.err { color: #b91c1c; }
        .b-kind { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        /* 비교 표 */
        .cmp-tbl { width: 100%; border-collapse: collapse; font-size: 0.84em; }
        .cmp-tbl th { padding: 9px 8px; color: #6b7280; font-size: 0.88em; border-bottom: 2px solid #f1f5f9; white-space: nowrap; text-align: center; }
        .cmp-tbl td { padding: 9px 8px; border-bottom: 1px solid #f8fafc; text-align: center; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .cmp-tbl td:first-child { text-align: left; font-weight: bold; }
        .cmp-tbl tr.best td { background: #f0fdfa; }
        .cmp-tbl tr.cmp-row { cursor: pointer; }
        .cmp-tbl tr.cmp-row:hover td { background: #f8fafc; }
        .cmp-tbl tr.cmp-row.best:hover td { background: #ccfbf1; }
        .lst-card { transition: box-shadow 0.3s, border-color 0.3s; }
        .lst-card.flash { border-color: #0e7490; box-shadow: 0 0 0 3px rgba(14,116,144,0.25); }
        .gap-lo { color: #1d4ed8; font-weight: bold; }
        .gap-ok { color: #047857; font-weight: bold; }
        .gap-up { color: #b45309; font-weight: bold; }
        .gap-hi { color: #b91c1c; font-weight: bold; }
        .tbl-scroll { overflow-x: auto; }
        .score { font-weight: 900; }
        .empty-note { text-align: center; color: #94a3b8; padding: 26px 0; font-size: 0.9em; }
        .foot-note { font-size: 0.8em; color: #9ca3af; margin: 20px 0 30px; line-height: 1.7; }
    </style>
    <link rel="stylesheet" href="theme.css?v=2">
</head>
<body>

<div class="search-box">
    <?php require_once __DIR__ . '/nav.php'; renderSideNav(); ?>
    <h2 class="page-title">🏷 매물 비교</h2>
    <p class="lead">
        <strong>상담 모드</strong>가 중개사·컨설팅에게 물어볼 질문을 순서대로 안내합니다 — 답을 받아 적으면서 확인한 항목을 체크하세요.
        저장하면 <a href="goodwill.php" style="color:#0e7490;">권리금 시세</a> 모델로 적정가를 계산해 <strong>호가와 비교</strong>하고,
        여러 매물을 나란히 놓고 어디가 합리적인지 보여줍니다. 매물 조건은 시세 개선을 위해 익명으로 서버에도 저장됩니다 (전화번호는 이 브라우저에만 보관).
    </p>
</div>

<div class="search-box">
    <div class="mode-tabs">
        <button type="button" class="mode-tab on" id="tab-wiz" onclick="setMode('wiz')">🗣 상담 모드 <small>질문 가이드 따라 입력</small></button>
        <button type="button" class="mode-tab" id="tab-quick" onclick="setMode('quick')">⌨ 빠른 입력 <small>조건을 한 번에</small></button>
    </div>

    <!-- 상담 모드: 중개사에게 물어볼 질문 순서대로 안내하며 기록 -->
    <div id="wiz-form">
        <div class="wiz-progress" id="wiz-progress"></div>
        <div id="wiz-body"></div>
        <div class="wiz-nav">
            <button type="button" class="wiz-btn ghost" id="wiz-prev" onclick="wizMove(-1)">← 이전</button>
            <button type="button" class="wiz-btn main" id="wiz-next" onclick="wizMove(1)">다음 →</button>
        </div>
    </div>

    <!-- 빠른 입력 -->
    <div id="quick-form" style="display:none;">
    <h3 style="margin: 0 0 14px; color: #0f172a; font-size: 1em;" id="form-title">➕ 매물 추가 <span class="hint" style="font-weight:normal; color:#9ca3af; font-size:0.8em;">— 중개사·컨설팅에서 받은 조건 그대로</span></h3>
    <div class="form-grid">
        <div>
            <label for="f-kind">매물 유형 *</label>
            <select id="f-kind">
                <option value="exist">기존 약국 인수 (운영 중 양수)</option>
                <option value="new">신규 자리 (새 개국·신축 상가)</option>
            </select>
        </div>
        <div>
            <label for="f-name">매물 이름 * <span class="hint">(구분용 별칭)</span></label>
            <input type="text" id="f-name" placeholder="예: OO동 내과 앞 약국" maxlength="30">
        </div>
        <div>
            <label for="f-source">출처 <span class="hint">(중개사·컨설팅명)</span></label>
            <input type="text" id="f-source" placeholder="예: A공인중개사" maxlength="30">
        </div>
        <div>
            <label for="f-phone">출처 전화번호 <span class="hint">(카드에서 바로 전화)</span></label>
            <input type="text" id="f-phone" inputmode="tel" placeholder="예: 02-1234-5678" maxlength="20">
        </div>
        <div>
            <label for="f-date">상담 일자</label>
            <input type="date" id="f-date">
        </div>
        <div>
            <label for="f-region">지역 (권역) *</label>
            <select id="f-region">
                <option value="seoul_core">강남 등 서울 핵심 상권</option>
                <option value="seoul" selected>서울 일반</option>
                <option value="metro">수도권 (경기·인천)</option>
                <option value="bigcity">광역시 (부산·대구 등)</option>
                <option value="local">지방 중소도시</option>
            </select>
        </div>
        <div>
            <label for="f-doctor">메인 병원 의사 연령</label>
            <select id="f-doctor">
                <option value="none">해당 없음·65세 미만</option>
                <option value="over65">65세 이상</option>
                <option value="over75">75세 이상</option>
            </select>
        </div>
        <div>
            <label for="f-presc">월 조제료 *</label>
            <div class="suffix-wrap"><input type="text" id="f-presc" inputmode="numeric" placeholder="예: 2,000"><span class="suffix">만원</span></div>
        </div>
        <div>
            <label for="f-otc">일반약(OTC) 일매출</label>
            <div class="suffix-wrap"><input type="text" id="f-otc" inputmode="numeric" placeholder="예: 20"><span class="suffix">만원</span></div>
        </div>
        <div>
            <label for="f-rent">월세 *</label>
            <div class="suffix-wrap"><input type="text" id="f-rent" inputmode="numeric" placeholder="예: 300"><span class="suffix">만원</span></div>
        </div>
        <div>
            <label for="f-deposit">보증금</label>
            <div class="suffix-wrap"><input type="text" id="f-deposit" inputmode="numeric" placeholder="예: 3,000"><span class="suffix">만원</span></div>
        </div>
        <div>
            <label for="f-ask">호가 권리금 * <span class="hint">(제시받은 금액)</span></label>
            <div class="suffix-wrap"><input type="text" id="f-ask" inputmode="numeric" placeholder="예: 25,000"><span class="suffix">만원</span></div>
        </div>
        <div>
            <label for="f-memo">메모</label>
            <input type="text" id="f-memo" placeholder="예: 병원 2곳, 재고 별도 정산" maxlength="80">
        </div>
    </div>
    <button type="button" class="btn-calc" id="save-btn" onclick="saveListing()">매물 추가</button>
    <button type="button" class="btn-ghost" id="cancel-btn" onclick="cancelEdit()">수정 취소</button>
    </div><!-- /quick-form -->
</div>

<div class="search-box">
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:4px;">
        <h3 style="margin:0; color: #0f172a; font-size: 1em;">📋 받은 매물 <span id="lst-count" style="color:#94a3b8; font-weight:normal; font-size:0.85em;"></span></h3>
        <select id="lst-sort" onchange="setSort(this.value)" style="width:auto; padding:7px 10px; font-size:0.82em; border:1px solid #d1d5db; border-radius:7px; background:#fff; margin-left:auto;">
            <option value="score">정렬: 종합점수순</option>
            <option value="ask_asc">호가 낮은순</option>
            <option value="ask_desc">호가 높은순</option>
            <option value="gap_asc">적정가 대비 낮은순 (싼 순)</option>
            <option value="gap_desc">적정가 대비 높은순</option>
            <option value="profit_desc">월 환산수익 높은순</option>
            <option value="payback_asc">호가 회수기간 짧은순</option>
            <option value="invest_asc">필요 투자금 적은순</option>
            <option value="rating">내 평가순</option>
            <option value="date_desc">상담일 최신순</option>
            <option value="added">등록순</option>
        </select>
    </div>
    <p style="font-size:0.8em; color:#94a3b8; margin: 0 0 14px;">배지는 호가가 시세 모델의 어느 구간에 있는지입니다 · 체크리스트로 중개사에게 확인한 항목을 기록하세요</p>
    <div id="lst-list"></div>
</div>

<div class="search-box" id="cmp-box" style="display:none;">
    <h3 style="margin: 0 0 4px; color: #0f172a; font-size: 1em;">⚖️ 매물 비교표 <span style="color:#94a3b8; font-weight:normal; font-size:0.8em;">— 종합점수순</span></h3>
    <p style="font-size:0.8em; color:#94a3b8; margin: 0 0 6px;">
        종합점수(100점) = 가격 매력 50 (호가가 적정가보다 낮을수록) + 수익성 30 (월 환산수익, 매물 간 상대평가) + 검증 완료율 20 (체크리스트).
        점수는 <b>참고용</b>이며 최종 판단은 실사 결과가 우선입니다.
    </p>
    <p id="cmp-note" style="font-size:0.8em; color:#b91c1c; margin: 0 0 12px;"></p>
    <div class="tbl-scroll"><table class="cmp-tbl" id="cmp-tbl"></table></div>
</div>

<p class="foot-note">
    ※ 적정가는 권리금 시세 모델(실거래 회수배수 기반)의 산출값으로 참고용입니다. 매물 조건·기록은 이 브라우저에 저장되고,
    권리금 시세 개선(지역별 호가 보정)을 위해 익명 식별자로 서버에도 백업됩니다 — 이름·연락처 등 개인정보는 수집하지 않으며 출처 전화번호는 서버로 전송되지 않습니다.
    조제료·월세 등 입력값은 반드시 체크리스트의 원자료 확인을 거친 뒤 신뢰하세요.
</p>

<script>
var STORE_KEY = 'listing_compare_v1';
var CALIB = <?= json_encode($calib) ?>;
var SURVEY_CAL = <?= json_encode($survey_cal) ?>;
var REGION_MONTHS = <?= json_encode($gw_months) ?>;
var GW = <?= json_encode($gw) ?>;
var PRESC_MULT = <?= json_encode($gw_presc_mult) ?>;
var CHECK_SETS = <?= json_encode($CHECK_SETS, JSON_UNESCAPED_UNICODE) ?>;
var KIND_NAMES = { exist: '기존 인수', 'new': '신규 자리' };
function kindGroups(kind) { return CHECK_SETS[kind === 'new' ? 'new' : 'exist']; }
function kindTotal(kind) { var n = 0, gs = kindGroups(kind); for (var g in gs) { n += Object.keys(gs[g]).length; } return n; }
function ckDef(kind, key) {
    var gs = kindGroups(kind);
    for (var g in gs) { if (gs[g][key]) { return gs[g][key]; } }
    return ['', '', ''];
}
var REGION_NAMES = { seoul_core: '서울 핵심', seoul: '서울 일반', metro: '수도권', bigcity: '광역시', local: '지방' };

var LISTINGS = [];
// 내 평가 — 후보지 선별용 (제외는 순위·비교표에서 빠지고 목록 맨 뒤로)
var RATINGS = [
    ['great', '😍 만족함', '#047857', '#ecfdf5', '#a7f3d0'],
    ['good', '👍 좋음', '#0e7490', '#ecfeff', '#a5f3fc'],
    ['soso', '😐 보통', '#64748b', '#f8fafc', '#e2e8f0'],
    ['bad', '👎 별로임', '#b45309', '#fffbeb', '#fde68a'],
    ['out', '🚫 제외', '#b91c1c', '#fef2f2', '#fecaca'],
];
var RATING_ORDER = { great: 6, good: 5, soso: 4, bad: 3, '': 2, out: 1 };
function ratingDef(key) {
    for (var i = 0; i < RATINGS.length; i++) { if (RATINGS[i][0] === key) { return RATINGS[i]; } }
    return null;
}
function setRating(id, key) {
    for (var i = 0; i < LISTINGS.length; i++) {
        if (LISTINGS[i].id === id) {
            LISTINGS[i].rating = LISTINGS[i].rating === key ? '' : key; // 같은 것 다시 누르면 해제
        }
    }
    persist();
    render();
}

var SORT_MODE = 'score';
try { SORT_MODE = localStorage.getItem('listing_sort') || 'score'; } catch (e) {}
function setSort(v) {
    SORT_MODE = v;
    try { localStorage.setItem('listing_sort', v); } catch (e) {}
    render();
}
try { LISTINGS = JSON.parse(localStorage.getItem(STORE_KEY) || '[]') || []; } catch (e) { LISTINGS = []; }
// 저장분 정규화 — 구버전 필드 보정 + 서버 왕복 시 빈 객체가 배열([])로 오는 것 교정 (기록 유실 방지)
function normalizeListing(l) {
    if (!l.kind) { l.kind = 'exist'; }
    if (!l.rating) { l.rating = ''; }
    if (!l.checks || l.checks instanceof Array) { l.checks = {}; }
    if (!l.notes || l.notes instanceof Array) { l.notes = {}; }
    for (var k in l.notes) {
        if (l.notes[k] instanceof Array) { l.notes[k] = {}; }
    }
    return l;
}
LISTINGS.forEach(normalizeListing);
var EDIT_ID = null;
var OPEN_CK = {}; // 체크리스트 펼침 상태 (화면 상태만, 저장 안 함)

// 서버 동기화 — 익명 토큰 기준 미러링 (저장·수정·삭제 1.2초 디바운스, 실패해도 로컬 사용엔 영향 없음)
var CLIENT_ID = (function () {
    try {
        var t = localStorage.getItem('listing_client_id');
        if (!t) {
            t = 'c' + Math.random().toString(16).slice(2, 14) + Date.now().toString(16);
            localStorage.setItem('listing_client_id', t);
        }
        return t;
    } catch (e) { return ''; }
})();
var SYNC_TIMER = null;
function syncSoon() {
    if (CLIENT_ID === '') { return; }
    clearTimeout(SYNC_TIMER);
    SYNC_TIMER = setTimeout(function () {
        var payload = LISTINGS.filter(function (l) { return !l.demo; }).map(function (l) {
            var m = modelCalc(l);
            return { id: l.id, kind: l.kind, name: l.name, source: l.source, cdate: l.cdate || '',
                region: l.region, doctor: l.doctor, presc: l.presc, otc: l.otc, rent: l.rent,
                deposit: l.deposit, ask: l.ask, rating: l.rating || '', model_fair: m.warning === 'critical' ? 0 : m.fair,
                model_profit: m.profit, checks: l.checks || {}, notes: l.notes || {} };
        });
        fetch('listings.php?api=sync', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, keepalive: true,
            body: JSON.stringify({ client: CLIENT_ID, listings: payload })
        }).catch(function () {});
    }, 800);
}

// 페이지 이탈 시 대기 중인 동기화 즉시 전송 (삭제 직후 이탈해도 서버 미러 유지)
window.addEventListener('pagehide', function () {
    if (CLIENT_ID === '' || SYNC_TIMER === null) { return; }
    clearTimeout(SYNC_TIMER);
    SYNC_TIMER = null;
    try {
        var payload = LISTINGS.filter(function (l) { return !l.demo; }).map(function (l) {
            var m = modelCalc(l);
            return { id: l.id, kind: l.kind, name: l.name, source: l.source, cdate: l.cdate || '',
                region: l.region, doctor: l.doctor, presc: l.presc, otc: l.otc, rent: l.rent,
                deposit: l.deposit, ask: l.ask, rating: l.rating || '', model_fair: m.warning === 'critical' ? 0 : m.fair,
                model_profit: m.profit, checks: l.checks || {}, notes: l.notes || {} };
        });
        navigator.sendBeacon('listings.php?api=sync',
            new Blob([JSON.stringify({ client: CLIENT_ID, listings: payload })], { type: 'application/json' }));
    } catch (e) {}
});

function persist() {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(LISTINGS)); } catch (e) {}
    syncSoon();
}
function todayStr() {
    var d = new Date();
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
}
function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function fmt(n) { return Math.round(n).toLocaleString('ko-KR'); }
function fmtEok(manwon) {
    if (manwon >= 10000) { var eok = manwon / 10000; return (eok >= 10 ? eok.toFixed(1) : eok.toFixed(2)) + '억'; }
    return fmt(manwon) + '만';
}
function num(id) { return parseFloat(String(document.getElementById(id).value).replace(/[^0-9.]/g, '')) || 0; }

function prescMultiplier(p) {
    if (p < 1000) return Number(PRESC_MULT.under1000);
    if (p < 2500) return Number(PRESC_MULT.b1000);
    if (p < 3500) return Number(PRESC_MULT.b2500);
    if (p < 5000) return Number(PRESC_MULT.b3500);
    return Number(PRESC_MULT.over5000);
}

// 권리금 시세 모델 — goodwill.php calc()와 동일 (렌더링만 없음)
function modelCalc(l) {
    var depositAdj = l.deposit > Number(GW.deposit_threshold) ? l.deposit * Number(GW.deposit_rate) / 12 : 0;
    var otcMonthly = l.otc * Number(GW.otc_margin) * Number(GW.work_days);
    var profit = l.presc + otcMonthly - (l.rent + depositAdj);

    var rm = REGION_MONTHS[l.region] || REGION_MONTHS.seoul;
    var pm = prescMultiplier(l.presc);
    var critP = Number(GW.critical_profit), cautP = Number(GW.caution_profit);
    var quick, fair, high, warning = null, ratio = 1;
    if (profit < critP) {
        quick = Number(GW.crit_quick); fair = Number(GW.crit_fair); high = Number(GW.crit_high);
        warning = 'critical';
    } else if (profit < cautP) {
        ratio = 0.5 + (profit - critP) / (cautP - critP) * 0.5;
        quick = Math.round(profit * rm.p30 * pm * ratio);
        fair  = Math.round(profit * rm.p50 * pm * ratio);
        high  = Math.round(profit * rm.p75 * pm * ratio);
        warning = 'caution';
    } else {
        quick = Math.round(profit * rm.p30 * pm);
        fair  = Math.round(profit * rm.p50 * pm);
        high  = Math.round(profit * rm.p75 * pm);
    }
    var doctorMult = l.doctor === 'over75' ? Number(GW.doctor75) : (l.doctor === 'over65' ? Number(GW.doctor65) : 1.0);
    if (warning !== 'critical' && doctorMult < 1.0) {
        quick = Math.round(quick * doctorMult); fair = Math.round(fair * doctorMult); high = Math.round(high * doctorMult);
    }
    var cal = CALIB[l.region] || { factor: 1.0 };
    if (warning !== 'critical' && cal.factor !== 1.0) {
        quick = Math.round(quick * cal.factor); fair = Math.round(fair * cal.factor); high = Math.round(high * cal.factor);
    }
    if (warning !== 'critical' && SURVEY_CAL && SURVEY_CAL.factor) {
        quick = Math.round(quick * SURVEY_CAL.factor); fair = Math.round(fair * SURVEY_CAL.factor); high = Math.round(high * SURVEY_CAL.factor);
    }
    return { profit: Math.round(profit), quick: quick, fair: fair, high: high, warning: warning };
}

// 호가 위치 배지: 시세 밴드 내 위치
function askBadge(ask, m) {
    if (m.warning === 'critical') { return { cls: 'b-crit', label: '수익 임계점 미달' }; }
    if (ask <= m.quick) { return { cls: 'b-cheap', label: '빠른매도가 이하' }; }
    if (ask <= m.fair) { return { cls: 'b-fair', label: '적정가 이하' }; }
    if (ask <= m.high) { return { cls: 'b-upper', label: '적정~높은 호가' }; }
    return { cls: 'b-high', label: '높은 호가 초과' };
}

// 필요 투자금 = 보증금 + 호가 권리금 + 재고(별도 정산 기록 시)
function investAmount(l) {
    var inv = 0;
    var n = l.notes && l.notes.inventory;
    if (n && !(n instanceof Array) && n.method === '별도 정산' && Number(n.amount) > 0) {
        inv = Number(n.amount);
    }
    return { total: (l.deposit || 0) + (l.ask || 0) + inv, inv: inv };
}

function ckDone(l) {
    var n = 0, gs = kindGroups(l.kind);
    for (var g in gs) {
        for (var k in gs[g]) { if (l.checks && l.checks[k]) { n++; } }
    }
    return n;
}

function saveListing() {
    var name = document.getElementById('f-name').value.trim();
    var kind = document.getElementById('f-kind').value;
    var presc = num('f-presc'), rent = num('f-rent'), ask = num('f-ask');
    if (name === '' || presc <= 0 || (kind !== 'new' && ask <= 0)) {
        alert(kind === 'new' ? '매물 이름과 예상 월 조제료는 필수입니다.' : '매물 이름, 월 조제료, 호가 권리금은 필수입니다.');
        return;
    }
    var data = {
        name: name, kind: kind,
        source: document.getElementById('f-source').value.trim(),
        phone: document.getElementById('f-phone').value.trim(),
        cdate: document.getElementById('f-date').value || todayStr(),
        region: document.getElementById('f-region').value,
        doctor: document.getElementById('f-doctor').value,
        presc: presc, otc: num('f-otc'), rent: rent, deposit: num('f-deposit'), ask: ask,
        memo: document.getElementById('f-memo').value.trim()
    };
    if (EDIT_ID !== null) {
        for (var i = 0; i < LISTINGS.length; i++) {
            if (LISTINGS[i].id === EDIT_ID) {
                data.id = EDIT_ID;
                data.checks = LISTINGS[i].checks || {};
                data.notes = LISTINGS[i].notes || {};
                LISTINGS[i] = data;
            }
        }
    } else {
        data.id = 'l' + (LISTINGS.length ? Math.max.apply(null, LISTINGS.map(function (x) { return parseInt(x.id.slice(1), 10); })) + 1 : 1);
        data.checks = {};
        data.notes = {};
        LISTINGS.push(data);
    }
    persist();
    cancelEdit();
    render();
}

function editListing(id) {
    var l = LISTINGS.filter(function (x) { return x.id === id; })[0];
    if (!l) { return; }
    setMode('quick'); // 수정은 빠른 입력 폼에서
    EDIT_ID = id;
    document.getElementById('f-kind').value = l.kind || 'exist';
    document.getElementById('f-name').value = l.name;
    document.getElementById('f-source').value = l.source;
    document.getElementById('f-phone').value = l.phone || '';
    document.getElementById('f-date').value = l.cdate || todayStr();
    document.getElementById('f-region').value = l.region;
    document.getElementById('f-doctor').value = l.doctor;
    document.getElementById('f-presc').value = l.presc || '';
    document.getElementById('f-otc').value = l.otc || '';
    document.getElementById('f-rent').value = l.rent || '';
    document.getElementById('f-deposit').value = l.deposit || '';
    document.getElementById('f-ask').value = l.ask || '';
    document.getElementById('f-memo').value = l.memo;
    document.getElementById('form-title').innerHTML = '✏️ 매물 수정 — ' + esc(l.name);
    document.getElementById('save-btn').textContent = '수정 저장';
    document.getElementById('cancel-btn').style.display = 'block';
    window.scrollTo(0, 0);
}

function cancelEdit() {
    EDIT_ID = null;
    ['f-name', 'f-source', 'f-phone', 'f-presc', 'f-otc', 'f-rent', 'f-deposit', 'f-ask', 'f-memo'].forEach(function (id) {
        document.getElementById(id).value = '';
    });
    document.getElementById('f-kind').value = 'exist';
    document.getElementById('f-region').value = 'seoul';
    document.getElementById('f-doctor').value = 'none';
    document.getElementById('f-date').value = todayStr();
    document.getElementById('form-title').innerHTML = '➕ 매물 추가 <span class="hint" style="font-weight:normal; color:#9ca3af; font-size:0.8em;">— 중개사·컨설팅에서 받은 조건 그대로</span>';
    document.getElementById('save-btn').textContent = '매물 추가';
    document.getElementById('cancel-btn').style.display = 'none';
}

function delListing(id) {
    var l = LISTINGS.filter(function (x) { return x.id === id; })[0];
    if (!l || !confirm('「' + l.name + '」 매물을 삭제할까요? 체크리스트 기록도 함께 지워집니다.')) { return; }
    LISTINGS = LISTINGS.filter(function (x) { return x.id !== id; });
    persist();
    render();
}

function toggleCk(id) {
    OPEN_CK[id] = !OPEN_CK[id];
    render();
}

function setCheck(id, key, on) {
    for (var i = 0; i < LISTINGS.length; i++) {
        if (LISTINGS[i].id === id) {
            LISTINGS[i].checks = LISTINGS[i].checks || {};
            LISTINGS[i].checks[key] = !!on;
        }
    }
    persist();
    render();
}

// ===== 항목별 구조화 기록 필드 =====
// notes[key] = 객체 {필드키: 값} — 구버전 문자열은 {memo: 문자열}로 자동 승격
// scope: 'c'(매물 카드) / 'w'(상담 모드) — 값 변경 즉시 저장, showIf 분기가 있는 셀렉트만 재렌더
var NOTE_FIELDS = {
    presc_detail: [
        { k: 'daily', t: 'num', label: '일 처방전 수', suffix: '건', ph: '예: 120' },
        { k: 'unit', t: 'num', label: '처방전당 단가', suffix: '원', ph: '예: 9500' },
    ],
    sales_mix: [ { k: 'card', t: 'pct2', label: '카드·현금 비중' } ],
    hosp_lease: [
        { k: 'remain', t: 'sel', label: '병원 임대차 잔여·이전 계획', opts: ['모름', '이전계획 없음', '잔여 1년', '잔여 2년', '잔여 3년', '잔여 4년', '잔여 5년', '잔여 6년', '잔여 7년', '잔여 8년', '잔여 9년', '잔여 10년 이상'] },
    ],
    concentration: [
        { k: 'dep', t: 'sel', label: '단일 병원 의존 여부', opts: ['모름', '의존 아님', '의존함'], rr: 1 },
        { k: 'dept', t: 'text', label: '의존 진료과', ph: '예: 내과', showIf: { f: 'dep', in: ['의존함'] } },
        { k: 'pct', t: 'num', label: '의존율', suffix: '%', ph: '예: 80', showIf: { f: 'dep', in: ['의존함'] } },
    ],
    lease_terms: [
        { k: 'succ', t: 'sel', label: '승계 여부', opts: ['모름', '승계 가능', '승계 불가', '조건부 가능'] },
        { k: 'remain', t: 'sel', label: '임대차 잔여기간', opts: ['모름', '1년', '2년', '3년', '4년', '5년', '6년', '7년', '8년', '9년', '10년 이상'] },
    ],
    rent_raise: [
        { k: 'plan', t: 'sel', label: '인상 예정 여부', opts: ['모름', '인상 예정 없음', '인상 예정'], rr: 1 },
        { k: 'when', t: 'sel', label: '인상 시점', opts: ['모름', '1개월 내', '3개월 내', '6개월 내', '1년 내', '18개월 내', '2년 내'], showIf: { f: 'plan', in: ['인상 예정'] } },
        { k: 'next', t: 'num', label: '인상 예정치', suffix: '%', ph: '예: 3', showIf: { f: 'plan', in: ['인상 예정'] } },
        { k: 'past', t: 'num', label: '이전(직전) 인상률', suffix: '%', ph: '예: 2' },
    ],
    mgmt_fee: [ { k: 'items', t: 'costs', label: '관리·부대비용 (항목 추가/삭제 가능)' } ],
    registry: [
        { k: 'flags', t: 'multi', label: '등기부 확인 사항', opts: ['근저당 있음', '신탁 등기', '압류·가압류 있음', '특이사항 없음', '확인 못함'] },
    ],
    tax_invoice: [ { k: 'issue', t: 'sel', label: '세금계산서 발행', opts: ['모름', '발행 가능', '발행 불가', '협의 필요'] } ],
    included: [
        { k: 'inc', t: 'sel', label: '시설·집기 포함 여부', opts: ['모름', '포함', '일부 포함', '미포함'], rr: 1 },
        { k: 'items', t: 'multi', label: '포함 물품', opts: ['조제대', '자동조제기(ATC)', '전산·포스', '조제 선반·진열장', '냉장고', '인테리어 일체', '간판', '기타'], showIf: { f: 'inc', in: ['포함', '일부 포함'] } },
    ],
    inventory: [
        { k: 'method', t: 'sel', label: '재고 인수·정산 방식', opts: ['모름', '별도 정산', '권리금 포함', '인수 안 함'], rr: 1 },
        { k: 'amount', t: 'num', label: '대략적인 재고 비용', suffix: '만원', ph: '예: 3000', showIf: { f: 'method', in: ['별도 정산', '권리금 포함'] } },
    ],
    staff: [
        { k: 'succ', t: 'sel', label: '직원 승계 여부', opts: ['모름', '승계 가능', '승계 불가', '해당 없음(직원 없음)'], rr: 1 },
        { k: 'people', t: 'rows', label: '인원별 특이사항', showIf: { f: 'succ', in: ['승계 가능', '승계 불가'] } },
    ],
    penalty: [
        { k: 'has', t: 'sel', label: '행정처분 이력', opts: ['모름·제공 거부', '없음', '있음'], rr: 1 },
        { k: 'detail', t: 'text', label: '처분 내용', ph: '예: 2023년 업무정지 15일', showIf: { f: 'has', in: ['있음'] } },
    ],
    competition: [
        { k: 'addr', t: 'addr500', label: '주소로 반경 500m 약국 조회' },
        { k: 'cnt', t: 'num', label: '주변 경쟁 약국 수', suffix: '곳', ph: '예: 2' },
    ],
    redevelop: [
        { k: 'type', t: 'sel', label: '상권 변화 유형', opts: ['모름', '해당 없음', '재개발 예정', '재건축 예정', '정비구역 지정', '신축 상가 예정', '인근 병원 이전 예정'], rr: 1 },
        { k: 'detail', t: 'text', label: '상세 정보 (구역명·시기·근거)', ph: '예: OO구역 2027년 착공 예정 (조합설립인가)', showIf: { f: 'type', in: ['재개발 예정', '재건축 예정', '정비구역 지정', '신축 상가 예정', '인근 병원 이전 예정'] } },
    ],
};
var COST_PRESET = [['관리비', '', '월'], ['전기세', '', '월'], ['수도세', '', '월']];

function noteObj(store, key) {
    var v = store[key];
    if (typeof v === 'string') { v = v !== '' ? { memo: v } : {}; store[key] = v; }
    if (!v || typeof v !== 'object') { v = {}; store[key] = v; }
    return v;
}
function nfListing(id) { return LISTINGS.filter(function (x) { return x.id === id; })[0]; }
function nfStore(scope, id) { return scope === 'w' ? WDATA.notes : (nfListing(id) || { notes: {} }).notes; }
function nfPersist(scope) { if (scope === 'w') { wizDraftSave(); } else { persist(); } }
function nfRerender(scope) { if (scope === 'w') { wizRender(); } else { render(); } }

function nfSet(scope, id, key, fk, val, rr) {
    var o = noteObj(nfStore(scope, id), key);
    o[fk] = typeof val === 'string' ? val.trim() : val;
    nfPersist(scope);
    if (rr) { nfRerender(scope); }
}
function nfSetNum(scope, id, key, fk, raw) {
    var n = parseFloat(String(raw).replace(/[^0-9.]/g, ''));
    nfSet(scope, id, key, fk, isNaN(n) ? '' : n, 0);
}
function nfToggleMulti(scope, id, key, fk, opt, el) {
    var o = noteObj(nfStore(scope, id), key);
    var arr = o[fk] instanceof Array ? o[fk] : (o[fk] = []);
    var idx = arr.indexOf(opt);
    if (idx >= 0) { arr.splice(idx, 1); } else { arr.push(opt); }
    el.classList.toggle('on', idx < 0);
    nfPersist(scope);
}

// 관리·부대비용: [항목명, 금액(만원), 월/년] — 월 환산 합계 표시
function nfCostMonthly(items) {
    var sum = 0;
    (items || []).forEach(function (r) {
        var a = parseFloat(r[1]) || 0;
        sum += r[2] === '년' ? a / 12 : a;
    });
    return sum;
}
function nfCostSet(scope, id, key, idx, col, val) {
    var o = noteObj(nfStore(scope, id), key);
    if (!o.items || !o.items[idx]) { return; }
    o.items[idx][col] = col === 1 ? String(val).replace(/[^0-9.]/g, '') : val;
    nfPersist(scope);
    var t = document.getElementById('nf-total-' + scope + '-' + key);
    if (t) { t.textContent = '월 환산 합계: ' + Math.round(nfCostMonthly(o.items) * 10) / 10 + '만원'; }
}
function nfCostAdd(scope, id, key) {
    var o = noteObj(nfStore(scope, id), key);
    o.items = o.items || COST_PRESET.map(function (r) { return r.slice(); });
    o.items.push(['', '', '월']);
    nfPersist(scope); nfRerender(scope);
}
function nfCostDel(scope, id, key, idx) {
    var o = noteObj(nfStore(scope, id), key);
    if (o.items) { o.items.splice(idx, 1); }
    nfPersist(scope); nfRerender(scope);
}
function nfRowSet(scope, id, key, idx, col, val) {
    var o = noteObj(nfStore(scope, id), key);
    if (o.people && o.people[idx]) { o.people[idx][col] = String(val).trim(); nfPersist(scope); }
}
function nfRowAdd(scope, id, key) {
    var o = noteObj(nfStore(scope, id), key);
    (o.people = o.people || []).push(['', '']);
    nfPersist(scope); nfRerender(scope);
}
function nfRowDel(scope, id, key, idx) {
    var o = noteObj(nfStore(scope, id), key);
    if (o.people) { o.people.splice(idx, 1); }
    nfPersist(scope); nfRerender(scope);
}
function nfSlide(val, out) {
    out.textContent = '카드 ' + val + '% · 현금 ' + (100 - val) + '%';
}

// 반경 500m 약국 조회 (경쟁 약국)
function nfNearby(scope, id, key, btn) {
    var wrap = btn.closest('.nf-field');
    var addr = wrap.querySelector('input').value.trim();
    var out = wrap.querySelector('.nf-addr-result');
    if (addr === '') { out.className = 'nf-addr-result err'; out.textContent = '주소를 입력해 주세요.'; return; }
    nfSet(scope, id, key, 'addr', addr, 0);
    btn.disabled = true; out.className = 'nf-addr-result'; out.textContent = '조회 중…';
    fetch('listings.php?api=nearby&addr=' + encodeURIComponent(addr))
        .then(function (r) { return r.json(); })
        .then(function (j) {
            btn.disabled = false;
            if (!j.ok) { out.className = 'nf-addr-result err'; out.textContent = j.error || '조회 실패'; return; }
            out.textContent = '📍 반경 500m 약국 ' + j.count + '곳' + (j.names.length ? ' — ' + j.names.join(', ') : '');
            var o = noteObj(nfStore(scope, id), key);
            o.found = j.count;
            if (o.cnt === undefined || o.cnt === '') { o.cnt = j.count; }
            nfPersist(scope); nfRerender(scope);
        })
        .catch(function () { btn.disabled = false; out.className = 'nf-addr-result err'; out.textContent = '네트워크 오류 — 다시 시도해 주세요.'; });
}

function nfFieldHtml(scope, id, key, f, obj) {
    var base = "'" + scope + "','" + id + "','" + key + "','" + f.k + "'";
    var val = obj[f.k] != null ? obj[f.k] : '';
    var full = (f.t === 'text' || f.t === 'pct2' || f.t === 'multi' || f.t === 'costs' || f.t === 'rows' || f.t === 'addr500');
    var h = '<div class="nf-field' + (full ? ' full' : '') + '"><span class="nl">' + esc(f.label) + '</span>';
    if (f.t === 'sel') {
        h += '<select onchange="nfSet(' + base + ', this.value, ' + (f.rr ? 1 : 0) + ')">';
        f.opts.forEach(function (o) { h += '<option' + (String(val) === o ? ' selected' : '') + '>' + esc(o) + '</option>'; });
        h += '</select>';
    } else if (f.t === 'num') {
        h += '<div class="nf-suffix"><input type="text" inputmode="numeric" placeholder="' + esc(f.ph || '') + '" value="' + esc(val) + '" onchange="nfSetNum(' + base + ', this.value)"><span class="sfx">' + esc(f.suffix || '') + '</span></div>';
    } else if (f.t === 'pct2') {
        var card = val === '' ? 80 : Number(val);
        h += '<div class="nf-range-row"><input type="range" min="0" max="100" step="5" value="' + card + '"'
            + ' oninput="nfSlide(this.value, this.nextElementSibling)"'
            + ' onchange="nfSet(' + base + ', Number(this.value), 0)">'
            + '<span class="nf-range-out">' + (val === '' ? '미기록 — 움직여서 기록' : '카드 ' + card + '% · 현금 ' + (100 - card) + '%') + '</span></div>';
    } else if (f.t === 'multi') {
        var arr = val instanceof Array ? val : [];
        h += '<div class="nf-multi">';
        f.opts.forEach(function (o) {
            h += '<label class="' + (arr.indexOf(o) >= 0 ? 'on' : '') + '" onclick="nfToggleMulti(' + base + ', \'' + o.replace(/'/g, '') + '\', this); return false;">' + esc(o) + '</label>';
        });
        h += '</div>';
    } else if (f.t === 'costs') {
        var items = obj.items instanceof Array ? obj.items : COST_PRESET.map(function (r) { return r.slice(); });
        obj.items = items;
        items.forEach(function (r, i) {
            var rb = "'" + scope + "','" + id + "','" + key + "'," + i;
            h += '<div class="nf-cost-row">'
                + '<input class="c-name" type="text" placeholder="항목" value="' + esc(r[0]) + '" onchange="nfCostSet(' + rb + ', 0, this.value)">'
                + '<input class="c-amt" type="text" inputmode="numeric" placeholder="금액(만원)" value="' + esc(r[1]) + '" onchange="nfCostSet(' + rb + ', 1, this.value)">'
                + '<select onchange="nfCostSet(' + rb + ', 2, this.value)"><option' + (r[2] !== '년' ? ' selected' : '') + '>월</option><option' + (r[2] === '년' ? ' selected' : '') + '>년</option></select>'
                + '<button type="button" class="nf-mini-btn del" onclick="nfCostDel(\'' + scope + '\', \'' + id + '\', \'' + key + '\', ' + i + ')">✕</button>'
                + '</div>';
        });
        h += '<button type="button" class="nf-mini-btn" onclick="nfCostAdd(\'' + scope + '\', \'' + id + '\', \'' + key + '\')">＋ 항목 추가</button>'
            + ' <span class="nf-total" id="nf-total-' + scope + '-' + key + '">월 환산 합계: ' + Math.round(nfCostMonthly(items) * 10) / 10 + '만원</span>';
    } else if (f.t === 'rows') {
        var people = obj.people instanceof Array ? obj.people : (obj.people = []);
        people.forEach(function (r, i) {
            var rb = "'" + scope + "','" + id + "','" + key + "'," + i;
            h += '<div class="nf-people-row">'
                + '<input type="text" placeholder="직책·이름 (예: 근무약사 A)" value="' + esc(r[0]) + '" onchange="nfRowSet(' + rb + ', 0, this.value)">'
                + '<input type="text" placeholder="특이사항 (예: 근속 5년, 퇴직금 승계)" value="' + esc(r[1]) + '" onchange="nfRowSet(' + rb + ', 1, this.value)">'
                + '<button type="button" class="nf-mini-btn del" onclick="nfRowDel(\'' + scope + '\', \'' + id + '\', \'' + key + '\', ' + i + ')">✕</button>'
                + '</div>';
        });
        h += '<button type="button" class="nf-mini-btn" onclick="nfRowAdd(\'' + scope + '\', \'' + id + '\', \'' + key + '\')">＋ 인원 추가</button>';
    } else if (f.t === 'addr500') {
        h += '<div class="nf-addr-row"><input type="text" placeholder="예: 서울 강남구 테헤란로 123" value="' + esc(obj.addr || '') + '">'
            + '<button type="button" class="nf-mini-btn" onclick="nfNearby(\'' + scope + '\', \'' + id + '\', \'' + key + '\', this)">🔍 조회</button></div>'
            + '<div class="nf-addr-result">' + (obj.found !== undefined ? '📍 최근 조회: 반경 500m 약국 ' + obj.found + '곳' : '') + '</div>';
    } else { // text
        h += '<input type="text" placeholder="' + esc(f.ph || '') + '" value="' + esc(val) + '" maxlength="120" onchange="nfSet(' + base + ', this.value, 0)">';
    }
    return h + '</div>';
}

function noteWidgetHtml(scope, id, kind, key, store) {
    var defs = NOTE_FIELDS[key];
    var obj = noteObj(store, key);
    if (!defs) { // 구조화 정의가 없는 항목(신규 전용 등)은 자유 기록
        defs = [{ k: 'memo', t: 'text', label: '들은 내용 기록', ph: ckDef(kind, key)[2] || '' }];
    } else {
        defs = defs.concat([{ k: 'memo', t: 'text', label: '기타 메모', ph: '추가로 들은 내용' }]);
    }
    var h = '<div class="nf-wrap">';
    defs.forEach(function (f) {
        if (f.showIf && f.showIf.in.indexOf(String(obj[f.showIf.f] || '')) < 0) { return; }
        h += nfFieldHtml(scope, id, key, f, obj);
    });
    return h + '</div>';
}

function ckHtml(l) {
    var html = '';
    var gs = kindGroups(l.kind);
    for (var g in gs) {
        html += '<div class="ck-group">' + esc(g) + '</div>';
        for (var k in gs[g]) {
            var item = gs[g][k];
            var on = !!(l.checks && l.checks[k]);
            html += '<div class="ck-item' + (on ? ' done' : '') + '">'
                + '<label class="ck-row"><input type="checkbox"' + (on ? ' checked' : '') + ' onchange="setCheck(\'' + l.id + '\', \'' + k + '\', this.checked)">'
                + '<span><span class="t">' + esc(item[0]) + '</span><br><span class="h">' + esc(item[1]) + '</span></span></label>'
                + noteWidgetHtml('c', l.id, l.kind, k, l.notes)
                + '</div>';
        }
    }
    return html;
}

function render() {
    var list = document.getElementById('lst-list');
    document.getElementById('lst-count').textContent = LISTINGS.length ? '— ' + LISTINGS.length + '건' : '';
    if (LISTINGS.length === 0) {
        list.innerHTML = '<div class="empty-note">아직 입력한 매물이 없습니다. 위에서 첫 매물을 추가하거나,<br><br>'
            + '<button type="button" class="nf-mini-btn" style="padding:10px 18px; font-size:0.95em;" onclick="loadDemo()">👀 예시 매물 20건 불러와서 둘러보기</button></div>';
        document.getElementById('cmp-box').style.display = 'none';
        return;
    }
    var demoCnt = LISTINGS.filter(function (l) { return l.demo; }).length;
    var demoBar = demoCnt > 0
        ? '<div style="font-size:0.8em; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:8px 12px; margin-bottom:12px;">'
          + '👀 예시 매물 ' + demoCnt + '건 표시 중 — 서버 저장·시세 보정에는 포함되지 않습니다. '
          + '<button type="button" class="nf-mini-btn" onclick="clearDemo()">예시 전체 삭제</button></div>'
        : '';

    var rows = LISTINGS.map(function (l) {
        var m = modelCalc(l);
        var noAsk = !(l.ask > 0); // 신규 자리 — 권리금 없음
        var badge = noAsk ? { cls: 'b-cheap', label: '권리금 없음' } : askBadge(l.ask, m);
        var gap = (!noAsk && m.fair > 0) ? (l.ask / m.fair - 1) * 100 : null;
        var payback = (!noAsk && m.profit > 0) ? l.ask / m.profit : null;
        var total = kindTotal(l.kind);
        var invest = investAmount(l);
        var done = ckDone(l);
        return { l: l, m: m, badge: badge, gap: gap, payback: payback, done: done, total: total, noAsk: noAsk, invest: invest };
    });

    // 2건 이상이면 종합점수로 대략적 순위 산정 (카드 배지·비교표 정렬 공용)
    var ranked = null;
    var inPlay = rows.filter(function (r) { return r.l.rating !== 'out'; }); // 제외 매물은 순위·비교표에서 제외
    if (inPlay.length >= 2) {
        var maxProfit = Math.max.apply(null, inPlay.map(function (r) { return Math.max(0, r.m.profit); }));
        inPlay.forEach(function (r) {
            // 가격 매력 50: 적정가 대비 갭 -30% → 50점, +30% → 0점 (선형) / 권리금 없음 = 만점
            var priceScore = r.m.warning === 'critical' ? 0
                : (r.gap === null ? 50 : Math.max(0, Math.min(50, 50 * (0.3 - r.gap / 100) / 0.6)));
            var profitScore = maxProfit > 0 ? Math.max(0, r.m.profit) / maxProfit * 30 : 0;
            var ckScore = r.done / r.total * 20;
            r.score = Math.round(priceScore + profitScore + ckScore);
        });
        ranked = inPlay.slice().sort(function (a, b) { return b.score - a.score; });
        ranked.forEach(function (r, i) { r.rank = i + 1; });
    }

    // 카드 정렬 — 선택 기준 (null 값은 뒤로)
    var sortSel = document.getElementById('lst-sort');
    if (sortSel && sortSel.value !== SORT_MODE) { sortSel.value = SORT_MODE; }
    var sorted = rows.slice();
    function nullsLast(v) { return v === null || v === undefined; }
    if (SORT_MODE === 'score' && ranked) {
        sorted = ranked.slice().concat(rows.filter(function (r) { return r.l.rating === 'out'; }));
    } else if (SORT_MODE === 'rating') {
        sorted.sort(function (a, b) { return RATING_ORDER[b.l.rating || ''] - RATING_ORDER[a.l.rating || '']; });
    } else if (SORT_MODE === 'ask_asc' || SORT_MODE === 'ask_desc') {
        sorted.sort(function (a, b) { return SORT_MODE === 'ask_asc' ? a.l.ask - b.l.ask : b.l.ask - a.l.ask; });
    } else if (SORT_MODE === 'gap_asc' || SORT_MODE === 'gap_desc') {
        sorted.sort(function (a, b) {
            if (nullsLast(a.gap)) { return 1; }
            if (nullsLast(b.gap)) { return -1; }
            return SORT_MODE === 'gap_asc' ? a.gap - b.gap : b.gap - a.gap;
        });
    } else if (SORT_MODE === 'profit_desc') {
        sorted.sort(function (a, b) { return b.m.profit - a.m.profit; });
    } else if (SORT_MODE === 'invest_asc') {
        sorted.sort(function (a, b) { return a.invest.total - b.invest.total; });
    } else if (SORT_MODE === 'payback_asc') {
        sorted.sort(function (a, b) {
            if (nullsLast(a.payback)) { return 1; }
            if (nullsLast(b.payback)) { return -1; }
            return a.payback - b.payback;
        });
    } else if (SORT_MODE === 'date_desc') {
        sorted.sort(function (a, b) { return String(b.l.cdate || '').localeCompare(String(a.l.cdate || '')); });
    }
    // 'added'·1건뿐인 score는 입력 순서 그대로

    // 매물 카드
    list.innerHTML = demoBar + sorted.map(function (r) {
        var l = r.l, m = r.m;
        var gapTxt = (m.warning === 'critical' || r.gap === null) ? '-'
            : (r.gap > 0 ? '+' : '') + r.gap.toFixed(0) + '%';
        return '<div class="lst-card' + (l.rating === 'out' ? ' excluded' : '') + '" id="card-' + l.id + '">'
            + '<div class="lst-head">'
            + '<span class="lst-name">' + esc(l.name) + '</span>'
            + (l.demo ? '<span class="lst-badge" style="background:#fffbeb; color:#92400e; border:1px solid #fde68a;">예시</span>' : '')
            + (function () { var rd = ratingDef(l.rating); return rd ? '<span class="lst-badge" style="color:' + rd[2] + '; background:' + rd[3] + '; border:1px solid ' + rd[4] + ';">' + rd[1] + '</span>' : ''; })()
            + (r.rank ? '<span class="lst-badge ' + (r.rank === 1 ? 'b-fair' : 'b-kind') + '">' + (r.rank === 1 ? '🏆 ' : '') + '비교 ' + r.rank + '위</span>' : '')
            + '<span class="lst-badge b-kind">' + (KIND_NAMES[l.kind] || '기존 인수') + '</span>'
            + '<span class="lst-badge ' + r.badge.cls + '">' + r.badge.label + '</span>'
            + '<span class="lst-src">' + esc(REGION_NAMES[l.region] || l.region) + (l.source ? ' · ' + esc(l.source) : '') + (l.cdate ? ' · 상담 ' + esc(l.cdate) : '') + '</span>'
            + '</div>'
            + '<div class="lst-nums">'
            + '<div class="lst-num"><div class="k">호가 권리금</div><div class="v">' + (r.noAsk ? '없음' : fmtEok(l.ask) + '원') + '</div></div>'
            + '<div class="lst-num"><div class="k">모델 적정가</div><div class="v accent">' + (m.warning === 'critical' ? '시설양도비 구간' : fmtEok(m.fair) + '원') + '</div></div>'
            + '<div class="lst-num"><div class="k">적정가 대비</div><div class="v">' + gapTxt + '</div></div>'
            + '<div class="lst-num"><div class="k">월 환산수익</div><div class="v">' + fmt(m.profit) + '만원</div></div>'
            + '<div class="lst-num"><div class="k">호가 회수기간</div><div class="v">' + (r.payback !== null ? r.payback.toFixed(1) + '개월' : '-') + '</div></div>'
            + '</div>'
            + '<div class="lst-nums sub">'
            + '<div class="lst-num"><div class="k">월 조제료</div><div class="v">' + fmt(l.presc) + '만원</div></div>'
            + '<div class="lst-num"><div class="k">OTC 일매출</div><div class="v">' + (l.otc > 0 ? fmt(l.otc) + '만원' : '-') + '</div></div>'
            + '<div class="lst-num"><div class="k">월세</div><div class="v">' + fmt(l.rent) + '만원</div></div>'
            + '<div class="lst-num"><div class="k">보증금</div><div class="v">' + (l.deposit > 0 ? fmtEok(l.deposit) + '원' : '-') + '</div></div>'
            + '<div class="lst-num"><div class="k">의사 연령</div><div class="v">' + (l.doctor === 'over75' ? '75세 이상' : (l.doctor === 'over65' ? '65세 이상' : '해당 없음')) + '</div></div>'
            + '</div>'
            + '<div class="invest-line">💼 필요 투자금 <b>' + fmtEok(r.invest.total) + '원</b>'
            + '<span class="iv-sub"> = 보증금 ' + fmtEok(l.deposit || 0) + ' + 권리금 ' + (r.noAsk ? '0' : fmtEok(l.ask)) + (r.invest.inv > 0 ? ' + 재고 ' + fmtEok(r.invest.inv) : '') + ' <span style="color:#cbd5e1;">(설비·인테리어·운영자금 별도)</span></span></div>'
            + (m.warning === 'critical' ? '<div class="lst-warn">⚠ 월 환산수익이 임계점(' + fmt(Number(GW.critical_profit)) + '만원) 미만 — 일반 권리금 거래가 어려운 조건입니다. 시설양도비 수준(' + fmt(Number(GW.crit_quick)) + '~' + fmt(Number(GW.crit_high)) + '만원)과 비교하세요.</div>' : '')
            + (m.warning === 'caution' ? '<div class="lst-warn">⚠ 수익이 임계점 부근이라 모델이 할인된 가격대를 적용했습니다.</div>' : '')
            + (l.memo ? '<div class="lst-memo">📝 ' + esc(l.memo) + '</div>' : '')
            + '<div class="rate-row">'
            + '<span class="rate-label">내 평가</span>'
            + RATINGS.map(function (rt) {
                var on = l.rating === rt[0];
                return '<button type="button" class="rate-btn' + (on ? ' on' : '') + '" '
                    + (on ? 'style="color:' + rt[2] + '; background:' + rt[3] + '; border-color:' + rt[4] + ';" ' : '')
                    + 'onclick="setRating(\'' + l.id + '\', \'' + rt[0] + '\')">' + rt[1] + '</button>';
            }).join('')
            + '</div>'
            + '<div class="lst-actions">'
            + (l.phone ? '<a class="lst-call" href="tel:' + esc(String(l.phone).replace(/[^0-9+\-]/g, '')) + '">📞 ' + esc(l.source || '출처') + ' ' + esc(l.phone) + '</a>' : '')
            + '<button type="button" onclick="toggleCk(\'' + l.id + '\')">✅ 체크리스트 ' + (OPEN_CK[l.id] ? '접기' : '열기') + '</button>'
            + '<button type="button" onclick="editListing(\'' + l.id + '\')">수정</button>'
            + '<button type="button" class="danger" onclick="delListing(\'' + l.id + '\')">삭제</button>'
            + '<span class="ck-progress"><span class="ck-bar"><i style="width:' + Math.round(r.done / r.total * 100) + '%"></i></span>'
            + '<span class="ck-pct">확인 ' + r.done + '/' + r.total + '</span></span>'
            + '</div>'
            + '<div class="ck-wrap" style="display:' + (OPEN_CK[l.id] ? 'block' : 'none') + ';">' + ckHtml(l) + '</div>'
            + '</div>';
    }).join('');

    // 비교표 (2건 이상)
    var box = document.getElementById('cmp-box');
    if (!ranked) {
        box.style.display = 'none';
        return;
    }
    box.style.display = 'block';
    var outCnt = rows.length - inPlay.length;
    document.getElementById('cmp-note').textContent = outCnt > 0 ? '🚫 제외로 평가한 매물 ' + outCnt + '건은 순위·비교에서 빠졌습니다.' : '';

    var tbl = '<tr><th>순위</th><th>매물</th><th>유형</th><th>지역</th><th>호가</th><th>필요 투자금</th><th>모델 적정가</th><th>적정가 대비</th><th>월 환산수익</th><th>회수기간</th><th>확인율</th><th>종합점수</th></tr>';
    ranked.forEach(function (r, i) {
        var gCls = r.gap === null ? 'gap-lo' : (r.gap <= -10 ? 'gap-lo' : (r.gap <= 10 ? 'gap-ok' : (r.gap <= 30 ? 'gap-up' : 'gap-hi')));
        tbl += '<tr class="cmp-row' + (i === 0 ? ' best' : '') + '" onclick="gotoCard(\'' + r.l.id + '\')" title="클릭하면 해당 매물 카드로 이동">'
            + '<td class="score">' + (i === 0 ? '🏆 ' : '') + r.rank + '위</td>'
            + '<td>' + esc(r.l.name) + '</td>'
            + '<td>' + (KIND_NAMES[r.l.kind] || '기존') + '</td>'
            + '<td>' + esc(REGION_NAMES[r.l.region] || '') + '</td>'
            + '<td>' + (r.noAsk ? '없음' : fmtEok(r.l.ask)) + '</td>'
            + '<td><b>' + fmtEok(r.invest.total) + '</b></td>'
            + '<td>' + (r.m.warning === 'critical' ? '<span style="color:#94a3b8;">임계점 미달</span>' : fmtEok(r.m.fair)) + '</td>'
            + '<td class="' + (r.m.warning === 'critical' ? '' : gCls) + '">' + ((r.m.warning === 'critical' || r.gap === null) ? '-' : (r.gap > 0 ? '+' : '') + r.gap.toFixed(0) + '%') + '</td>'
            + '<td>' + fmt(r.m.profit) + '만</td>'
            + '<td>' + (r.payback !== null ? r.payback.toFixed(1) + '개월' : '-') + '</td>'
            + '<td>' + Math.round(r.done / r.total * 100) + '%</td>'
            + '<td class="score">' + r.score + '</td>'
            + '</tr>';
    });
    document.getElementById('cmp-tbl').innerHTML = tbl;
}

// ===== 상담 모드 (질문 가이드 위저드) =====
// 체크리스트 항목이 곧 질문 가이드 — 물어보면서 답을 기록하고, 확인한 항목은 체크 + 들은 내용 기록
var REGION_OPTS = [['seoul_core', '강남 등 서울 핵심 상권'], ['seoul', '서울 일반'], ['metro', '수도권 (경기·인천)'], ['bigcity', '광역시 (부산·대구 등)'], ['local', '지방 중소도시']];
var DOCTOR_OPTS = [['none', '해당 없음·65세 미만'], ['over65', '65세 이상'], ['over75', '75세 이상']];
var KIND_OPTS = [['exist', '기존 약국 인수 (운영 중 양수)'], ['new', '신규 자리 (새 개국·신축 상가)']];

// 매물 유형에 따라 질문 세트가 달라짐 — 기존: 실적 검증 중심 / 신규: 추정 근거·입점 확정 중심
function buildWiz(kind) {
    var step1 = { t: '1 · 기본 정보', d: '매물 유형에 따라 확인 질문이 달라집니다.', items: [
        { type: 'select', key: 'kind', q: '어떤 매물인가요?', opts: KIND_OPTS, def: 'exist', h: '기존 약국 인수는 실적 검증, 신규 자리는 추정 근거 검증 중심으로 안내합니다' },
        { type: 'text', key: 'name', q: '매물 이름 (별칭)', ph: '예: OO동 내과 앞 약국', req: true },
        { type: 'text', key: 'source', q: '어디서 소개받은 매물인가요?', ph: '예: A공인중개사' },
        { type: 'text', key: 'phone', q: '그 업체(담당자) 전화번호는?', ph: '예: 02-1234-5678', h: '저장 후 매물 카드에서 바로 전화를 걸 수 있습니다' },
        { type: 'date', key: 'cdate', q: '상담 일자', h: '기본값은 오늘입니다' },
        { type: 'select', key: 'region', q: '지역(권역)은 어디인가요?', opts: REGION_OPTS, def: 'seoul' },
    ] };
    if (kind === 'new') {
        return [step1,
            { t: '2 · 💰 수익 추정', d: '실적이 없는 자리입니다. 예상 조제료의 근거를 집요하게 물어보세요.', items: [
                { type: 'money', key: 'presc', q: '예상 월 조제료는 얼마로 보나요?', ph: '예: 1,500', req: true, ck: 'presc_basis' },
                { type: 'money', key: 'otc', q: '예상 일반약(OTC) 일매출은?', ph: '예: 15' },
                { type: 'check', ck: 'hosp_confirm' },
                { type: 'check', ck: 'presc_detail' },
                { type: 'check', ck: 'rx_share' },
            ] },
            { t: '3 · 🏥 처방 원천', d: '입점 병원이 곧 수익의 전부입니다 — 확정 여부와 지속성을 확인하세요.', items: [
                { type: 'select', key: 'doctor', q: '개원(예정) 의사 연령은?', opts: DOCTOR_OPTS, def: 'none', h: '65세 이상이면 시세 모델이 권리금을 30~50% 할인합니다', ck: 'doctor_age' },
                { type: 'check', ck: 'hosp_lease' },
                { type: 'check', ck: 'concentration' },
            ] },
            { t: '4 · 🏢 임대차·건물', d: '신규 상가는 렌트프리·인테리어 지원 협상 여지가 큽니다.', items: [
                { type: 'money', key: 'rent', q: '월세는 얼마인가요?', ph: '예: 250', req: true },
                { type: 'money', key: 'deposit', q: '보증금은 얼마인가요?', ph: '예: 3,000' },
                { type: 'check', ck: 'lease_terms' },
                { type: 'check', ck: 'rent_raise' },
                { type: 'check', ck: 'mgmt_fee' },
                { type: 'check', ck: 'registry' },
                { type: 'check', ck: 'exclusive' },
            ] },
            { t: '5 · 📄 권리금·시설 조건', d: '신규 자리도 바닥권리금을 요구하는 경우가 많습니다 — 근거를 확인하세요.', items: [
                { type: 'money', key: 'ask', q: '바닥권리금·요구 권리금이 있나요?', ph: '없으면 비워두세요', ck: 'floor_premium' },
                { type: 'check', ck: 'interior' },
                { type: 'check', ck: 'tax_invoice' },
            ] },
            { t: '6 · 🧾 기타 실사·메모', d: '마지막 리스크 항목을 확인하고 저장하세요.', items: [
                { type: 'check', ck: 'competition' },
                { type: 'check', ck: 'redevelop' },
                { type: 'check', ck: 'vacancy' },
                { type: 'text', key: 'memo', q: '메모', ph: '예: 내과 10월 개원 예정, 렌트프리 협상 중' },
            ] },
        ];
    }
    return [step1,
        { t: '2 · 💰 수익 확인', d: '권리금 계산의 핵심 입력값입니다. 금액을 받아 적되, 반드시 자료 근거를 요청하세요.', items: [
            { type: 'money', key: 'presc', q: '월 조제료는 얼마라고 하나요?', ph: '예: 2,000', req: true, ck: 'presc_proof' },
            { type: 'money', key: 'otc', q: '일반약(OTC) 일매출은 얼마인가요?', ph: '예: 20', ck: 'otc_proof' },
            { type: 'check', ck: 'presc_detail' },
            { type: 'check', ck: 'sales_mix' },
        ] },
        { t: '3 · 🏥 처방 원천', d: '권리금의 지속성을 좌우합니다 — 병원이 떠나면 처방전도 떠납니다.', items: [
            { type: 'select', key: 'doctor', q: '메인 병·의원 의사 연령은?', opts: DOCTOR_OPTS, def: 'none', h: '65세 이상이면 시세 모델이 권리금을 30~50% 할인합니다', ck: 'doctor_age' },
            { type: 'check', ck: 'hosp_lease' },
            { type: 'check', ck: 'concentration' },
        ] },
        { t: '4 · 🏢 임대차·건물', d: '월세·보증금은 시세 계산의 차감 항목입니다. 인상 예정이면 인상 후 금액으로 적으세요.', items: [
            { type: 'money', key: 'rent', q: '월세는 얼마인가요?', ph: '예: 300', req: true },
            { type: 'money', key: 'deposit', q: '보증금은 얼마인가요?', ph: '예: 3,000' },
            { type: 'check', ck: 'lease_terms' },
            { type: 'check', ck: 'rent_raise' },
            { type: 'check', ck: 'mgmt_fee' },
            { type: 'check', ck: 'registry' },
        ] },
        { t: '5 · 📄 권리금 조건', d: '같은 호가라도 포함 범위·세금 조건에 따라 실부담이 달라집니다.', items: [
            { type: 'money', key: 'ask', q: '호가 권리금은 얼마인가요?', ph: '예: 25,000', req: true },
            { type: 'check', ck: 'tax_invoice' },
            { type: 'check', ck: 'included' },
            { type: 'check', ck: 'inventory' },
            { type: 'check', ck: 'staff' },
        ] },
        { t: '6 · 🧾 기타 실사·메모', d: '마지막 리스크 항목을 확인하고 저장하세요.', items: [
            { type: 'check', ck: 'penalty' },
            { type: 'check', ck: 'competition' },
            { type: 'check', ck: 'redevelop' },
            { type: 'text', key: 'memo', q: '메모', ph: '예: 병원 2곳, 재고 별도 정산' },
        ] },
    ];
}

var DRAFT_KEY = 'listing_wiz_draft_v1';
var WSTEP = 0;
var WDATA = { checks: {}, notes: {} };
try {
    var d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
    if (d && d.data) {
        WDATA = d.data;
        WDATA.checks = WDATA.checks || {};
        WDATA.notes = WDATA.notes || {};
        WSTEP = Math.min(5, d.step || 0);
    }
} catch (e) {}

function wizKind() { return WDATA.kind === 'new' ? 'new' : 'exist'; }
function wizSteps() { return buildWiz(wizKind()); }

function wizDraftSave() { try { localStorage.setItem(DRAFT_KEY, JSON.stringify({ step: WSTEP, data: WDATA })); } catch (e) {} }
function wizDraftClear() { try { localStorage.removeItem(DRAFT_KEY); } catch (e) {} }

function setMode(m) {
    document.getElementById('wiz-form').style.display = m === 'wiz' ? 'block' : 'none';
    document.getElementById('quick-form').style.display = m === 'wiz' ? 'none' : 'block';
    document.getElementById('tab-wiz').classList.toggle('on', m === 'wiz');
    document.getElementById('tab-quick').classList.toggle('on', m !== 'wiz');
}

function wizNoteHtml(ckey) {
    return noteWidgetHtml('w', '', wizKind(), ckey, WDATA.notes);
}

function wizItemHtml(it) {
    var kind = wizKind();
    var h = '<div class="wiz-item">';
    if (it.type === 'check') {
        var def = ckDef(kind, it.ck);
        var on = !!WDATA.checks[it.ck];
        h += '<div class="wiz-q">' + esc(def[0]) + '</div>'
            + '<div class="wiz-h">' + esc(def[1]) + '</div>'
            + wizNoteHtml(it.ck)
            + '<br><label class="wiz-ck' + (on ? ' on' : '') + '"><input type="checkbox" data-ck="' + it.ck + '"' + (on ? ' checked' : '') + '> 확인했음</label>';
    } else {
        h += '<div class="wiz-q">' + esc(it.q) + (it.req ? ' <span class="req">*</span>' : '') + '</div>';
        var hint = it.h || (it.ck ? ckDef(kind, it.ck)[1] : '');
        if (hint) { h += '<div class="wiz-h">' + esc(hint) + '</div>'; }
        var val = WDATA[it.key] != null ? WDATA[it.key] : '';
        if (it.type === 'select') {
            h += '<select id="wiz-f-' + it.key + '"' + (it.key === 'kind' ? ' onchange="wizKindChange(this.value)"' : '') + '>';
            it.opts.forEach(function (o) {
                h += '<option value="' + o[0] + '"' + ((val || it.def) === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
            });
            h += '</select>';
        } else if (it.type === 'money') {
            h += '<div class="suffix-wrap"><input type="text" id="wiz-f-' + it.key + '" inputmode="numeric" placeholder="' + esc(it.ph || '') + '" value="' + esc(val === 0 ? '' : val) + '"><span class="suffix">만원</span></div>';
        } else if (it.type === 'date') {
            h += '<input type="date" id="wiz-f-' + it.key + '" value="' + esc(val || todayStr()) + '">';
        } else {
            h += '<input type="text" id="wiz-f-' + it.key + '" placeholder="' + esc(it.ph || '') + '" value="' + esc(val) + '" maxlength="80">';
        }
        if (it.ck) {
            var on2 = !!WDATA.checks[it.ck];
            h += wizNoteHtml(it.ck)
                + '<br><label class="wiz-ck' + (on2 ? ' on' : '') + '"><input type="checkbox" data-ck="' + it.ck + '"' + (on2 ? ' checked' : '') + '> ' + esc(ckDef(kind, it.ck)[0]) + '</label>';
        }
    }
    return h + '</div>';
}

function wizKindChange(v) {
    WDATA.kind = v; // 유형이 바뀌면 이후 단계 질문 세트가 바뀜 (1단계에서만 선택 가능)
    wizDraftSave();
}

function wizRender() {
    var steps = wizSteps();
    var step = steps[WSTEP];
    document.getElementById('wiz-progress').innerHTML = steps.map(function (_, i) {
        return '<i class="' + (i <= WSTEP ? 'done' : '') + '"></i>';
    }).join('');
    document.getElementById('wiz-body').innerHTML =
        '<div class="wiz-step-head"><div class="st">' + esc(step.t) + ' <span style="font-weight:normal; color:#94a3b8; font-size:0.8em;">(' + (WSTEP + 1) + '/' + steps.length + ')</span>'
        + (WSTEP > 0 ? ' <span style="font-weight:normal; font-size:0.72em; color:#0e7490;">[' + (KIND_NAMES[wizKind()] || '') + ']</span>' : '') + '</div>'
        + '<div class="sd">' + esc(step.d) + '</div></div>'
        + step.items.map(wizItemHtml).join('');
    document.getElementById('wiz-prev').disabled = WSTEP === 0;
    document.getElementById('wiz-next').textContent = WSTEP === steps.length - 1 ? '💾 매물 저장' : '다음 →';
    // 확인 체크·기록은 즉시 저장 (다음/이전과 무관)
    document.querySelectorAll('#wiz-body input[type=checkbox][data-ck]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            WDATA.checks[cb.getAttribute('data-ck')] = cb.checked;
            cb.closest('.wiz-ck').classList.toggle('on', cb.checked);
            wizDraftSave();
        });
    });
}

function wizCollect(validate) {
    var step = wizSteps()[WSTEP];
    for (var i = 0; i < step.items.length; i++) {
        var it = step.items[i];
        if (it.type === 'check') { continue; }
        var el = document.getElementById('wiz-f-' + it.key);
        if (!el) { continue; }
        var v = el.value;
        if (it.type === 'money') {
            var n = parseFloat(String(v).replace(/[^0-9.]/g, '')) || 0;
            if (validate && it.req && n <= 0) {
                alert('「' + it.q + '」 항목은 필수입니다.');
                el.focus();
                return false;
            }
            WDATA[it.key] = n;
        } else {
            if (validate && it.req && String(v).trim() === '') {
                alert('「' + it.q + '」 항목은 필수입니다.');
                el.focus();
                return false;
            }
            WDATA[it.key] = it.type === 'select' ? v : String(v).trim();
        }
    }
    wizDraftSave();
    return true;
}

function wizMove(dir) {
    if (dir > 0) {
        if (!wizCollect(true)) { return; }
        if (WSTEP === wizSteps().length - 1) { wizSave(); return; }
        WSTEP++;
    } else {
        wizCollect(false);
        WSTEP = Math.max(0, WSTEP - 1);
    }
    wizDraftSave();
    wizRender();
    window.scrollTo(0, 0);
}

function wizSave() {
    var kind = wizKind();
    var data = {
        name: WDATA.name || '', kind: kind, source: WDATA.source || '', phone: WDATA.phone || '',
        cdate: WDATA.cdate || todayStr(),
        region: WDATA.region || 'seoul', doctor: WDATA.doctor || 'none',
        presc: Number(WDATA.presc) || 0, otc: Number(WDATA.otc) || 0,
        rent: Number(WDATA.rent) || 0, deposit: Number(WDATA.deposit) || 0,
        ask: Number(WDATA.ask) || 0, memo: WDATA.memo || '',
        checks: WDATA.checks || {}, notes: WDATA.notes || {}
    };
    if (data.name === '' || data.presc <= 0 || (kind !== 'new' && data.ask <= 0)) {
        alert(kind === 'new' ? '매물 이름과 예상 월 조제료는 필수입니다. 이전 단계를 확인해 주세요.'
            : '매물 이름, 월 조제료, 호가 권리금은 필수입니다. 이전 단계를 확인해 주세요.');
        return;
    }
    data.id = 'l' + (LISTINGS.length ? Math.max.apply(null, LISTINGS.map(function (x) { return parseInt(x.id.slice(1), 10); })) + 1 : 1);
    LISTINGS.push(data);
    persist();
    wizDraftClear();
    WSTEP = 0;
    WDATA = { checks: {}, notes: {} };
    wizRender();
    render();
    var list = document.getElementById('lst-list');
    if (list) { list.scrollIntoView(); }
}

// 금액 입력칸 콤마 표시 (표시만 — 파싱은 num()이 숫자만 추출)
['f-presc', 'f-otc', 'f-rent', 'f-deposit', 'f-ask'].forEach(function (id) {
    document.getElementById(id).addEventListener('blur', function () {
        var v = parseFloat(String(this.value).replace(/[^0-9.]/g, ''));
        if (v > 0) { this.value = v.toLocaleString('ko-KR'); }
    });
});

// 비교표 행 클릭 → 해당 매물 카드로 스크롤 + 잠시 강조
function gotoCard(id) {
    var el = document.getElementById('card-' + id);
    if (!el) { return; }
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add('flash');
    setTimeout(function () { el.classList.remove('flash'); }, 1600);
}

function loadDemo() {
    fetch('listings.php?api=demo')
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j.ok || !j.listings.length) { alert('예시 매물을 불러오지 못했습니다.'); return; }
            LISTINGS = LISTINGS.filter(function (l) { return !l.demo; }).concat(j.listings.map(normalizeListing)); // 중복 방지
            try { localStorage.setItem(STORE_KEY, JSON.stringify(LISTINGS)); } catch (e) {} // 동기화 없이 로컬만
            render();
        })
        .catch(function () { alert('네트워크 오류 — 다시 시도해 주세요.'); });
}

function clearDemo() {
    LISTINGS = LISTINGS.filter(function (l) { return !l.demo; });
    try { localStorage.setItem(STORE_KEY, JSON.stringify(LISTINGS)); } catch (e) {}
    render();
}

// 서버 저장분 복원 — 로컬(localStorage)에 없는 매물만 병합 (DB가 원본, 로컬이 최신)
function loadServer() {
    if (CLIENT_ID === '') { return; }
    fetch('listings.php?api=load&client=' + encodeURIComponent(CLIENT_ID))
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j.ok || !j.listings.length) { return; }
            var have = {};
            LISTINGS.forEach(function (l) { have[l.id] = 1; });
            var added = 0;
            j.listings.forEach(function (l) {
                if (!have[l.id]) { LISTINGS.push(normalizeListing(l)); added++; }
            });
            if (added > 0) {
                try { localStorage.setItem(STORE_KEY, JSON.stringify(LISTINGS)); } catch (e) {}
                render();
            }
        })
        .catch(function () {});
}

document.getElementById('f-date').value = todayStr();
wizRender();
render();
loadServer();

// 시연·자료 캡처용 URL 파라미터: ?demo=1 예시 자동 로드, ?focus=cards|cmp 해당 영역만 표시
(function () {
    try {
        var q = new URLSearchParams(location.search);
        if (q.get('demo') === '1' && LISTINGS.length === 0) { loadDemo(); }
        var focus = q.get('focus');
        if (focus) {
            document.querySelectorAll('.search-box').forEach(function (box, i) {
                if (i <= 1) { box.style.display = 'none'; } // 소개·입력 폼 숨김
            });
            if (focus === 'cmp') {
                var lst = document.getElementById('lst-list');
                if (lst) { lst.closest('.search-box').style.display = 'none'; }
            }
        }
    } catch (e) {}
})();
</script>
</body>
</html>
