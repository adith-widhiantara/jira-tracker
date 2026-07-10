#!/bin/bash

# 1. Matikan sisa kontainer lama dan nyalakan ulang core services
./vendor/bin/sail down --remove-orphans
./vendor/bin/sail up -d

echo "Menunggu inisialisasi kontainer & database..."
sleep 5

# Jalankan migrate
./vendor/bin/sail artisan migrate

# 2. Tembakkan perintah Worker & Reverb untuk berjalan di background internal kontainer
./vendor/bin/sail artisan queue:listen > /dev/null 2>&1 &
./vendor/bin/sail artisan reverb:start --host=0.0.0.0 --port=8080 > /dev/null 2>&1 &

echo "Sistem Async & Reverb diaktifkan di background kontainer!"

# 3. Jalankan compiler Vite di foreground terminal ini
./vendor/bin/sail npm run dev