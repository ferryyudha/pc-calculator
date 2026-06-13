# PC Calculator & Telemetry System 🖥️🎮

Aplikasi web modern berbasis **Laravel 11 (Backend API)** dan **React.js + Vite (Frontend)** untuk melakukan kalkulasi spesifikasi PC, estimasi FPS gaming berbasis AI, pemeriksaan kompatibilitas komponen, kalkulator kapasitas daya PSU, serta pemantauan telemetri perangkat komputer secara real-time.

---

## 🌟 Fitur Utama

### 1. 🎮 Estimasi FPS Gaming (AI-Driven)
* Menghitung perkiraan FPS rata-rata untuk game populer (seperti *Valorant*, *GTA V*, *Cyberpunk 2077*, *Elden Ring*, dll.).
* Mendukung estimasi di berbagai tingkat grafis (Low, Medium, High, Ultra) dan resolusi (720p, 1080p, 4K).
* **RAM Modifier**: Otomatis menyesuaikan hasil FPS berdasarkan spesifikasi RAM yang dipilih (kecepatan MHz, versi DDR, kapasitas, dan single/dual channel mode).

### 2. 🔗 Cek Kompatibilitas Komponen
* Memvalidasi keselarasan antar komponen secara otomatis:
  - Socket CPU ↔ Socket Motherboard.
  - Tipe RAM (DDR4/DDR5) ↔ Dukungan CPU.
  - Tipe RAM (DDR4/DDR5) ↔ Slot Motherboard.
  - Kecukupan daya watt PSU terhadap konsumsi total sistem.

### 3. ⚡ Kalkulator Kebutuhan PSU
* Menghitung total kebutuhan daya sistem berdasarkan TDP CPU, GPU, jumlah Fan case, serta jenis penyimpanan (NVMe SSD & HDD).
* Memberikan rekomendasi kapasitas watt PSU dan sertifikasi efisiensi terbaik (80 Plus) beserta estimasi produk yang tersedia.

### 4. 🏗️ Rekomendasi Rakitan PC (Build Builder)
* Algoritma cerdas yang merakit kombinasi komponen PC terbaik (CPU, GPU, RAM, Motherboard, SSD, PSU) berdasarkan game target, resolusi yang dituju, dan budget Rupiah pengguna.

### 5. 📊 Real-Time PC Monitoring (Telemetry)
* **Agent Integration**: Menerima data metrik langsung dari komputer klien menggunakan aplikasi scanner background agent (`ScanDevice`).
* **Metrik Pemantauan**: Suhu CPU & GPU, beban kerja (load) CPU & GPU, tegangan, penggunaan RAM, dan status kesehatan penyimpanan.
* **Peringatan Hardware (Alerts)**: Notifikasi instan jika suhu CPU/GPU terlalu tinggi (>90°C) atau kesehatan media penyimpanan kritis (<70%).
* **PLN Billing Calculator**: Menghitung estimasi biaya konsumsi listrik bulanan perangkat berdasarkan tarif listrik PLN terkini (Rupiah per kWh) secara real-time.

---

## 🛠️ Teknologi yang Digunakan

* **Backend API**: PHP 8.x, Laravel 11, MySQL.
* **Frontend UI**: React.js (Vite), Axios, TailwindCSS (Sleek Dark Neon Glassmorphism theme).
* **AI Provider**: Groq API SDK (`llama-3.3-70b-versatile` model) untuk proses generasi data benchmark yang realistis.

---

## 🚀 Instalasi Lokal

### 1. Prasyarat
* PHP >= 8.2 terpasang
* Composer terpasang
* Node.js & NPM terpasang
* Database MySQL aktif (XAMPP / Laragon)

### 2. Setup Backend (Laravel)
1. Buka folder `pc_caculator`.
2. Salin `.env.example` menjadi `.env` dan sesuaikan koneksi database MySQL Anda.
3. Jalankan perintah instalasi pustaka:
   ```bash
   composer install
   ```
4. Buat kunci enkripsi aplikasi:
   ```bash
   php artisan key:generate
   ```
5. Jalankan migrasi dan seeder database untuk mengisi data dasar komponen PC:
   ```bash
   php artisan migrate --seed
   ```
6. Jalankan server backend:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

### 3. Setup Frontend (React)
1. Buka folder `pc_caculator/frontend`.
2. Pasang dependensi npm:
   ```bash
   npm install
   ```
3. Sesuaikan alamat backend API pada file `src/services/api.js` (`BACKEND_URL`).
4. Jalankan server development:
   ```bash
   npm run dev -- --host
   ```
5. Akses antarmuka web di browser Anda melalui alamat: `http://localhost:5173`.
