<?php

namespace Tests\Feature;

use App\Exports\SetupDataExport;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Major;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use App\Services\SetupData\StudentDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SetupDataImportTest extends TestCase
{
    use RefreshDatabase;

    private User $administrasi;

    private User $bendahara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrasi = User::factory()->create();
        $this->administrasi->assignRole(Role::firstOrCreate(['name' => 'Administrasi']));

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole(Role::firstOrCreate(['name' => 'Bendahara']));
    }

    public function test_administrasi_can_download_a_template(): void
    {
        $this->actingAs($this->administrasi)
            ->get('/api/v1/setup/majors/template')
            ->assertOk()
            ->assertDownload('template-majors.xlsx');
    }

    public function test_dataset_is_limited_to_the_role_that_owns_it(): void
    {
        $this->actingAs($this->administrasi)->get('/api/v1/setup/payment-rates/template')->assertForbidden();
        $this->actingAs($this->bendahara)->get('/api/v1/setup/students/export')->assertForbidden();
        $this->actingAs($this->administrasi)->get('/api/v1/setup/unknown/template')->assertNotFound();
    }

    public function test_import_creates_new_rows_updates_existing_ones_and_skips_invalid_ones(): void
    {
        Major::create(['code' => 'TKJ', 'name' => 'Nama Lama']);
        $csv = "Kode,Nama,Aktif\nTKJ,Teknik Komputer dan Jaringan,Ya\nAKL,Akuntansi,Ya\nRPL,,Ya\nakl,Duplikat,Ya\n";

        $this->actingAs($this->administrasi)
            ->post('/api/v1/setup/majors/import', ['file' => $this->csv($csv)])
            ->assertOk()
            ->assertJsonPath('summary', ['total' => 4, 'create' => 1, 'update' => 1, 'invalid' => 2]);

        $this->assertSame('Teknik Komputer dan Jaringan', Major::where('code', 'TKJ')->value('name'));
        $this->assertSame('Akuntansi', Major::where('code', 'AKL')->value('name'));
        $this->assertFalse(Major::where('code', 'RPL')->exists());
    }

    public function test_preview_explains_why_a_row_is_skipped_without_saving(): void
    {
        $this->classInActiveYear('X TKJ 1');
        $csv = "Nama Lengkap,Kelas,NIS\nSiswa Satu,X TKJ 1,2026101\nSiswa Dua,X TKJ 9,2026102\n";

        $this->actingAs($this->administrasi)
            ->post('/api/v1/setup/students/preview', ['file' => $this->csv($csv)])
            ->assertOk()
            ->assertJsonPath('rows.0.row', 2)
            ->assertJsonPath('rows.0.action', 'create')
            ->assertJsonPath('rows.1.is_valid', false)
            ->assertJsonPath('rows.1.errors.0', 'Kelas tidak ditemukan: X TKJ 9');

        $this->assertSame(0, Student::count());
    }

    public function test_file_without_the_template_columns_is_rejected(): void
    {
        $this->actingAs($this->administrasi)
            ->postJson('/api/v1/setup/students/preview', ['file' => $this->csv("Nama,Alamat\nSiswa,Jalan\n")])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_exported_students_import_back_with_leading_zeros_and_class_major(): void
    {
        $class = $this->classInActiveYear('X TKJ 1');
        Student::create(['nisn' => '0012345678', 'nis' => '2026001', 'name' => 'Siswa Lama', 'class_id' => $class->id, 'status' => 'AKTIF']);

        $content = Excel::raw(new SetupDataExport(new StudentDataset, (new StudentDataset)->exportRows()), ExcelWriter::XLSX);
        Student::query()->update(['name' => 'Diubah']);

        $this->actingAs($this->administrasi)
            ->post('/api/v1/setup/students/import', ['file' => UploadedFile::fake()->createWithContent('siswa.xlsx', $content)])
            ->assertOk()
            ->assertJsonPath('summary.update', 1);

        $student = Student::sole();
        $this->assertSame('0012345678', $student->nisn);
        $this->assertSame('Siswa Lama', $student->name);
        $this->assertSame($class->major_id, $student->major_id);
    }

    public function test_rate_import_never_overwrites_an_existing_rate(): void
    {
        $class = $this->classInActiveYear('X TKJ 1');
        $type = PaymentType::create(['code' => 'SPP', 'name' => 'SPP', 'type' => 'BULANAN']);
        PaymentRate::create(['payment_type_id' => $type->id, 'academic_year_id' => $class->academic_year_id, 'amount' => 150000]);
        $csv = "Kode Jenis,Tahun Pelajaran,Kelas,Nominal\nSPP,2026/2027,,200000\nSPP,2026/2027,X TKJ 1,Rp 175.000\n";

        $this->actingAs($this->bendahara)
            ->post('/api/v1/setup/payment-rates/import', ['file' => $this->csv($csv)])
            ->assertOk()
            ->assertJsonPath('summary.create', 1)
            ->assertJsonPath('summary.invalid', 1);

        $this->assertEquals(150000, PaymentRate::whereNull('class_id')->value('amount'));
        $this->assertEquals(175000, PaymentRate::where('class_id', $class->id)->value('amount'));
    }

    public function test_status_lists_setup_steps_in_order(): void
    {
        $this->classInActiveYear('X TKJ 1');

        $this->actingAs($this->administrasi)
            ->getJson('/api/v1/setup/status')
            ->assertOk()
            ->assertJsonPath('data.1.key', 'academic-years')
            ->assertJsonPath('data.1.done', true)
            ->assertJsonPath('data.3.count', 1)
            ->assertJsonPath('data.4.done', false);
    }

    private function classInActiveYear(string $name): ClassRoom
    {
        $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-30', 'is_active' => true]);
        $major = Major::create(['name' => 'Teknik Komputer dan Jaringan', 'code' => 'TKJ']);

        return ClassRoom::create(['name' => $name, 'grade' => 'X', 'major_id' => $major->id, 'academic_year_id' => $year->id]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('data.csv', $content);
    }
}
