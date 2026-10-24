FROM ubuntu:22.04
ARG DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Argentina/San_Juan

# Configurar locales
RUN apt-get update && \
    apt-get install -y locales && \
    locale-gen es_ES.UTF-8 && \
    update-locale LANG=es_ES.UTF-8

# Establecer variables de entorno de locale
ENV LANG=es_ES.UTF-8
ENV LC_ALL=es_ES.UTF-8

# Instala PHP 8.5 y extensiones comunes para Laravel, incluyendo SQLite
RUN apt-get update && \
    apt-get install -y software-properties-common tzdata ca-certificates gnupg && \
    add-apt-repository ppa:ondrej/php -y && \
    apt-get update && \
    apt-get install -y \
        php8.5 php8.5-cli php8.5-mbstring php8.5-xml php8.5-bcmath php8.5-curl php8.5-zip \
        php8.5-sqlite3 php8.5-pdo php8.5-pdo-mysql php8.5-gd php8.5-intl php8.5-readline \
        openssl curl git unzip composer \
        # Herramientas de red y diagnóstico
        iputils-ping \
        net-tools \
        dnsutils \
        traceroute \
        telnet \
        netcat-openbsd \
        wget \
        iproute2 \
        tcpdump \
        nmap \
        htop \
        vim \
        nano

# Instalar Node.js 20 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Verificar instalación de Node.js y npm
RUN node --version && npm --version

# Instalar Mailpit (servidor SMTP local para desarrollo)
RUN curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh | bash

# Instalar dependencias para renderizado de video (Remotion / Puppeteer)
RUN apt-get update && \
    apt-get install -y \
        ffmpeg \
        libnss3 \
        libnspr4 \
        libatk1.0-0 \
        libatk-bridge2.0-0 \
        libcups2 \
        libdrm2 \
        libxkbcommon0 \
        libxcomposite1 \
        libxdamage1 \
        libxfixes3 \
        libxrandr2 \
        libgbm1 \
        libasound2 \
        libpango-1.0-0 \
        libcairo2

# Limpieza
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala Composer (por si acaso)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
