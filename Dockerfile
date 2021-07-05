FROM php:8.0-apache
RUN set -eux; \
    apt update && \
    apt install iputils-ping -y && \
    apt-get autoremove -y; \
    apt-get clean; \
    rm -r /var/lib/apt/lists/*

COPY . /var/www/html/