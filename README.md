# Nano AI Chat Assistant (PHP + Azure OpenAI)

Aplikasi chat **single-file PHP** yang terintegrasi dengan **Azure OpenAI** (deployment `gpt-4o`). Menyimpan riwayat percakapan di **session** dan memberikan UI sederhana (HTML+CSS+JS) dalam satu berkas.

> Berkas utama: `chat.php` (semua logic backend + tampilan frontend)

---

## ✨ Fitur
- Kirim & terima pesan ke model Azure OpenAI (Chat Completions).
- Riwayat percakapan di-*persist* per session browser (`$_SESSION['chat_history']`).
- Tombol **Clear Chat** untuk mengosongkan riwayat.
- Tampilan modern, ada indikator “AI mengetik…”, *auto-scroll*, dan *error toast*.
- Tanpa database; cukup PHP + cURL.

---

## 🧩 Arsitektur Singkat
- **Backend (PHP)**: menerima `POST` AJAX dengan `action`:
  - `send_message`: kirim string `message` → forward ke Azure → balasan JSON.
  - `clear_history`: kosongkan `$_SESSION['chat_history']`.
- **Frontend (HTML/JS)**: form input, fetch API ke halaman yang sama (`chat.php`), render balasan di list.

---

## 🔧 Kebutuhan
- PHP **7.4+** (disarankan 8.x).
- Ekstensi PHP **cURL** & **OpenSSL** aktif.
- Akses internet keluar (HTTPS) ke endpoint Azure OpenAI.

Cek cepat:
```bash
php -m | findstr /I "curl openssl"
php -v
```

---

## ⚙️ Konfigurasi Azure OpenAI
Di bagian atas `chat.php` ada konstanta berikut (ganti sesuai akunmu):

```php
define('AZURE_OPENAI_ENDPOINT', 'https://<NAMA-RESOURCE>.openai.azure.com/');
define('AZURE_OPENAI_KEY', '...API_KEY_AZURE...');
define('AZURE_DEPLOYMENT_NAME', 'gpt-4o'); // atau nama deployment milikmu
define('AZURE_API_VERSION', '2025-01-01-preview');
```

**Langkah ringkas:**
1. Buat **Azure OpenAI resource** di Azure Portal.
2. Deploy model (contoh: `gpt-4o`) → catat **Deployment name**.
3. Salin **Endpoint** & **API Key** dari *Keys and Endpoint*.
4. Isi ke konstanta di atas (jangan commit key ke repo publik).

---

## ▶️ Cara Menjalankan

### A. Lokal (XAMPP/Windows)
1. Letakkan `chat.php` di `C:\xampp\htdocs\nano-ai\chat.php` (contoh).
2. Pastikan `php_curl` & `openssl` aktif (XAMPP → `php.ini`).
3. Akses di browser: `http://localhost/nano-ai/chat.php`.
4. Masukkan API Key/Endpoint → coba chat.

### B. Server (aaPanel / Nginx / Apache)
1. Upload `chat.php` ke webroot (mis. `/www/wwwroot/domain.com/public/chat.php`).
2. Pastikan PHP versi ≥ 7.4 dan ekstensi cURL aktif.
3. Buka `https://domain.com/chat.php` dan mulai chat.

> **Catatan**: Jika ingin struktur lebih rapi (mis. `public/` + `src/`), bisa menaruh `chat.php` di `src/` dan membuat *wrapper* `public/index.php` yang hanya `require ../src/chat.php` (tanpa mengubah baris di file asli).

---

## 📡 Format API (AJAX)
- **Endpoint**: `POST` ke `chat.php`
- **Header**: `multipart/form-data` (FormData)
- **Body**:
  - `action=send_message` dan `message=...`
  - `action=clear_history`

**Contoh respons sukses (`send_message`):**
```json
{
  "ok": true,
  "reply": "Halo! Ada yang bisa saya bantu?",
  "chat_history": [
    { "role": "user", "content": "Hai" },
    { "role": "assistant", "content": "Halo! Ada yang bisa saya bantu?" }
  ]
}
```

**Contoh respons error:**
```json
{ "ok": false, "error": "Unauthorized (invalid API key)" }
```

---

## 📝 Perilaku Riwayat
- Riwayat disimpan di `$_SESSION['chat_history']`.
- Clear chat akan mengosongkan array tersebut.
- Riwayat tidak permanent; menutup browser/incognito akan mereset session.

---

## 🧰 Troubleshooting
- **401/403** → Key/Endpoint/Deployment salah.
- **404** → Cek path endpoint atau versi API.
- **429** → Rate limit / kuota habis; coba ulang atau turunkan frekuensi.
- **cURL error (SSL/timeout)** → Pastikan OpenSSL aktif & server bisa akses Internet.
- **Tidak ada balasan** → cek `error_log` PHP atau tambahkan log pada blok `catch`.

---

## 📦 Lisensi
Gunakan bebas untuk keperluan pribadi/komersial, *tanpa garansi*. Pastikan mematuhi **Azure OpenAI Terms** & ketentuan privasi data.

---

_Dokumentasi ini dibuat otomatis berdasarkan isi `chat.php`. Terakhir diperbarui: 2025-08-16 10:50:18 UTC._
