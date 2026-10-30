<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\Invoice;
use App\Models\Payment;
use Spatie\Permission\Models\Role;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    protected $kepsek;
    protected $bendahara;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup Roles
        Role::firstOrCreate(['name' => 'Kepala Sekolah']);
        Role::firstOrCreate(['name' => 'Bendahara']);

        $this->kepsek = User::factory()->create();
        $this->kepsek->assignRole('Kepala Sekolah');

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole('Bendahara');
    }

    public function test_kepsek_can_access_metrics_and_charts()
    {
        $response = $this->actingAs($this->kepsek, 'sanctum')->getJson('/api/v1/dashboard/metrics-kepsek');
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['total_siswa', 'total_tagihan', 'total_pembayaran', 'total_tunggakan', 'collection_rate']]);
                 
        $chartResponse = $this->actingAs($this->kepsek, 'sanctum')->getJson('/api/v1/dashboard/charts');
        $chartResponse->assertStatus(200)
                      ->assertJsonStructure(['data' => ['monthly_income', 'payment_by_type', 'payment_status', 'arrears_by_class']]);
    }

    public function test_bendahara_can_access_metrics()
    {
        $response = $this->actingAs($this->bendahara, 'sanctum')->getJson('/api/v1/dashboard/metrics-bendahara');
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['pemasukan_hari_ini', 'transaksi_hari_ini', 'pemasukan_bulan_ini', 'total_tunggakan', 'siswa_menunggak', 'recent_transactions']]);
    }

    public function test_daily_report_filtering_and_export()
    {
        // Export should return stream CSV
        $response = $this->actingAs($this->bendahara, 'sanctum')->get('/api/v1/reports/harian?export=1');
        
        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_aggregated_reports()
    {
        $endpoints = ['per-kelas', 'per-jurusan', 'per-jenis'];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($this->kepsek, 'sanctum')->getJson("/api/v1/reports/{$endpoint}");
            $response->assertStatus(200)->assertJsonStructure(['data']);
        }
    }
}
