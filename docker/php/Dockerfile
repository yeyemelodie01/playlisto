FROM php:8.3-fpm

# Installation des dépendances système
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        zlib1g-dev \
        libxml2-dev \
        librabbitmq-dev \
        less \
        git \
        openssh-client \
        unzip \
        vim \
        libpng-dev \
        curl \
        libzip-dev \
        locales \
        apt-utils \
        libicu-dev \
        g++ \
        libonig-dev \
        libxslt-dev \
        gnupg \
        nano

# Configuration des locales
RUN echo "en_US.UTF-8 UTF-8" >> /etc/locale.gen && \
    echo "fr_FR.UTF-8 UTF-8" >> /etc/locale.gen && \
    locale-gen

# Configure et installe intl AVEC les autres extensions (on ajoute pcntl pour la CLI)
RUN docker-php-ext-configure intl && \
    docker-php-ext-install pdo pdo_mysql mysqli sockets zip soap gd intl calendar dom mbstring xsl pcntl

# Installation via PECL des extensions apcu, amqp, mailparse et xdebug
RUN pecl install apcu amqp mailparse xdebug && \
    docker-php-ext-enable apcu amqp mailparse xdebug pdo_mysql gd opcache intl zip calendar dom mbstring xsl

# Installation de Composer
RUN curl --location --output /usr/local/bin/composer https://getcomposer.org/composer.phar && \
    chmod +x /usr/local/bin/composer

# Installation de Node.js, npm et outils associés
RUN apt-get update && apt-get install -y software-properties-common nodejs npm
RUN echo node -v
RUN npm install npm@9 -g && \
    npm install n -g && \
    npm install yarn -g && \
    n latest

# Configuration Git (pour éviter les erreurs de safe.directory)
RUN git config --global --add safe.directory /var/www

# Variable d'environnement pour Node
ENV NODE_OPTIONS="--openssl-legacy-provider"

# Configuration des limites de taille de fichier PHP
RUN echo 'upload_max_filesize=512M' > /usr/local/etc/php/conf.d/upload_max_filesize.ini && \
    echo 'post_max_size=512M' > /usr/local/etc/php/conf.d/post_max_size.ini

# Répertoire de travail
WORKDIR /var/www

# Mise à jour du PATH
ENV PATH="/var/www/vendor/bin:bin:${PATH}"