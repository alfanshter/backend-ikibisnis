<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionPeriodFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Transaksi tersebar di beberapa bulan & tahun
        Transaction::create(['type' => 'masuk', 'name' => 'Jan 2026', 'date' => '2026-01-15', 'category' => 'Pembayaran Client', 'amount' => 1000]);
        Transaction::create(['type' => 'masuk', 'name' => 'Mar 2026', 'date' => '2026-03-15', 'category' => 'Pembayaran Client', 'amount' => 2000]);
        Transaction::create(['type' => 'keluar', 'name' => 'Jul 2026', 'date' => '2026-07-10', 'category' => 'Material', 'amount' => 500]);
        Transaction::create(['type' => 'masuk', 'name' => 'Okt 2026', 'date' => '2026-10-05', 'category' => 'Pembayaran Client', 'amount' => 3000]);
        Transaction::create(['type' => 'masuk', 'name' => 'Feb 2025', 'date' => '2025-02-20', 'category' => 'Pembayaran Client', 'amount' => 4000]);
    }

    public function test_stats_bulanan_hanya_menghitung_bulan_terpilih(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/transactions/stats?period=month&month=3&year=2026');

        $response->assertStatus(200)
            ->assertJsonPath('current_period.period', 'month')
            ->assertJsonPath('current_period.label', 'Maret 2026')
            ->assertJsonPath('current_period.start_date', '2026-03-01')
            ->assertJsonPath('current_period.end_date', '2026-03-31')
            ->assertJsonPath('current_period.total_masuk', 2000)
            ->assertJsonPath('current_period.total_keluar', 0);
    }

    public function test_stats_semester_1_mencakup_januari_sampai_juni(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/transactions/stats?period=semester&month=2&year=2026');

        $response->assertStatus(200)
            ->assertJsonPath('current_period.period', 'semester')
            ->assertJsonPath('current_period.label', 'Semester 1 2026')
            ->assertJsonPath('current_period.start_date', '2026-01-01')
            ->assertJsonPath('current_period.end_date', '2026-06-30')
            ->assertJsonPath('current_period.total_masuk', 3000);
    }

    public function test_stats_semester_2_mencakup_juli_sampai_desember(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/transactions/stats?period=semester&month=8&year=2026');

        $response->assertStatus(200)
            ->assertJsonPath('current_period.label', 'Semester 2 2026')
            ->assertJsonPath('current_period.start_date', '2026-07-01')
            ->assertJsonPath('current_period.end_date', '2026-12-31')
            ->assertJsonPath('current_period.total_masuk', 3000)
            ->assertJsonPath('current_period.total_keluar', 500);
    }

    public function test_stats_tahunan_mencakup_sepuluh_tahun_dan_12_bar_chart(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/transactions/stats?period=year&year=2026');

        $response->assertStatus(200)
            ->assertJsonPath('current_period.period', 'year')
            ->assertJsonPath('current_period.label', 'Tahun 2026')
            ->assertJsonPath('current_period.start_date', '2026-01-01')
            ->assertJsonPath('current_period.end_date', '2026-12-31')
            ->assertJsonPath('current_period.total_masuk', 6000)
            ->assertJsonPath('current_period.total_keluar', 500)
            ->assertJsonPath('current_period.saldo_bersih', 5500);

        $this->assertCount(12, $response->json('chart_data'));
    }

    public function test_daftar_transaksi_difilter_menurut_periode(): void
    {
        $yearList = $this->actingAs($this->user)->getJson('/api/transactions?period=year&year=2026');
        $yearList->assertStatus(200)->assertJsonPath('meta.total', 4);

        $semesterList = $this->actingAs($this->user)->getJson('/api/transactions?period=semester&month=8&year=2026');
        $semesterList->assertStatus(200)->assertJsonPath('meta.total', 2);

        $monthList = $this->actingAs($this->user)->getJson('/api/transactions?period=month&month=3&year=2026');
        $monthList->assertStatus(200)->assertJsonPath('meta.total', 1);
    }

    public function test_rentang_tanggal_kustom_menggantikan_preset(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/api/transactions/stats?period=month&start_date=2026-03-01&end_date=2026-07-31'
        );

        $response->assertStatus(200)
            ->assertJsonPath('current_period.period', 'custom')
            ->assertJsonPath('current_period.label', 'Rentang Tanggal')
            ->assertJsonPath('current_period.start_date', '2026-03-01')
            ->assertJsonPath('current_period.end_date', '2026-07-31')
            ->assertJsonPath('current_period.total_masuk', 2000)
            ->assertJsonPath('current_period.total_keluar', 500);

        // Bar chart diagregasi per bulan dalam rentang (Mar, Apr, Mei, Jun, Jul)
        $this->assertCount(5, $response->json('chart_data'));
    }

    public function test_rentang_tanggal_tertukar_otomatis_jika_terbalik(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/api/transactions/stats?start_date=2026-10-31&end_date=2026-01-01'
        );

        $response->assertStatus(200)
            ->assertJsonPath('current_period.start_date', '2026-01-01')
            ->assertJsonPath('current_period.end_date', '2026-10-31');
    }

    public function test_daftar_transaksi_difilter_oleh_rentang_tanggal(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/api/transactions?start_date=2026-01-01&end_date=2026-03-31'
        );

        $response->assertStatus(200)->assertJsonPath('meta.total', 2);
    }

    public function test_periode_tidak_dikenal_fallback_ke_bulanan(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/transactions/stats?period=abc&month=3&year=2026');

        $response->assertStatus(200)
            ->assertJsonPath('current_period.period', 'month')
            ->assertJsonPath('current_period.total_masuk', 2000);
    }
}
