<?php

use App\Models\Student;
use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\PaymentRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentMethod;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\User;
use App\Models\CashierSession;
use Illuminate\Support\Str;

$academicYear = AcademicYear::where('is_active', true)->first();
if (!$academicYear) {
    $academicYear = AcademicYear::create(['name' => '2023/2024', 'is_active' => true]);
}

// Ensure Payment Types
$spp = PaymentType::firstOrCreate(['name' => 'SPP Bulanan'], ['type' => 'BULANAN', 'is_active' => true]);
$bangunan = PaymentType::firstOrCreate(['name' => 'Uang Gedung'], ['type' => 'TAHUNAN', 'is_active' => true]);
$seragam = PaymentType::firstOrCreate(['name' => 'Seragam'], ['type' => 'KHUSUS', 'is_active' => true]);

// Ensure Payment Rates
PaymentRate::firstOrCreate([
    'payment_type_id' => $spp->id,
    'academic_year_id' => $academicYear->id,
], ['amount' => 250000]);

PaymentRate::firstOrCreate([
    'payment_type_id' => $bangunan->id,
    'academic_year_id' => $academicYear->id,
], ['amount' => 3000000]);

PaymentRate::firstOrCreate([
    'payment_type_id' => $seragam->id,
    'academic_year_id' => $academicYear->id,
], ['amount' => 1200000]);

// Ensure Payment Methods
$cashMethod = PaymentMethod::firstOrCreate(['code' => 'CASH'], ['name' => 'Tunai', 'is_active' => true]);
$tfMethod = PaymentMethod::firstOrCreate(['code' => 'TRANSFER'], ['name' => 'Transfer Bank', 'is_active' => true]);
$onlineMethod = PaymentMethod::firstOrCreate(['code' => 'ONLINE'], ['name' => 'QRIS / Virtual Account', 'is_active' => true]);

// Get Bendahara User for offline payments
$bendahara = User::role('Bendahara')->first();
if (!$bendahara) {
    echo "Bendahara user not found. Skipping offline payments.\n";
    $bendahara = User::first(); // fallback
}

// Create an open Cashier Session for Bendahara
$session = CashierSession::create([
    'user_id' => $bendahara->id,
    'start_time' => now()->subHours(2),
    'starting_balance' => 500000, // modal
    'status' => 'OPEN',
]);

$students = Student::all();

$months = ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

foreach ($students as $index => $student) {
    
    $totalInvoiceAmount = 0;
    
    // Create Invoice
    $invoice = Invoice::create([
        'invoice_number' => 'INV-' . date('Ymd') . '-' . Str::upper(Str::random(5)),
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'total_amount' => 0,
        'paid_amount' => 0,
        'remaining_amount' => 0,
        'status' => 'BELUM BAYAR'
    ]);

    // Create SPP Items (6 months)
    foreach ($months as $month) {
        $amount = 250000;
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'payment_type_id' => $spp->id,
            'period' => $month,
            'amount' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'status' => 'BELUM BAYAR'
        ]);
        $totalInvoiceAmount += $amount;
    }

    // Create Uang Gedung Item
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'payment_type_id' => $bangunan->id,
        'amount' => 3000000,
        'paid_amount' => 0,
        'remaining_amount' => 3000000,
        'status' => 'BELUM BAYAR'
    ]);
    $totalInvoiceAmount += 3000000;

    // Create Seragam Item
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'payment_type_id' => $seragam->id,
        'amount' => 1200000,
        'paid_amount' => 0,
        'remaining_amount' => 1200000,
        'status' => 'BELUM BAYAR'
    ]);
    $totalInvoiceAmount += 1200000;

    // Update Invoice Total
    $invoice->update([
        'total_amount' => $totalInvoiceAmount,
        'remaining_amount' => $totalInvoiceAmount
    ]);

    // --- Generate Dummy Payments ---

    // Student 1 (index 0): Paid 3 months SPP (CASH) + Uang Gedung (Partial - Transfer)
    if ($index === 0) {
        // Pay 3 months SPP
        $sppItems = $invoice->items()->where('payment_type_id', $spp->id)->take(3)->get();
        $pay1 = Payment::create([
            'transaction_number' => 'PAY-' . date('Ymd') . '-00' . rand(10, 99),
            'student_id' => $student->id,
            'cashier_id' => $bendahara->id,
            'cashier_session_id' => $session->id,
            'payment_method_id' => $cashMethod->id,
            'payment_date' => now()->subDays(5),
            'total_amount' => 750000,
            'status' => 'SUCCESS',
            'notes' => 'Lunas SPP 3 Bulan Pertama'
        ]);
        
        foreach ($sppItems as $item) {
            PaymentItem::create([
                'payment_id' => $pay1->id,
                'invoice_id' => $invoice->id,
                'invoice_item_id' => $item->id,
                'amount' => 250000,
            ]);
            $item->update([
                'paid_amount' => 250000,
                'remaining_amount' => 0,
                'status' => 'LUNAS'
            ]);
        }

        // Pay Uang Gedung (Partial)
        $gedungItem = $invoice->items()->where('payment_type_id', $bangunan->id)->first();
        $pay2 = Payment::create([
            'transaction_number' => 'PAY-' . date('Ymd') . '-00' . rand(10, 99),
            'student_id' => $student->id,
            'cashier_id' => $bendahara->id,
            'cashier_session_id' => $session->id,
            'payment_method_id' => $tfMethod->id,
            'payment_date' => now()->subDays(2),
            'total_amount' => 1000000,
            'status' => 'SUCCESS',
            'notes' => 'Cicilan 1 Uang Gedung'
        ]);
        
        PaymentItem::create([
            'payment_id' => $pay2->id,
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $gedungItem->id,
            'amount' => 1000000,
        ]);
        $gedungItem->update([
            'paid_amount' => 1000000,
            'remaining_amount' => 2000000,
            'status' => 'SEBAGIAN'
        ]);

        $invoice->update([
            'paid_amount' => 1750000,
            'remaining_amount' => $totalInvoiceAmount - 1750000,
            'status' => 'SEBAGIAN'
        ]);
    }

    // Student 2: Paid Seragam Online (SUCCESS)
    if ($index === 1) {
        $seragamItem = $invoice->items()->where('payment_type_id', $seragam->id)->first();
        $payOnline = Payment::create([
            'transaction_number' => 'PAY-' . date('Ymd') . '-ON' . rand(10, 99),
            'student_id' => $student->id,
            'cashier_id' => null, // Online
            'cashier_session_id' => null,
            'payment_method_id' => $onlineMethod->id,
            'payment_date' => now()->subDays(1),
            'total_amount' => 1200000,
            'status' => 'SUCCESS',
            'gateway_reference' => 'midtrans-' . rand(1000, 9999),
            'notes' => 'Pembayaran Online QRIS'
        ]);

        PaymentItem::create([
            'payment_id' => $payOnline->id,
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $seragamItem->id,
            'amount' => 1200000,
        ]);
        $seragamItem->update([
            'paid_amount' => 1200000,
            'remaining_amount' => 0,
            'status' => 'LUNAS'
        ]);

        $invoice->update([
            'paid_amount' => 1200000,
            'remaining_amount' => $totalInvoiceAmount - 1200000,
            'status' => 'SEBAGIAN'
        ]);
    }

    // Student 3: Has a PENDING online payment
    if ($index === 2) {
        $spp1 = $invoice->items()->where('payment_type_id', $spp->id)->first();
        $payPending = Payment::create([
            'transaction_number' => 'PAY-' . date('Ymd') . '-ON' . rand(10, 99),
            'student_id' => $student->id,
            'cashier_id' => null,
            'cashier_session_id' => null,
            'payment_method_id' => $onlineMethod->id,
            'payment_date' => now(),
            'total_amount' => 250000,
            'status' => 'PENDING',
            'gateway_reference' => 'midtrans-' . rand(1000, 9999),
            'checkout_url' => 'https://sandbox.midtrans.com/checkout/dummy',
            'notes' => 'Menunggu Pembayaran VA'
        ]);

        PaymentItem::create([
            'payment_id' => $payPending->id,
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $spp1->id,
            'amount' => 250000,
        ]);
        // Invoice untouched until webhook comes
    }
}

// Generate some Audit Logs
\App\Services\AuditService::log('SYSTEM_SETUP', 'SYSTEM', 'Berhasil melakukan pengisian data sampel untuk uji coba.');

echo "Berhasil mengisi data tagihan (invoices) & simulasi transaksi pembayaran untuk 5 siswa.\n";
