# Catatan Error & Panduan Debugging (Asisten PC Calculator)

Dokumen ini mencatat masalah-masalah umum yang terjadi pada chatbot Asisten PC Calculator beserta penyebab dan solusinya untuk memudahkan AI/developer melakukan pengecekan di masa mendatang.

---

## 1. Error Koneksi Frontend (Connection Refused / Timeout)
* **Pesan di Chatbox**: `"Maaf, gagal menghubungi server. Coba lagi."`
* **Penyebab**:
  1. **Port 8000 Offline**: Frontend ([api.js](frontend/src/services/api.js)) diatur untuk menghubungi backend di `http://localhost:8000` saat diakses via `localhost`. Jika perintah `php artisan serve` belum dijalankan di folder `pc_caculator`, koneksi akan gagal (*Connection Refused*).
  2. **Timeout Terlalu Singkat**: Sebelumnya, timeout Axios diatur sebesar 15 detik. Pemanggilan asisten AI yang membutuhkan pencarian database berulang (*multi-turn tool calls*) sering kali memakan waktu lebih dari 15 detik, menyebabkan request dibatalkan secara prematur oleh frontend.
* **Solusi**:
  * Jalankan server backend secara lokal menggunakan:
    ```bash
    php artisan serve
    ```
  * Timeout Axios telah dinaikkan dari **15 detik menjadi 30 detik** (`timeout: 30000`) di [api.js](frontend/src/services/api.js) untuk mengakomodasi proses AI yang kompleks.

---

## 2. Kegagalan API Groq (Rate Limit / Outage)
* **Pesan di Chatbox**: `"Maaf, terjadi kesalahan. Coba lagi sebentar."`
* **Penyebab**:
  * Backend berhasil dihubungi, namun panggilan ke API Groq gagal (mengembalikan `null`).
  * Penyebab paling sering adalah **Rate Limit (HTTP 429)** pada API Key Groq yang dipasang di file [.env](.env) karena penggunaan bersama pada *Free Tier*.
  * Ketika asisten AI mendeteksi komponen dalam pesan user, ia dapat memanggil tool pencarian berturut-turut (misal: mencari CPU, mencari GPU, dst.), yang dengan cepat menghabiskan kuota request per menit (RPM/TPM) dari API Key gratis tersebut.
* **Solusi**:
  * Ganti `GROQ_API_KEY` di file [.env](.env) dengan API Key Groq pribadi Anda yang masih aktif dan memiliki kuota yang cukup:
    ```env
    GROQ_API_KEY=gsk_your_personal_api_key_here
    ```

---

## 3. Lokasi Log untuk Pengecekan Error
Jika terjadi error di masa mendatang, silakan periksa file log berikut:
* **Log Backend Laravel**: [storage/logs/laravel.log](storage/logs/laravel.log) (mencatat aktivitas ChatAssistant, kesalahan SQL, atau kegagalan API Groq).
* **Log Server Apache (XAMPP)**: `C:\xampp\apache\logs\error.log` (mencatat jika ada crash pada PHP atau masalah server web).
