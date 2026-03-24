<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use App\Models\Book;
use App\Models\User;
use App\Models\Court;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class VendorControllerAPI extends Controller
{
    // ----------------------------------------Vendor Dashboard----------------------------------------
    public function vendorDashboard(Request $request)
    {
        $actor = $request->user();
        if (!$actor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if (!($actor instanceof Vendor)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        try {
            $stats = $this->buildVendorDashboardStats($actor);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'vendor' => $actor,
                    'stats' => $stats,
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error('vendorDashboard failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load dashboard stats.'
            ], 500);
        }
    }

    /**
     * Aggregated metrics for the vendor home dashboard (Flutter + native).
     */
    private function buildVendorDashboardStats(Vendor $vendor): array
    {
        $now = Carbon::now(config('app.timezone'));
        $todayStr = $now->format('Y-m-d');
        $yesterdayStr = $now->copy()->subDay()->format('Y-m-d');

        $courts = Court::where('vendor_id', $vendor->id)->get();
        $courtIds = $courts->pluck('id');
        if ($courtIds->isEmpty()) {
            return $this->emptyDashboardStats();
        }

        $bookings = Book::whereIn('court_id', $courtIds)->with('court')->get();

        $activeCourts = $courts->filter(function ($c) {
            return strtolower((string) $c->status) === 'active';
        });

        $openHoursPerDay = 0.0;
        foreach ($activeCourts as $court) {
            $openHoursPerDay += $this->courtDailyOpenHours($court);
        }

        $todayBookings = $this->filterBookingsForDate($bookings, $todayStr);
        $yesterdayBookings = $this->filterBookingsForDate($bookings, $yesterdayStr);

        $todayBookingsCount = $todayBookings->count();

        $activeUsersToday = count($this->distinctCustomerKeys($todayBookings));
        $activeUsersYesterday = count($this->distinctCustomerKeys($yesterdayBookings));
        $userDelta = $activeUsersToday - $activeUsersYesterday;
        if ($userDelta > 0) {
            $activeUsersSubtext = '+' . $userDelta . ' vs yesterday';
        } elseif ($userDelta < 0) {
            $activeUsersSubtext = $userDelta . ' vs yesterday';
        } else {
            $activeUsersSubtext = 'Same as yesterday';
        }

        $occupiedNow = 0;
        foreach ($activeCourts as $court) {
            if ($this->courtOccupiedNow($court->id, $bookings, $now, $todayStr)) {
                $occupiedNow++;
            }
        }
        $activeCourtCount = $activeCourts->count();
        $courtsAvailableNow = max(0, $activeCourtCount - $occupiedNow);
        $todaySubtext = $activeCourtCount === 0
            ? 'No active courts'
            : ($courtsAvailableNow === 0
                ? 'All courts in use'
                : $courtsAvailableNow . ' court' . ($courtsAvailableNow === 1 ? '' : 's') . ' free now');

        $revenueToday = round($this->sumGrossRevenueForBookings($todayBookings), 2);
        $revenueYesterday = round($this->sumGrossRevenueForBookings($yesterdayBookings), 2);
        $revenueChangePct = $this->percentChange($revenueYesterday, $revenueToday);

        $earningsToday = round($this->sumPaidEarnings($todayBookings), 2);
        $earningsYesterday = round($this->sumPaidEarnings($yesterdayBookings), 2);
        $earningsChangePct = $this->percentChange($earningsYesterday, $earningsToday);

        $hoursToday = round($this->sumBookingHours($todayBookings), 2);
        $hoursYesterday = round($this->sumBookingHours($yesterdayBookings), 2);
        $hoursChangePct = $this->percentChange($hoursYesterday, $hoursToday);

        $todayStart = $now->copy()->startOfDay();
        // Rolling 7 days including today vs the 7 days immediately before (matches typical “this week vs last week”).
        $currWeekStartStr = $todayStart->copy()->subDays(6)->format('Y-m-d');
        $currWeekEndStr = $todayStr;
        $prevWeekStartStr = $todayStart->copy()->subDays(13)->format('Y-m-d');
        $prevWeekEndStr = $todayStart->copy()->subDays(7)->format('Y-m-d');

        $weekBooked = $this->sumBookingHoursBetweenDates($bookings, $currWeekStartStr, $currWeekEndStr);
        $prevWeekBooked = $this->sumBookingHoursBetweenDates($bookings, $prevWeekStartStr, $prevWeekEndStr);

        $weekOpen = $openHoursPerDay * 7.0;
        $weekUtil = $weekOpen > 0 ? round($weekBooked / $weekOpen * 100.0, 1) : 0.0;
        $prevUtil = $weekOpen > 0 ? round($prevWeekBooked / $weekOpen * 100.0, 1) : 0.0;
        $utilDelta = round($weekUtil - $prevUtil, 1);

        return [
            'today_bookings' => $todayBookingsCount,
            'today_bookings_subtext' => $todaySubtext,
            'active_users_today' => $activeUsersToday,
            'active_users_subtext' => $activeUsersSubtext,
            'active_courts' => $activeCourtCount,
            'courts_occupied_now' => $occupiedNow,
            'courts_available_now' => $courtsAvailableNow,
            'total_revenue_today' => $revenueToday,
            'total_earnings_today' => $earningsToday,
            'hours_booked_today' => $hoursToday,
            'revenue_change_vs_yesterday_percent' => $revenueChangePct,
            'earnings_change_vs_yesterday_percent' => $earningsChangePct,
            'hours_change_vs_yesterday_percent' => $hoursChangePct,
            'weekly_utilization_percent' => $weekUtil,
            'weekly_utilization_delta_vs_prev_week' => $utilDelta,
            'weekly_open_hours' => round($weekOpen, 2),
            'weekly_booked_hours' => round($weekBooked, 2),
        ];
    }

    private function emptyDashboardStats(): array
    {
        return [
            'today_bookings' => 0,
            'today_bookings_subtext' => 'Add a court to get started',
            'active_users_today' => 0,
            'active_users_subtext' => 'No data yet',
            'active_courts' => 0,
            'courts_occupied_now' => 0,
            'courts_available_now' => 0,
            'total_revenue_today' => 0.0,
            'total_earnings_today' => 0.0,
            'hours_booked_today' => 0.0,
            'revenue_change_vs_yesterday_percent' => null,
            'earnings_change_vs_yesterday_percent' => null,
            'hours_change_vs_yesterday_percent' => null,
            'weekly_utilization_percent' => 0.0,
            'weekly_utilization_delta_vs_prev_week' => 0.0,
            'weekly_open_hours' => 0.0,
            'weekly_booked_hours' => 0.0,
        ];
    }

    private function filterBookingsForDate($bookings, string $date)
    {
        return $bookings->filter(function (Book $b) use ($date) {
            return $b->date === $date && $this->bookingCountsForDashboard($b);
        })->values();
    }

    private function bookingCountsForDashboard(Book $b): bool
    {
        return !in_array($b->status, ['Cancelled', 'Rejected'], true);
    }

    /**
     * @return array<string, bool>
     */
    private function distinctCustomerKeys($bookings): array
    {
        $keys = [];
        foreach ($bookings as $b) {
            if (!$this->bookingCountsForDashboard($b)) {
                continue;
            }
            if ($b->user_id) {
                $keys['u' . $b->user_id] = true;
            } elseif ($b->customer_phone && trim((string) $b->customer_phone) !== '') {
                $keys['p' . preg_replace('/\D+/', '', (string) $b->customer_phone)] = true;
            } elseif ($b->customer_name && trim((string) $b->customer_name) !== '') {
                $keys['n' . mb_strtolower(trim((string) $b->customer_name))] = true;
            }
        }

        return $keys;
    }

    private function sumPaidEarnings($bookings): float
    {
        $sum = 0.0;
        foreach ($bookings as $b) {
            if (!$this->bookingCountsForDashboard($b)) {
                continue;
            }
            if (strcasecmp((string) $b->payment_status, 'Paid') !== 0) {
                continue;
            }
            $sum += $this->resolveBookingPrice($b);
        }

        return $sum;
    }

    /**
     * Gross value of bookings (slot price), including unpaid / pending.
     */
    private function sumGrossRevenueForBookings($bookings): float
    {
        $sum = 0.0;
        foreach ($bookings as $b) {
            if (!$this->bookingCountsForDashboard($b)) {
                continue;
            }
            $sum += $this->resolveBookingPrice($b);
        }

        return $sum;
    }

    private function resolveBookingPrice(Book $b): float
    {
        $raw = $b->price;
        if ($raw !== null && trim((string) $raw) !== '') {
            return (float) preg_replace('/[^0-9.]/', '', (string) $raw);
        }
        if ($b->court && $b->court->price !== null && trim((string) $b->court->price) !== '') {
            return (float) preg_replace('/[^0-9.]/', '', (string) $b->court->price);
        }

        return 0.0;
    }

    private function sumBookingHours($bookings): float
    {
        $sum = 0.0;
        foreach ($bookings as $b) {
            if (!$this->bookingCountsForDashboard($b)) {
                continue;
            }
            $sum += $this->bookingDurationHours($b->start_time, $b->end_time);
        }

        return $sum;
    }

    private function sumBookingHoursBetweenDates($bookings, string $startStr, string $endStr): float
    {
        $sum = 0.0;
        foreach ($bookings as $b) {
            if (!$this->bookingCountsForDashboard($b) || !$b->date) {
                continue;
            }
            if ($b->date < $startStr || $b->date > $endStr) {
                continue;
            }
            $sum += $this->bookingDurationHours($b->start_time, $b->end_time);
        }

        return $sum;
    }

    private function bookingDurationHours(?string $start, ?string $end): float
    {
        $s = $this->parseTimeOnBaseDate($start);
        $e = $this->parseTimeOnBaseDate($end);
        if (!$s || !$e) {
            return 0.0;
        }
        if ($e->lessThanOrEqualTo($s)) {
            return 0.0;
        }

        return $s->diffInMinutes($e) / 60.0;
    }

    private function parseTimeOnBaseDate(?string $time): ?Carbon
    {
        if ($time === null || trim($time) === '') {
            return null;
        }
        $t = trim($time);
        if (strlen($t) >= 8 && preg_match('/^\d{2}:\d{2}:\d{2}/', $t)) {
            $t = substr($t, 0, 8);
        }
        $base = '2000-01-01 ';
        try {
            return Carbon::parse($base . $t, config('app.timezone'));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function courtDailyOpenHours(Court $court): float
    {
        $open = $court->opening_time;
        $close = $court->closing_time;
        if ($open === null || $close === null || trim((string) $open) === '' || trim((string) $close) === '') {
            return 16.0;
        }
        try {
            $o = Carbon::parse(trim((string) $open), config('app.timezone'));
            $c = Carbon::parse(trim((string) $close), config('app.timezone'));
        } catch (Throwable $e) {
            return 16.0;
        }
        $oMin = $o->hour * 60 + $o->minute;
        $cMin = $c->hour * 60 + $c->minute;
        if ($cMin <= $oMin) {
            $cMin += 24 * 60;
        }

        return ($cMin - $oMin) / 60.0;
    }

    private function courtOccupiedNow(int $courtId, $bookings, Carbon $now, string $todayStr): bool
    {
        foreach ($bookings as $b) {
            if ((int) $b->court_id !== $courtId) {
                continue;
            }
            if ($b->date !== $todayStr || $b->status !== 'Confirmed') {
                continue;
            }
            $start = $this->combineDateAndTime($b->date, $b->start_time);
            $end = $this->combineDateAndTime($b->date, $b->end_time);
            if ($start && $end && $now->greaterThanOrEqualTo($start) && $now->lessThanOrEqualTo($end)) {
                return true;
            }
        }

        return false;
    }

    private function combineDateAndTime(?string $date, ?string $time): ?Carbon
    {
        if (!$date || !$time || trim($time) === '') {
            return null;
        }
        $t = trim($time);
        if (strlen($t) >= 8 && preg_match('/^\d{2}:\d{2}:\d{2}/', $t)) {
            $t = substr($t, 0, 8);
        }
        try {
            return Carbon::parse($date . ' ' . $t, config('app.timezone'));
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @return float|null null when comparison is not meaningful
     */
    private function percentChange(float $baseline, float $current): ?float
    {
        if ($baseline > 0.0) {
            return round(($current - $baseline) / $baseline * 100.0, 1);
        }
        if ($current > 0.0) {
            return 100.0;
        }

        return null;
    }

    /**
     * Vendor: view all their courts
     */
    public function viewVendorCourts(Request $request)
    {
        $actor = $request->user();
        Log::info('viewVendorCourts called', ['actor' => $actor?->id]);

        if (!($actor instanceof Vendor)) {
            Log::warning('viewVendorCourts: unauthorized actor', ['actor' => $actor?->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        try {
            $courts = Court::where('vendor_id', $actor->id)
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('viewVendorCourts: courts retrieved', ['vendor_id' => $actor->id, 'count' => $courts->count()]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'courts' => $courts,
                    'total_courts' => $courts->count()
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error('viewVendorCourts failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve courts. See server logs for details.'
            ], 500);
        }
    }

    /**
     * Vendor: view all users who have booked from this vendor
     */
    public function viewVendorCustomers(Request $request)
    {
        $actor = $request->user();
        Log::info('viewVendorCustomers called', ['actor' => $actor?->id]);

        if (!($actor instanceof Vendor)) {
            Log::warning('viewVendorCustomers: unauthorized actor', ['actor' => $actor?->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        try {
            // Get all courts for this vendor
            $courtIds = Court::where('vendor_id', $actor->id)->pluck('id');

            // Get all unique users who have booked these courts
            $userIds = Book::whereIn('court_id', $courtIds)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            // Get user details with their booking statistics
            $customers = User::whereIn('id', $userIds)->get()->map(function ($user) use ($courtIds) {
                $userBookings = Book::whereIn('court_id', $courtIds)
                    ->where('user_id', $user->id)
                    ->get();

                $totalBookings = $userBookings->count();
                $confirmedBookings = $userBookings->where('status', 'Confirmed')->count();
                $totalSpent = $userBookings->where('payment_status', 'Paid')->sum('price');
                $lastBookingDate = $userBookings->max('date');

                $fallbackPhone = $userBookings->pluck('customer_phone')
                    ->filter(fn ($p) => $p !== null && trim((string) $p) !== '')
                    ->first();

                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone ?: $fallbackPhone,
                    'profile_photo' => $user->profile_photo_url,
                    'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->format('c') : null,
                    'statistics' => [
                        'total_bookings' => $totalBookings,
                        'confirmed_bookings' => $confirmedBookings,
                        'total_spent' => number_format($totalSpent, 2),
                        'last_booking_date' => $lastBookingDate
                    ]
                ];
            });

            Log::info('viewVendorCustomers: customers retrieved', ['vendor_id' => $actor->id, 'count' => $customers->count()]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'customers' => $customers,
                    'total_customers' => $customers->count()
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error('viewVendorCustomers failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customers. See server logs for details.'
            ], 500);
        }
    }

    public function vendorAddCourt(Request $request)
    {
        $validated = $request->validate([
            'court_name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'images' => 'nullable|array|max:8',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string|max:100',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:active,inactive',
            'opening_time' => 'nullable|date_format:g A',
            'closing_time' => 'nullable|date_format:g A',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ]);

        $actor = $request->user();
        Log::info('vendorAddCourt called', ['actor' => $actor?->id, 'actor_class' => $actor ? get_class($actor) : null]);

        if (!($actor instanceof Vendor)) {
            Log::warning('vendorAddCourt: unauthorized actor', ['actor' => $actor?->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        // Handle optional image uploads (up to 8)
        $imageUrls = [];
        try {
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $imagePath = $imageFile->store('images', 'public');
                    $imageUrls[] = Storage::url($imagePath);
                    Log::info('vendorAddCourt: image stored', ['path' => $imagePath, 'url' => end($imageUrls)]);
                }
            }

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images', 'public');
                $imageUrls[] = Storage::url($imagePath);
                Log::info('vendorAddCourt: image stored', ['path' => $imagePath, 'url' => end($imageUrls)]);
            }

            Log::info('vendorAddCourt: validated payload', ['validated' => $validated]);

            // Convert 12-hour AM/PM format to 24-hour format for database storage
            if (isset($validated['opening_time']) && $validated['opening_time'] !== '') {
                try {
                    $validated['opening_time'] = \Carbon\Carbon::createFromFormat('g A', $validated['opening_time'])->format('H:00:00');
                } catch (\Exception $e) {
                    $validated['opening_time'] = null;
                }
            } else {
                $validated['opening_time'] = null;
            }
            
            if (isset($validated['closing_time']) && $validated['closing_time'] !== '') {
                try {
                    $validated['closing_time'] = \Carbon\Carbon::createFromFormat('g A', $validated['closing_time'])->format('H:00:00');
                } catch (\Exception $e) {
                    $validated['closing_time'] = null;
                }
            } else {
                $validated['closing_time'] = null;
            }

            $court = Court::create([
                'court_name' => $validated['court_name'],
                'location' => $validated['location'],
                'price' => $validated['price'],
                'image' => count($imageUrls) > 0 ? json_encode($imageUrls) : null,
                'facilities' => $validated['facilities'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'inactive',
                'opening_time' => $validated['opening_time'] ?? null,
                'closing_time' => $validated['closing_time'] ?? null,
                'latitude' => isset($validated['latitude']) ? $validated['latitude'] : null,
                'longitude' => isset($validated['longitude']) ? $validated['longitude'] : null,
                'vendor_id' => $actor->id
            ]);

            Log::info('vendorAddCourt: court created', ['court_id' => $court->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Court added successfully.',
                'court' => $court
            ], 201);
        } catch (Throwable $e) {
            Log::error('vendorAddCourt failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add court. See server logs for details.'
            ], 500);
        }
    }

    /**
     * Vendor: edit a court
     */
    public function vendorEditCourt(Request $request, $courtId)
    {
        $validated = $request->validate([
            'court_name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'images' => 'nullable|array|max:8',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string|max:100',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:active,inactive',
            'opening_time' => 'nullable|date_format:g A',
            'closing_time' => 'nullable|date_format:g A',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ]);

        $actor = $request->user();

        if (!$actor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated. Please login first.',
                'debug' => [
                    'has_token' => !empty($request->bearerToken()),
                    'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : null
                ]
            ], 401);
        }

        if (!($actor instanceof Vendor)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.',
                'debug' => [
                    'actor_class' => get_class($actor),
                    'actor_id' => $actor->id
                ]
            ], 403);
        }

        try {
            $court = Court::find($courtId);

            if (!$court) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Court not found.',
                    'debug' => [
                        'requested_court_id' => $courtId,
                        'available_courts' => Court::pluck('id')->toArray()
                    ]
                ], 404);
            }

            if ((int) $court->vendor_id !== (int) $actor->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to edit this court.',
                    'debug' => [
                        'your_vendor_id' => (int) $actor->id,
                        'court_owner_vendor_id' => (int) $court->vendor_id,
                        'court_name' => $court->court_name,
                        'match_check' => [
                            'strict' => ($court->vendor_id === $actor->id),
                            'loose' => ($court->vendor_id == $actor->id),
                            'int_cast' => ((int) $court->vendor_id === (int) $actor->id)
                        ]
                    ]
                ], 403);
            }

            /** @var Court $court */
            // Handle optional image uploads (up to 8)
            $imageUrls = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $imagePath = $imageFile->store('images', 'public');
                    $imageUrls[] = Storage::url($imagePath);
                    Log::info('vendorEditCourt: image stored', ['path' => $imagePath, 'url' => end($imageUrls)]);
                }
            }

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images', 'public');
                $imageUrls[] = Storage::url($imagePath);
                Log::info('vendorEditCourt: image stored', ['path' => $imagePath, 'url' => end($imageUrls)]);
            }

            // Only update image field if new images were uploaded
            if (count($imageUrls) > 0) {
                $validated['image'] = json_encode($imageUrls);
            } else {
                // Remove image from validated data to preserve existing images
                unset($validated['image']);
            }

            // Only update facilities if provided
            if (!isset($validated['facilities']) || $validated['facilities'] === null) {
                unset($validated['facilities']);
            }

            // Only update status if provided
            if (!isset($validated['status']) || $validated['status'] === null) {
                unset($validated['status']);
            }

            // Only update coordinates if provided
            if (!isset($validated['latitude']) || $validated['latitude'] === null) {
                unset($validated['latitude']);
            }
            if (!isset($validated['longitude']) || $validated['longitude'] === null) {
                unset($validated['longitude']);
            }

            // Convert 12-hour AM/PM format to 24-hour format for database storage
            if (isset($validated['opening_time']) && $validated['opening_time'] !== '' && $validated['opening_time'] !== null) {
                try {
                    $validated['opening_time'] = \Carbon\Carbon::createFromFormat('g A', $validated['opening_time'])->format('H:00:00');
                } catch (\Exception $e) {
                    unset($validated['opening_time']);
                }
            } else {
                unset($validated['opening_time']);
            }
            
            if (isset($validated['closing_time']) && $validated['closing_time'] !== '' && $validated['closing_time'] !== null) {
                try {
                    $validated['closing_time'] = \Carbon\Carbon::createFromFormat('g A', $validated['closing_time'])->format('H:00:00');
                } catch (\Exception $e) {
                    unset($validated['closing_time']);
                }
            } else {
                unset($validated['closing_time']);
            }

            $court->update($validated);
            Log::info('vendorEditCourt: court updated', ['court_id' => $court->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Court updated successfully.',
                'court' => $court
            ], 200);
        } catch (Throwable $e) {
            Log::error('vendorEditCourt failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to edit court. See server logs for details.'
            ], 500);
        }
    }

    /**
     * Vendor: delete a court
     */
    public function vendorDeleteCourt(Request $request, $courtId)
    {
        $actor = $request->user();

        if (!$actor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated. Please login first.'
            ], 401);
        }

        if (!($actor instanceof Vendor)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.',
                'debug' => [
                    'actor_class' => get_class($actor),
                    'actor_id' => $actor->id
                ]
            ], 403);
        }

        try {
            $court = Court::find($courtId);

            if (!$court) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Court not found.',
                    'debug' => [
                        'requested_court_id' => $courtId
                    ]
                ], 404);
            }

            if ((int) $court->vendor_id !== (int) $actor->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to delete this court.',
                    'debug' => [
                        'your_vendor_id' => (int) $actor->id,
                        'court_owner_vendor_id' => (int) $court->vendor_id,
                        'court_name' => $court->court_name
                    ]
                ], 403);
            }

            /** @var Court $court */
            $court->delete();
            Log::info('vendorDeleteCourt: court deleted', ['court_id' => $courtId]);

            return response()->json([
                'status' => 'success',
                'message' => 'Court deleted successfully.'
            ], 200);
        } catch (Throwable $e) {
            Log::error('vendorDeleteCourt failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete court. See server logs for details.'
            ], 500);
        }
    }
}
