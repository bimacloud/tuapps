<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CashierSession;
use App\Models\ClassRoom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Major;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentCardTest extends TestCase
{
    use RefreshDatabase;

    protected User $bendahara;

    protected Student $student;

    protected AcademicYear $academicYear;

    protected Invoice $invoice;

    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole(Role::firstOrCreate(['name' => 'Bendahara']));

        $this->academicYear = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $major = Major::create(['name' => 'Teknik Komputer dan Jaringan', 'code' => 'TKJ']);
        $class = ClassRoom::create(['name' => 'X TKJ 1', 'grade' => 'X', 'major_id' => $major->id]);
        $this->student = Student::create(['name' => 'Siti Aminah', 'nis' => '2026001', 'class_id' => $class->id, 'status' => 'AKTIF']);

        $this->invoice = Invoice::create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'BELUM BAYAR',
        ]);

        $this->paymentMethod = PaymentMethod::create(['code' => 'CASH', 'name' => 'Tunai', 'is_active' => true]);

        CashierSession::create([
            'user_id' => $this->bendahara->id,
            'start_time' => now(),
            'starting_balance' => 0,
            'status' => 'OPEN',
        ]);
    }

    public function test_card_fills_monthly_and_annual_rows_from_successful_payments(): void
    {
        $sppJuli = $this->billItem('SPP', 'BULANAN', 'Juli', 150000);
        $this->billItem('SPP', 'BULANAN', 'Agustus', 150000);
        $daftarUlang = $this->billItem('Daftar Ulang', 'TAHUNAN', 'Tahunan', 1000000);
        $lksGenap = $this->billItem('LKS', 'SEMESTER', 'Semester 2', 120000);
        $this->billItem('Kegiatan OSIS', 'KHUSUS', 'Tahunan', 50000);

        $this->pay($sppJuli, 150000);
        $this->pay($daftarUlang, 400000);
        $this->pay($daftarUlang, 300000);
        $voidedPayment = $this->pay($lksGenap, 120000);
        Payment::whereKey($voidedPayment)->update(['status' => 'VOID']);

        $response = $this->actingAs($this->bendahara)
            ->getJson("/api/v1/payments/student/{$this->student->id}/card");

        $response->assertOk()
            ->assertJsonPath('data.student.name', 'Siti Aminah')
            ->assertJsonPath('data.student.class_name', 'X TKJ 1')
            ->assertJsonPath('data.academic_year.name', '2026/2027')
            ->assertJsonCount(12, 'data.monthly')
            ->assertJsonPath('data.monthly.0.month', 'Juli')
            ->assertJsonPath('data.monthly.0.date', now()->toDateString())
            ->assertJsonPath('data.monthly.1.month', 'Agustus')
            ->assertJsonPath('data.monthly.1.date', null)
            ->assertJsonPath('data.monthly.1.status', 'BELUM BAYAR')
            ->assertJsonPath('data.monthly.11.month', 'Juni')
            ->assertJsonPath('data.monthly.11.status', null)
            ->assertJsonCount(10, 'data.annual');

        $annual = collect($response->json('data.annual'))->keyBy('key');

        $this->assertSame(['Daftar Ulang', 4], [$annual['daftar_ulang']['label'], $annual['daftar_ulang']['min_rows']]);
        $this->assertEqualsCanonicalizing([400000, 300000], array_column($annual['daftar_ulang']['rows'], 'amount'));
        $this->assertSame([], $annual['lks_2']['rows'], 'Voided payments must not appear on the card.');
        $this->assertSame(['LKS Semester 2'], $annual['lks_2']['items']);
        $this->assertSame(['Kegiatan OSIS Tahunan'], $annual['lainnya']['items']);
        $this->assertSame(10, $annual['lainnya']['no']);
    }

    public function test_receipt_tells_which_card_row_to_write_and_what_was_left_at_that_time(): void
    {
        $sppJuli = $this->billItem('SPP', 'BULANAN', 'Juli', 150000);
        $daftarUlang = $this->billItem('Daftar Ulang', 'TAHUNAN', 'Tahunan', 1000000);
        $this->invoice->update(['total_amount' => 1150000, 'remaining_amount' => 1150000]);

        $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [
                ['invoice_item_id' => $sppJuli->id, 'amount' => 150000],
                ['invoice_item_id' => $daftarUlang->id, 'amount' => 400000],
            ],
        ])->assertCreated();
        $firstPaymentId = Payment::latest('id')->value('id');

        $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [['invoice_item_id' => $daftarUlang->id, 'amount' => 600000]],
        ])->assertCreated();

        $lines = collect(
            $this->actingAs($this->bendahara)
                ->getJson("/api/v1/payments/{$firstPaymentId}/receipt")
                ->assertOk()
                ->json('card_lines')
        )->keyBy('row');

        $this->assertSame(['bulanan', 1, 150000, 0], [$lines['Juli']['side'], $lines['Juli']['no'], (int) $lines['Juli']['amount'], (int) $lines['Juli']['remaining_after']]);
        $this->assertSame(['semester', 1, 400000, 600000], [$lines['Daftar Ulang']['side'], $lines['Daftar Ulang']['no'], (int) $lines['Daftar Ulang']['amount'], (int) $lines['Daftar Ulang']['remaining_after']], 'An instalment receipt keeps showing what was left at that time.');
        $this->assertSame(now()->toDateString(), $lines['Juli']['date']);
    }

    public function test_spp_instalment_is_written_on_the_card_only_when_the_month_is_paid_off(): void
    {
        $sppAgustus = $this->billItem('SPP', 'BULANAN', 'Agustus', 150000);
        $this->invoice->update(['total_amount' => 150000, 'remaining_amount' => 150000]);

        $firstPaymentId = $this->payAndGetId($sppAgustus, 100000);
        $secondPaymentId = $this->payAndGetId($sppAgustus, 50000);

        $first = $this->actingAs($this->bendahara)->getJson("/api/v1/payments/{$firstPaymentId}/receipt")->json('card_lines.0');
        $second = $this->actingAs($this->bendahara)->getJson("/api/v1/payments/{$secondPaymentId}/receipt")->json('card_lines.0');

        $this->assertFalse($first['write_now'], 'A partly paid month is not written on the card yet.');
        $this->assertSame(50000, (int) $first['remaining_after']);
        $this->assertTrue($second['write_now']);
        $this->assertSame(['Agustus', 2, 150000], [$second['row'], $second['no'], (int) $second['card_amount']], 'The paid-off month is written with its full total.');
    }

    public function test_every_daftar_ulang_instalment_gets_its_own_card_line(): void
    {
        $daftarUlang = $this->billItem('Daftar Ulang', 'TAHUNAN', '2026/2027', 1200000);
        $this->invoice->update(['total_amount' => 1200000, 'remaining_amount' => 1200000]);

        $paymentId = $this->payAndGetId($daftarUlang, 200000);
        $line = $this->actingAs($this->bendahara)->getJson("/api/v1/payments/{$paymentId}/receipt")->json('card_lines.0');

        $this->assertTrue($line['write_now']);
        $this->assertSame([200000, 1000000], [(int) $line['card_amount'], (int) $line['remaining_after']]);
    }

    public function test_class_cards_list_only_active_students_by_name(): void
    {
        Student::create(['name' => 'Ahmad Fauzi', 'nis' => '2026002', 'class_id' => $this->student->class_id, 'status' => 'AKTIF']);
        Student::create(['name' => 'Budi Lulus', 'nis' => '2026003', 'class_id' => $this->student->class_id, 'status' => 'LULUS']);

        $this->actingAs($this->bendahara)
            ->getJson("/api/v1/payment-cards?class_id={$this->student->class_id}")
            ->assertOk()
            ->assertJsonPath('data.class.name', 'X TKJ 1')
            ->assertJsonPath('data.academic_year.name', '2026/2027')
            ->assertJsonCount(12, 'data.months')
            ->assertJsonCount(10, 'data.annual_slots')
            ->assertJsonCount(2, 'data.students')
            ->assertJsonPath('data.students.0.name', 'Ahmad Fauzi')
            ->assertJsonPath('data.students.1.name', 'Siti Aminah')
            ->assertJsonPath('data.students.1.class_name', 'X TKJ 1');
    }

    public function test_card_requires_authentication(): void
    {
        $this->getJson("/api/v1/payments/student/{$this->student->id}/card")->assertUnauthorized();
    }

    public function test_card_rejects_unknown_academic_year(): void
    {
        $this->actingAs($this->bendahara)
            ->getJson("/api/v1/payments/student/{$this->student->id}/card?academic_year_id=999")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('academic_year_id');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function slotNames(): array
    {
        return [
            'daftar ulang' => ['Daftar Ulang', 'Tahunan', 'daftar_ulang'],
            'lks semester 1' => ['LKS', 'Semester 1', 'lks_1'],
            'buku genap' => ['Buku Paket', 'Genap', 'lks_2'],
            'asts 2 in name' => ['ASTS 2', '2026/2027', 'asts_2'],
            'asts gasal' => ['ASTS', 'Gasal', 'asts_1'],
            'asas' => ['ASAS', 'Semester 1', 'asas_1'],
            'asat' => ['ASAT', 'Semester 2', 'asat'],
            'pkl' => ['PKL', 'Kelas XI', 'pkl'],
            'asesmen akhir' => ['Asesmen Akhir', 'Kelas XII', 'asesmen_akhir'],
            'unknown' => ['Seragam', 'Tahunan', 'lainnya'],
        ];
    }

    #[DataProvider('slotNames')]
    public function test_payment_type_names_map_to_card_rows(string $typeName, string $period, string $expectedKey): void
    {
        $item = new InvoiceItem(['period' => $period]);
        $item->setRelation('paymentType', new PaymentType(['name' => $typeName, 'type' => 'TAHUNAN']));

        $this->assertSame($expectedKey, app(PaymentCardService::class)->slotKeyFor($item));
    }

    private function billItem(string $typeName, string $type, string $period, int $amount): InvoiceItem
    {
        $paymentType = PaymentType::firstOrCreate(['name' => $typeName], ['type' => $type, 'status' => true]);

        return InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'payment_type_id' => $paymentType->id,
            'period' => $period,
            'amount' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'status' => 'BELUM BAYAR',
        ]);
    }

    private function payAndGetId(InvoiceItem $item, int $amount): int
    {
        $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [['invoice_item_id' => $item->id, 'amount' => $amount]],
        ])->assertCreated();

        return Payment::latest('id')->value('id');
    }

    private function pay(InvoiceItem $item, int $amount): int
    {
        $this->invoice->increment('total_amount', $amount);
        $this->invoice->increment('remaining_amount', $amount);

        $response = $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [['invoice_item_id' => $item->id, 'amount' => $amount]],
        ]);

        $response->assertCreated();

        return Payment::latest('id')->value('id');
    }
}
