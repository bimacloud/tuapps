<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\WhatsAppChannel;

class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Payment $payment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->routeNotificationFor('mail', $this)) {
            $channels[] = 'mail';
        }

        if ($notifiable->routeNotificationFor('whatsApp', $this)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Pembayaran Berhasil: ' . $this->payment->transaction_number)
                    ->greeting('Halo, ' . $notifiable->name)
                    ->line('Kami telah menerima pembayaran Anda sebesar Rp ' . number_format($this->payment->total_amount, 0, ',', '.'))
                    ->line('Nomor Transaksi: ' . $this->payment->transaction_number)
                    ->line('Tanggal: ' . $this->payment->payment_date->format('d M Y H:i'))
                    ->action('Lihat Kuitansi', url('/pembayaran/' . $this->payment->id . '/kuitansi'))
                    ->line('Terima kasih!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'transaction_number' => $this->payment->transaction_number,
            'amount' => $this->payment->total_amount,
            'message' => 'Pembayaran berhasil dikonfirmasi.',
        ];
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $amount = number_format($this->payment->total_amount, 0, ',', '.');
        return "Halo {$notifiable->name}, pembayaran tagihan sebesar Rp {$amount} (No. Transaksi: {$this->payment->transaction_number}) telah berhasil kami terima. Terima kasih.";
    }
}
