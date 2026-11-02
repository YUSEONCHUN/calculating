<?php
// 권리금 적정성 설문 — 기존 배포 링크 호환용 (실체는 survey.php, 주제 goodwill 고정)
// 급여·OTC 설문: survey.php?topic=salary / survey.php?topic=otc
$FORCE_TOPIC = 'goodwill';
require __DIR__ . '/survey.php';
