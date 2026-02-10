<?php

namespace App\Console\Commands;

use Exception;
use Carbon\Carbon;
use App\Models\Book;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications 30 and 10 minutes before bookings start';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for upcoming bookings...');

        $now = Carbon::now();
        
        // Get all confirmed bookings that haven't started yet
        $upcomingBookings = Book::where('status', 'confirmed')
            ->where('date', '>=', $now->toDateString())
            ->get();

        $reminders30Sent = 0;
        $reminders10Sent = 0;

        foreach ($upcomingBookings as $booking) {
            try {
                // Combine date and time to get the booking start datetime
                $bookingDateTime = Carbon::parse($booking->date . ' ' . $booking->time);
                
                // Calculate minutes until booking starts
                $minutesUntilStart = $now->diffInMinutes($bookingDateTime, false);

                // Skip if booking has already started
                if ($minutesUntilStart < 0) {
                    continue;
                }

                // Send 30-minute reminder (if between 30 and 35 minutes before start)
                if (!$booking->reminder_30min_sent && $minutesUntilStart >= 30 && $minutesUntilStart <= 35) {
                    $this->sendReminder($booking, 30);
                    $booking->reminder_30min_sent = true;
                    $booking->save();
                    $reminders30Sent++;
                    $this->info("Sent 30-min reminder for booking #{$booking->id}");
                }

                // Send 10-minute reminder (if between 10 and 15 minutes before start)
                if (!$booking->reminder_10min_sent && $minutesUntilStart >= 10 && $minutesUntilStart <= 15) {
                    $this->sendReminder($booking, 10);
                    $booking->reminder_10min_sent = true;
                    $booking->save();
                    $reminders10Sent++;
                    $this->info("Sent 10-min reminder for booking #{$booking->id}");
                }
            } catch (Exception $e) {
                Log::error("Error processing booking reminder #{$booking->id}: " . $e->getMessage());
                $this->error("Error processing booking #{$booking->id}: " . $e->getMessage());
            }
        }

        $this->info("Reminders sent: {$reminders30Sent} (30-min), {$reminders10Sent} (10-min)");
        
        return 0;
    }

    /**
     * Send reminder notification to user
     */
    private function sendReminder(Book $booking, int $minutes)
    {
        try {
            $user = User::find($booking->user_id);
            if (!$user) {
                Log::warning("User not found for booking #{$booking->id}");
                return;
            }

            if (!$user->fcm_token) {
                Log::warning("No FCM token for user #{$user->id}");
                return;
            }

            // Load court relationship
            $court = $booking->court;
            $courtName = $court ? $court->court_name : 'your court';

            $title = "Match Starting Soon!";
            $body = "Your match at {$courtName} starts in {$minutes} minutes. Get ready!";

            $messaging = app(Messaging::class);
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(
                    Notification::create($title, $body)
                )
                ->withData([
                    'type' => 'booking_reminder',
                    'booking_id' => (string) $booking->id,
                    'minutes' => (string) $minutes,
                    'court_name' => $courtName,
                    'time' => $booking->time,
                    'date' => $booking->date,
                ]);

            $messaging->send($message);
            Log::info("Reminder sent to user #{$user->id} for booking #{$booking->id} ({$minutes} min)");
        } catch (Exception $e) {
            Log::error("Failed to send reminder for booking #{$booking->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
