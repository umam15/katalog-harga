# API Katalog Harga

Akses baca-saja (read-only) ke katalog, harga, dan stok lewat HTTP JSON.
Dirancang supaya gampang dipakai program biasa maupun agent/LLM.

Untuk penjelasan singkat cara mengaktifkan, lihat `README.md`. Dokumen ini
isinya detail teknis tiap endpoint.

## Skema OpenAPI

Semua endpoint di bawah juga terdeskripsikan lengkap (parameter, response,
error) dalam format OpenAPI 3.0 yang bisa dibaca otomatis oleh
tool/agent/LLM:

```
GET /api/v1/openapi.json
```

## Autentikasi

Semua endpoint (kecuali `/api/v1/health`) wajib header:

```
Authorization: Bearer <API key>
```

API key dibuat di **Panel Admin → Pengaturan API**. Key hanya ditampilkan
sekali saat dibuat — simpan baik-baik, tidak bisa dilihat ulang.

Kalau API dimatikan lewat toggle "Aktifkan API", **semua** endpoint
`/api/v1/*` (termasuk `/openapi.json`) balas `404 Not Found`, seolah tidak
pernah ada — supaya tidak membocorkan ke pihak luar bahwa fitur ini ada
tapi sedang nonaktif.

## Format error

Semua error dibalas dengan bentuk yang sama:

```json
{ "error": { "code": "unauthorized", "message": "API key tidak valid." } }
```

| HTTP status | code | Arti |
|---|---|---|
| 400 | `invalid_request` / `invalid_kantor` / `kantor_required` | Parameter tidak valid/kurang. |
| 401 | `unauthorized` | Header `Authorization` tidak ada atau key-nya salah/sudah dihapus. |
| 403 | `forbidden` | Key valid, tapi tidak punya akses ke kantor yang diminta. |
| 404 | `not_found` / `item_not_found` | Endpoint tidak ada (API dimatikan) / item tidak ketemu. |
| 405 | `method_not_allowed` | Method selain GET. |
| 429 | `rate_limited` | Terlalu banyak request dari key ini - coba lagi setelah waktu di header `Retry-After` (detik). |
| 503 | `database_unavailable` | Database katalog sedang tidak bisa disambung. |

## Scope kantor

Tiap API key dibatasi ke kantor/gudang tertentu saat dibuat (kosong =
semua kantor). Parameter `kantor` di endpoint yang butuh selalu divalidasi
terhadap scope ini — minta kantor di luar scope key akan dibalas `403`.

---

## `GET /api/v1/items`

Cari item katalog beserta harga & stok di satu kantor.

| Parameter | Wajib? | Default | Keterangan |
|---|---|---|---|
| `q` | tidak | — | Kata kunci: nama, merek, jenis, kode item, atau barcode. |
| `kantor` | wajib kecuali key cuma punya scope 1 kantor | — | Kode kantor/gudang. |
| `limit` | tidak | 20 | Maksimal 100. |
| `offset` | tidak | 0 | Untuk paging. |

**Contoh:**

```bash
curl -H "Authorization: Bearer kh_live_xxxx" \
  "https://contoh.domain/api/v1/items?q=indomie&kantor=UTM&limit=10"
```

```json
{
  "kantor": "UTM",
  "items": [
    { "kodeitem": "IT001", "nama": "Indomie Goreng", "merek": "Indomie",
      "jenis": "Mie Instan", "satuan_dasar": "DUS", "stok": 42, "harga": 120000 }
  ],
  "pagination": { "limit": 10, "offset": 0, "total": 1 }
}
```

---

## `GET /api/v1/items/{kodeitem}`

Detail satu item: info dasar, daftar harga per satuan (mengikuti sistem
harga jual item - tetap/bertingkat/grosir), barcode, dan stok di satu
kantor.

| Parameter | Wajib? | Keterangan |
|---|---|---|
| `kodeitem` | wajib (bagian path) | Kode item. |
| `kantor` | wajib kecuali key cuma punya scope 1 kantor | Kode kantor/gudang. |

**Contoh:**

```bash
curl -H "Authorization: Bearer kh_live_xxxx" \
  "https://contoh.domain/api/v1/items/IT001?kantor=UTM"
```

```json
{
  "kodeitem": "IT001",
  "nama": "Indomie Goreng",
  "merek": "Indomie",
  "jenis": "Mie Instan",
  "keterangan": "",
  "satuan_dasar": "PCS",
  "stok": 504,
  "kantor": "UTM",
  "harga_list": [
    { "satuan": "PCS", "barcode": "8991002101010", "harga": 3000, "level": null, "jmlsampai": null, "info": "" },
    { "satuan": "DUS", "barcode": "8991002101027", "harga": 120000, "level": null, "jmlsampai": null, "info": "" }
  ]
}
```

`level` terisi kalau sistem harga item ini bertingkat per level (harga
beda tiap level pelanggan), `jmlsampai` terisi kalau sistem harganya
grosir bertingkat jumlah (makin banyak beli, makin murah). Keduanya
`null` kalau item pakai harga tetap.

---

## `GET /api/v1/kantor`

Daftar kantor/gudang yang boleh diakses API key ini (mengikuti scope key).
Tidak ada parameter.

```bash
curl -H "Authorization: Bearer kh_live_xxxx" "https://contoh.domain/api/v1/kantor"
```

```json
{ "kantor": ["CABANG1", "GUDANG", "UTM"] }
```

---

## `GET /api/v1/health`

Cek status server & koneksi database - untuk monitoring/uptime checker.
**Tidak butuh API key**, dan tetap jalan meski toggle "Aktifkan API"
dimatikan (ini soal status infrastruktur, bukan bagian fitur katalog-nya).
Tidak pernah membocorkan data katalog atau detail error.

```bash
curl "https://contoh.domain/api/v1/health"
```

```json
{ "status": "ok", "database": "ok", "time": "2026-09-17T10:00:00+00:00" }
```

Balas HTTP `200` kalau sehat, `503` (`"status": "degraded"`) kalau
database tidak bisa disambung.

---

## Batasan saat ini

- **Rate limit**: 30 request burst per key, terisi ulang 0.5 request/detik
  (30 request/menit sustained). Kena limit dibalas `429` dengan header
  `Retry-After` (detik). Belum bisa diatur lewat Panel Admin - kalau
  butuh angka berbeda, ubah `API_RATE_LIMIT_CAPACITY`/
  `API_RATE_LIMIT_REFILL_PER_SEC` di `includes/functions.php`.
- **Belum ada log akses** — request ke API belum tercatat di log
  terpisah untuk audit (lihat `TODO.md`).
- Semua endpoint **read-only** — tidak ada cara menulis/mengubah data
  katalog lewat API ini.
