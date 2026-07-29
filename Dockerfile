FROM php:8.2-apache

# Ekstensi PHP yang dibutuhkan aplikasi: pdo_pgsql (koneksi ke database
# katalog iPos5/PostgreSQL), pdo_sqlite (penyimpanan pengaturan & akun di
# data/settings.db), dan gd (generate thumbnail gambar produk di image.php -
# tanpa ini aplikasi tetap jalan, tapi thumbnail di daftar katalog fallback
# ke gambar ukuran penuh, lebih berat).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libsqlite3-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql pdo_sqlite gd \
    && rm -rf /var/lib/apt/lists/*
# Catatan: libpq-dev/libsqlite3-dev sengaja TIDAK di-purge setelah build.
# `apt-get purge --auto-remove` akan ikut menghapus libpq5 (runtime lib yang
# dipakai pdo_pgsql.so saat request), yang menyebabkan error
# "could not find driver" walau ekstensinya "terpasang".

# OPcache sudah include di image PHP resmi, tinggal diaktifkan. Ini mencegah
# PHP mem-parse ulang semua file .php di tiap request.
# validate_timestamps=1 + revalidate_freq=2 dipilih supaya tetap aman kalau
# ada yang update file langsung di server (bukan cuma lewat rebuild image);
# kalau deploy-nya selalu lewat `docker compose up -d --build`, boleh ganti
# validate_timestamps ke 0 untuk performa maksimal.
RUN docker-php-ext-enable opcache
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache-custom.ini

RUN a2enmod rewrite deflate expires headers

# Image php:8.2-apache defaultnya AllowOverride None untuk /var/www/, jadi
# .htaccess di root project (kompresi gzip, cache header aset statis) tidak
# akan pernah dibaca kalau tidak diubah ke AllowOverride All.
RUN sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . /var/www/html

# Folder data/ harus bisa ditulis oleh web server (menyimpan settings.db
# dan cache gambar produk di data/img-cache/, dibuat otomatis saat pertama
# kali ada request gambar).
RUN mkdir -p /var/www/html/data/img-cache \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 775 /var/www/html/data

EXPOSE 80
