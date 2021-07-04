FROM php:8.0-apache
RUN set -eux; apt update; apt install iputils-ping -y
COPY . /var/www/html/