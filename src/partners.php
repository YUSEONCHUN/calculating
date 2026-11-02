<?php
// 지역 파트너 소개 (약국 컨설팅 업체 · 공인중개사) — 목록은 관리자 > 파트너 관리(admin_partners.php)에서 관리
// 노출 우선순위: 시군구 일치 → 시도 일치(시군구 미지정 항목) → 전국(지역 미지정 항목)
require_once __DIR__ . '/config.php';

function partnersEnsureTable($pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS partner_listings (
          id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          category   VARCHAR(12) NOT NULL,             -- consulting(약국 컨설팅) | realtor(공인중개사)
          name       VARCHAR(60) NOT NULL,
          phone      VARCHAR(30) NOT NULL DEFAULT '',
          address    VARCHAR(120) NOT NULL DEFAULT '',
          sido       VARCHAR(20) NOT NULL DEFAULT '',  -- 노출 지역 시/도 ('' = 전국)
          sigungu    VARCHAR(30) NOT NULL DEFAULT '',  -- 노출 지역 시/군/구 ('' = 시/도 전체)
          note       VARCHAR(120) NOT NULL DEFAULT '', -- 한 줄 소개
          sort_order INT NOT NULL DEFAULT 0,
          is_active  TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY idx_region (sido, sigungu),
          KEY idx_cat (category, is_active)
        ) ENGINE=InnoDB"
    );
    // 기존 테이블에 website 컬럼 추가 (선택 입력 — 홈페이지 이동 경로)
    try {
        $pdo->query('SELECT website FROM partner_listings LIMIT 1');
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE partner_listings ADD COLUMN website VARCHAR(200) NOT NULL DEFAULT '' AFTER address");
    }
    // 노출·클릭 집계 (partner_track.php) — 파트너 영업·정산 근거
    try {
        $pdo->query('SELECT views FROM partner_listings LIMIT 1');
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE partner_listings ADD COLUMN views INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active");
        $pdo->exec("ALTER TABLE partner_listings ADD COLUMN clicks INT UNSIGNED NOT NULL DEFAULT 0 AFTER views");
    }
    // 프로모션 기간 이력 (강조효과 highlight / 상단고정 pin) — 기간이 끝나면 새 기간을 추가 등록 (이력 보존)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS partner_promotions (
          id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          partner_id INT UNSIGNED NOT NULL,
          promo_type VARCHAR(10) NOT NULL,            -- highlight(강조효과) | pin(상단고정)
          start_date DATE NOT NULL,
          end_date   DATE NOT NULL,
          amount     INT UNSIGNED NOT NULL DEFAULT 0, -- 지불 비용 (원) — pin 은 금액 높은 순으로 상단 정렬
          memo       VARCHAR(120) NOT NULL DEFAULT '',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY idx_partner (partner_id, promo_type, end_date)
        ) ENGINE=InnoDB"
    );
}

/** 파트너 카테고리 정의 (키 => 표시 제목) — 새 종류 추가 시 여기와 admin_partners.php 셀렉트에 키 추가 */
function partnerCategories()
{
    return array(
        'consulting' => '💼 약국 컨설팅 업체',
        'realtor'    => '🏢 공인중개사',
        'equipment'  => '🔧 설비·인테리어 업체',
        'tax'        => '🧾 세무사·회계사',
        'labor'      => '📋 노무사',
        'wholesale'  => '📦 의약품 도매상',
        'stock'      => '♻️ 불용재고 정리·매입',
        'finance'    => '🏦 대출·금융',
        'lawyer'     => '⚖️ 변호사 (약국 거래)',
        'billing'    => '💻 청구 프로그램·전산',
        'insurance'  => '🛡️ 보험 설계사',
    );
}

/**
 * 카테고리별 활성 파트너 전체 조회 — 지역 선택형 UI(설비 견적 등)의 JSON 데이터용
 */
function partnersFetchByCategory($category)
{
    try {
        $pdo = getConnection();
        partnersEnsureTable($pdo);
        $st = $pdo->prepare(
            "SELECT name, phone, address, website, sido, sigungu, note FROM partner_listings
              WHERE is_active = 1 AND category = ?
              ORDER BY CASE WHEN sido = '' THEN 1 ELSE 0 END, sido, sigungu, sort_order, id"
        );
        $st->execute(array($category));
        return $st->fetchAll();
    } catch (Exception $e) {
        return array();
    }
}

/**
 * 지역 매칭 파트너 리스트 HTML 반환 — 데이터·DB 없으면 빈 문자열 (페이지 동작에 영향 없음)
 * $cats: 표시할 카테고리 키 배열 (partnerCategories() 참조, 기본 컨설팅+중개사)
 */
function renderPartnerList($sido, $sigungu, $cats = array('consulting', 'realtor'))
{
    // $sido 에 배열을 넘기면 권역 모드: 해당 시도들의 모든 업체(시군구 지정 포함) + 전국 업체 표시 (권리금 시세 등 권역 선택형 페이지용)
    // 카카오 주소검색은 시도를 축약형("서울")으로 넘김 → 등록 데이터의 공식 명칭("서울특별시")으로 정규화
    $sidoArr = null;
    if (is_array($sido)) {
        $sidoArr = array_values(array_filter(array_map('normalizeSido', $sido)));
        $sido = implode('·', $sidoArr);
    } else {
        $sido = normalizeSido((string) $sido);
    }
    $catMeta = partnerCategories();
    $cats = array_values(array_intersect($cats, array_keys($catMeta)));
    if (count($cats) === 0) {
        return '';
    }
    $rows = array();
    try {
        $pdo = getConnection();
        partnersEnsureTable($pdo);
        $catPh = implode(',', array_fill(0, count($cats), '?'));
        if ($sidoArr !== null && count($sidoArr) > 0) {
            // 권역 모드: 시도 목록 내 모든 업체(시군구 지정 포함) + 전국
            $sidoPh = implode(',', array_fill(0, count($sidoArr), '?'));
            $regionCond = "( p.sido IN ({$sidoPh}) OR p.sido = '' )";
            $regionParams = $sidoArr;
        } else {
            // 주소 모드: 시군구 일치 → 시도 전체 → 전국
            $regionCond = "( (p.sido = ? AND p.sigungu = ? AND p.sigungu <> '')
                      OR (p.sido = ? AND p.sigungu = '')
                      OR p.sido = '' )";
            $regionParams = array((string) $sido, (string) $sigungu, (string) $sido);
        }
        // 진행 중인 프로모션 조인: pin(상단고정)은 지불 금액 높은 순으로 그룹 최상단, highlight(강조)는 배지·강조 스타일
        $stmt = $pdo->prepare(
            "SELECT p.id, p.category, p.name, p.phone, p.address, p.website, p.sido, p.sigungu, p.note,
                    MAX(CASE WHEN pr.promo_type = 'pin' THEN pr.amount END) AS pin_amount,
                    MAX(CASE WHEN pr.promo_type = 'highlight' THEN 1 ELSE 0 END) AS is_hl
               FROM partner_listings p
               LEFT JOIN partner_promotions pr
                 ON pr.partner_id = p.id AND CURDATE() BETWEEN pr.start_date AND pr.end_date
              WHERE p.is_active = 1 AND p.category IN ({$catPh})
                AND {$regionCond}
              GROUP BY p.id
              ORDER BY p.category,
                       (MAX(CASE WHEN pr.promo_type = 'pin' THEN pr.amount END) IS NULL),
                       MAX(CASE WHEN pr.promo_type = 'pin' THEN pr.amount END) DESC,
                       CASE WHEN p.sigungu <> '' THEN 0 WHEN p.sido <> '' THEN 1 ELSE 2 END,
                       p.sort_order, p.id"
        );
        $stmt->execute(array_merge($cats, $regionParams));
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {
        return ''; // DB 미연결·테이블 없음 → 표시 생략
    }
    if (count($rows) === 0) {
        return '';
    }

    $groups = array();
    foreach ($cats as $ck) {
        $groups[$ck] = array('title' => $catMeta[$ck], 'items' => array());
    }
    foreach ($rows as $r) {
        if (isset($groups[$r['category']])) {
            $groups[$r['category']]['items'][] = $r;
        }
    }

    static $pl_n = 0;
    $pl_n++;
    $plid = 'plb' . $pl_n;

    // 실제 항목이 있는 카테고리만 필터 셀렉트에 노출
    $activeGroups = array();
    foreach ($groups as $ck => $g) {
        if (count($g['items']) > 0) {
            $activeGroups[$ck] = $g;
        }
    }

    $html = "
<style>
    .partner-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px 24px; margin-top: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .partner-box h3 { margin: 0 0 4px; font-size: 1.0em; color: #1e293b; }
    .partner-box .pb-sub { font-size: 0.8em; color: #94a3b8; margin: 0 0 14px; }
    .partner-filter { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap; }
    .partner-filter label { font-size: 0.82em; font-weight: bold; color: #374151; }
    .partner-filter select { padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.88em; background: #fff; cursor: pointer; }
    .partner-grp-title { font-size: 0.85em; font-weight: bold; color: #0e7490; margin: 14px 0 8px; }
    .partner-item { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; padding: 10px 2px; border-bottom: 1px solid #f1f5f9; }
    .partner-item:last-child { border-bottom: none; }
    .partner-item .p-name { font-weight: bold; color: #1e293b; font-size: 0.93em; }
    .partner-item .p-region { font-size: 0.7em; font-weight: bold; color: #0e7490; background: #ecfeff; border: 1px solid #a5f3fc; border-radius: 3px; padding: 1px 6px; }
    .partner-item .p-note { font-size: 0.8em; color: #64748b; flex-basis: 100%; order: 5; }
    .partner-item .p-phone { font-size: 0.88em; font-weight: bold; color: #0e7490; text-decoration: none; white-space: nowrap; margin-left: auto; }
    .partner-item .p-phone:hover { text-decoration: underline; }
    .partner-item .p-web { font-size: 0.82em; font-weight: bold; color: #475569; text-decoration: none; white-space: nowrap; }
    .partner-item .p-web:hover { text-decoration: underline; color: #0e7490; }
    .partner-item .p-addr { font-size: 0.8em; color: #94a3b8; flex-basis: 100%; order: 6; }
    .partner-item.p-hl { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 12px; margin: 4px 0; }
    .partner-item .p-badge { font-size: 0.68em; font-weight: bold; color: #b45309; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 3px; padding: 1px 6px; }
    .partner-item .p-pin { font-size: 0.68em; font-weight: bold; color: #1d4ed8; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 3px; padding: 1px 6px; }
    .partner-note { font-size: 0.75em; color: #b0b8c4; margin: 12px 0 0; line-height: 1.5; }
</style>
<div class='partner-box' id='{$plid}'>
    <h3>🤝 이 지역 전문 파트너</h3>
    <p class='pb-sub'>" . htmlspecialchars(trim($sido . ' ' . $sigungu)) . " 인근에서 약국 거래·개국을 도와줄 수 있는 업체입니다.</p>";

    if (count($activeGroups) > 1) {
        $html .= "<div class='partner-filter'><label>종류</label><select onchange=\"plFilter('{$plid}', this.value)\"><option value=''>전체</option>";
        foreach ($activeGroups as $ck => $g) {
            $html .= "<option value='" . $ck . "'>" . htmlspecialchars($g['title']) . "</option>";
        }
        $html .= "</select></div>";
    }

    foreach ($activeGroups as $ck => $g) {
        $html .= "<div class='partner-grp' data-cat='" . $ck . "'>";
        $html .= "<div class='partner-grp-title'>" . $g['title'] . "</div>";
        foreach ($g['items'] as $it) {
            $regionLabel = $it['sido'] === '' ? '전국' : trim($it['sido'] . ' ' . $it['sigungu']);
            $telDigits = preg_replace('/[^0-9]/', '', $it['phone']);
            $isPinned = isset($it['pin_amount']) && $it['pin_amount'] !== null;
            $isHl = !empty($it['is_hl']);
            $html .= "<div class='partner-item" . ($isHl ? " p-hl" : "") . "'>"
                . "<span class='p-name'>" . htmlspecialchars($it['name']) . "</span>"
                . ($isPinned ? "<span class='p-pin'>📌 상단</span>" : "")
                . ($isHl ? "<span class='p-badge'>★ 추천</span>" : "")
                . "<span class='p-region'>" . htmlspecialchars($regionLabel) . "</span>"
                . ($it['phone'] !== '' ? "<a class='p-phone' href='tel:{$telDigits}' onclick=\"navigator.sendBeacon&&navigator.sendBeacon('partner_track.php?api=click&id=" . (int) $it['id'] . "')\">📞 " . htmlspecialchars($it['phone']) . "</a>" : "")
                . (isset($it['website']) && $it['website'] !== '' ? "<a class='p-web' href='partner_track.php?go=" . (int) $it['id'] . "' target='_blank' rel='noopener'>🔗 홈페이지</a>" : "")
                . ($it['note'] !== '' ? "<span class='p-note'>" . htmlspecialchars($it['note']) . "</span>" : "")
                . ($it['address'] !== '' ? "<span class='p-addr'>📍 " . htmlspecialchars($it['address']) . "</span>" : "")
                . "</div>";
        }
        $html .= "</div>";
    }

    // 노출 집계 비콘 — script와 달리 img는 innerHTML로 삽입돼도 로드됨 (권역 선택형 페이지 대응)
    $viewIds = array();
    foreach ($rows as $r) {
        $viewIds[] = (int) $r['id'];
    }
    $viewIds = array_slice(array_unique($viewIds), 0, 30);
    $html .= "<img src='partner_track.php?api=view&ids=" . implode(',', $viewIds) . "&r=" . mt_rand() . "' width='1' height='1' style='position:absolute;opacity:0;' alt=''>";
    $html .= "<p class='partner-note'>※ 소개 목록은 정보 제공 목적이며, 거래 조건·수수료는 업체와 직접 협의하세요.</p></div>
<script>
if (!window.plFilter) {
    window.plFilter = function (id, cat) {
        document.querySelectorAll('#' + id + ' .partner-grp').forEach(function (g) {
            g.style.display = (cat === '' || g.getAttribute('data-cat') === cat) ? '' : 'none';
        });
    };
}
</script>";
    return $html;
}

/**
 * 지역 선택형 파트너 박스 HTML 반환 — 주소 입력이 없는 페이지(손익분기·세금·조제수가·재고 등)용
 * 시/도 셀렉트로 필터, 전국 업체는 항상 포함. 데이터 없으면 빈 문자열.
 */
function renderPartnerPicker($cats, $lead = '')
{
    static $picker_n = 0;
    $catMeta = partnerCategories();
    $cats = array_values(array_intersect($cats, array_keys($catMeta)));
    if (count($cats) === 0) {
        return '';
    }
    $rows = array();
    try {
        $pdo = getConnection();
        partnersEnsureTable($pdo);
        $catPh = implode(',', array_fill(0, count($cats), '?'));
        $stmt = $pdo->prepare(
            "SELECT p.id, p.category, p.name, p.phone, p.address, p.website, p.sido, p.sigungu, p.note,
                    MAX(CASE WHEN pr.promo_type = 'pin' THEN pr.amount END) AS pin_amount,
                    MAX(CASE WHEN pr.promo_type = 'highlight' THEN 1 ELSE 0 END) AS is_hl
               FROM partner_listings p
               LEFT JOIN partner_promotions pr
                 ON pr.partner_id = p.id AND CURDATE() BETWEEN pr.start_date AND pr.end_date
              WHERE p.is_active = 1 AND p.category IN ({$catPh})
              GROUP BY p.id
              ORDER BY p.category, CASE WHEN p.sido = '' THEN 1 ELSE 0 END, p.sido, p.sigungu, p.sort_order, p.id"
        );
        $stmt->execute($cats);
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {
        return '';
    }
    if (count($rows) === 0) {
        return '';
    }

    $picker_n++;
    $pid = 'ppk' . $picker_n;
    $sido_list = array('서울특별시', '경기도', '인천광역시', '부산광역시', '대구광역시', '광주광역시', '대전광역시',
        '울산광역시', '세종특별자치시', '강원특별자치도', '충청북도', '충청남도', '전북특별자치도', '전라남도',
        '경상북도', '경상남도', '제주특별자치도');
    $titles = array();
    foreach ($cats as $ck) {
        $titles[$ck] = $catMeta[$ck];
    }

    $sidoOpts = '';
    foreach ($sido_list as $s) {
        $sidoOpts .= "<option value='" . $s . "'>" . $s . "</option>";
    }

    return "
<style>
    .ppk-box { background: #fff; border: 1px solid #e5e7eb; border-top: 3px solid #0e7490; border-radius: 8px; padding: 20px 24px; margin-top: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .ppk-box h3 { margin: 0 0 4px; font-size: 1.0em; color: #1e293b; }
    .ppk-box .ppk-sub { font-size: 0.8em; color: #94a3b8; margin: 0 0 12px; }
    .ppk-region { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
    .ppk-region label { font-size: 0.85em; font-weight: bold; color: #374151; }
    .ppk-region select { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9em; background: #fff; cursor: pointer; }
    .ppk-grp { font-size: 0.85em; font-weight: bold; color: #0e7490; margin: 14px 0 6px; }
    .ppk-item { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; padding: 10px 2px; border-bottom: 1px solid #f1f5f9; }
    .ppk-item:last-child { border-bottom: none; }
    .ppk-item .p-name { font-weight: bold; color: #1e293b; font-size: 0.93em; }
    .ppk-item .p-region { font-size: 0.7em; font-weight: bold; color: #0e7490; background: #ecfeff; border: 1px solid #a5f3fc; border-radius: 3px; padding: 1px 6px; }
    .ppk-item .p-phone { font-size: 0.88em; font-weight: bold; color: #0e7490; text-decoration: none; white-space: nowrap; margin-left: auto; }
    .ppk-item .p-phone:hover { text-decoration: underline; }
    .ppk-item .p-web { font-size: 0.82em; font-weight: bold; color: #475569; text-decoration: none; white-space: nowrap; }
    .ppk-item .p-web:hover { text-decoration: underline; color: #0e7490; }
    .ppk-item .p-note { font-size: 0.8em; color: #64748b; flex-basis: 100%; }
    .ppk-item .p-addr { font-size: 0.8em; color: #94a3b8; flex-basis: 100%; }
    .ppk-item.p-hl { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 12px; margin: 4px 0; }
    .ppk-item .p-badge { font-size: 0.68em; font-weight: bold; color: #b45309; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 3px; padding: 1px 6px; }
    .ppk-item .p-pin { font-size: 0.68em; font-weight: bold; color: #1d4ed8; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 3px; padding: 1px 6px; }
    .ppk-empty { font-size: 0.85em; color: #9ca3af; padding: 8px 2px; }
    .ppk-note { font-size: 0.75em; color: #b0b8c4; margin: 12px 0 0; line-height: 1.5; }
</style>
<div class='ppk-box'>
    <h3>🤝 관련 전문 파트너 찾기</h3>
    <p class='ppk-sub'>" . htmlspecialchars($lead !== '' ? $lead : '지역을 선택하면 해당 지역 업체와 전국 업체가 함께 표시됩니다.') . "</p>
    <div class='ppk-region'>
        <label for='{$pid}-sel'>지역 선택</label>
        <select id='{$pid}-sel' onchange='{$pid}_render()'>
            <option value=''>전체 (전국 업체 포함)</option>
            {$sidoOpts}
        </select>" . (count($titles) > 1 ? "
        <label for='{$pid}-cat'>종류</label>
        <select id='{$pid}-cat' onchange='{$pid}_render()'>
            <option value=''>전체</option>" . implode('', array_map(function ($ck) use ($titles) {
                return "<option value='" . $ck . "'>" . htmlspecialchars($titles[$ck]) . "</option>";
            }, array_keys($titles))) . "
        </select>" : "") . "
    </div>
    <div id='{$pid}-list'></div>
    <p class='ppk-note'>※ 소개 목록은 정보 제공 목적이며, 조건은 업체와 직접 협의하세요. (관리자 &gt; 파트너 관리에서 갱신)</p>
</div>
<script>
(function () {
    var DATA = " . json_encode($rows, JSON_UNESCAPED_UNICODE) . ";
    var TITLES = " . json_encode($titles, JSON_UNESCAPED_UNICODE) . ";
    function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }
    window['{$pid}_render'] = function () {
        var sido = document.getElementById('{$pid}-sel').value;
        var catSel = document.getElementById('{$pid}-cat');
        var catF = catSel ? catSel.value : '';
        var box = document.getElementById('{$pid}-list');
        var html = '';
        Object.keys(TITLES).forEach(function (cat) {
            if (catF !== '' && cat !== catF) { return; } // 종류 필터
            var rows = DATA.filter(function (p) {
                return p.category === cat && (sido === '' || p.sido === '' || p.sido === sido);
            }).sort(function (a, b) {
                // 상단고정(지불 금액 높은 순) 우선 → 지역 지정 업체 → 전국
                var ap = a.pin_amount !== null ? 1 : 0, bp = b.pin_amount !== null ? 1 : 0;
                if (ap !== bp) { return bp - ap; }
                if (ap && bp && a.pin_amount !== b.pin_amount) { return b.pin_amount - a.pin_amount; }
                return (a.sido === '' ? 1 : 0) - (b.sido === '' ? 1 : 0);
            });
            if (rows.length === 0) { return; }
            html += \"<div class='ppk-grp'>\" + TITLES[cat] + '</div>';
            rows.forEach(function (p) {
                var region = p.sido === '' ? '전국' : (p.sido + (p.sigungu ? ' ' + p.sigungu : ''));
                var tel = String(p.phone || '').replace(/[^0-9]/g, '');
                var hl = String(p.is_hl) === '1';
                html += \"<div class='ppk-item\" + (hl ? ' p-hl' : '') + \"'><span class='p-name'>\" + esc(p.name) + '</span>'
                    + (p.pin_amount !== null ? \"<span class='p-pin'>📌 상단</span>\" : '')
                    + (hl ? \"<span class='p-badge'>★ 추천</span>\" : '')
                    + \"<span class='p-region'>\" + esc(region) + '</span>'
                    + (p.phone ? \"<a class='p-phone' href='tel:\" + tel + \"' onclick=\\\"navigator.sendBeacon&&navigator.sendBeacon('partner_track.php?api=click&id=\" + p.id + \"')\\\">📞 \" + esc(p.phone) + '</a>' : '')
                    + (p.website ? \"<a class='p-web' href='partner_track.php?go=\" + p.id + \"' target='_blank' rel='noopener'>🔗 홈페이지</a>\" : '')
                    + (p.note ? \"<span class='p-note'>\" + esc(p.note) + '</span>' : '')
                    + (p.address ? \"<span class='p-addr'>📍 \" + esc(p.address) + '</span>' : '')
                    + '</div>';
            });
        });
        box.innerHTML = html !== '' ? html : \"<div class='ppk-empty'>선택한 지역에 등록된 업체가 없습니다.</div>\";
    };
    window['{$pid}_render']();
})();
</script>";
}
