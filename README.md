# NADI Central

NADI Central adalah control plane dan monitoring console untuk platform NADI. Aplikasi ini mengelola identitas server dan Agent, registrasi aplikasi, approval, activation grant, kredensial Agent, penerimaan telemetry, serta pemantauan operasional.

Repository: [simfinance/nadi-central](https://github.com/simfinance/nadi-central)

## Teknologi

- PHP `^8.3`
- Laravel Framework `^13.17`
- Laravel Tinker `^3.0`
- Database utama: MySQL melalui konfigurasi koneksi Laravel
- Runtime deployment saat ini menggunakan PHP-FPM dan Nginx
- Signing release manifest menggunakan Ed25519 melalui OpenSSL

Versi dependency yang menjadi acuan adalah `composer.json` dan `composer.lock` pada branch repository.

## Fitur

### Dashboard dan monitoring Agent

- Ringkasan server, Agent, dan aplikasi yang terdaftar.
- Status registration, activation, dan monitoring aplikasi.
- Informasi freshness telemetry berdasarkan waktu penerimaan stream.
- Ringkasan batch dan record telemetry.
- Halaman monitoring server dan aplikasi.
- Tampilan telemetry per Agent serta detail record.

### Manajemen dan lifecycle

- Agent dan server registry.
- Enrollment token untuk proses registrasi Agent.
- Registration dan approval workflow.
- Activation grant yang ditandatangani dengan Ed25519 dan diverifikasi oleh Agent.
- Pengelolaan Agent credential, termasuk penerbitan, rotasi, dan pencabutan.
- Suspend dan resume monitoring aplikasi.
- Konfigurasi kebijakan retensi telemetry.

Approval, activation, dan monitoring merupakan status yang berbeda. Monitoring operasional aplikasi bergantung pada lifecycle dan grant yang valid.

### Agent API

Base API: `/api/v1/nadi`

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/releases/manifest` | Mengambil release manifest Agent yang ditandatangani |
| POST | `/agent/enroll` | Registrasi Agent; dibatasi rate limit |
| POST | `/agent/control/poll` | Agent mengambil control-plane response |
| POST | `/agent/telemetry/batches` | Mengirim batch telemetry |
| POST | `/agent/activation-grants/{grantId}/ack` | Acknowledgement activation grant |
| GET | `/agent/credentials/deliveries/{deliveryId}` | Mengambil credential delivery yang ditujukan ke Agent |
| POST | `/agent/credentials/deliveries/{deliveryId}/ack` | Acknowledgement credential delivery |

Endpoint Agent selain release manifest dan enrollment berada di dalam middleware autentikasi `nadi.agent`. Enrollment menggunakan throttle `10 requests/minute`.

Release manifest mengembalikan JSON dan detached signature pada header `X-NADI-Manifest-Signature`. Private signing key disimpan di luar repository dan tidak boleh dimasukkan ke source control.

### Central Health

Central Health tersedia pada:

- URL: `/central-health`
- Route name: `monitoring.central-health`
- Akses: pengguna yang sudah login (`auth` middleware)
- Refresh default: setiap 5 detik; pilihan 3, 5, 10, atau 30 detik.

Central Health memeriksa host yang menjalankan NADI Central, terpisah dari monitoring server Agent.

Cakupan snapshot yang saat ini diimplementasikan:

- **Host dan CPU:** hostname, OS, kernel, arsitektur, virtualisasi, uptime, CPU utilization total/per-core, load average, context switches, dan process count.
- **Memory dan pressure:** RAM, cache, swap, OOM counter, serta Linux Pressure Stall Information jika tersedia.
- **Storage:** filesystem dan mount point, kapasitas, inode, writable state, disk throughput, IOPS, dan device utilization.
- **Network:** interface, alamat IPv4, throughput, packet errors/drops, TCP connection states, dan listening ports.
- **Processes:** ringkasan proses, top CPU, dan top memory.
- **Services:** status systemd untuk Nginx, PHP-FPM 8.5, MySQL, Cron, serta layanan opsional yang terpasang.
- **Database:** konektivitas database Central dan statistik engine yang dapat dibaca oleh koneksi aplikasi.
- **PHP runtime:** versi PHP, SAPI, memory limit, execution/upload limits, extensions, dan OPcache.
- **Laravel application:** versi dan environment Laravel, konfigurasi queue/cache/session, status APP_KEY, maintenance mode, cache status, writable storage, failed/pending jobs yang didukung, ukuran log, dan error log terbaru.
- **System housekeeping:** kebutuhan reboot, sinkronisasi waktu/NTP, dan informasi update OS jika tersedia.

Central Health bersifat **read-only**. Pemeriksaan membaca sumber lokal seperti `/proc`, `/sys`, `systemctl show`, `df`, serta query database ringan. Halaman tidak melakukan restart service, perubahan konfigurasi, firewall, atau remediation otomatis.

Beberapa batasan yang perlu diketahui:

- Pemeriksaan yang tidak tersedia atau tidak dapat diakses akan ditampilkan sebagai N/A/Unknown; hal tersebut bukan bukti kondisi sehat.
- Grafik menggunakan rolling samples di browser (maksimal 90 sampel). Histori grafik tidak disimpan di database dan akan hilang ketika tab/browser ditutup atau halaman dimuat ulang.
- Health score adalah ringkasan heuristik dari findings saat snapshot diambil, bukan SLA atau pengukuran availability historis.
- Pemeriksaan layanan opsional hanya ditampilkan jika unit terkait terpasang.
- Statistik queue bergantung pada driver queue dan tabel yang tersedia; tidak semua backend queue menyediakan jumlah pending job melalui pemeriksaan ini.

## Keamanan dan batas operasional

- Komunikasi Agent menggunakan HTTPS.
- Agent API menggunakan middleware autentikasi Agent, kecuali endpoint yang memang dipakai untuk release manifest dan enrollment awal.
- Release manifest ditandatangani dengan Ed25519. Public key digunakan untuk verifikasi di sisi Agent.
- Activation Grant menggunakan Ed25519. Central menyimpan private seed raw 32-byte; Agent menerima public key raw 32-byte. Private signing key hanya berada di host Central pada secret path yang dikonfigurasi; jangan commit key, credential, token, atau file `.env`.
- Telemetry harus mengikuti batas minimisasi data dan redaction. Jangan mengirim password, token, private key, isi `.env`, atau data bisnis yang tidak diperlukan.
- NADI v1 berfokus pada observability dan lifecycle control. Central Health tidak melakukan auto-healing, restart, failover, atau perubahan konfigurasi host.

## Struktur penting

| Path | Keterangan |
|---|---|
| `app/Http/Controllers/` | Controller web dan API |
| `app/Http/Middleware/` | Middleware autentikasi Agent |
| `app/Services/` | Service lifecycle, credential, activation, dan telemetry |
| `app/Models/` | Model data NADI |
| `config/nadi.php` | Konfigurasi NADI |
| `routes/web.php` | Route dashboard dan halaman manajemen |
| `routes/api.php` | Route API Agent |
| `resources/views/` | Blade views |
| `storage/logs/` | Log aplikasi Laravel |

## Activation Grant Ed25519

Generate key pair hanya pada lingkungan yang dituju dan melalui prosedur perubahan yang disetujui:

```bash
php artisan nadi:activation-keys:generate
```

Command tidak menimpa key yang sudah ada. Private seed disimpan dengan permission `0600`; public key disimpan sebagai raw 32-byte Ed25519 public key. Lihat [Activation Grant Ed25519 Key Management](docs/operations/activation-grant-ed25519-key-management-v1.0.md) untuk distribusi public key dan prosedur rotasi.

## Persyaratan deployment

Deployment harus menyediakan:

- PHP yang memenuhi constraint Composer dan ekstensi Laravel yang diperlukan.
- Composer dan dependency yang tercantum di `composer.lock`.
- Database MySQL yang telah disiapkan sesuai schema NADI yang digunakan deployment.
- Nginx atau web server yang meneruskan request PHP ke PHP-FPM.
- TLS/HTTPS yang valid untuk domain Central.
- Permission Laravel yang benar untuk direktori `storage` dan `bootstrap/cache`.
- Secret dan signing key di luar repository dengan permission minimum yang diperlukan.

Central Health memanfaatkan informasi host Linux. Beberapa pemeriksaan membutuhkan binary sistem seperti `systemctl`, `df`, `ip`, dan `timedatectl`. Hasil yang tidak dapat diperoleh karena binary, permission, atau dukungan OS yang tidak tersedia akan ditandai tidak tersedia; jangan memberikan privilege root penuh ke PHP-FPM hanya untuk menghilangkan status tersebut.

## Instalasi dan deployment

Repository ini merupakan source aplikasi NADI Central, bukan pengganti prosedur provisioning server dan persiapan schema database.

Untuk deployment baru:

1. Siapkan host, PHP, PHP-FPM, web server, TLS, dan database sesuai deployment architecture.
2. Clone repository dan checkout branch atau release yang telah ditetapkan.
3. Jalankan `composer install --no-dev --prefer-dist --optimize-autoloader` untuk deployment production.
4. Siapkan konfigurasi environment secara aman. Jangan commit `.env`.
5. Pastikan schema database NADI yang sesuai sudah disiapkan dan diaudit sebelum menjalankan perubahan database.
6. Konfigurasikan document root web server ke direktori `public/`.
7. Pastikan permission `storage/` dan `bootstrap/cache/` sesuai user PHP-FPM.
8. Jalankan optimasi Laravel setelah konfigurasi production siap, misalnya `php artisan optimize`.
9. Verifikasi route, login, Agent API, release manifest, telemetry ingestion, dan Central Health.

**Penting:** jangan menjalankan `migrate:fresh`, `db:wipe`, atau perintah yang menghapus/membangun ulang database pada environment yang berisi data. Jalankan migration hanya jika ada migration yang ditinjau dan disetujui untuk deployment tersebut.

Perintah pemeriksaan yang berguna:

```bash
php artisan about
php artisan route:list
php artisan route:list --path=api/v1/nadi
php artisan route:list --path=central-health
php artisan test
```

Jalankan test pada environment pengembangan atau staging dengan konfigurasi database pengujian, bukan dengan asumsi database produksi aman untuk test.

## Status dokumentasi

README ini mendeskripsikan route dan fitur yang ditemukan pada source repository. Status operasional setiap deployment tetap harus diverifikasi pada server yang bersangkutan. Keberadaan route atau halaman tidak dengan sendirinya membuktikan bahwa seluruh integrasi eksternal, scheduler, notifikasi, backup, atau monitoring historis telah dikonfigurasi dan berjalan.

## Lisensi

Lisensi dan ketentuan distribusi proyek mengikuti file lisensi yang berlaku di repository ini.
