FROM php:8.2-apache

# Ekstensi PHP yang dibutuhkan aplikasi: pdo_pgsql (koneksi ke database
# katalog iPos5/PostgreSQL) dan pdo_sqlite (penyimpanan pengaturan & akun di
# data/settings.sqlite).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libsqlite3-dev \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*
# Catatan: libpq-dev/libsqlite3-dev sengaja TIDAK di-purge setelah build.
# `apt-get purge --auto-remove` akan ikut menghapus libpq5 (runtime lib yang
# dipakai pdo_pgsql.so saat request), yang menyebabkan error
# "could not find driver" walau ekstensinya "terpasang".

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html

# Folder data/ harus bisa ditulis oleh web server (menyimpan settings.sqlite)
RUN chown -R www-data:www-data /var/www/html/data \
    && chmod -R 775 /var/www/html/data

EXPOSE 80
