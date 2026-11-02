<?php
// ===== 데모 회원·권한 공용 (가상 로그인) =====
// 입장 조건 3단계: ① 회원인가 ② 약사인가 ③ 개국약사인가 — 페이지별 최소 등급으로 접근 제한
// 회원 데이터는 site_members(데모 계정)에서 확인 — 추후 데일리팜 본사이트 회원 이관 시
// authUser()가 이관된 회원 정보를 읽도록 이 파일만 교체하면 나머지 페이지는 수정 불필요
require_once __DIR__ . '/config.php';

// 세션 (admin_common.php와 동일 저장소 — 중복 start 방지)
if (session_status() === PHP_SESSION_NONE) {
    $authSessDir = __DIR__ . '/.sessions';
    if (!is_dir($authSessDir)) {
        @mkdir($authSessDir, 0700, true);
    }
    if (is_dir($authSessDir) && is_writable($authSessDir)) {
        session_save_path($authSessDir);
    } else {
        session_save_path(sys_get_temp_dir());
    }
    session_start();
}

/** 등급 => 권한 레벨 (0 비회원 / 1 회원 / 2 약사 / 3 개국 준비 약사 / 4 개국약사 / 9 관리자) */
function authGrades()
{
    return array('member' => 1, 'pharmacist' => 2, 'prep' => 3, 'owner' => 4, 'admin' => 9);
}

function authGradeNames()
{
    return array('member' => '일반 회원', 'pharmacist' => '약사 회원(근무)', 'prep' => '개국 준비 약사', 'owner' => '개국약사 회원', 'admin' => '최고 관리자');
}

function authLevelNames()
{
    return array(0 => '비회원', 1 => '회원', 2 => '약사 회원', 3 => '개국 준비 약사', 4 => '개국약사 회원', 9 => '관리자');
}

/** 데모 회원 테이블 — 비어 있으면 가상 계정 시드 (추후 본사이트 회원 이관 대상 자리) */
function authEnsureMembers($pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_members (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        login_id VARCHAR(30) NOT NULL UNIQUE,
        name VARCHAR(30) NOT NULL,
        grade VARCHAR(12) NOT NULL,           -- member|pharmacist|owner|admin
        is_demo TINYINT(1) NOT NULL DEFAULT 1, -- 1=가상 계정 (이관 회원은 0)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // 개국 준비 신청 상태 컬럼 (구설치 보강)
    try {
        $col = $pdo->query("SHOW COLUMNS FROM site_members LIKE 'upgrade_status'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE site_members ADD COLUMN upgrade_status VARCHAR(10) NOT NULL DEFAULT '' AFTER grade");
            $pdo->exec("ALTER TABLE site_members ADD COLUMN requested_at TIMESTAMP NULL AFTER upgrade_status");
        }
    } catch (Exception $e) {
    }
    $n = (int) $pdo->query('SELECT COUNT(*) FROM site_members')->fetchColumn();
    if ($n === 0) {
        $st = $pdo->prepare('INSERT INTO site_members (login_id, name, grade) VALUES (?, ?, ?)');
        $st->execute(array('member1', '김일반', 'member'));
        $st->execute(array('pharm1', '이근무', 'pharmacist'));
        $st->execute(array('prep1', '최준비', 'prep'));
        $st->execute(array('owner1', '박개국', 'owner'));
        $st->execute(array('admin', '최고관리자', 'admin'));
    } else {
        // 기존 설치 보강 — 개국 준비 약사 데모 계정
        $pdo->exec("INSERT IGNORE INTO site_members (login_id, name, grade) VALUES ('prep1', '최준비', 'prep')");
    }
}

/** 현재 로그인 회원 (없으면 null) — 등급·신청 상태는 요청당 1회 DB에서 재동기화 (관리자 승인 즉시 반영) */
function authUser()
{
    static $synced = false;
    if (!isset($_SESSION['site_user']) || !is_array($_SESSION['site_user'])) {
        return null;
    }
    if (!$synced) {
        $synced = true;
        try {
            $pdo = getConnection();
            $st = $pdo->prepare('SELECT id, login_id, name, grade, upgrade_status FROM site_members WHERE id = ?');
            $st->execute(array((int) $_SESSION['site_user']['id']));
            $m = $st->fetch(PDO::FETCH_ASSOC);
            if ($m) {
                $_SESSION['site_user'] = $m;
            } else {
                unset($_SESSION['site_user']); // 삭제된 계정
                return null;
            }
        } catch (Exception $e) {
            // DB 오류 — 세션 값으로 계속
        }
    }
    return $_SESSION['site_user'];
}

/** 현재 권한 레벨 */
function authLevel()
{
    $u = authUser();
    if ($u === null) {
        return 0;
    }
    $g = authGrades();
    return isset($g[$u['grade']]) ? $g[$u['grade']] : 1;
}

/**
 * 페이지별 최소 입장 레벨 — 전체 리스트는 nav.php에 그대로 두고, 여기서 접근 조건만 관리
 * 0 공개 / 1 회원 / 2 약사 / 3 개국약사 (관리자 페이지는 기존 비밀번호 체계 유지)
 */
function authPageLevels()
{
    return array(
        // 공개
        'index.php' => 0, 'login.php' => 0,
        'goodwill_survey.php' => 0, 'survey.php' => 0,   // 조사원 배포용 설문 링크
        // 개국 준비 약사 — 권리금 도구
        'goodwill.php' => 3, 'premium.php' => 3, 'goodwill_parts.php' => 3,
        'premium_curve.php' => 3, 'goodwill_curve.php' => 3, 'listings.php' => 3,
    );
}

function authPageLevel($file)
{
    $map = authPageLevels();
    return isset($map[$file]) ? $map[$file] : 0;
}

/** 페이지 상단에서 호출 — 권한 미달이면 로그인 페이지로 */
function authGuard($file = null)
{
    if ($file === null) {
        $file = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    }
    $need = authPageLevel($file);
    if (authLevel() >= $need) {
        return;
    }
    header('Location: login.php?next=' . urlencode($file) . '&need=' . $need);
    exit;
}

/** 등급별 사용 가능한 '내 상황' 목록 — 전체 리스트(navPersonas)는 그대로 두고 여기서 제한
 *  모두보기 2종(기본=핵심 도구 / dev=개발 리스트)은 회원 이상 공통, 상황별 보기는 기존 등급 체계 유지 */
function authPersonaKeys()
{
    $lv = authLevel();
    if ($lv >= 9) {
        return null; // 관리자: 전체 (테스트 포함)
    }
    if ($lv >= 4) { // 개국약사: 운영·매도 포함 전체
        return array('all', 'dev', 'prep', 'acq', 'open', 'hire', 'work', 'move', 'rest', 'sell', 'custom');
    }
    if ($lv >= 3) { // 개국 준비 약사: 준비·인수 + 근무 상황
        return array('all', 'dev', 'prep', 'acq', 'work', 'move', 'rest', 'custom');
    }
    if ($lv >= 2) {
        return array('all', 'dev', 'work', 'move', 'rest', 'custom');
    }
    if ($lv >= 1) {
        return array('all', 'dev', 'custom');
    }
    return array('all'); // 비회원: 모두보기(기본)만
}
