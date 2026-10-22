<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Roles
        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name'        => 'Super Admin',
                'description' => 'Memiliki akses penuh ke seluruh sistem.',
                'color'       => '#ef4444',
                'is_system'   => true,
            ]
        );

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name'        => 'Admin',
                'description' => 'Administrator sistem dengan akses terbatas.',
                'color'       => '#3b82f6',
                'is_system'   => false,
            ]
        );

        // 2. Seed permissions
        //    Super Admin → semua true
        $this->seedPermissions($superAdminRole, true, []);

        //    Admin → semua true, KECUALI user_mgmt & role_mgmt (false)
        $this->seedPermissions($adminRole, true, ['user_mgmt', 'role_mgmt']);

        // 3. Create Users
        User::firstOrCreate(
            ['email' => 'superadmin@superadmin.com'],
            [
                'name'         => 'Super Admin',
                'password'     => Hash::make('superadmin123'),
                'role_id'      => $superAdminRole->id,
                'status'       => 'aktif',
                'avatar_color' => '#ef4444',
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name'         => 'Administrator',
                'password'     => Hash::make('admin123'),
                'role_id'      => $adminRole->id,
                'status'       => 'aktif',
                'avatar_color' => '#3b82f6',
            ]
        );

        // 4. Seed Transactions
        $this->seedTransactions();

        // 5. Seed Survei demo
        $this->seedSurveis();
    }

    /**
     * Seed or update permissions for a role.
     *
     * @param Role     $role       Role to seed
     * @param bool     $grantAll   Default grant for all modules
     * @param string[] $deny       Module keys that should be fully denied
     */
    private function seedPermissions(Role $role, bool $grantAll, array $deny = []): void
    {
        $modules = config('permissions.modules', []);

        foreach ($modules as $module) {
            $key    = $module['key'];
            $isDeny = in_array($key, $deny);

            Permission::updateOrCreate(
                [
                    'role_id' => $role->id,
                    'module'  => $key,
                ],
                [
                    'module_label' => $module['label'],
                    'section'      => $module['section'],
                    'can_view'     => $isDeny ? false : $grantAll,
                    'can_create'   => $isDeny ? false : $grantAll,
                    'can_edit'     => $isDeny ? false : $grantAll,
                    'can_delete'   => $isDeny ? false : $grantAll,
                ]
            );
        }
    }

    private function seedTransactions(): void
    {
        $transactions = [
            ['type' => 'masuk',  'name' => 'DP 30% CCTV Warehouse - PT Logistics Indo',    'date' => '2024-06-14', 'category' => 'Pembayaran Client', 'proyek_id' => 'PWS-2024-031', 'amount' => 25500000],
            ['type' => 'keluar', 'name' => 'Kabel coaxial + connector, NVR 32ch - Toko Elektro Jaya', 'date' => '2024-06-13', 'category' => 'Material',           'proyek_id' => 'PWS-2024-031', 'amount' => 18750000],
            ['type' => 'masuk',  'name' => 'Pelunasan Access Control - CV Maju Bersama',   'date' => '2024-06-12', 'category' => 'Pembayaran Client', 'proyek_id' => 'PWS-2024-032', 'amount' => 31500000],
            ['type' => 'keluar', 'name' => 'Fee tim proyek PWS-2024-032 (Ahmad + Budi)',   'date' => '2024-06-12', 'category' => 'Fee Tim',           'proyek_id' => 'PWS-2024-032', 'amount' => 5000000],
            ['type' => 'masuk',  'name' => 'Termin 1 Sistem Alarm - PT Sumber Makmur',    'date' => '2024-06-10', 'category' => 'Pembayaran Client', 'proyek_id' => 'PWS-2024-033', 'amount' => 16000000],
            ['type' => 'keluar', 'name' => 'Pembelian kamera IP Hikvision 16 unit',        'date' => '2024-06-09', 'category' => 'Material',           'proyek_id' => 'PWS-2024-033', 'amount' => 12800000],
            ['type' => 'masuk',  'name' => 'Invoice termin 2 Perumahan Graha Indah',       'date' => '2024-06-07', 'category' => 'Pembayaran Client', 'proyek_id' => 'PWS-2024-034', 'amount' => 24000000],
            ['type' => 'keluar', 'name' => 'Sewa kendaraan pengiriman alat ke Bekasi',     'date' => '2024-06-06', 'category' => 'Operasional',       'proyek_id' => 'PWS-2024-033', 'amount' => 850000],
            ['type' => 'keluar', 'name' => 'Biaya bensin tim lapangan Juni',               'date' => '2024-06-05', 'category' => 'Operasional',       'proyek_id' => 'PWS-2024-031', 'amount' => 1200000],
            ['type' => 'masuk',  'name' => 'Pelunasan Fiber Pabrik Sidoarjo',              'date' => '2024-06-03', 'category' => 'Pembayaran Client', 'proyek_id' => 'PWS-2024-035', 'amount' => 37500000],
            ['type' => 'keluar', 'name' => 'Penarikan kabel FO 200m + patch cord',         'date' => '2024-06-02', 'category' => 'Material',           'proyek_id' => 'PWS-2024-035', 'amount' => 9200000],
            ['type' => 'masuk',  'name' => 'DP Proyek CCTV Perumahan Graha Indah',        'date' => '2024-06-01', 'category' => 'Pembayaran Client', 'proyek_id' => 'PWS-2024-034', 'amount' => 36000000],
            ['type' => 'keluar', 'name' => 'Langganan internet kantor + telepon',           'date' => '2024-06-01', 'category' => 'Operasional',       'proyek_id' => 'PWS-2024-031', 'amount' => 1100000],
        ];

        // shift dates to current month to make stats look good on dashboard
        $now = \Carbon\Carbon::now();
        foreach ($transactions as $idx => $tx) {
            $date = \Carbon\Carbon::parse($tx['date']);
            $date->year($now->year)->month($now->month);
            
            // If date is in the future relative to now, shift it back a bit
            if ($date->gt($now)) {
                $date = $now->copy()->subDays(rand(1, 15));
            }
            
            $tx['date'] = $date->format('Y-m-d');
            \App\Models\Transaction::create($tx);
        }

        // Add some random transactions for previous 5 months
        $categoriesMasuk = ['Pembayaran Client', 'Lain-lain'];
        $categoriesKeluar = ['Material', 'Fee Tim', 'Operasional', 'Lain-lain'];
        
        for ($i = 1; $i <= 5; $i++) {
            $pastDate = $now->copy()->subMonths($i);
            
            // 3-5 masuk tx
            for ($j = 0; $j < rand(3, 5); $j++) {
                \App\Models\Transaction::create([
                    'type' => 'masuk',
                    'name' => 'Pembayaran Termin ' . rand(1, 3) . ' Proyek ' . rand(100, 999),
                    'date' => $pastDate->copy()->addDays(rand(1, 28))->format('Y-m-d'),
                    'category' => $categoriesMasuk[array_rand($categoriesMasuk)],
                    'amount' => rand(10, 50) * 1000000,
                ]);
            }

            // 5-8 keluar tx
            for ($k = 0; $k < rand(5, 8); $k++) {
                \App\Models\Transaction::create([
                    'type' => 'keluar',
                    'name' => 'Pengeluaran rutin ' . rand(100, 999),
                    'date' => $pastDate->copy()->addDays(rand(1, 28))->format('Y-m-d'),
                    'category' => $categoriesKeluar[array_rand($categoriesKeluar)],
                    'amount' => rand(1, 15) * 1000000,
                ]);
            }
        }
    }

    private function seedSurveis(): void
    {
        $superAdmin = \App\Models\User::where('email', 'superadmin@superadmin.com')->first();

        // ── Survei 1: Selesai, dengan form lengkap ────────────────────────────
        $s1 = \App\Models\Survei::firstOrCreate(
            ['client' => 'Budi Santoso', 'tanggal' => '2026-06-05'],
            [
                'perusahaan' => 'PT Maju Jaya',
                'lokasi'     => 'Gudang Surabaya Barat',
                'waktu'      => '09:00',
                'surveyor'   => 'Rizal Firmansyah',
                'kebutuhan'  => 'Instalasi CCTV 32 titik gudang',
                'estimasi'   => 85000000,
                'status'     => 'Selesai',
                'catatan'    => 'Lokasi cukup luas, akses kabel perlu koordinasi dengan pengelola gedung.',
                'rekomendasi'=> 'Gunakan kamera varifocal untuk area loading dock. Tambahkan 2 kamera di pintu darurat.',
                'created_by' => $superAdmin?->id,
            ]
        );

        if ($s1->wasRecentlyCreated) {
            $cat1 = $s1->categories()->create(['name' => 'Lokasi & Bangunan', 'urutan' => 0]);
            $cat1->questions()->createMany([
                ['survei_id' => $s1->id, 'label' => 'Tipe bangunan', 'answer' => 'Gudang industri 2 lantai', 'urutan' => 0],
                ['survei_id' => $s1->id, 'label' => 'Luas area yang dicover (m²)', 'answer' => '±2.400 m² per lantai', 'urutan' => 1],
                ['survei_id' => $s1->id, 'label' => 'Jumlah pintu masuk/keluar', 'answer' => '4 pintu utama + 2 pintu darurat', 'urutan' => 2],
                ['survei_id' => $s1->id, 'label' => 'Kondisi pencahayaan eksisting', 'answer' => 'Cukup di siang hari, minim di malam hari', 'notes' => 'Perlu kamera starlight/IR', 'urutan' => 3],
            ]);

            $cat2 = $s1->categories()->create(['name' => 'Kelistrikan', 'urutan' => 1]);
            $cat2->questions()->createMany([
                ['survei_id' => $s1->id, 'label' => 'Daya listrik tersedia (kVA / Ampere)', 'answer' => '33kVA, 3 phase', 'urutan' => 0],
                ['survei_id' => $s1->id, 'label' => 'Posisi panel listrik utama (MCB/SDP)', 'answer' => 'Di ruang panel samping pintu masuk utama', 'urutan' => 1],
                ['survei_id' => $s1->id, 'label' => 'Apakah tersedia grounding?', 'answer' => 'Ya, grounding existing cukup baik', 'urutan' => 2],
                ['survei_id' => $s1->id, 'label' => 'Sumber UPS / genset tersedia?', 'answer' => 'Genset 15kVA, perlu tambah UPS untuk NVR', 'urutan' => 3],
            ]);

            $cat3 = $s1->categories()->create(['name' => 'Kebutuhan Sistem', 'urutan' => 2]);
            $cat3->questions()->createMany([
                ['survei_id' => $s1->id, 'label' => 'Jumlah titik / zona yang dibutuhkan', 'answer' => '32 titik kamera — 24 indoor + 8 outdoor', 'urutan' => 0],
                ['survei_id' => $s1->id, 'label' => 'Spesifikasi / merk yang diminta client', 'answer' => 'Hikvision / Dahua resolusi 4MP', 'urutan' => 1],
                ['survei_id' => $s1->id, 'label' => 'Kebutuhan NVR / DVR', 'answer' => 'NVR 32 channel, HDD 4TB', 'urutan' => 2],
                ['survei_id' => $s1->id, 'label' => 'Kebutuhan monitor/layar', 'answer' => 'Monitor 32" di pos satpam + 1 di kantor', 'urutan' => 3],
            ]);

            $cat4 = $s1->categories()->create(['name' => 'Infrastruktur Jaringan', 'urutan' => 3]);
            $cat4->questions()->createMany([
                ['survei_id' => $s1->id, 'label' => 'Jenis kabel yang akan digunakan', 'answer' => 'UTP Cat6 untuk IP Camera + kabel coaxial untuk area outdoor', 'urutan' => 0],
                ['survei_id' => $s1->id, 'label' => 'Panjang estimasi total kabel', 'answer' => '±900 meter', 'urutan' => 1],
                ['survei_id' => $s1->id, 'label' => 'Akses jalur kabel (plafon/conduit)', 'answer' => 'Sebagian melalui plafon, sebagian perlu conduit baru', 'notes' => 'Koordinasi dengan pengelola gedung', 'urutan' => 2],
            ]);
        }

        // ── Survei 2: Dijadwalkan, form kosong ────────────────────────────────
        \App\Models\Survei::firstOrCreate(
            ['client' => 'Dewi Lestari', 'tanggal' => now()->addDays(3)->format('Y-m-d')],
            [
                'perusahaan' => 'Hotel Grand Plaza',
                'lokasi'     => 'Hotel Grand Plaza Gresik, Lt. 5',
                'waktu'      => '13:00',
                'surveyor'   => 'Ahmad Yusuf',
                'kebutuhan'  => 'PA System & Intercom 50 zona',
                'estimasi'   => 0,
                'status'     => 'Dijadwalkan',
                'created_by' => $superAdmin?->id,
            ]
        );

        // ── Survei 3: Dijadwalkan besok ───────────────────────────────────────
        \App\Models\Survei::firstOrCreate(
            ['client' => 'PT Sumber Makmur', 'tanggal' => now()->addDay()->format('Y-m-d')],
            [
                'perusahaan' => 'PT Sumber Makmur',
                'lokasi'     => 'Pabrik Sidoarjo, Kawasan Industri PIER',
                'waktu'      => '08:30',
                'surveyor'   => 'Rizal Firmansyah',
                'kebutuhan'  => 'Fire Alarm & Access Control 8 pintu',
                'estimasi'   => 0,
                'status'     => 'Dijadwalkan',
                'created_by' => $superAdmin?->id,
            ]
        );
    }
}
