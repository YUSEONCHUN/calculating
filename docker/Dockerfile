# 권리금 도구 — PHP 8.2 + Apache
# 원본(데일리팜 약국 툴)이 PHP 7.2 호환으로 작성되어 8.x 에서도 그대로 동작한다.
FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev zlib1g-dev libonig-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 소스는 /var/www/html 로 마운트(개발) 또는 복사(배포)
COPY src/ /var/www/html/

# .env·sql·md 웹 노출 차단 + 세션 디렉터리
RUN { \
      echo '<FilesMatch "^\.env">'; echo '  Require all denied'; echo '</FilesMatch>'; \
      echo '<FilesMatch "\.(sql|md|sh)$">'; echo '  Require all denied'; echo '</FilesMatch>'; \
    } > /etc/apache2/conf-available/deny-sensitive.conf \
    && a2enconf deny-sensitive \
    && mkdir -p /var/www/html/.sessions && chown -R www-data:www-data /var/www/html/.sessions

# 운영 기본값: 화면에 오류 노출 금지 (개발 시 compose 에서 덮어씀)
RUN { echo 'display_errors = Off'; echo 'log_errors = On'; echo 'error_log = /dev/stderr'; } \
      > /usr/local/etc/php/conf.d/zz-app.ini

EXPOSE 80
