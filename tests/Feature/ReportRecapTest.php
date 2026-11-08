<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Major;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportRecapTest extends TestCase
{
    use RefreshDatabase;

    private User $bendahara;

    private AcademicYear $year;

    private ClassRoom $class;

    private Student $ani;

    private Student $budi;

    private PaymentType $spp;

    private PaymentType $daftarUlang;

    private PaymentMethod $cash;

    private PaymentMethod $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole(Role::firstOrCreate(['name' => 'Bendahara']));

        $this->year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-30', 'is_active' => true]);
        $major = Major::create(['code' => 'TKJ', 'name' => 'Teknik Komputer dan Jaringan']);
        $this->class = ClassRoom::create(['name' => 'X TKJ 1', 'grade' => 'X', 'major_id' => $major->id, 'academic_year_id' => $this->year->id]);
        $this->ani = Student::create(['name' => 'Ani', 'nis' => '1001', 'class_id' => $this->class->id, 'status' => 'AKTIF']);
        $this->budi = Student::create(['name' => 'Budi', 'nis' => '1002', 'class_id' => $this->class->id, 'status' => 'AKTIF']);

        $this->spp = PaymentType::create(['code' => 'SPP', 'name' => 'SPP', 'type' => 'BULANAN']);
        $this->daftarUlang = PaymentType::create(['code' => 'DU', 'name' => 'Daftar Ulang', 'type' => 'TAHUNAN']);
        $this->cash = PaymentMethod::create(['code' => 'CASH', 'name' => 'Tunai', 'is_active' => true]);
        $this->transfer = PaymentMethod::create(['code' => 'TRANSFER', 'name' => 'Transfer Bank', 'is_active' => true]);
    }

    public function test_class_recap_puts_each_bill_in_its_month_or_card_slot(): void
    {
        $aniJuly = $this->bill($this->ani, $this->spp, 'Juli', 150000);
        $this->bill($this->budi, $this->spp, 'Juli', 150000);
        $aniRegistration = $this->bill($this->ani, $this->daftarUlang, '2026/2027', 500000);
        $this->openCash();
        $this->pay($this->ani, $this->cash, [$aniJuly->id => 150000, $aniRegistration->id => 200000]);

        $response = $this->actingAs($this->bendahara)
            ->getJson("/api/v1/reports/rekap-kelas?academic_year_id={$this->year->id}&class_id={$this->class->id}")
            ->assertOk();

        $response->assertJsonCount(13, 'data.columns')
            ->assertJsonPath('data.columns.0.label', 'Juli')
            ->assertJsonPath('data.columns.12.key', 'daftar_ulang')
            ->assertJsonPath('data.students.0.name', 'Ani')
            ->assertJsonPath('data.students.0.cells.m1.status', 'LUNAS')
            ->assertJsonPath('data.students.0.cells.daftar_ulang.status', 'SEBAGIAN')
            ->assertJsonPath('data.students.0.cells.daftar_ulang.remaining', 300000)
            ->assertJsonPath('data.students.1.cells.m1.status', 'BELUM BAYAR')
            ->assertJsonPath('data.column_totals.m1.unpaid', 1)
            ->assertJsonPath('data.total.remaining', 450000)
            ->assertJsonPath('data.total.students_with_arrears', 2);
    }

    public function test_class_recap_keeps_students_in_the_class_they_were_billed_in(): void
    {
        $this->bill($this->budi, $this->spp, 'Juli', 150000);
        $nextClass = ClassRoom::create(['name' => 'XI TKJ 1', 'grade' => 'XI', 'major_id' => $this->class->major_id, 'academic_year_id' => $this->year->id]);
        $this->budi->update(['class_id' => $nextClass->id]);

        $this->actingAs($this->bendahara)
            ->getJson("/api/v1/reports/rekap-kelas?academic_year_id={$this->year->id}&class_id={$this->class->id}")
            ->assertOk()
            ->assertJsonPath('data.students.1.name', 'Budi')
            ->assertJsonPath('data.students.1.cells.m1.remaining', 150000);
    }

    public function test_class_recap_downloads_as_excel(): void
    {
        Excel::fake();

        $this->actingAs($this->bendahara)
            ->get("/api/v1/reports/rekap-kelas?academic_year_id={$this->year->id}&class_id={$this->class->id}&export=1")
            ->assertOk();

        Excel::assertDownloaded('rekap-kelas-x-tkj-1-20262027.xlsx');
    }

    public function test_income_recap_splits_successful_payments_and_checks_the_cash(): void
    {
        $aniJuly = $this->bill($this->ani, $this->spp, 'Juli', 150000);
        $budiJuly = $this->bill($this->budi, $this->spp, 'Juli', 150000);
        $budiRegistration = $this->bill($this->budi, $this->daftarUlang, '2026/2027', 500000);
        $this->openCash();
        $this->pay($this->ani, $this->cash, [$aniJuly->id => 150000]);
        $this->pay($this->budi, $this->transfer, [$budiJuly->id => 150000]);
        $this->pay($this->budi, $this->cash, [$budiRegistration->id => 100000]);
        Payment::query()->latest('id')->first()->update(['status' => 'CANCELLED']);

        $this->actingAs($this->bendahara)
            ->postJson('/api/v1/cashier-sessions/close', ['actual_balance' => 140000, 'notes' => 'Kurang hitung'])
            ->assertOk();

        $today = now()->toDateString();
        $response = $this->actingAs($this->bendahara)
            ->getJson("/api/v1/reports/pemasukan?date_from={$today}&date_to={$today}")
            ->assertOk();

        $response->assertJsonPath('data.summary.total', 300000)
            ->assertJsonPath('data.summary.cash', 150000)
            ->assertJsonPath('data.summary.non_cash', 150000)
            ->assertJsonPath('data.summary.transactions', 2)
            ->assertJsonPath('data.per_day.0.total', 300000)
            ->assertJsonCount(1, 'data.per_type')
            ->assertJsonPath('data.per_type.0.name', 'SPP')
            ->assertJsonPath('data.per_class.0.name', 'X TKJ 1')
            ->assertJsonCount(2, 'data.per_method')
            ->assertJsonPath('data.per_cashier.0.total', 300000)
            ->assertJsonPath('data.cash_check.sessions', 1)
            ->assertJsonPath('data.cash_check.difference', -10000)
            ->assertJsonPath('data.cash_check.mismatches.0.notes', 'Kurang hitung');
    }

    public function test_income_recap_rejects_ranges_longer_than_a_year(): void
    {
        $this->actingAs($this->bendahara)
            ->getJson('/api/v1/reports/pemasukan?date_from=2025-01-01&date_to=2026-06-30')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }

    public function test_recaps_are_closed_to_administrasi(): void
    {
        $administrasi = User::factory()->create();
        $administrasi->assignRole(Role::firstOrCreate(['name' => 'Administrasi']));

        $this->actingAs($administrasi)->getJson('/api/v1/reports/pemasukan?date_from=2026-07-01&date_to=2026-07-31')->assertForbidden();
    }

    private function bill(Student $student, PaymentType $type, string $period, int $amount): InvoiceItem
    {
        $invoice = Invoice::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'academic_year_id' => $this->year->id,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'status' => 'BELUM BAYAR',
        ]);

        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'payment_type_id' => $type->id,
            'period' => $period,
            'amount' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'status' => 'BELUM BAYAR',
        ]);
    }

    private function openCash(): void
    {
        $this->actingAs($this->bendahara)->postJson('/api/v1/cashier-sessions/open')->assertCreated();
    }

    /**
     * @param  array<int, int>  $amountsByItem
     */
    private function pay(Student $student, PaymentMethod $method, array $amountsByItem): void
    {
        $items = [];
        foreach ($amountsByItem as $itemId => $amount) {
            $items[] = ['invoice_item_id' => $itemId, 'amount' => $amount];
        }

        $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $student->id,
            'payment_method_id' => $method->id,
            'items' => $items,
        ])->assertCreated();
    }
}
