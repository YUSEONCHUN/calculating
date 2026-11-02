<?php
// 관리자 — 참고 데이터 관리 (지역 평균 급여, 회수개월수, 마진 밴드, 수가, 설비 단가)
$ADMIN_PAGE = 'data';
require __DIR__ . '/admin_common.php';

adminPageOpen(
    '🗂 참고 데이터 관리',
    '각 계산기가 기준으로 삼는 참고 데이터를 설정합니다. 실측·조사 데이터를 확보하면 여기서 갱신하세요.'
    . ' 계산식의 비율·계수는 <a href="admin_weights.php">가중치 관리</a>에서 다룹니다.',
    '참고 데이터'
);

foreach ($ADMIN_GROUPS['data'] as $group) {
    renderGroup($group, $overrides, $updated_map);
}

adminPageClose();
