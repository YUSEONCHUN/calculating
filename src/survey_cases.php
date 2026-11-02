<?php
// 적정성 설문 — 주제(권리금·급여·OTC 판매가)별 케이스 정의·테이블 (survey.php 설문 / admin_survey.php 집계 공용)
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

/** 응답자 유형 (키 => 표시명) — 권리금 */
function gsRespTypes()
{
    return array(
        'buyer'  => '매수(인수) 검토 약사',
        'seller' => '매도(양도) 검토 약사',
        'pharm'  => '일반 약사',
        'broker' => '중개사·컨설턴트',
    );
}

// ===== 급여 시세 설문 =====
// 모델가 = salary_table 근무약사 월급여의 지역(시도) 중앙값 (만원 반올림, 2026-08 적재 기준 표본 20~134건)
// quick/high 는 중앙값 ±15% 밴드 — 관리자 집계의 "밴드 안" 판정에 사용
function gsSalaryCases()
{
    return array(
        'S1' => array('region' => '서울', 'fair' => 465, 'quick' => 395, 'high' => 535),
        'S2' => array('region' => '경기·인천', 'fair' => 528, 'quick' => 449, 'high' => 607),
        'S3' => array('region' => '부산', 'fair' => 567, 'quick' => 482, 'high' => 652),
        'S4' => array('region' => '충남', 'fair' => 514, 'quick' => 437, 'high' => 591),
        'S5' => array('region' => '경남', 'fair' => 602, 'quick' => 512, 'high' => 692),
        'S6' => array('region' => '경북', 'fair' => 701, 'quick' => 596, 'high' => 806),
    );
}

// ===== OTC 판매가 설문 =====
// 모델가 = otc_prices 전국 판매가 중앙값 (원, 제품별 표본 2,100~3,400건) / quick·high = ±15% 밴드
function gsOtcCases()
{
    // fair 는 전국 중앙값 (폴백용) — 설문에서는 응답자 소재 지역의 중앙값(표본 30건+)으로 대체된다
    return array(
        'O1' => array('region' => '전국', 'product' => '타이레놀이알서방정 650mg', 'pack' => '6정', 'db' => '타이레놀ER', 'fair' => 2800, 'quick' => 2380, 'high' => 3220),
        'O2' => array('region' => '전국', 'product' => '게보린정 300mg', 'pack' => '10정', 'db' => '게보린', 'fair' => 3500, 'quick' => 2975, 'high' => 4025),
        'O3' => array('region' => '전국', 'product' => '지르텍정 10mg', 'pack' => '10정', 'db' => '지르텍정', 'fair' => 5000, 'quick' => 4250, 'high' => 5750),
        'O4' => array('region' => '전국', 'product' => '까스활명수큐액 75mL', 'pack' => '1병', 'db' => '까스활명수큐액', 'fair' => 1200, 'quick' => 1020, 'high' => 1380),
        'O5' => array('region' => '전국', 'product' => '케토톱플라스타 30mg', 'pack' => '34매', 'db' => '케토톱플라스타', 'fair' => 12000, 'quick' => 10200, 'high' => 13800),
        'O6' => array('region' => '전국', 'product' => '아로나민골드정', 'pack' => '100정', 'db' => '아로나민골드', 'fair' => 32000, 'quick' => 27200, 'high' => 36800),
    );
}

/** 시도명 → 약사회 지부명 (otc_prices.jibu_name) */
function gsSidoJibu($sido)
{
    $map = array(
        '서울특별시' => '서울시약사회', '부산광역시' => '부산시약사회', '대구광역시' => '대구시약사회',
        '인천광역시' => '인천시약사회', '광주광역시' => '광주시약사회', '대전광역시' => '대전시약사회',
        '울산광역시' => '울산시약사회', '세종특별자치시' => '세종시약사회', '경기도' => '경기도약사회',
        '강원특별자치도' => '강원도약사회', '충청북도' => '충북약사회', '충청남도' => '충남약사회',
        '전북특별자치도' => '전북약사회', '전라남도' => '전남약사회', '경상북도' => '경북약사회',
        '경상남도' => '경남약사회', '제주특별자치도' => '제주도약사회',
    );
    return isset($map[$sido]) ? $map[$sido] : '';
}

/**
 * OTC 설문 케이스를 응답자 소재 지역 기준으로 재계산 — 지역 표본 30건+ 이면 지역 중앙값, 아니면 전국 폴백
 * 반환: case_key => array('fair' => int, 'label' => '서울특별시' | '전국 (지역 표본 부족)')
 */
function gsOtcRegionalFairs($sido)
{
    $cases = gsOtcCases();
    $out = array();
    $jibu = gsSidoJibu($sido);
    try {
        $pdo = getConnection();
        foreach ($cases as $ck => $c) {
            $fair = (int) $c['fair'];
            $label = '전국';
            if ($jibu !== '') {
                $st = $pdo->prepare('SELECT price FROM otc_prices WHERE product_name = ? AND jibu_name = ? AND price > 100 ORDER BY price');
                $st->execute(array($c['db'], $jibu));
                $ps = $st->fetchAll(PDO::FETCH_COLUMN);
                if (count($ps) >= 30) {
                    $fair = (int) $ps[(int) floor(count($ps) / 2)];
                    $label = $sido;
                } else {
                    $label = '전국 (지역 표본 부족)';
                }
            }
            $out[$ck] = array('fair' => $fair, 'label' => $label);
        }
    } catch (Exception $e) {
        foreach ($cases as $ck => $c) {
            $out[$ck] = array('fair' => (int) $c['fair'], 'label' => '전국');
        }
    }
    return $out;
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
        'salary' => array(
            'name' => '급여 시세',
            'title' => '근무약사 급여 적정성 설문',
            'lead' => '지역별 근무약사 조건 <b>6건</b>마다 조사 데이터의 시세 중앙값이 표시됩니다. ① 금액의 적정 여부를 고르고 ② 내가 생각하는 적정 월급여로 조정해 주세요. 약 2분 걸립니다. 응답은 익명으로 통계에만 사용됩니다.',
            'unit' => '만원', 'eok' => false, 'min' => 100, 'max' => 3000,
            'q1' => '이 조건의 적정 월급여(세전)는 얼마라고 보십니까?',
            'model_label' => '조사 데이터의 해당 지역 시세 중앙값',
            'q2' => '위 시세 금액은 어떻습니까?',
            'verdicts' => array('낮음' => '실제보다 낮다', '적정' => '', '높음' => '실제보다 높다'),
            'q3' => '내가 생각하는 적정 월급여',
            'verdict_adj' => array('낮음' => 1.15, '적정' => 1.0, '높음' => 0.85), // ±10% 적정 밴드 밖으로

            'note' => '※ 풀타임(주 40시간 내외) 근무약사 세전 월급여 기준, 금액 단위는 만원. 본 설문의 어떤 응답도 개인을 식별하지 않습니다.',
        ),
        'otc' => array(
            'name' => 'OTC 판매가',
            'title' => '일반약(OTC) 판매가 적정성 설문',
            'lead' => '대표 일반약 <b>6개 품목</b>마다 <b>약국 소재 지역</b>의 조사 시세 중앙값이 표시됩니다(지역 표본 부족 시 전국). ① 금액의 적정 여부를 고르고 ② 내가 생각하는 적정 판매가로 조정해 주세요. 약 2분 걸립니다. 응답은 익명으로 통계에만 사용됩니다.',
            'unit' => '원', 'eok' => false, 'min' => 100, 'max' => 500000,
            'q1' => '이 품목의 적정 약국 판매가는 얼마라고 보십니까?',
            'model_label' => '판매가 조사 시세 중앙값',
            'q2' => '위 시세 금액은 어떻습니까?',
            'verdicts' => array('쌈' => '실제보다 낮다', '적정' => '', '비쌈' => '실제보다 높다'),
            'q3' => '내가 생각하는 적정 판매가',
            'verdict_adj' => array('쌈' => 1.15, '적정' => 1.0, '비쌈' => 0.85), // ±10% 적정 밴드 밖으로

            'note' => '※ 소비자 판매가(부가세 포함) 기준, 금액 단위는 원. 본 설문의 어떤 응답도 개인을 식별하지 않습니다.',
        ),
    );
}

function gsTopicCases($topic)
{
    if ($topic === 'salary') {
        return gsSalaryCases();
    }
    if ($topic === 'otc') {
        return gsOtcCases();
    }
    return gsCases();
}

/** 주제별 응답자 유형 */
function gsTopicTypes($topic)
{
    if ($topic === 'salary') {
        return array(
            'owner'  => '개국약사 (고용주)',
            'staff'  => '근무약사',
            'seeker' => '구직·이직 준비 약사',
            'etc'    => '기타 (학생·컨설턴트 등)',
        );
    }
    if ($topic === 'otc') {
        return array(
            'owner'    => '개국약사',
            'staff'    => '근무약사',
            'consumer' => '일반 소비자',
            'etc'      => '기타',
        );
    }
    return gsRespTypes();
}

/** 케이스 조건표 행 (라벨, 표시값) — 설문 화면 렌더용 */
function gsCondRows($topic, $c)
{
    if ($topic === 'salary') {
        return array(
            array('지역', $c['region']),
            array('대상', '근무약사 (풀타임)'),
            array('근무시간', '주 40시간 내외'),
            array('기준', '세전 월급여'),
        );
    }
    if ($topic === 'otc') {
        return array(
            array('품목', $c['product']),
            array('포장', $c['pack']),
            array('기준 지역', $c['region']),
        );
    }
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
    if ($topic === 'salary') {
        return $c['region'] . ' · 풀타임 근무약사';
    }
    if ($topic === 'otc') {
        return $c['product'] . ' ' . $c['pack'];
    }
    return $c['region'] . ' · 조제 ' . number_format($c['presc']) . '만';
}

function gsEnsureTable($pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS goodwill_survey (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        topic VARCHAR(10) NOT NULL DEFAULT 'goodwill', -- goodwill|salary|otc
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
