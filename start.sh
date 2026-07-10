#!/bin/bash

# --- LANGKAH 1: PEMBERSIHAN INTERNAL LARAVEL & REDIS CACHE ---
echo "Membersihkan cache internal Laravel dan antrean Job..."
# Jalankan perintah via Sail jika kontainer masih samar-samar menyala, 
# atau abaikan error-nya jika kontainer memang sudah mati.
./vendor/bin/sail artisan cache:clear --quiet 2>/dev/null
./vendor/bin/sail artisan queue:clear --quiet 2>/dev/null
./vendor/bin/sail artisan queue:flush --quiet 2>/dev/null
./vendor/bin/sail artisan config:clear --quiet 2>/dev/null
./vendor/bin/sail artisan route:clear --quiet 2>/dev/null

# Hentikan sisa kontainer lama dan bersihkan volume untuk memastikan Redis & DB segar
echo "Menghentikan kontainer lama dan menghancurkan volume Docker..."
./vendor/bin/sail down --volumes --remove-orphans

# --- LANGKAH 2: INISIALISASI ULANG MESIN DOCKER ---
echo "Menyalakan kontainer Laravel Sail..."
./vendor/bin/sail up -d

# Jeda waktu aman untuk memastikan database PostgreSQL dan Redis benar-benar siap menerima koneksi
echo "Menunggu inisialisasi Docker & Jaringan..."
sleep 5

# --- LANGKAH 3: SEGARKAN DATABASE ---
echo "Menjalankan migrasi database baru..."
./vendor/bin/sail artisan migrate:fresh --seed

# --- LANGKAH 4: AKTIFKAN BACKGROUND PROCESS ---
# Menggunakan --timeout=0 untuk mencegah hilangnya koneksi asinkronus akibat pembatasan waktu Symfony
echo "Mengaktifkan Queue Worker dan Reverb Server di latar belakang kontainer..."
./vendor/bin/sail artisan queue:work --timeout=0 > /dev/null 2>&1 &
./vendor/bin/sail artisan reverb:start --host=0.0.0.0 --port=8080 > /dev/null 2>&1 &

echo "--------------------------------------------------------"
echo "Sistem Async (Redis Worker) & WebSocket (Reverb) AKTIF!"
echo "--------------------------------------------------------"

# --- LANGKAH 5: JALANKAN COMPILER VITE ---
echo "Menjalankan Vite Development Server..."
./vendor/bin/sail npm run dev