<?php

namespace App\Exports;

use App\Models\Setting;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Income recap workbook: summary first, then one sheet per breakdown and the cash check.
 */
class IncomeRecapExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $recap  output of IncomeRecapService::build()
     */
    public function __construct(private array $recap) {}

    public function sheets(): array
    {
        $summary = $this->recap['summary'];
        $cash = $this->recap['cash_check'];

        $summaryRows = array_merge($this->header('Ringkasan'), [
            ['Total pemasukan', $summary['total']],
            ['Tunai', $summary['cash']],
            ['Non-tunai', $summary['non_cash']],
            ['Jumlah transaksi', $summary['transactions']],
            ['Jumlah siswa membayar', $summary['students']],
            [],
            ['Cocok kas'],
            ['Kas ditutup', $cash['sessions']],
            ['Kas masih dibuka', $cash['open_sessions']],
            ['Seharusnya di laci', $cash['expected']],
            ['Uang fisik', $cash['actual']],
            ['Selisih', $cash['difference']],
        ]);

        return [
            new ReportSheet('Ringkasan', $summaryRows, [4, 10], ['B']),
            $this->table('Per hari', ['Tanggal', 'Transaksi', 'Total', 'Tunai', 'Non-tunai'], array_map(
                fn (array $row): array => [$row['date'], $row['transactions'], $row['total'], $row['cash'], $row['non_cash']],
                $this->recap['per_day'],
            ), ['C', 'D', 'E']),
            $this->table('Per jenis', ['Jenis pembayaran', 'Tipe', 'Transaksi', 'Total'], array_map(
                fn (array $row): array => [$row['name'], $row['type'], $row['transactions'], $row['total']],
                $this->recap['per_type'],
            ), ['D']),
            $this->table('Per kelas', ['Kelas', 'Transaksi', 'Total'], array_map(
                fn (array $row): array => [$row['name'], $row['transactions'], $row['total']],
                $this->recap['per_class'],
            ), ['C']),
            $this->table('Per metode', ['Metode', 'Transaksi', 'Total'], array_map(
                fn (array $row): array => [$row['name'], $row['transactions'], $row['total']],
                $this->recap['per_method'],
            ), ['C']),
            $this->table('Per kasir', ['Kasir', 'Transaksi', 'Total'], array_map(
                fn (array $row): array => [$row['name'], $row['transactions'], $row['total']],
                $this->recap['per_cashier'],
            ), ['C']),
            $this->table('Kas tidak cocok', ['Dibuka', 'Ditutup', 'Kasir', 'Seharusnya di laci', 'Uang fisik', 'Selisih', 'Catatan'], array_map(
                fn (array $row): array => [
                    $this->dateTime($row['opened_at']),
                    $this->dateTime($row['closed_at']),
                    $row['cashier'],
                    $row['expected'],
                    $row['actual'],
                    $row['difference'],
                    $row['notes'],
                ],
                $cash['mismatches'],
            ), ['D', 'E', 'F']),
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    private function header(string $title): array
    {
        $period = $this->recap['period'];

        return [
            [Setting::query()->where('key', 'school_name')->value('value') ?? ''],
            ["Rekap pemasukan {$period['from']} s.d. {$period['to']}: {$title}"],
            [],
        ];
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $data
     * @param  list<string>  $moneyColumns
     */
    private function table(string $title, array $headings, array $data, array $moneyColumns): ReportSheet
    {
        $rows = array_merge($this->header($title), [$headings], $data);

        return new ReportSheet($title, $rows, [4], $moneyColumns);
    }

    private function dateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i') : null;
    }
}
