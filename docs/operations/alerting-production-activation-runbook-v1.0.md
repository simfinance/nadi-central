# Panduan Aktivasi Alerting NADI di Produksi v1.0

## Status dan ruang lingkup

Panduan ini menjelaskan prosedur aktivasi produksi secara terkendali untuk aturan alert Prometheus yang sudah tersedia, integrasi Alertmanager lokal, dan webhook NADI Central yang menggunakan autentikasi.

Dokumen ini merupakan prosedur operasional, bukan bukti bahwa aktivasi produksi telah dilakukan. Ikuti jendela perubahan yang disetujui, kontrol akses, kebijakan pencadangan, dan prosedur deployment yang berlaku di lingkungan tersebut.

Aktivasi ini terbatas pada evaluasi alert dan penyimpanan data alert di NADI Central. Aktivasi ini tidak mencakup konfigurasi SMTP, Telegram, tujuan notifikasi eksternal, perubahan perilaku Agent, maupun remediasi otomatis.

## Prasyarat

Jangan mulai melakukan perubahan di produksi sebelum seluruh kondisi berikut terpenuhi:

- Implementasi webhook Central dan migrasi databasenya telah ditinjau serta di-deploy melalui proses rilis Central yang disetujui.
- Migrasi yang membuat tabel `nadi_alerts` dan `nadi_alert_events` telah selesai dengan sukses.
- Aplikasi Central dapat diakses melalui HTTPS pada URL webhook.
- Layanan Prometheus dan Alertmanager dalam kondisi sehat serta tetap terikat hanya pada listener lokal yang telah disetujui.
- Konfigurasi Prometheus dan Alertmanager saat ini telah dicadangkan, dan lokasi serta pemilik file backup telah dicatat.
- Secret webhook khusus telah dibuat dan dikirimkan melalui kanal pengelolaan secret yang disetujui.
- Environment Central dan file secret Alertmanager dapat diperbarui tanpa mengekspos secret di source control, riwayat shell, rekaman terminal, atau log.
- Operator memiliki jendela rollback dan izin untuk melakukan reload pada kedua layanan.

Jika salah satu prasyarat belum terpenuhi, hentikan proses. Jangan mengatasinya dengan melewati autentikasi, mengekspos API lokal, atau mengubah konfigurasi Agent.

## Nilai integrasi yang telah ditetapkan

| Item | Nilai |
| --- | --- |
| Webhook Central | `https://nadi.pesantrenalirsyad.org/api/v1/nadi/alertmanager/webhook` |
| Key environment Central | `NADI_ALERTMANAGER_WEBHOOK_TOKEN` |
| File token Alertmanager | `/etc/alertmanager/secrets/nadi-central-webhook-token` |
| Target lokal Alertmanager | `127.0.0.1:9093` |
| File aturan Prometheus | `/etc/prometheus/rules/nadi-alert-rules.yml` |

Secret webhook harus berbeda dari credential Agent dan `NADI_PROMETHEUS_METRICS_TOKEN`. Jangan pernah melakukan commit terhadap secret atau memasukkannya ke dalam label maupun anotasi alert.

## Urutan aktivasi

Lakukan satu tahap pada satu waktu. Catat hasilnya sebelum melanjutkan ke tahap berikutnya.

### Tahap 1 — Persiapan dan pencadangan

1. Pastikan nama layanan yang aktif, akun layanan, lokasi konfigurasi, dan mekanisme reload yang disetujui pada server.
2. Cadangkan `/etc/prometheus/prometheus.yml` dan `/etc/alertmanager/alertmanager.yml` ke file dengan nama bertimestamp dan akses terbatas.
3. Pastikan target Prometheus saat ini sehat dan catat status layanan Alertmanager saat ini.
4. Pastikan rilis webhook Central dan migrasinya telah selesai sebelum Alertmanager dikonfigurasi untuk mengirimkan alert ke webhook tersebut.

Jangan menimpa konfigurasi mana pun menggunakan contoh konfigurasi dari repository. Kedua contoh tersebut hanya menjadi referensi untuk penggabungan konfigurasi.

### Tahap 2 — Konfigurasi secret webhook khusus

1. Buat satu secret acak dengan entropi tinggi menggunakan metode pembuatan secret yang disetujui di lingkungan tersebut. Nilai acak 32 byte atau lebih kuat sudah sesuai.
2. Tetapkan nilai yang sama sebagai `NADI_ALERTMANAGER_WEBHOOK_TOKEN` pada environment Central melalui prosedur pengelolaan secret yang disetujui.
3. Simpan nilai yang sama pada `/etc/alertmanager/secrets/nadi-central-webhook-token` di host Alertmanager.
4. Batasi izin direktori dan file secret agar hanya akun layanan Alertmanager dan administrator tepercaya yang dapat membacanya. Pastikan akun layanan dapat membaca file tersebut.
5. Jangan menempatkan nilai secret sebagai argumen perintah, dalam riwayat shell, source control, tiket, tangkapan layar, atau log.

Setelah environment Central diubah, perbarui cache konfigurasi Laravel menggunakan prosedur deployment yang disetujui. Jangan menjalankan perintah pembersihan cache secara luas sebagai pengganti prosedur rilis yang semestinya.

### Tahap 3 — Konfigurasi receiver Alertmanager

1. Gabungkan pengaturan receiver dan route dari `docs/operations/alertmanager/alertmanager.yml.example` ke konfigurasi Alertmanager yang sudah ada.
2. Pertahankan route, receiver, inhibition rule, dan pengaturan global lain yang telah disetujui dan tidak terkait perubahan ini. Selesaikan konflik route secara sengaja; jangan mengganti seluruh file secara langsung.
3. Pastikan listener Alertmanager tetap terikat pada `127.0.0.1:9093`.
4. Validasi konfigurasi hasil penggabungan secara lengkap:

```bash
amtool check-config /etc/alertmanager/alertmanager.yml
```

5. Jika validasi gagal, hentikan proses dan pulihkan atau perbaiki konfigurasi sebelum melakukan reload.
6. Lakukan reload atau restart Alertmanager menggunakan prosedur perubahan yang disetujui.
7. Pastikan layanan berstatus aktif dan periksa log layanan untuk menemukan kesalahan konfigurasi atau pengiriman. Jangan mencetak atau mencatat bearer token.

### Tahap 4 — Memasang aturan Prometheus dan menghubungkannya ke Alertmanager

1. Pasang `docs/operations/prometheus/nadi-alert-rules.yml` ke `/etc/prometheus/rules/nadi-alert-rules.yml`.
2. Pastikan akun layanan Prometheus dapat membaca file aturan beserta direktori induknya.
3. Gabungkan bagian berikut ke konfigurasi `/etc/prometheus/prometheus.yml` yang sudah ada. Pertahankan bagian `global` dan `scrape_configs` beserta seluruh pengaturan lain yang tidak terkait:

```yaml
rule_files:
    - /etc/prometheus/rules/nadi-alert-rules.yml

alerting:
    alertmanagers:
        - static_configs:
              - targets:
                    - 127.0.0.1:9093
```

4. Validasi file aturan:

```bash
promtool check rules /etc/prometheus/rules/nadi-alert-rules.yml
```

5. Validasi seluruh konfigurasi Prometheus:

```bash
promtool check config /etc/prometheus/prometheus.yml
```

6. Jika salah satu validasi gagal, hentikan proses. Jangan melakukan reload Prometheus dengan konfigurasi yang tidak valid.
7. Lakukan reload atau restart Prometheus menggunakan prosedur perubahan yang disetujui.

### Tahap 5 — Verifikasi pengiriman dari ujung ke ujung

1. Pastikan target Prometheus tetap sehat.
2. Pastikan kelompok aturan telah dimuat dan dievaluasi tanpa kesalahan.
3. Pastikan Prometheus melaporkan Alertmanager lokal sebagai tujuan yang telah dikonfigurasi dan dapat dijangkau.
4. Picu satu alert pengujian yang terkendali dan tidak mengganggu, menggunakan metode pengujian yang disetujui. Jangan sengaja menimbulkan gangguan CPU, memori, disk, jaringan, atau aplikasi di produksi.
5. Pastikan Alertmanager menerima notifikasi berstatus firing dan mengirimkannya ke webhook Central.
6. Pastikan alert muncul di menu Alert Management NADI Central dan satu event terkait tersimpan.
7. Pastikan notifikasi resolved memperbarui status alert dan membuat event histori perubahan status yang diharapkan.
8. Pastikan pengiriman duplikat tidak membuat baris histori duplikat.
9. Periksa log untuk menemukan kegagalan pengiriman tanpa mengekspos credential atau payload sensitif.

Jangan menyatakan aktivasi selesai sebelum pengiriman firing dan resolved sama-sama berhasil diamati. Jika pengujian yang aman tidak dapat dilakukan, catat integrasi sebagai sudah dikonfigurasi tetapi belum terverifikasi dari ujung ke ujung.

## Rollback

Lakukan rollback hanya pada komponen yang diubah selama aktivasi ini.

1. Jika pengiriman gagal atau endpoint Central tidak tersedia, pulihkan konfigurasi Alertmanager sebelumnya dari backup yang telah dicatat, lalu validasi dengan `amtool check-config`.
2. Lakukan reload/restart Alertmanager menggunakan prosedur yang disetujui. Langkah ini menghentikan pengiriman webhook tanpa mengekspos Alertmanager ke jaringan publik.
3. Jika evaluasi aturan Prometheus atau konektivitas Alertmanager tidak sehat, pulihkan konfigurasi Prometheus sebelumnya dan validasi dengan `promtool check config`.
4. Hapus file aturan yang baru dipasang hanya jika file tersebut memang dibuat oleh perubahan ini dan konfigurasi Prometheus yang telah dipulihkan tidak lagi merujuk kepadanya.
5. Lakukan reload/restart Prometheus menggunakan prosedur yang disetujui.
6. Cabut atau rotasi secret webhook khusus jika ada kemungkinan secret tersebut terekspos. Perbarui Central dan Alertmanager secara bersamaan melalui prosedur pengelolaan secret yang disetujui.
7. Pertahankan record alert dan histori event di Central untuk keperluan investigasi. Jangan menghapus tabel alert sebagai bagian dari rollback rutin.
8. Pastikan proses scraping Prometheus dan jalur monitoring sebelum perubahan kembali sehat.

Jangan melakukan rollback terhadap route, receiver, scrape job, pengaturan Agent, atau konfigurasi aplikasi lain yang tidak terkait.

## Catatan penyelesaian perubahan

Catat informasi berikut pada tiket perubahan atau log operasional yang disetujui:

- Jendela perubahan dan operator yang melaksanakan.
- Lokasi serta waktu backup (jangan lampirkan secret).
- Identitas rilis Central dan hasil migrasi.
- Hasil validasi Prometheus dan Alertmanager.
- Hasil reload/restart layanan.
- Hasil pengiriman firing dan referensi alert di Central.
- Hasil pengiriman resolved dan referensi histori.
- Perilaku saat menerima pengiriman duplikat.
- Peringatan, penyimpangan prosedur, atau tindakan rollback yang dilakukan.

## Batasan operasional

- Prometheus mengevaluasi metrics dan meneruskan notifikasi alert.
- Alertmanager mengelompokkan dan meneruskan notifikasi ke webhook Central.
- NADI Central mengautentikasi webhook serta menyimpan metadata dan histori alert.
- SMTP, Telegram, dan tujuan notifikasi eksternal lainnya berada di luar cakupan aktivasi ini.
- Tidak ada remediasi otomatis yang dilakukan.
- Tidak ada kontrak, database, atau perilaku runtime Agent yang diubah oleh prosedur ini.
- Konfigurasi produksi hanya boleh diubah oleh operator yang mengikuti proses perubahan yang disetujui.
