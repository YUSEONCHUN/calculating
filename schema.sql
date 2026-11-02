-- ============================================================
-- 권리금 도구 (forkAddon) — 테이블 스키마
-- 데일리팜 약국 툴에서 권리금 관련 테이블만 분리한 것
-- 적용: mariadb -u <계정> -p <DB명> < schema.sql
-- 모든 CREATE/ALTER 가 IF NOT EXISTS — 반복 실행해도 기존 데이터에 영향 없음
-- ============================================================

-- Gemini API 토큰 사용량 기록 (대시보드 통계용)
CREATE TABLE IF NOT EXISTS api_usage (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page          VARCHAR(40) NOT NULL,     -- 호출한 계산기 파일명
  model         VARCHAR(60) NOT NULL,     -- 사용된 모델 (폴백 포함)
  prompt_tokens INT UNSIGNED DEFAULT 0,   -- 입력 토큰
  output_tokens INT UNSIGNED DEFAULT 0,   -- 응답 토큰
  total_tokens  INT UNSIGNED DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at),
  KEY idx_page (page)
) ENGINE=InnoDB;

-- 관리자 설정 — 참고 데이터·계산 가중치 오버라이드 (admin_data.php / admin_weights.php 에서 관리)
CREATE TABLE IF NOT EXISTS admin_settings (
  setting_key VARCHAR(60) PRIMARY KEY,   -- 예: salary_region_avg, goodwill_weights
  value_json  TEXT NOT NULL,             -- 설정값 (JSON) — 미설정 키는 코드의 기본값 사용
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 권리금 시세 피드백 — 지역별 회수배수 자동 보정의 원천 데이터 (금액: 만원)
CREATE TABLE IF NOT EXISTS goodwill_feedback (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role             VARCHAR(10) NOT NULL,        -- 매도 / 매수
  verdict          TINYINT NOT NULL,            -- -1 싸다 / 0 적정 / 1 비싸다
  suggested_manwon INT UNSIGNED NULL,           -- 응답자가 제시한 적정 권리금 (만원)
  region           VARCHAR(20) NOT NULL,        -- seoul_core/seoul/metro/bigcity/local
  presc            INT UNSIGNED NULL,           -- 입력: 월 조제료 (만원)
  otc_daily        INT UNSIGNED NULL,           -- 입력: 일반약 일매출 (만원)
  rent             INT UNSIGNED NULL,           -- 입력: 월세 (만원)
  deposit          INT UNSIGNED NULL,           -- 입력: 보증금 (만원)
  doctor_age       VARCHAR(10) NULL,            -- none/over65/over75
  calc_quick       INT UNSIGNED NULL,           -- 제출 시점 계산 결과 (만원)
  calc_fair        INT UNSIGNED NULL,
  calc_high        INT UNSIGNED NULL,
  is_critical      TINYINT(1) NOT NULL DEFAULT 0, -- 시설양도비 구간 여부 (보정 표본에서 제외)
  correction       DECIMAL(5,3) NOT NULL DEFAULT 1.000, -- 제출 시점 적용 중이던 보정계수
  fingerprint      VARCHAR(64) NULL,            -- 일 단위 중복 방지 (IP+UA 해시)
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_region (region, created_at),
  KEY idx_fp (fingerprint)
) ENGINE=InnoDB;


-- 매물 상담기록 (listings.php 매물 비교 — 브라우저 저장분을 서버에도 축적)
-- 활용: 지역별 실매물 호가(ask) ÷ 모델 적정가(model_fair) ratio → 권리금 시세·계산 지역 보정에 합산 (goodwillRegionCalib)
CREATE TABLE IF NOT EXISTS listing_reports (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id    VARCHAR(32) NOT NULL,     -- 브라우저 익명 토큰 (개인 미식별)
  local_id     VARCHAR(10) NOT NULL,     -- 브라우저 내 매물 id (client_id와 함께 upsert 키)
  kind         VARCHAR(6) NOT NULL DEFAULT 'exist', -- exist(기존 인수) | new(신규 자리)
  name         VARCHAR(40) NOT NULL,
  source       VARCHAR(40) NULL,         -- 중개사·컨설팅명
  cdate        DATE NULL,                -- 상담 일자
  region       VARCHAR(12) NOT NULL,     -- seoul_core|seoul|metro|bigcity|local
  doctor       VARCHAR(8) NOT NULL DEFAULT 'none',
  presc        INT UNSIGNED NOT NULL,    -- 월 조제료 (만원)
  otc_daily    DECIMAL(8,1) NOT NULL DEFAULT 0,
  rent         INT UNSIGNED NOT NULL DEFAULT 0,
  deposit      INT UNSIGNED NOT NULL DEFAULT 0,
  ask          INT UNSIGNED NOT NULL DEFAULT 0,     -- 호가 권리금 (만원, 신규는 0 가능)
  rating       VARCHAR(6) NOT NULL DEFAULT '',      -- 내 평가: great|good|soso|bad|out
  model_fair   INT UNSIGNED NOT NULL DEFAULT 0,     -- 저장 시점 모델 적정가 (만원)
  model_profit INT NOT NULL DEFAULT 0,              -- 저장 시점 월 환산수익 (만원)
  checks_json  TEXT NULL,               -- 확인 체크 상태
  notes_json   TEXT NULL,               -- 항목별 기록 (구조화)
  is_test      TINYINT(1) NOT NULL DEFAULT 0,       -- 1=테스트 가데이터 (보정 집계 제외)
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_client (client_id, local_id),
  KEY idx_region (region, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 적정성 설문 (survey.php 응답 — admin_survey.php 집계) : 권리금(goodwill)·급여(salary)·OTC 판매가(otc)
-- ratio = est_amount ÷ model_amount 의 유형별 중앙값이 유형별 보정 계수
CREATE TABLE IF NOT EXISTS goodwill_survey (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic        VARCHAR(10) NOT NULL DEFAULT 'goodwill', -- goodwill|salary|otc
  submission   VARCHAR(32) NOT NULL,     -- 응답자 1명 묶음 키
  resp_type    VARCHAR(10) NOT NULL,     -- gsTopicTypes() 키 (주제별 상이)
  region       VARCHAR(20) NOT NULL DEFAULT '',
  surveyor     VARCHAR(40) NOT NULL DEFAULT '',
  case_key     VARCHAR(10) NOT NULL,
  est_amount   INT UNSIGNED NOT NULL,    -- 응답자 추정액 (주제 단위: 권리금·급여=만원, OTC=원 / 시세 공개 전 기입)
  model_amount INT UNSIGNED NOT NULL,    -- 제시된 모델·시세 금액 (주제 단위)
  adj_amount   INT UNSIGNED NULL,        -- 시세 공개 후 ± 조정액 (앵커링 있음 — 협상 범위 참고용)
  verdict      VARCHAR(6) NOT NULL,      -- 낮음/적정/높음 계열 (gsTopics() verdicts 키)
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sub (submission),
  KEY idx_type (topic, resp_type, case_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE listing_reports ADD COLUMN IF NOT EXISTS rating VARCHAR(6) NOT NULL DEFAULT '' AFTER ask;
ALTER TABLE goodwill_survey ADD COLUMN IF NOT EXISTS topic VARCHAR(10) NOT NULL DEFAULT 'goodwill' AFTER id;
ALTER TABLE goodwill_survey ADD KEY IF NOT EXISTS idx_topic (topic, resp_type, case_key);
ALTER TABLE goodwill_survey ADD COLUMN IF NOT EXISTS adj_amount INT UNSIGNED NULL AFTER model_amount;
