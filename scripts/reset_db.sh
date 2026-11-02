#!/bin/bash
# DB 를 비우고 schema.sql 을 다시 적용한다 (데이터 전부 삭제 — 개발용)
set -euo pipefail
cd "$(dirname "$0")/.."
read -p "DB 의 모든 데이터가 삭제됩니다. 계속할까요? (yes 입력) " ok
[ "$ok" = "yes" ] || { echo "취소"; exit 1; }
docker compose down -v
docker compose up -d
echo "완료 — 스키마가 새로 적용되었습니다."
