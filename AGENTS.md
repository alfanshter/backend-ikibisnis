# AGENTS.md — Backend Ikibisnis

> Turunan dari `../AGENTS.md`. **Baca `../AGENTS.md` terlebih dahulu** — aturan di sana berlaku penuh.
> File ini berisi aturan **spesifik backend** yang lebih detail.

## Stack (WAJIB pakai yang sudah ada)
Laravel 13 · PHP 8.3 · Sanctum (Bearer token) · SQLite (`database/database.sqlite`) ·
`spatie/simple-excel` (import/export Excel) · PHPUnit 12 + `RefreshDatabase` · Laravel Pint.

---

## 1. Struktur & Penempatan File

| Lokasi | Isi | Penamaan |
|---|---|---|
| `app/Http/Controllers/Api/` | Controller endpoint API | `XxxController.php` (WAJIB) |
| `app/Http/Controllers/` | Controller non-API (jarang, mis. `PartController.php`) | `XxxController.php` |
| `app/Http/Requests/{Auth,Role,User}/` | FormRequest | `XxxRequest.php` |
| `app/Http/Resources/` | JSON Resource | `XxxResource.php` |
| `app/Models/` | Eloquent model | `Xxx.php` (tanpa suffix `Model`) |
| `app/Services/` | Logic bisnis lintas controller | `XxxService.php` |
| `app/Traits/` | Trait reusable | `Xxx.php` |
| `routes/api.php` | SEMUA route API | — |
| `database/migrations/` | Migration | `YYYY_MM_DD_HHMMSS_create_xxx_tables.php` |
| `database/seeders/` | Seeder | `XxxSeeder.php` |
| `database/factories/` | Factory model | `XxxFactory.php` |
| `tests/Feature/` | Test integrasi | `XxxTest.php` |
| `tests/Unit/` | Test unit murni | `XxxTest.php` |

- **Controller baru untuk API → `app/Http/Controllers/Api/`**. Jangan taruh controller API di root `Controllers/` (kecuali sudah ada precedent lama, mis. `PartController.php`).
- **Logika bisnis kompleks (perhitungan, generate nomor dokumen, sinkronisasi) → `app/Services/`**, bukan di controller.
- **Format response berulang → `app/Http/Resources/`**, bukan array manual panjang di controller.

---

## 2. Aturan Model

```php
class Proyek extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [ /* ... */ ];

    protected $casts = [
        'nilai'   => 'integer',
        'allowed_roles' => 'array',
        'sewa_mulai' => 'date:Y-m-d',
    ];

    const VALID_STATUSES = ['Belum Dimulai', 'Berjalan', 'QC', 'Serah Terima', 'Selesai', 'Hold'];

    // Relationships dengan return type eksplisit
    public function picUser(): BelongsTo { return $this->belongsTo(User::class, 'pic_id'); }
}
```

- Selalu pakai `HasFactory` dan (bila relevan) trait `Auditable`.
- **Wajib `$fillable`** — jangan `$guarded = []`.
- Cast wajib untuk: integer (nominal/nilai/progress), boolean, array/JSON, date.
- Konstanta domain (`VALID_STATUSES`, `VALID_DIVISI`, `VALID_TIPE_KONTRAK`, dst.) dipakai di model **dan** divalidasi ulang di controller/layer validasi.
- Relasi wajib punya return type (`BelongsTo`, `HasMany`, `HasOne`).
- Nama kolom DB & atribut Eloquent: **snake_case**.
- Hapus relasi yang tidak dipakai → hindari N+1; pakai `with()` eksplisit di query.

---

## 3. Aturan Controller

```php
namespace App\Http\Controllers\Api;

class ProyekController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Proyek::query()->with(['client', 'picUser'])->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $s = $request->string('search');
            $query->where('nama', 'ilike', '%' . $s . '%');
        }

        return response()->json(['data' => $query->get()]);
    }
}
```

- **Semua method return type eksplisit** (`JsonResponse` atau `array`).
- **Validasi selalu di backend** memakai `$request->validate([...])` (FormRequest bila validasinya besar/reusable).
- Jangan menulis query mentah di controller → panggil model/service.
- Format angka uang: kirim angka mentah **dan** string terformat (`nominal` + `nominal_format` dengan `number_format($v, 0, ',', '.')`).
- Format tanggal: `?->format('Y-m-d')` atau `?->format('Y-m-d H:i:s')`.
- Pesan error ke user dalam Bahasa Indonesia.
- Sub-resource ditangani dengan route prefix (lihat pola `Route::prefix('proyeks/{proyek}')` di `routes/api.php`).
- Jangan pernah membocorkan nilai sensitif (`nilai`, `nominal`) ke user tanpa izin — ikuti `tampilkan_nilai` / `akses_tipe` / `allowed_roles`.

---

## 4. Aturan Route (`routes/api.php`)

- Semua route API hanya di file ini (kecuali route web di `routes/web.php`).
- Route publik (tanpa auth) di luar group; sisanya di dalam `Route::middleware('auth:sanctum')->group()`.
- Resource utama pakai `Route::apiResource('proyeks', ProyekController::class);`
- Route tambahan dengan aksi non-CRUD memakai method + path deskriptif kebab-case:
  ```php
  Route::get('/proyeks/stats', [ProyekController::class, 'stats']);           // WAJIB sebelum {proyek}
  Route::patch('/proyeks/{proyek}/status', [ProyekController::class, 'updateStatus']);
  ```
- **Route statis harus diletakkan sebelum route dengan parameter** (`/leads/stats` sebelum `/leads/{lead}`) agar tidak tertangkap.
- Gunakan import `use App\Http\Controllers\Api\XxxController;` di bagian atas (hindari `\App\Http\Controllers\...` inline yang sudah ada di file lama — kalau menambahkan, ikuti gaya import yang dominant).

---

## 5. Validasi & Error Handling

- Pesan validasi Bahasa Indonesia, spesifik per field.
- Pola status yang sudah dipakai di repo:
  - 401 unauthenticated (Sanctum otomatis)
  - 403 forbidden → `['message' => 'Tidak ada akses.']`
  - 404 not found → `Model::findOrFail()` / `abort(404)`
  - 422 validation → default Laravel (`message` + `errors`), frontend sudah menanganinya
- Jangan try/catch diam-diam; biarkan exception ter-log oleh Laravel (`storage/logs/laravel.log`).

---

## 6. Aturan Migration

- Nama file: `YYYY_MM_DD_HHMMSS_create_nama_tabel.php` atau `..._add_kolom_to_tabel.php`.
- **Jangan mengubah migration yang sudah pernah dijalankan** → buat migration baru.
- `up()` dan `down()` **wajib** lengkap (reversible).
- Untuk menambah kolom ke tabel yang sudah ada → `Schema::table(...)` + nullable/default yang aman (SQLite butuh default untuk kolom NOT NULL baru).
- Index untuk kolom yang sering difilter/sortir (`$table->index([...])`).
- Setelah membuat migration: jalankan `php artisan migrate` dan catat hasilnya.
- Seeder untuk data awal wajib **idempotent** (`updateOrCreate`, bukan `insert`).

---

## 7. Service & Konfigurasi Dinamis

- Nomor dokumen (surat penawaran, invoice, pembayaran) → `App\Services\DocumentNumberingService` + tabel `document_numberings`. **Jangan** membuat nomor hard-coded di controller.
- Pengaturan dinamis (contoh: template PDF penawaran) → tabel `settings` + `SettingController`. Jangan hard-code di view/backend.
- Permission/modul daftar → `config/permissions.php` (sumber tunggal).

---

## 8. Testing (PHPUnit)

- Test integrasi API → `tests/Feature/`. Test unit murni → `tests/Unit/`.
- Gunakan `RefreshDatabase` untuk test yang menyentuh DB.
- Nama test deskriptif dengan `test_` prefix atau method `testXxx()` (pola repo: `test_admin_can_view_all_projects_and_leads`).
- Siapkan data factory di `setUp()`, bukan di dalam test method.
- Factory: `database/factories/UserFactory.php` — jangan bikin user manual dengan `User::create([... 'password' => ...])` tanpa hash.
- Assertion: `$this->assertStatus(200)`, `assertJson([...])`, `assertDatabaseHas('proyeks', [...])`.
- **Setiap fitur/fix baru wajib disertai test** di `tests/Feature/` (kecuali perubahan trivial UI/konfigurasi).

---

## 9. Perintah

```bash
composer install
php artisan serve            # server lokal (port 8000)
php artisan migrate
php artisan db:seed
php artisan test             # WAJIB lulus
vendor/bin/pint              # format PHP
vendor/bin/pint --test       # cek format (CI)
php artisan route:list       # cek route
```

**Checklist sebelum menyatakan selesai:**
- [ ] `php artisan test` lulus (test baru ada & relevan)
- [ ] `vendor/bin/pint --test` lulus
- [ ] Migration punya `up()` + `down()` dan sudah dijalankan
- [ ] Response JSON konsisten dengan kontrak di `../AGENTS.md` §5
- [ ] Permission/access control dicek di server, bukan hanya UI
- [ ] Tidak ada `dd()`, `dump()`, `TODO` tersisa
