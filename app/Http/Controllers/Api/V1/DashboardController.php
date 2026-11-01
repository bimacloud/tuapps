<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Invoice;
use App\Models\Major;
use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function getActiveYearId($request)
    {
        $id = $request->academic_year_id;
        if (! $id) {
            $activeYear = AcademicYear::where('is_active', true)->first();

            return $activeYear ? $activeYear->id : null;
        }

        return $id;
    }

    public function metricsKepsek(Request $request)
    {
        $yearId = $this->getActiveYearId($request);

        $totalSiswa = Student::where('status', 'AKTIF')->count();

        $invoiceQuery = Invoice::query();
        if ($yearId) {
            $invoiceQuery->where('academic_year_id', $yearId);
        }

        // Aggregate SQL to prevent loading huge data
        $invoiceStats = $invoiceQuery->selectRaw('
            SUM(total_amount) as total_tagihan,
            SUM(paid_amount) as total_pembayaran,
            SUM(remaining_amount) as total_tunggakan
        ')->first();

        $totalTagihan = (float) $invoiceStats->total_tagihan;
        $totalPembayaran = (float) $invoiceStats->total_pembayaran;
        $totalTunggakan = (float) $invoiceStats->total_tunggakan;

        $collectionRate = $totalTagihan > 0 ? round(($totalPembayaran / $totalTagihan) * 100, 2) : 0;

        return response()->json([
            'data' => [
                'total_siswa' => $totalSiswa,
                'total_tagihan' => $totalTagihan,
                'total_pembayaran' => $totalPembayaran,
                'total_tunggakan' => $totalTunggakan,
                'collection_rate' => $collectionRate,
                'academic_year_id' => $yearId,
            ],
        ]);
    }

    public function metricsBendahara(Request $request)
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // Hari ini
        $pembayaranHariIni = Payment::whereDate('payment_date', $today)->where('status', 'SUCCESS');
        $totalPemasukanHariIni = (float) $pembayaranHariIni->sum('total_amount');
        $jumlahTransaksiHariIni = $pembayaranHariIni->count();

        // Bulan ini
        $pemasukanBulanIni = (float) Payment::where('status', 'SUCCESS')
            ->where('payment_date', '>=', $thisMonth)
            ->sum('total_amount');

        // Total Tunggakan (Global or By Year)
        $yearId = $this->getActiveYearId($request);
        $tunggakanQuery = Invoice::query();
        if ($yearId) {
            $tunggakanQuery->where('academic_year_id', $yearId);
        }
        $totalTunggakan = (float) $tunggakanQuery->sum('remaining_amount');

        // Siswa Menunggak count
        $siswaMenunggak = $tunggakanQuery->where('remaining_amount', '>', 0)
            ->distinct('student_id')
            ->count('student_id');

        // Transaksi Terbaru
        $recentTransactions = Payment::with(['student:id,name,nis,class_id', 'student.classRoom:id,name', 'paymentMethod:id,name'])
            ->where('status', 'SUCCESS')
            ->orderBy('payment_date', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'pemasukan_hari_ini' => $totalPemasukanHariIni,
                'transaksi_hari_ini' => $jumlahTransaksiHariIni,
                'pemasukan_bulan_ini' => $pemasukanBulanIni,
                'total_tunggakan' => $totalTunggakan,
                'siswa_menunggak' => $siswaMenunggak,
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }

    /**
     * Data-entry overview for Administrasi: how complete the student and class data is.
     */
    public function metricsAdministrasi(Request $request)
    {
        $yearId = $this->getActiveYearId($request);
        $activeYearClasses = ClassRoom::where('academic_year_id', $yearId);

        return response()->json([
            'data' => [
                'siswa_aktif' => Student::where('status', 'AKTIF')->count(),
                'kelas_tahun_aktif' => (clone $activeYearClasses)->count(),
                'kelas_tanpa_siswa' => (clone $activeYearClasses)
                    ->whereDoesntHave('students', fn ($query) => $query->where('status', 'AKTIF'))
                    ->count(),
                'jumlah_jurusan' => Major::count(),
                'siswa_terbaru' => Student::with('classRoom:id,name')
                    ->latest('id')
                    ->take(5)
                    ->get(['id', 'name', 'nis', 'class_id', 'status', 'created_at']),
            ],
        ]);
    }

    public function charts(Request $request)
    {
        $yearId = $this->getActiveYearId($request);

        // 1. Pemasukan per Bulan (Last 6 Months)
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
        $monthlyIncome = Payment::select('payment_date', 'total_amount')
            ->where('status', 'SUCCESS')
            ->where('payment_date', '>=', $sixMonthsAgo)
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->payment_date)->format('Y-m');
            })
            ->map(function ($group, $key) {
                $parts = explode('-', $key);

                return [
                    'year' => (int) $parts[0],
                    'month' => (int) $parts[1],
                    'total' => $group->sum('total_amount'),
                ];
            })
            ->values()
            ->sortBy(function ($item) {
                return $item['year'].str_pad($item['month'], 2, '0', STR_PAD_LEFT);
            })
            ->values();

        // 2. Pembayaran per Jenis (Based on Invoice Items paid)
        $paymentByType = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('payment_types', 'invoice_items.payment_type_id', '=', 'payment_types.id')
            ->where('invoices.academic_year_id', $yearId)
            ->where('invoice_items.paid_amount', '>', 0)
            ->select('payment_types.name', DB::raw('SUM(invoice_items.paid_amount) as total'))
            ->groupBy('payment_types.name')
            ->get();

        // 3. Status Pembayaran (Lunas, Sebagian, Belum)
        $paymentStatus = DB::table('invoices')
            ->where('academic_year_id', $yearId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        // 4. Tunggakan per Kelas (Top 5)
        $arrearsByClass = DB::table('invoices')
            ->join('students', 'invoices.student_id', '=', 'students.id')
            ->join('classes', 'students.class_id', '=', 'classes.id')
            ->where('invoices.academic_year_id', $yearId)
            ->where('invoices.remaining_amount', '>', 0)
            ->select('classes.name', DB::raw('SUM(invoices.remaining_amount) as total'))
            ->groupBy('classes.name')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'monthly_income' => $monthlyIncome,
                'payment_by_type' => $paymentByType,
                'payment_status' => $paymentStatus,
                'arrears_by_class' => $arrearsByClass,
            ],
        ]);
    }
}
