<?php
/**
 * 공공데이터·카카오 API 공용 함수 (입지 분석기·권리금 계산기·OTC 판매가에서 사용)
 * - fetchParallel: 여러 HTTP 요청을 curl_multi로 동시 실행 (순차 대기 누적 방지)
 * - fetchLocationBundle: 병의원·약국·카카오 지오코딩 동시 호출 + 상권정보 연계 (페이지 공용 진입점)
 * - fetchKakaoCoords / fetchCommercialData / fetchRegionMedicalData: 개별 조회 (내부적으로 병렬 사용)
 */

/**
 * 여러 HTTP 요청을 curl_multi로 동시 실행 — 순차 호출로 타임아웃이 누적되는 것을 방지.
 * $reqs: array(키 => array('url' => 주소, 'headers' => 배열(선택), 'timeout' => 초(선택, 기본 15)))
 * 반환: array(키 => array('ok' => bool, 'code' => HTTP코드, 'body' => 본문, 'err' => 전송 오류 메시지))
 */
function fetchParallel($reqs)
{
    $mh = curl_multi_init();
    $chs = array();
    foreach ($reqs as $k => $r) {
        $ch = curl_init($r['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, isset($r['timeout']) ? (int) $r['timeout'] : 15);
        if (!empty($r['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $r['headers']);
        }
        if (DEV_INSECURE_SSL) { // 로컬 개발 환경 한정 — 운영에서는 SSL 검증 수행
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        curl_multi_add_handle($mh, $ch);
        $chs[$k] = $ch;
    }

    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 5); // 최대 5초 대기 후 재확인 (일부 환경에서 select가 즉시 반환되는 문제 방지)
        }
    } while ($running > 0 && $status === CURLM_OK);

    // 주의: 멀티 핸들에서는 curl_errno()/curl_error()가 결과를 신뢰성 있게 반영하지 않는다
    // (연결 실패·DNS 실패에도 0/빈 문자열 반환 확인됨) — curl_multi_info_read()로 실제 결과를 읽어야 한다
    $failed = array();
    while ($info = curl_multi_info_read($mh)) {
        if ((int) $info['result'] === CURLE_OK) {
            continue;
        }
        $msg = function_exists('curl_strerror') ? curl_strerror($info['result']) : ('curl 오류 코드 ' . $info['result']);
        foreach ($chs as $k => $ch) {
            if ($ch === $info['handle']) {
                $failed[$k] = $msg;
                break;
            }
        }
    }

    $out = array();
    foreach ($chs as $k => $ch) {
        $ok = !isset($failed[$k]);
        $out[$k] = array(
            'ok' => $ok,
            'code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => $ok ? curl_multi_getcontent($ch) : '',
            'err' => $ok ? '' : $failed[$k],
        );
        curl_multi_remove_handle($mh, $ch);
        if (PHP_VERSION_ID < 80000) { curl_close($ch); } // PHP 8.0+ 불필요(8.5부터 deprecated), 7.x 호환 유지
    }
    curl_multi_close($mh);
    return $out;
}

/** fetchParallel 결과 1건을 SimpleXML로 변환 (실패 시 null + 오류 메시지) */
function pcXmlFromResult($r, &$errOut)
{
    if (!$r['ok']) {
        $errOut = $r['err'];
        return null;
    }
    $xml = @simplexml_load_string($r['body']);
    if ($xml === false) {
        $errOut = 'XML 파싱 실패';
        return null;
    }
    return $xml;
}

/** 카카오 지오코딩 응답 파싱 — fetchKakaoCoords·fetchLocationBundle 공용 */
function parseKakaoCoords($r, &$err)
{
    if (!$r['ok']) {
        $err = '카카오 지오코딩: ' . $r['err'];
        return null;
    }
    if ($r['code'] !== 200) {
        $detail = '';
        $ej = json_decode($r['body'], true);
        if (isset($ej['message'])) {
            $detail = ' — ' . $ej['message'];
            if (strpos($ej['message'], 'OPEN_MAP_AND_LOCAL') !== false) {
                $detail .= ' (카카오 개발자 콘솔 → 해당 앱 → [제품 설정] → 카카오맵 → 활성화 필요)';
            }
        }
        $err = '카카오 지오코딩 실패 (HTTP ' . $r['code'] . $detail . ')';
        return null;
    }
    $j = json_decode($r['body'], true);
    if (!isset($j['documents'][0]['x'])) {
        $err = '카카오 지오코딩: 주소 좌표를 찾지 못했습니다';
        return null;
    }
    return array('x' => (float) $j['documents'][0]['x'], 'y' => (float) $j['documents'][0]['y']);
}

/** 카카오 지오코딩 요청 스펙 (fetchParallel 입력용) */
function pcKakaoRequest($restKey, $address)
{
    return array(
        'url' => 'https://dapi.kakao.com/v2/local/search/address.json?query=' . urlencode($address),
        'headers' => array('Authorization: KakaoAK ' . $restKey),
        'timeout' => 10,
    );
}

/** 카카오 로컬 API — 주소를 좌표(경도 x, 위도 y)로 변환 */
function fetchKakaoCoords($restKey, $address, &$err)
{
    $res = fetchParallel(array('k' => pcKakaoRequest($restKey, $address)));
    return parseKakaoCoords($res['k'], $err);
}

/**
 * 소상공인시장진흥공단 상가(상권)정보 API — 좌표 반경 내 상가 업소 조회
 * https://apis.data.go.kr/B553077/api/open/sdsc2/storeListInRadius
 * (좌표가 있어야 호출 가능하므로 지오코딩 이후 단독 실행 — 병렬 대상 아님)
 */
function fetchCommercialData($key, $x, $y, $radius, &$err)
{
    $q = http_build_query(array(
        'serviceKey' => $key,
        'radius' => $radius,
        'cx' => $x,
        'cy' => $y,
        'numOfRows' => 1000,
        'pageNo' => 1,
        'type' => 'json',
    ));
    $res = fetchParallel(array('c' => array(
        'url' => 'https://apis.data.go.kr/B553077/api/open/sdsc2/storeListInRadius?' . $q,
    )));
    $r = $res['c'];
    if (!$r['ok']) {
        $err = '상권정보 API: ' . $r['err'];
        return null;
    }
    $resp = $r['body'];

    $j = json_decode($resp, true);
    if ($j === null) {
        // 인증 오류 시 XML로 응답하는 경우 처리
        $xml = @simplexml_load_string($resp);
        $err = '상권정보 API: ' . ($xml !== null && isset($xml->cmmMsgHeader->returnAuthMsg)
            ? (string) $xml->cmmMsgHeader->returnAuthMsg
            : '응답 파싱 실패');
        return null;
    }

    // 응답 구조 방어적 처리 (루트 또는 response 하위)
    $header = isset($j['header']) ? $j['header'] : (isset($j['response']['header']) ? $j['response']['header'] : null);
    $body = isset($j['body']) ? $j['body'] : (isset($j['response']['body']) ? $j['response']['body'] : null);
    if ($header !== null && isset($header['resultCode']) && (string) $header['resultCode'] !== '00') {
        $err = '상권정보 API: ' . (isset($header['resultMsg']) ? $header['resultMsg'] : ('코드 ' . $header['resultCode']));
        return null;
    }
    if ($body === null) {
        $err = '상권정보 API: 응답 구조 인식 실패';
        return null;
    }

    $out = array('total' => (int) (isset($body['totalCount']) ? $body['totalCount'] : 0), 'types' => array());
    $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : array();
    foreach ($items as $it) {
        $lc = isset($it['indsLclsNm']) && $it['indsLclsNm'] !== '' ? $it['indsLclsNm'] : '기타';
        if (!isset($out['types'][$lc])) {
            $out['types'][$lc] = 0;
        }
        $out['types'][$lc]++;
    }
    arsort($out['types']);
    return $out;
}

/** 국립중앙의료원 병의원/약국 API 요청 URL 2건 (병의원, 약국 순) */
function pcMedicalApiUrls($key, $sido, $sigungu)
{
    // "고양시 덕양구"처럼 시+구가 나뉜 경우 API는 공백 없는 "고양시덕양구" 표기만 인식 → 질의 시 공백 제거
    // 시/도는 개편 후 공식 명칭만 인식(전라북도→0건) → 질의 시 정규화 (수동 입력 우회 경로 대비)
    $q = http_build_query(array(
        'serviceKey' => $key,
        'Q0' => normalizeSido($sido),
        'Q1' => str_replace(' ', '', $sigungu),
        'numOfRows' => 2000,
        'pageNo' => 1,
    ));
    return array(
        'https://apis.data.go.kr/B552657/HsptlAsembySearchService/getHsptlMdcncListInfoInqire?' . $q,
        'https://apis.data.go.kr/B552657/ErmctInsttInfoInqireService/getParmacyListInfoInqire?' . $q,
    );
}

/** 병의원/약국 XML 응답 파싱 → 집계 (실패 시 null + $err 누적) */
function parseRegionMedicalXml($hx, $px, $bname, $err1, $err2, &$err)
{
    $out = array(
        'hosp_total' => 0, 'hosp_types' => array(), 'hosp_dong' => 0, 'dong_hosp_names' => array(),
        'pharm_total' => 0, 'pharm_dong' => 0,
    );
    $ok = false;

    if ($hx !== null && isset($hx->header->resultCode) && (string) $hx->header->resultCode === '00') {
        $ok = true;
        $out['hosp_total'] = (int) $hx->body->totalCount;
        if (isset($hx->body->items->item)) {
            foreach ($hx->body->items->item as $it) {
                $div = trim((string) $it->dutyDivNam);
                if ($div === '') {
                    $div = '기타';
                }
                if (!isset($out['hosp_types'][$div])) {
                    $out['hosp_types'][$div] = 0;
                }
                $out['hosp_types'][$div]++;
                if ($bname !== '' && strpos((string) $it->dutyAddr, $bname) !== false) {
                    $out['hosp_dong']++;
                    if (count($out['dong_hosp_names']) < 8) {
                        $out['dong_hosp_names'][] = (string) $it->dutyName . ' (' . $div . ')';
                    }
                }
            }
        }
        arsort($out['hosp_types']);
    } else {
        $msg1 = $err1;
        if ($hx !== null) {
            if (isset($hx->header->resultMsg) && (string) $hx->header->resultMsg !== '') {
                $msg1 = (string) $hx->header->resultMsg;
            } elseif (isset($hx->cmmMsgHeader->returnAuthMsg)) { // 인증 오류 시 별도 구조
                $msg1 = (string) $hx->cmmMsgHeader->returnAuthMsg;
            }
        }
        $err .= '병의원 API: ' . $msg1 . ' ';
    }

    if ($px !== null && isset($px->header->resultCode) && (string) $px->header->resultCode === '00') {
        $ok = true;
        $out['pharm_total'] = (int) $px->body->totalCount;
        if (isset($px->body->items->item)) {
            foreach ($px->body->items->item as $it) {
                if ($bname !== '' && strpos((string) $it->dutyAddr, $bname) !== false) {
                    $out['pharm_dong']++;
                }
            }
        }
    } else {
        $msg2 = $err2;
        if ($px !== null) {
            if (isset($px->header->resultMsg) && (string) $px->header->resultMsg !== '') {
                $msg2 = (string) $px->header->resultMsg;
            } elseif (isset($px->cmmMsgHeader->returnAuthMsg)) {
                $msg2 = (string) $px->cmmMsgHeader->returnAuthMsg;
            }
        }
        $err .= '약국 API: ' . $msg2;
    }

    return $ok ? $out : null;
}

/**
 * 국립중앙의료원 전국 병·의원/약국 정보 API (공공데이터포털 B552657)
 * 시도·시군구(한글명) 기준 조회 → 병의원 수(종별), 약국 수, 해당 동 단위 집계
 * 두 API를 병렬 호출한다 (순차 호출 대비 대기시간 절반).
 */
function fetchRegionMedicalData($key, $sido, $sigungu, $bname, &$err)
{
    list($hospUrl, $pharmUrl) = pcMedicalApiUrls($key, $sido, $sigungu);
    $res = fetchParallel(array(
        'hosp' => array('url' => $hospUrl),
        'pharm' => array('url' => $pharmUrl),
    ));
    $err1 = '';
    $err2 = '';
    $hx = pcXmlFromResult($res['hosp'], $err1);
    $px = pcXmlFromResult($res['pharm'], $err2);
    return parseRegionMedicalXml($hx, $px, $bname, $err1, $err2, $err);
}

/**
 * 입지 관련 실측 데이터 일괄 조회 (location.php·premium.php·otc.php 공용 진입점)
 * 서로 독립인 병의원·약국·카카오 지오코딩 3건을 동시에 호출하고,
 * 좌표가 확보되면 상권정보(반경 500m)까지 이어서 조회한다.
 * 기존 순차 호출(의료 → 지오코딩 → 상권) 대비 최악 대기시간을 크게 줄인다.
 * 키가 없는 항목은 건너뛰며(기존 각 페이지의 게이트 조건과 동일), 반환: array($real_data|null, $biz_data|null)
 */
function fetchLocationBundle($dataKey, $kakaoKey, $sido, $sigungu, $bname, $address, &$realErr, &$bizErr)
{
    $wantMedical = ($dataKey !== '' && $sido !== '');
    $wantBiz = ($dataKey !== '' && $kakaoKey !== '' && $address !== '');

    $reqs = array();
    if ($wantMedical) {
        list($hospUrl, $pharmUrl) = pcMedicalApiUrls($dataKey, $sido, $sigungu);
        $reqs['hosp'] = array('url' => $hospUrl);
        $reqs['pharm'] = array('url' => $pharmUrl);
    }
    if ($wantBiz) {
        $reqs['kakao'] = pcKakaoRequest($kakaoKey, $address);
    }
    if (count($reqs) === 0) {
        return array(null, null);
    }
    $res = fetchParallel($reqs);

    $real = null;
    if ($wantMedical) {
        $err1 = '';
        $err2 = '';
        $hx = pcXmlFromResult($res['hosp'], $err1);
        $px = pcXmlFromResult($res['pharm'], $err2);
        $real = parseRegionMedicalXml($hx, $px, $bname, $err1, $err2, $realErr);
    }

    $biz = null;
    if ($wantBiz) {
        $coords = parseKakaoCoords($res['kakao'], $bizErr);
        if ($coords !== null) {
            $biz = fetchCommercialData($dataKey, $coords['x'], $coords['y'], 500, $bizErr);
        }
    }
    return array($real, $biz);
}
