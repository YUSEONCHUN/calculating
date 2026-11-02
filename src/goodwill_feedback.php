<?php
// 권리금 시세 피드백 수집 API — 같은 사람(지문)·같은 날은 1건으로 갱신 (마음 바꾸기 허용)
// 저장 데이터는 goodwill.php 의 지역별 회수배수 자동 보정(표본 30건+, ±10% 한도)에 사용됨
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function respond($arr)
{
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

$valid_regions = array('seoul_core', 'seoul', 'metro', 'bigcity', 'local');
$valid_roles = array('매도', '매수');
$valid_doctor = array('none', 'over65', 'over75');

$role = isset($_POST['role']) ? $_POST['role'] : '';
$verdict = isset($_POST['verdict']) ? (int) $_POST['verdict'] : 99;
$region = isset($_POST['region']) ? $_POST['region'] : '';
$doctor_age = isset($_POST['doctor_age']) ? $_POST['doctor_age'] : 'none';
$suggested = isset($_POST['suggested_manwon']) && $_POST['suggested_manwon'] !== '' ? (int) $_POST['suggested_manwon'] : null;
$is_critical = isset($_POST['is_critical']) && $_POST['is_critical'] === '1' ? 1 : 0;
$correction = isset($_POST['correction']) ? (float) $_POST['correction'] : 1.0;

$intField = function ($key) {
    return isset($_POST[$key]) && $_POST[$key] !== '' ? max(0, min(2000000000, (int) $_POST[$key])) : null;
};
$presc = $intField('presc');
$otc_daily = $intField('otc_daily');
$rent = $intField('rent');
$deposit = $intField('deposit');
$calc_quick = $intField('calc_quick');
$calc_fair = $intField('calc_fair');
$calc_high = $intField('calc_high');

if (!in_array($role, $valid_roles, true) || !in_array($verdict, array(-1, 0, 1), true)
    || !in_array($region, $valid_regions, true) || !in_array($doctor_age, $valid_doctor, true)) {
    respond(array('ok' => false, 'error' => '잘못된 요청입니다'));
}
if ($suggested !== null && ($suggested <= 0 || $suggested > 2000000000)) {
    $suggested = null; // 비정상 제시값은 무시
}
if ($correction < 0.5 || $correction > 2.0) {
    $correction = 1.0;
}

// 일 단위 중복 방지 지문 (IP + UA + 날짜)
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$fp = hash('sha256', $ip . '|' . $ua . '|' . date('Y-m-d') . '|goodwill');

try {
    $pdo = getConnection();

    // 라이브에서 schema.sql 재실행 없이도 동작하도록 테이블 보장
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS goodwill_feedback (
          id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          role             VARCHAR(10) NOT NULL,
          verdict          TINYINT NOT NULL,
          suggested_manwon INT UNSIGNED NULL,
          region           VARCHAR(20) NOT NULL,
          presc            INT UNSIGNED NULL,
          otc_daily        INT UNSIGNED NULL,
          rent             INT UNSIGNED NULL,
          deposit          INT UNSIGNED NULL,
          doctor_age       VARCHAR(10) NULL,
          calc_quick       INT UNSIGNED NULL,
          calc_fair        INT UNSIGNED NULL,
          calc_high        INT UNSIGNED NULL,
          is_critical      TINYINT(1) NOT NULL DEFAULT 0,
          correction       DECIMAL(5,3) NOT NULL DEFAULT 1.000,
          fingerprint      VARCHAR(64) NULL,
          created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY idx_region (region, created_at),
          KEY idx_fp (fingerprint)
        ) ENGINE=InnoDB"
    );

    $stmt = $pdo->prepare('SELECT id FROM goodwill_feedback WHERE fingerprint = ? LIMIT 1');
    $stmt->execute(array($fp));
    $row = $stmt->fetch();

    if ($row) {
        // 오늘 이미 남긴 피드백 → 최신 의견으로 갱신
        $stmt = $pdo->prepare(
            'UPDATE goodwill_feedback SET role = ?, verdict = ?, suggested_manwon = ?, region = ?,
                    presc = ?, otc_daily = ?, rent = ?, deposit = ?, doctor_age = ?,
                    calc_quick = ?, calc_fair = ?, calc_high = ?, is_critical = ?, correction = ?
              WHERE id = ?'
        );
        $stmt->execute(array($role, $verdict, $suggested, $region,
            $presc, $otc_daily, $rent, $deposit, $doctor_age,
            $calc_quick, $calc_fair, $calc_high, $is_critical, $correction, (int) $row['id']));
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO goodwill_feedback
                    (role, verdict, suggested_manwon, region, presc, otc_daily, rent, deposit,
                     doctor_age, calc_quick, calc_fair, calc_high, is_critical, correction, fingerprint)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array($role, $verdict, $suggested, $region, $presc, $otc_daily, $rent, $deposit,
            $doctor_age, $calc_quick, $calc_fair, $calc_high, $is_critical, $correction, $fp));
    }
    respond(array('ok' => true));
} catch (Exception $e) {
    error_log('goodwill_feedback insert failed: ' . $e->getMessage());
    respond(array('ok' => false, 'error' => '저장에 실패했습니다'));
}
