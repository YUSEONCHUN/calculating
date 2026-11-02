<?php
// ===== 접근 제어 (비로그인 공개형) =====
// 이 프로젝트의 권리금 도구는 로그인 없이 누구나 사용한다.
// 관리자 페이지만 admin_common.php 의 비밀번호(.env DASHBOARD_PASSWORD)로 따로 보호된다.
//
// 원본(데일리팜 약국 툴)의 회원 등급 체계를 걷어낸 자리이며, 각 페이지가 호출하는
// authGuard() / authLevel() / authPersonaKeys() 를 그대로 유지해 페이지 수정 없이 공개로 전환했다.
// 다시 로그인 기반으로 돌리려면 이 파일만 교체하면 된다.
require_once __DIR__ . '/config.php';

// 세션 (admin_common.php 와 동일 저장소 — 중복 start 방지)
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

/** 관리자 비밀번호로 인증된 세션인지 (admin_common.php 가 설정) */
function authIsAdmin()
{
    return !empty($_SESSION['dash_auth']);
}

/** 권한 레벨 — 일반 방문자 0, 관리자 비밀번호 통과 시 9 (관리자 메뉴 노출용) */
function authLevel()
{
    return authIsAdmin() ? 9 : 0;
}

/** 로그인 개념이 없으므로 항상 null */
function authUser()
{
    return null;
}

function authLevelNames()
{
    return array(0 => '방문자', 9 => '관리자');
}

function authGradeNames()
{
    return array('admin' => '관리자');
}

/** 모든 도구 페이지가 공개 — 관리자 페이지는 자체 비밀번호로 보호된다 */
function authPageLevel($file)
{
    return 0;
}

function authPageLevels()
{
    return array();
}

/** 공개형이라 통과만 시킨다 (호출부 호환용) */
function authGuard($file = null)
{
    return;
}

/** '내 상황' 제한 없음 — 전체 선택 가능 */
function authPersonaKeys()
{
    return null;
}
