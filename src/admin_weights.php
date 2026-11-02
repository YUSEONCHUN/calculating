<?php
// 관리자 — 계산 가중치 관리 (경력 곡선, 권리금 조정 계수, 시세 가중치 등)
$ADMIN_PAGE = 'weights';
require __DIR__ . '/admin_common.php';

adminPageOpen(
    '⚖ 계산 가중치 관리',
    '각 계산식에 곱해지는 비율·계수·임계값을 설정합니다. 비율은 소수로 입력하세요 (0.15 = 15%).'
    . ' 기준 데이터 표는 <a href="admin_data.php">참고 데이터 관리</a>에서 다룹니다.',
    '가중치 관리'
);

foreach ($ADMIN_GROUPS['weights'] as $group) {
    renderGroup($group, $overrides, $updated_map);
}

adminPageClose();
