<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Book;
use Illuminate\Console\Command;

class ExpirePendingEsewaBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:expire-esewa-holds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel expired pending eSewa bookings (release reserved slots)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        $expiredCount = Book::query()
            ->where('payment', 'eSewa')
            ->where('status', 'Pending')
            ->where('payment_status', 'Pending')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', $now)
            ->update([
                'payment_status' => 'Failed',
                'status' => 'Cancelled',
                'payment_expires_at' => null,
            ]);

        $this->info("Expired eSewa holds cancelled: {$expiredCount}");

        return self::SUCCESS;
    }
}

