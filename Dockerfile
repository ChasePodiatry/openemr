FROM php:5.6-apache

WORKDIR /var/www/html

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions imagick mailparse mysql mysqli pdo_mysql redis xsl zip

COPY . /var/www/html
