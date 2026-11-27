# Activation Grant Ed25519 Key Management v1.0

## 1. Tujuan

Dokumen ini menetapkan format key pair yang digunakan NADI Central untuk menandatangani Activation Grant dan Agent untuk memverifikasinya.

Implementasi Central dan Agent harus menggunakan algoritma Ed25519 yang sama. Central menyimpan private key; Agent hanya menerima public key.

## 2. Format key yang diwajibkan

| Item | Format | Lokasi default Central |
|---|---|---|
| Private seed | Raw 32-byte Ed25519 seed; Central juga dapat membaca raw 64-byte sodium secret key | `/etc/nadi-central/keys/activation-ed25519-seed.key` |
| Public key | Raw 32-byte Ed25519 public key | `/etc/nadi-central/keys/activation-ed25519-public.key` |
| Key ID | String konfigurasi | `NADI_ACTIVATION_KEY_ID` |

Agent saat ini menerima raw 32-byte public key atau PEM PKIX Ed25519 public key. Command Central menghasilkan raw 32-byte public key yang dapat digunakan langsung sebagai file public key Agent.

Jangan mengirim private seed ke Agent. Jangan menyimpan private seed di MySQL, source control, image container, atau backup yang tidak terenkripsi.

## 3. Membuat key pair

Jalankan hanya pada host atau lingkungan non-produksi yang dituju dan melalui prosedur pengelolaan secret yang disetujui:

```bash
php artisan nadi:activation-keys:generate
```

Command:

- Membuat Ed25519 key pair dengan PHP sodium.
- Menulis private seed 32 byte ke path konfigurasi Central.
- Menulis public key 32 byte ke path konfigurasi Central.
- Mengatur private file ke mode `0600` dan public file ke mode `0644`.
- Menolak menimpa file key yang sudah ada.
- Tidak menampilkan isi key ke terminal.

Pastikan direktori key hanya dapat diakses oleh administrator dan service account yang memerlukan akses. PHP-FPM/Central harus dapat membaca private seed. Agent harus dapat membaca salinan public key pada host Agent.

## 4. Konfigurasi Central

Atur environment Central:

- `NADI_ACTIVATION_KEY_ID`
- `NADI_ACTIVATION_PRIVATE_KEY`
- `NADI_ACTIVATION_PUBLIC_KEY`
- `NADI_ACTIVATION_GRANT_LIFETIME`

Setelah konfigurasi diperbarui, gunakan prosedur deployment Central yang disetujui untuk membangun ulang konfigurasi Laravel. Jangan menaruh nilai private seed ke environment variable; environment hanya menyimpan path file.

Central memeriksa bahwa public key yang dikonfigurasi cocok dengan private seed sebelum menandatangani grant. Mismatch akan menggagalkan penerbitan grant.

## 5. Distribusi public key ke Agent

1. Ambil file public key raw 32 byte dari secret/configuration management Central melalui kanal distribusi yang disetujui.
2. Verifikasi identitas key melalui fingerprint yang disampaikan terpisah oleh administrator yang berwenang.
3. Pasang public key pada path Agent yang dikonfigurasi oleh `NADI_ACTIVATION_PUBLIC_KEY`; default Agent saat ini adalah `/etc/nadi-agent/keys/activation-public.pem`.
4. Atur permission agar hanya Agent dan administrator tepercaya yang dapat membaca file.
5. Pastikan `NADI_ACTIVATION_KEY_ID` Agent sama persis dengan `NADI_ACTIVATION_KEY_ID` Central.
6. Uji penerbitan, verifikasi, dan acknowledgement grant di non-produksi sebelum mengaktifkan monitoring.

Public key bukan secret, tetapi integritasnya wajib dijaga. Public key yang salah akan membuat Agent menolak Activation Grant.

## 6. Batasan rotasi saat ini

Verifier Agent pada source yang diaudit hanya menerima satu public key dan satu `key_id` aktif. Belum ada dukungan multi-key overlap/grace period di sisi Agent.

Karena itu, rotasi tidak boleh dilakukan dengan hanya mengganti file Central. Rotasi memerlukan perubahan terkoordinasi:

1. Siapkan key pair baru tanpa menimpa key lama.
2. Rencanakan jendela perubahan untuk memperbarui public key dan key ID pada seluruh Agent.
3. Perbarui konfigurasi Agent dan Central dalam urutan yang telah diuji di non-produksi.
4. Terbitkan ulang Activation Grant setelah seluruh Agent terkait menggunakan key baru.
5. Verifikasi acknowledgement sukses dan monitoring aktif pada setiap scope.
6. Cabut/revoke grant lama setelah proses transisi diverifikasi.

Sampai Agent mendukung beberapa key ID sekaligus, tidak boleh mengklaim rotasi tanpa downtime atau overlap aman.

## 7. Migrasi dari key RSA lama

Implementasi Central sebelumnya menggunakan OpenSSL RSA untuk Activation Grant, sedangkan Agent memverifikasi Ed25519. Kedua format tersebut tidak kompatibel.

Private/public key RSA lama tidak dapat digunakan oleh implementasi Ed25519. Jangan mengubah ekstensi file atau menyalin file RSA lama ke path Ed25519.

Untuk migrasi:

- Buat Ed25519 key pair baru.
- Distribusikan public key Ed25519 ke Agent melalui prosedur yang disetujui.
- Pastikan key ID konsisten.
- Terbitkan grant Ed25519 baru.
- Verifikasi Agent mengirim acknowledgement sukses.
- Pertahankan audit history grant lama; jangan menghapus data grant atau approval log.

Tidak ada perubahan key produksi yang dilakukan oleh dokumen ini.

## 8. Verifikasi non-produksi

Sebelum rollout, buktikan seluruh hal berikut:

- PHP sodium tersedia.
- Central dapat membaca private seed dan public key.
- Central menolak pasangan key yang tidak cocok.
- Signature yang dihasilkan berukuran 64 byte setelah base64 decode.
- Agent berhasil memverifikasi signature Ed25519 atas canonical JSON payload yang sama.
- Agent mengirim activation acknowledgement sukses.
- Central menyimpan acknowledgement dan mengubah activation status menjadi active.
- Agent hanya memulai collector setelah grant valid dan acknowledgement state sesuai.

## 9. Batas keselamatan

- Jangan menjalankan key generation pada produksi tanpa change ticket dan persetujuan eksplisit.
- Jangan menimpa atau menghapus private key yang sudah digunakan.
- Jangan mengirim private seed melalui chat, email, tiket, atau source control.
- Jangan mengaktifkan monitoring sebelum Agent berhasil memverifikasi grant Ed25519.
- Jangan menyatakan integrasi produksi siap hanya berdasarkan test PHP; verifikasi lintas bahasa Central–Agent wajib dilakukan.
