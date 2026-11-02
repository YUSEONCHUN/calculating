<?php
// ===== 내비게이션 + 홈 카드 목록 (전체 페이지 공용 마스터) =====
require_once __DIR__ . '/auth.php'; // 데모 회원·권한 (로그인 상태·페이지별 입장 조건)
// 사이드바와 홈 화면 카드가 모두 이 목록 하나로 만들어집니다.
//
// ▶ 항목 숨기기: 해당 줄 앞에 // 를 붙이세요. 사이드바와 홈 카드에서 함께 사라집니다.
// ▶ 항목 형식:
//    array('파일명.php', '사이드바 이름', '홈카드 설명', '#색상', '홈카드 제목(없으면 null)', '상황태그'),
//    - 5번째 홈카드 제목이 null 이면 사이드바 이름이 카드 제목으로 쓰입니다.
//    - 6번째 상황태그: 아래 navPersonas() 의 키를 쉼표로 나열. 생략하거나 '' 이면 항상 표시 (관리자 메뉴 등).
// ▶ 카테고리의 항목이 전부 숨겨지면 카테고리도 자동으로 숨겨집니다.

/** 약사 상황 셀렉트 (키 => 표시 이름) — 순서대로 표시됨. 상황 추가 시 여기와 항목 태그에 키를 추가
 *  모두보기(기본)는 핵심 도구(navCoreTools)만, 모두보기(개발 리스트)는 전체를 표시.
 *  상황별 보기는 기존과 동일하게 해당 상황 태그가 붙은 도구를 표시. */
function navPersonas()
{
    return array(
        'all' => '모두보기 (기본)',
        'dev' => '모두보기 (개발 리스트)',
        'prep' => '개국을 준비하는 중',
        'acq'  => '기존 약국 인수를 검토하는 중',
        'open' => '운영중',
        'hire' => '운영중 - 직원을 구하는중',
        'work' => '근무중',
        'move' => '근무중 - 이직준비중',
        'rest' => '취업준비중',
        'sell' => '약국 매도를 준비하는 중',
        'test' => '테스트',
        'custom' => '직접 선택 (내가 고른 도구만)',
    );
}

/** 모두보기(기본)에 노출하는 핵심 도구 — 나머지는 '개발 리스트'에서만 표시 (임시 중단 상태) */
function navCoreTools()
{
    return array('goodwill.php', 'premium.php', 'goodwill_parts.php', 'listings.php');
}

function sideNavItems()
{
    return array(
        '💰 권리금' => array(
            'desc' => '시세 산정부터 구성 분해·매물 비교까지',
            'items' => array(
                array('goodwill.php',  '권리금 시세', '실거래 회수배수 기반 최소~최대 범위 즉시 산정', '#b45309', null, 'prep,acq,sell,test'),
                array('premium.php',   '권리금 계산', '회수배수 시세에 상권·병원·임대차 조건을 반영한 정밀 산정과 개국 자금 계획', '#0f9d58', null, 'prep,acq,sell,test'),
                array('goodwill_parts.php', '권리금 구성', '총 권리금을 바닥·영업·시설(재고 포함)로 분해 — 협상 근거 확보', '#6d28d9', null, 'prep,acq,sell,test'),
                array('listings.php',  '매물 비교',   '중개사·컨설팅에서 받은 매물을 시세 모델로 평가·비교', '#0e7490', null, 'prep,acq,test'),
                array('goodwill_survey.php', '적정성 설문', '조사원 배포용 권리금 적정성 설문 — 응답이 쌓이면 지역 보정에 반영', '#9a3412', null, 'test'),
                array('premium_curve.php', '권리금 계산 (곡선형)', '임대료 부담 조정을 계단형 대신 선형보간으로 — 원본과 비교용', '#0d9488', null, 'test'),
                array('goodwill_curve.php', '권리금 시세 (곡선형)', '조제료 규모 계수를 계단형 대신 선형보간으로 — 원본과 비교용', '#0d9488', null, 'test'),
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

/** 항목의 상황 태그 반환 ('' = 항상 표시) */
function navItemPersonas($it)
{
    return isset($it[5]) ? (string) $it[5] : '';
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
    .sn-persona { margin: 6px 0 4px; }
    .sn-persona .sp-label { font-size: 0.66em; color: #94a3b8; font-weight: bold; margin: 0 0 4px 2px; }
    .sn-persona select { width: 100%; padding: 7px 8px; border: 1px solid rgba(255,255,255,0.22); border-radius: 7px;
                         background: rgba(255,255,255,0.08); color: #e2e8f0; font-size: 0.78em; font-weight: bold; cursor: pointer; }
    .sn-persona select:focus { outline: none; border-color: rgba(255,255,255,0.5); }
    .sn-persona select option { background: #1e293b; color: #e2e8f0; }

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
        <?php $auth_lv = authLevel(); $auth_personas = authPersonaKeys(); ?>
        <?php if ($auth_lv >= 9) : ?>
        <div class="sn-auth">
            <span class="sa-name">🔧 관리자</span>
            <a class="sa-btn" href="?dash_logout=1">관리 종료</a>
        </div>
        <?php endif; ?>
        <?php if ($auth_personas === null || count($auth_personas) > 0) : ?>
        <div class="sn-persona">
            <div class="sp-label">내 상황</div>
            <select id="sn-persona-sel" onchange="navSetPersona(this.value)">
            <?php foreach (navPersonas() as $pk => $pn): if ($auth_personas !== null && !in_array($pk, $auth_personas, true)) { continue; } ?>
                <option value="<?= $pk ?>"><?= htmlspecialchars($pn) ?></option>
            <?php endforeach; ?>
            </select>
            <a href="#" onclick="menuEditorOpen(); return false;" style="display:block; font-size:0.72em; color:#94a3b8; text-decoration:none; margin:5px 2px 0;">⚙ 메뉴 편집</a>
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
            $pAttr = navItemPersonas($it) !== '' ? ' data-p="' . htmlspecialchars(navItemPersonas($it)) . '"' : '';
            $pAttr .= ' data-f="' . htmlspecialchars($it[0]) . '"'; // 직접 선택(custom) 필터용 파일명
            if (in_array($it[0], navCoreTools(), true)) {
                $pAttr .= ' data-core="1"'; // 모두보기(기본) 노출 대상
            }
            if ($it[0] === $current) {
                echo '        <span class="sn-on"' . $pAttr . '>' . htmlspecialchars($it[1]) . '</span>' . "\n";
            } else {
                echo '        <a href="' . htmlspecialchars($it[0]) . '"' . $pAttr . '>' . htmlspecialchars($it[1]) . '</a>' . "\n";
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
    // 약사 상황 필터 — 선택은 localStorage 로 저장되어 모든 페이지·홈 카드에 공통 적용
    // '직접 선택(custom)'은 홈에서 고른 도구 목록(nav_custom_tools)만 표시
    function navCustomTools() {
        try {
            var raw = localStorage.getItem('nav_custom_tools');
            if (raw !== null) { return JSON.parse(raw); }
        } catch (e) {}
        return null; // 아직 고른 적 없음 → 전체 표시
    }
    function navApplyPersona(p) {
        var sel = document.getElementById('sn-persona-sel');
        if (!sel) { p = 'all'; } // 상황 선택이 없는 등급(비회원)은 전체 기준
        if (sel) {
            var valid = Array.prototype.some.call(sel.options, function (o) { return o.value === p; });
            if (!valid) { p = 'all'; }
            sel.value = p;
        }
        var customList = (p === 'custom') ? navCustomTools() : null;
        document.querySelectorAll('.side-nav .sn-group').forEach(function (g) {
            var visible = 0;
            g.querySelectorAll('a, .sn-on').forEach(function (el) {
                var tags = el.getAttribute('data-p');
                var show;
                if (p === 'custom') {
                    // 태그 없는 항목(관리자 등)과 현재 페이지는 항상 표시, 그 외에는 고른 목록만
                    show = !tags || el.classList.contains('sn-on')
                        || customList === null || customList.indexOf(el.getAttribute('data-f')) !== -1;
                } else if (p === 'all') {
                    // 모두보기(기본): 핵심 도구 + 태그 없는 항목(관리자) + 현재 페이지만
                    show = el.hasAttribute('data-core') || !tags || el.classList.contains('sn-on');
                } else {
                    // 개발 리스트(dev) 및 상황 태그: dev는 전체, 그 외는 태그 일치 항목
                    show = (p === 'dev') || !tags || tags.split(',').indexOf(p) !== -1;
                }
                el.style.display = show ? '' : 'none';
                if (show) { visible++; }
            });
            g.style.display = visible > 0 ? '' : 'none';
        });
        if (typeof homeApplyPersona === 'function') { homeApplyPersona(p); } // 홈 카드 연동
    }
    function navSetPersona(p) {
        try { localStorage.setItem('nav_persona', p); } catch (e) {}
        navApplyPersona(p);
    }
    // 모바일 드로어 열기/닫기 (ESC 로도 닫힘)
    function navDrawerToggle(open) {
        document.body.classList.toggle('nav-open', open);
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { navDrawerToggle(false); }
    });
    (function () {
        var p = 'all';
        try { p = localStorage.getItem('nav_persona') || 'all'; } catch (e) {}
        // URL로 상황 지정 — 예: index.php?mode=test → '테스트' 상황이 선택·저장된 상태로 열림
        var mode = new URLSearchParams(window.location.search).get('mode');
        if (mode) {
            var sel = document.getElementById('sn-persona-sel');
            if (sel && Array.prototype.some.call(sel.options, function (o) { return o.value === mode; })) {
                p = mode;
                try { localStorage.setItem('nav_persona', p); } catch (e) {}
            }
        }
        navApplyPersona(p);
    })();
</script>
    <?php
    renderMenuEditor();
}

/**
 * 메뉴 편집 모달 — '직접 선택(custom)' 도구 목록을 어느 페이지에서나 편집.
 * renderSideNav()가 자동 포함하며, 사이드바 없는 홈(index.php)은 직접 호출한다.
 */
function renderMenuEditor()
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;
    ?>
<style>
    #menu-editor { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); z-index: 1300;
                   align-items: center; justify-content: center; padding: 16px; }
    .me-box { background: #fff; border-radius: 14px; padding: 22px 24px; width: 560px; max-width: 96vw;
              max-height: 86vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
    .me-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
    .me-head strong { font-size: 1.02em; color: #1e293b; }
    .me-head .me-btn { width: auto; margin: 0; padding: 6px 12px; font-size: 0.76em; background: #64748b; border-radius: 8px; }
    .me-head .me-btn:hover { background: #475569; }
    .me-desc { font-size: 0.8em; color: #94a3b8; margin: 0 0 6px; line-height: 1.5; }
    .me-cat { font-size: 0.8em; font-weight: bold; color: #64748b; margin: 14px 0 6px; }
    .me-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 4px 12px; }
    .me-item { display: flex; align-items: center; gap: 6px; font-size: 0.87em; color: #334155; cursor: pointer; padding: 3px 0; }
    .me-item input { accent-color: #1967d2; cursor: pointer; }
    .me-close { width: 100%; margin-top: 18px; padding: 11px; font-size: 0.95em; background: #1e293b; color: #fff;
                border: none; border-radius: 9px; font-weight: bold; cursor: pointer; }
    .me-close:hover { background: #0f172a; }
    @media print { #menu-editor { display: none !important; } }
</style>
<div id="menu-editor" onclick="if (event.target === this) menuEditorClose()">
    <div class="me-box">
        <div class="me-head">
            <strong>⚙ 메뉴 편집</strong>
            <span style="flex:1;"></span>
            <button type="button" class="me-btn" onclick="menuEditorAll(true)">전체 선택</button>
            <button type="button" class="me-btn" onclick="menuEditorAll(false)">전체 해제</button>
        </div>
        <p class="me-desc">체크한 도구만 홈 카드와 모든 페이지의 메뉴에 표시됩니다. 이 브라우저에 저장되며, 상황 선택이 '직접 선택'으로 전환됩니다.</p>
        <?php foreach (sideNavItems() as $me_cat => $me_group): if (!empty($me_group['admin']) || count($me_group['items']) === 0) { continue; }
            $me_items = $me_group['items'];
            if (count($me_items) === 0) { continue; } ?>
        <div class="me-cat"><?= htmlspecialchars($me_cat) ?></div>
        <div class="me-grid">
            <?php foreach ($me_items as $me_it): ?>
            <label class="me-item"><input type="checkbox" value="<?= htmlspecialchars($me_it[0]) ?>" onchange="menuEditorToggle()"> <?= htmlspecialchars($me_it[1]) ?></label>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <button type="button" class="me-close" onclick="menuEditorClose()">완료</button>
    </div>
</div>
<script>
    // 고른 도구 목록 (null = 아직 고른 적 없음 → 전체 표시)
    function customTools() {
        try {
            var raw = localStorage.getItem('nav_custom_tools');
            if (raw !== null) { return JSON.parse(raw); }
        } catch (e) {}
        return null;
    }
    function menuEditorApply(p) {
        // 사이드바 페이지는 navSetPersona, 홈은 homeSetPersona 로 저장+반영
        if (typeof navSetPersona === 'function') { navSetPersona(p); }
        else if (typeof homeSetPersona === 'function') { homeSetPersona(p); }
    }
    function menuEditorSync() {
        var list = customTools();
        document.querySelectorAll('#menu-editor input[type=checkbox]').forEach(function (cb) {
            cb.checked = (list === null) || list.indexOf(cb.value) !== -1;
        });
    }
    function menuEditorOpen() {
        menuEditorSync();
        document.getElementById('menu-editor').style.display = 'flex';
        menuEditorApply('custom'); // 편집 = 직접 선택 모드로 전환 (배경에 실시간 반영)
    }
    function menuEditorClose() {
        document.getElementById('menu-editor').style.display = 'none';
    }
    function menuEditorToggle() {
        var list = [];
        document.querySelectorAll('#menu-editor input[type=checkbox]').forEach(function (cb) {
            if (cb.checked) { list.push(cb.value); }
        });
        try { localStorage.setItem('nav_custom_tools', JSON.stringify(list)); } catch (e) {}
        menuEditorApply('custom');
    }
    function menuEditorAll(on) {
        document.querySelectorAll('#menu-editor input[type=checkbox]').forEach(function (cb) { cb.checked = on; });
        menuEditorToggle();
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { menuEditorClose(); }
    });

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
