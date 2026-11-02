<?php
// 적정성 설문 — 권리금 케이스 정의·테이블 (survey.php 설문 / admin_survey.php 집계 공용)
// 케이스는 권리금_비교_20건 세트에서 층화 추출: 지역(강남·서울·수도권·광역시·지방) × 규모(초소형~대형)
// model = 1번 모델(goodwill 권리금 시세)의 정밀 산출값 (만원): quick(빠른매도)/fair(적정 호가)/high(높은 호가)
// C4는 두 모델(계단 vs 곡선) 격차가 큰 소형 케이스 — 곡선형 채택 판단에도 활용

function gsCases()
{
    return array(
        'C1' => array(
            'region' => '강남 등 서울 핵심 상권', 'presc' => 2900, 'otc_daily' => 29, 'rent' => 460, 'deposit' => 8500,
            'quick' => 55255, 'fair' => 70325, 'high' => 90732,
        ),
        'C2' => array(
            'region' => '서울 일반', 'presc' => 1200, 'otc_daily' => 17.6, 'rent' => 330, 'deposit' => 3500,
            'quick' => 15376, 'fair' => 19560, 'high' => 25209,
        ),
        'C3' => array(
            'region' => '수도권 (경기·인천)', 'presc' => 1900, 'otc_daily' => 21.5, 'rent' => 280, 'deposit' => 2000,
            'quick' => 28259, 'fair' => 33581, 'high' => 43306,
        ),
        'C4' => array(
            'region' => '수도권 (경기·인천)', 'presc' => 800, 'otc_daily' => 13.3, 'rent' => 160, 'deposit' => 4000,
            'quick' => 6512, 'fair' => 7739, 'high' => 9980,
        ),
        'C5' => array(
            'region' => '광역시 (부산·대구 등)', 'presc' => 2700, 'otc_daily' => 34.2, 'rent' => 200, 'deposit' => 4000,
            'quick' => 38566, 'fair' => 57522, 'high' => 75498,
        ),
        'C6' => array(
            'region' => '지방 중소도시', 'presc' => 1900, 'otc_daily' => 27.2, 'rent' => 100, 'deposit' => 2000,
            'quick' => 21963, 'fair' => 32738, 'high' => 43098,
        ),
    );
}

/** 권리금 설문 응답자 유형 */
function gsRespTypes()
{
    return array(
        'buyer'  => '매수(인수) 검토 약사',
        'seller' => '매도(양도) 검토 약사',
        'pharm'  => '일반 약사',
        'broker' => '중개사·컨설턴트',
    );
}

/**
 * 주제별 설문 설정 (survey.php / admin_survey.php 공용)
 * verdicts: 낮음→적정→높음 순서 고정 (관리자 분포 막대의 파랑·초록·빨강 순서와 동일)
 */
function gsTopics()
{
    return array(
        'goodwill' => array(
            'name' => '권리금',
            'title' => '약국 권리금 적정성 설문',
            'lead' => '가상의 약국 조건 <b>6건</b>마다 산출 모델의 계산 결과가 표시됩니다. ① 금액의 적정 여부를 고르고 ② 내가 생각하는 적정 권리금으로 조정해 주세요. 약 2~3분 걸립니다. 응답은 익명으로 통계에만 사용됩니다.',
            'unit' => '만원', 'eok' => true, 'min' => 100, 'max' => 500000,
            'q1' => '이 약국의 적정 권리금은 얼마라고 보십니까?',
            'model_label' => '산출 모델이 계산한 적정 권리금',
            'q2' => '모델이 계산한 위 금액은 어떻습니까?',
            'verdicts' => array('쌈' => '실제보다 낮다', '적정' => '', '비쌈' => '실제보다 높다'),
            'q3' => '내가 생각하는 적정 권리금',
            'verdict_adj' => array('쌈' => 1.2, '적정' => 1.0, '비쌈' => 0.8), // 판정 선택 시 조정액 자동 반영 배율 (±20%)
            'note' => '※ 케이스의 조건과 금액은 조사용 가상 시나리오입니다. 월세·보증금·조제료는 월 기준, 금액 단위는 만원. 본 설문의 어떤 응답도 개인을 식별하지 않습니다.',
        ),
    );
}

function gsTopicCases($topic)
{
    return gsCases();   // 이 프로젝트는 권리금 단일 주제
}

/** 응답자 유형 (권리금 단일 주제) */
function gsTopicTypes($topic)
{
    return gsRespTypes();
}

/** 케이스 조건표 행 (라벨, 표시값) — 설문 화면 렌더용 */
function gsCondRows($topic, $c)
{
    return array(
        array('지역', $c['region']),
        array('월 조제료', number_format($c['presc']) . '만원'),
        array('일반약(OTC) 일매출', number_format($c['otc_daily']) . '만원'),
        array('월세', number_format($c['rent']) . '만원'),
        array('보증금', number_format($c['deposit']) . '만원'),
    );
}

/** 케이스 한 줄 요약 (관리자 매트릭스용) */
function gsCaseSummary($topic, $c)
{
    return $c['region'] . ' · 조제 ' . number_format($c['presc']) . '만';
}

function gsEnsureTable($pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS goodwill_survey (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        topic VARCHAR(10) NOT NULL DEFAULT 'goodwill', -- 이 프로젝트는 goodwill 단일
        submission VARCHAR(32) NOT NULL,      -- 응답자 1명의 묶음 키
        resp_type VARCHAR(10) NOT NULL,       -- gsTopicTypes() 키
        region VARCHAR(20) NOT NULL DEFAULT '',
        surveyor VARCHAR(40) NOT NULL DEFAULT '',
        case_key VARCHAR(10) NOT NULL,
        est_amount INT UNSIGNED NOT NULL,     -- ① 응답자 추정액 (주제 단위, 시세 공개 전 기입)
        model_amount INT UNSIGNED NOT NULL,   -- 제시된 모델·시세 금액 (주제 단위)
        adj_amount INT UNSIGNED NULL,         -- ③ 시세 공개 후 ± 조정액 (앵커링 있음 — 협상 범위 참고용)
        verdict VARCHAR(6) NOT NULL,          -- ② 판정: gsTopics() verdicts 키
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sub (submission),
        KEY idx_type (topic, resp_type, case_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 구설치 보강 (topic·adj_amount) — schema.sql 미적용 상태에서도 동작하도록
    try {
        $col = $pdo->query("SHOW COLUMNS FROM goodwill_survey LIKE 'topic'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE goodwill_survey ADD COLUMN topic VARCHAR(10) NOT NULL DEFAULT 'goodwill' AFTER id");
        }
        $col = $pdo->query("SHOW COLUMNS FROM goodwill_survey LIKE 'adj_amount'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE goodwill_survey ADD COLUMN adj_amount INT UNSIGNED NULL AFTER model_amount");
        }
    } catch (Exception $e) {
        // 권한 부족 등 — schema.sql 적용(deploy.sh)에 맡김
    }
}
