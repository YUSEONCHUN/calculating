<?php
// ===== 내비게이션 + 홈 카드 목록 (전체 페이지 공용 마스터) =====
require_once __DIR__ . '/auth.php'; // 접근 제어 (이 프로젝트는 공개형 — 관리자만 비밀번호)
// 사이드바와 홈 화면 카드가 모두 이 목록 하나로 만들어집니다.
//
// ▶ 항목 숨기기: 해당 줄 앞에 // 를 붙이세요. 사이드바와 홈 카드에서 함께 사라집니다.
// ▶ 항목 형식:
//    array('파일명.php', '사이드바 이름', '홈카드 설명', '#색상', '홈카드 제목(없으면 null)'),
//    - 5번째 홈카드 제목이 null 이면 사이드바 이름이 카드 제목으로 쓰입니다.
// ▶ 카테고리의 항목이 전부 숨겨지면 카테고리도 자동으로 숨겨집니다.

function sideNavItems()
{
    return array(
        '💰 권리금' => array(
            'desc' => '계단형 v1 → 곡선형 v2 → 조건·실측 반영 v3',
            'items' => array(
                array('goodwill.php',  '권리금 계산 v1', '실거래 회수배수 기반 최소~최대 범위 즉시 산정 — 규모 계수 계단형', '#b45309', '권리금 계산 v1 (계단형)'),
                array('goodwill_curve.php', '권리금 계산 v2', '조제료 규모 계수를 구간 경계 없이 선형보간으로 — v1과 비교용', '#0d9488', '권리금 계산 v2 (곡선형)'),
                array('premium.php',   '권리금 계산 v3', '상권·병원·임대차 조건과 주변 의료기관 실측까지 반영한 정밀 산정 + AI 분석·자금 계획', '#0f9d58', '권리금 계산 v3 (정밀)'),
                array('goodwill_survey.php', '적정성 설문', '조사원 배포용 권리금 적정성 설문 — 응답이 쌓이면 지역 보정에 반영', '#9a3412', null),
            ),
        ),
        '🔧 관리자' => array(
            'desc' => '',
            'admin' => true,
            'items' => array(
                array('admin_data.php',    '참고 데이터', '지역 회수개월수·조제료 규모 계수 등 모델 기준값', '#7c2d12', '참고 데이터 관리'),
                array('admin_weights.php', '가중치 관리', '권리금 조정 계수·시세 가중치 등 계산식 계수', '#92400e'),
                array('admin_survey.php',  '설문 집계',   '권리금 적정성 설문 집계와 원클릭 보정 반영', '#9a3412'),
                array('admin_collected.php', '수집 데이터', '매물 호가·설문 등 수집 데이터 집계', '#155e75'),
                array('status.php',        '시스템 상태', 'DB 연결 상태 확인', '#6b7280'),
            ),
        ),
    );
}

/**
 * 사이드바 출력 — 현재 페이지는 강조(.sn-on) 처리, 상단에 상황 셀렉트 포함
 * $current 생략 시 현재 실행 중인 파일명으로 자동 판별
 */
function renderSideNav($current = null)
{
    if ($current === null) {
        $current = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    }
    ?>
<style>
    /* 모바일: 상단 그리드 대신 우측 슬라이드 드로어 */
    .nav-toggle { display: none; position: fixed; top: 12px; right: 12px; z-index: 1100;
                  align-items: center; gap: 6px; padding: 9px 14px; border: none; border-radius: 999px;
                  background: #1e293b; color: #fff; font-size: 0.9em; font-weight: bold; cursor: pointer;
                  box-shadow: 0 4px 14px rgba(15,23,42,0.35); width: auto; margin: 0; }
    .nav-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 1150; }
    .sn-close { display: none; }
    @media (max-width: 1119px) {
        .nav-toggle { display: flex; }
        body.nav-open .nav-backdrop { display: block; }
        .nav-links.side-nav { position: fixed; top: 0; right: -290px; bottom: 0; left: auto; width: 264px;
                              display: block; border-radius: 0; margin: 0; padding: 16px 14px;
                              transition: right 0.25s ease; z-index: 1200; overflow-y: auto;
                              box-shadow: -8px 0 28px rgba(0,0,0,0.3); }
        body.nav-open .nav-links.side-nav { right: 0; }
        .sn-close { display: block; position: absolute; top: 10px; right: 10px; width: 34px; height: 34px;
                    border: none; border-radius: 8px; background: rgba(255,255,255,0.12); color: #cbd5e1;
                    font-size: 1.05em; font-weight: bold; cursor: pointer; padding: 0; margin: 0; }
        .sn-close:hover { background: rgba(255,255,255,0.22); color: #fff; }
        .side-nav .sn-home { margin-right: 40px; }
    }
    @media print { .nav-toggle, .nav-backdrop, .nav-links.side-nav { display: none !important; } }

    /* 로그인 상태 칩 */
    .sn-auth { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin: 8px 0 2px; padding: 7px 8px;
               background: rgba(255,255,255,0.06); border-radius: 8px; font-size: 0.74em; }
    .sn-auth .sa-name { color: #e2e8f0; font-weight: bold; }
    .sn-auth .sa-grade { color: #7dd3fc; font-size: 0.92em; }
    .sn-auth .sa-btn { margin-left: auto; color: #94a3b8; text-decoration: none; border: 1px solid rgba(255,255,255,0.25);
                       border-radius: 6px; padding: 2px 8px; }
    .sn-auth .sa-btn:hover { color: #fff; border-color: rgba(255,255,255,0.5); }

    /* 모바일·태블릿 폼 공통 (전 페이지 공용) —
       입력칸이 줄어들 수 있게 해 단위(만원 등)·버튼이 아래로 떨어지지 않도록 하고,
       긴 단위 설명은 입력칸 옆에서 줄바꿈되도록 처리 */
    @media (max-width: 1119px) {
        .form-group > input, .form-group > select, .form-group > textarea { min-width: 0; }
        .form-group .unit { white-space: normal; max-width: 45%; line-height: 1.35; }
    }
    @media (max-width: 640px) {
        .form-group { flex-wrap: wrap; }
        .form-group > label { width: 100%; margin-bottom: 5px; }
        .form-group > input, .form-group > select, .form-group > textarea { flex: 1 1 0; }
        .form-group > button { flex: 0 0 auto; }
        .form-group .unit { flex: 0 1 auto; max-width: 46%; }
    }
</style>
<button type="button" class="nav-toggle" onclick="navDrawerToggle(true)" aria-label="메뉴 열기">☰ 메뉴</button>
<div class="nav-backdrop" onclick="navDrawerToggle(false)"></div>
<nav class="nav-links side-nav">
        <button type="button" class="sn-close" onclick="navDrawerToggle(false)" aria-label="메뉴 닫기">✕</button>
        <a href="index.php" class="sn-home">💰 권리금 도구</a>
        <?php $auth_lv = authLevel(); ?>
        <?php if ($auth_lv >= 9) : ?>
        <div class="sn-auth">
            <span class="sa-name">🔧 관리자</span>
            <a class="sa-btn" href="?dash_logout=1">관리 종료</a>
        </div>
        <?php endif; ?>
    <?php
    foreach (sideNavItems() as $cat => $group) {
        if (count($group['items']) === 0) {
            continue;
        }
        if (!empty($group['admin']) && $auth_lv < 9) {
            continue; // 관리자 그룹은 관리자 비밀번호 인증 후에만 렌더
        }
        echo '        <div class="sn-group"><div class="sn-cat">' . htmlspecialchars($cat) . '</div>' . "\n";
        foreach ($group['items'] as $it) {
            if ($it[0] === $current) {
                echo '        <span class="sn-on">' . htmlspecialchars($it[1]) . '</span>' . "\n";
            } else {
                echo '        <a href="' . htmlspecialchars($it[0]) . '">' . htmlspecialchars($it[1]) . '</a>' . "\n";
            }
        }
        echo '        </div>' . "\n";
    }
    ?>
    <div class="sn-ai" id="sn-ai" title="AI 분석(Gemini) 상태 — 5분 간격 자동 점검">
        <span class="sn-ai-dot" id="sn-ai-dot"></span><span id="sn-ai-label">AI 상태 확인 중…</span>
    </div>
    </nav>
<style>
    .sn-ai { margin-top: 14px; padding: 8px 10px; border-top: 1px solid rgba(255,255,255,0.1);
             font-size: 0.72em; color: #94a3b8; display: flex; align-items: center; gap: 7px; }
    .sn-ai-dot { width: 9px; height: 9px; border-radius: 50%; background: #64748b; flex: 0 0 auto; }
    .sn-ai.ok .sn-ai-dot { background: #4ade80; box-shadow: 0 0 6px rgba(74,222,128,0.6); }
    .sn-ai.ok { color: #86efac; }
    .sn-ai.degraded .sn-ai-dot { background: #fbbf24; box-shadow: 0 0 6px rgba(251,191,36,0.6); }
    .sn-ai.degraded { color: #fcd34d; }
    .sn-ai.down .sn-ai-dot { background: #f87171; box-shadow: 0 0 6px rgba(248,113,113,0.6); }
    .sn-ai.down { color: #fca5a5; }
</style>
<script>
    // 모바일 드로어 열기/닫기 (ESC 로도 닫힘)
    function navDrawerToggle(open) {
        document.body.classList.toggle('nav-open', open);
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { navDrawerToggle(false); }
    });
</script>
    <?php
    renderNavScripts();
}

/**
 * 사이드바 공통 스크립트 — AI 상태 램프와 입력 단위 표시 통일.
 * renderSideNav() 가 자동으로 한 번만 출력한다.
 * (원본의 '메뉴 편집' 모달과 상황별 필터는 이 프로젝트에서 사용하지 않아 제거했다)
 */
function renderNavScripts()
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;
    ?>
<script>
    // ---- AI(Gemini) 상태 램프 — 캐시된 상태를 비동기로 표시 (페이지 속도 영향 없음) ----
    (function () {
        var box = document.getElementById('sn-ai');
        if (!box) { return; }
        fetch('ai_status.php').then(function (r) { return r.json(); }).then(function (j) {
            var s = j && j.status ? j.status : null;
            var label = document.getElementById('sn-ai-label');
            if (!s || s.overall === 'unknown') { label.textContent = 'AI 상태 미확인'; return; }
            box.className = 'sn-ai ' + s.overall;
            if (s.overall === 'ok') { label.textContent = 'AI 분석 정상'; }
            else if (s.overall === 'degraded') { label.textContent = 'AI 예비 모델로 동작 중'; }
            else { label.textContent = 'AI 분석 일시 중단'; }
            var f = s.models && s.models.flash ? s.models.flash : null;
            var l = s.models && s.models.lite ? s.models.lite : null;
            box.title = 'AI 분석(Gemini) 상태 — ' + Math.round(s.age / 60) + '분 전 점검\n'
                + (f ? '기본 모델: ' + f.state + ' (' + f.ms + 'ms)\n' : '')
                + (l ? '예비 모델: ' + l.state + ' (' + l.ms + 'ms)' : '');
        }).catch(function () {});
    })();

    // ---- 단위 표시 통일: input 뒤 span.unit의 선두 단위를 권리금 시세(goodwill) 기준
    //      서픽스형(입력칸 안 오른쪽 겹침)으로 자동 변환. 단위 뒤 부가 설명은 그대로 남긴다.
    document.addEventListener('DOMContentLoaded', function () {
        var UNIT_RE = /^(만원|원|%|건|평)(?![가-힣])/;
        document.querySelectorAll('span.unit').forEach(function (u) {
            var input = u.previousElementSibling;
            if (!input || input.tagName !== 'INPUT') { return; }
            var t = (input.getAttribute('type') || 'text').toLowerCase();
            if (t !== 'number' && t !== 'text' && t !== 'tel') { return; }
            if (input.parentElement && input.parentElement.classList.contains('suffix-wrap')) { return; }
            var first = u.firstChild;
            if (!first || first.nodeType !== 3) { return; }
            var m = first.nodeValue.match(UNIT_RE);
            if (!m) { return; }

            var wrap = document.createElement('div');
            wrap.className = 'suffix-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            var suf = document.createElement('span');
            suf.className = 'suffix';
            suf.textContent = m[1];
            wrap.appendChild(suf);

            // span.unit에서 단위와 이어지는 구분 기호(— 등)를 걷어내고 설명만 남김
            first.nodeValue = first.nodeValue.slice(m[1].length).replace(/^[\s—–-]+\s*/, '');
            if (u.textContent.replace(/\s/g, '') === '' && !u.firstElementChild) {
                u.style.display = 'none';
            }
            // 단위 실제 폭만큼 입력 오른쪽 여백 확보 (12px 오프셋 + 6px 간격)
            var w = suf.offsetWidth;
            if (w > 0) { input.style.paddingRight = (w + 18) + 'px'; }
        });
    });
</script>
    <?php
}
