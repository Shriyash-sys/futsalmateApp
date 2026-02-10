# Booking Reminders Setup Guide

This guide explains how to set up automatic booking reminder notifications that are sent 30 minutes and 10 minutes before a match starts.

## What Was Implemented

1. **Database Migration**: Adds tracking columns to prevent duplicate reminders
2. **Laravel Console Command**: Checks for upcoming bookings and sends FCM notifications
3. **Task Scheduler**: Runs the command automatically every 5 minutes

## Setup Instructions

### Step 1: Run the Database Migration

```bash
cd /home/sameemin/futsalmateapp.sameem.in.net
php artisan migrate
```

This adds two columns to the `books` table:
- `reminder_30min_sent` - tracks if 30-min reminder was sent
- `reminder_10min_sent` - tracks if 10-min reminder was sent

### Step 2: Test the Command Manually

```bash
php artisan bookings:send-reminders
```

You should see output like:
```
Checking for upcoming bookings...
Sent 30-min reminder for booking #123
Sent 10-min reminder for booking #456
Reminders sent: 1 (30-min), 1 (10-min)
```

### Step 3: Set Up the Cron Job

The Laravel scheduler needs a cron job to run. Add this to your server's crontab:

```bash
# Edit crontab
crontab -e

# Add this line (replace path with your actual project path):
* * * * * cd /home/sameemin/futsalmateapp.sameem.in.net && php artisan schedule:run >> /dev/null 2>&1
```

This runs every minute and Laravel's scheduler will execute the reminder command every 5 minutes.

### Step 4: Verify Cron is Running

Check the Laravel logs to confirm reminders are being sent:

```bash
tail -f /home/sameemin/futsalmateapp.sameem.in.net/storage/logs/laravel.log
```

Look for entries like:
```
[2026-02-10 10:25:00] production.INFO: Reminder sent to user #5 for booking #123 (30 min)
[2026-02-10 10:45:00] production.INFO: Reminder sent to user #5 for booking #123 (10 min)
```

## How It Works

1. **Every 5 minutes**, the scheduler runs `bookings:send-reminders`
2. The command checks all **confirmed** bookings
3. For bookings starting in **30-35 minutes**: Sends 30-min reminder (if not already sent)
4. For bookings starting in **10-15 minutes**: Sends 10-min reminder (if not already sent)
5. Reminders are sent via **Firebase Cloud Messaging** to the user's device
6. The booking is marked to prevent duplicate reminders

## Reminder Windows

- **30-minute reminder**: Sent when 30-35 minutes remain (5-minute window)
- **10-minute reminder**: Sent when 10-15 minutes remain (5-minute window)

These windows ensure reminders are sent even if the cron runs slightly off-schedule.

## Notification Content

**30-minute reminder:**
```
Title: "Match Starting Soon!"
Body: "Your match at [Court Name] starts in 30 minutes. Get ready!"
```

**10-minute reminder:**
```
Title: "Match Starting Soon!"
Body: "Your match at [Court Name] starts in 10 minutes. Get ready!"
```

## Troubleshooting

### Reminders not being sent?

1. **Check cron is running:**
   ```bash
   grep CRON /var/log/syslog | tail
   ```

2. **Verify Firebase credentials are set:**
   ```bash
   php artisan config:show firebase
   ```

3. **Check user has FCM token:**
   ```sql
   SELECT id, name, fcm_token FROM users WHERE id = [user_id];
   ```

4. **Manually test the command:**
   ```bash
   php artisan bookings:send-reminders --verbose
   ```

5. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Duplicate reminders?

This shouldn't happen as the system tracks sent reminders in the database. If it does, check:
- Database columns `reminder_30min_sent` and `reminder_10min_sent` exist
- Migration was run successfully

## Testing

To test the system:

1. Create a test booking for 35 minutes from now (confirmed status)
2. Wait 5 minutes
3. Check logs - you should see the 30-min reminder sent
4. Wait another 20 minutes
5. Check logs - you should see the 10-min reminder sent
6. Check the Android app - notifications should appear in the notifications page

## Additional Notes

- Only **confirmed** bookings receive reminders
- Reminders are **not sent** for pending, rejected, or cancelled bookings
- Past bookings are automatically skipped
- The system is timezone-aware (uses server timezone)
