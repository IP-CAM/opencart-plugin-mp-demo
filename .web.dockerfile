FROM php:5.6-apache

ENV MYSQL_HOST db
ENV VIRTUAL_HOST 127.0.0.1


# ============================
# Web Server Section
# ============================

RUN apt-get update && apt-get install -y inetutils-ftp vim wget

RUN a2enmod rewrite

# Install mcrypt php extension
RUN apt-get update && apt-get install -y \
    libmcrypt-dev \
    && docker-php-ext-install -j$(nproc) mcrypt \
    && rm -rf /var/lib/apt/lists/*

# Install gd php extension
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng12-dev \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# Install mysqli php extension
RUN apt-get update && apt-get install -y \
    libmysqlclient-dev \
    && docker-php-ext-install -j$(nproc) mysqli \
    && rm -rf /var/lib/apt/lists/*

# Install zip extension
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    && docker-php-ext-install -j$(nproc) zip \
    && rm -rf /var/lib/apt/lists/*



WORKDIR /


# ============================
# FTP Section
# ============================

RUN apt-get update && apt-get install -y --no-install-recommends vsftpd
RUN apt-get update && apt-get install -y xinetd


ADD /utils/vsftpd.conf /etc/vsftpd.conf
ADD /utils/vsftpd /etc/xinetd.d/vsftpd

RUN useradd -m ftpuser -s /bin/bash -d /home/ftpuser
RUN echo ftpuser:ftpass | /usr/sbin/chpasswd

RUN usermod -d /var/ftp/ftpuser/ ftpuser

RUN service xinetd stop

RUN service vsftpd restart &

EXPOSE 21


# ============================
# Apache2 Section
# ============================

ADD /utils/vhost.conf /tmp/vhost.conf

RUN apt-get update

RUN apt-get install wget -y
RUN apt-get install unzip -y

RUN mv /tmp/vhost.conf etc/apache2/sites-available/000-default.conf

RUN mkdir /tmp/opencart

WORKDIR /tmp/opencart


# ============================
# Opencart Section
# ============================

RUN wget https://github.com/opencart/opencart/archive/master.zip -P /tmp/opencart

RUN unzip master.zip

RUN ls -la /tmp/opencart

RUN cp -R /tmp/opencart/opencart-master/upload/* /var/www

RUN chmod -R 777 /var/www/

RUN mv /var/www/config-dist.php /var/www/config.php
RUN mv /var/www/admin/config-dist.php /var/www/admin/config.php

RUN cp /var/www/php.ini /usr/local/etc/php/php.ini

RUN ls -la /var/www/


# ============================
# Selenium Section
# ============================


