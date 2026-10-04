<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GuestProfile;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    /**
     * Two distinct populations, shown side by side rather than merged:
     * registered customers (real User accounts, via the existing
     * users->bookings relationship) and guest-only bookings (no account —
     * grouped by email straight from the bookings table). We never
     * synthesize a User record for a guest booking; the two stay separate
     * exactly as the rest of the app already treats them.
     */
    public function index(Request $request)
    {
        $registeredSearch = $request->string('registered_search')->toString();

        $registeredCustomers = User::query()
            ->withCount('bookings')
            ->withSum(['bookings as total_amount' => function ($q) {
                $q->where('status', '!=', Booking::STATUS_CANCELLED);
            }], 'total_price')
            ->with(['bookings' => fn ($q) => $q->latest()->limit(1)])
            ->when($registeredSearch, function ($q) use ($registeredSearch) {
                $q->where(function ($sub) use ($registeredSearch) {
                    $sub->where('name', 'like', "%{$registeredSearch}%")
                        ->orWhere('email', 'like', "%{$registeredSearch}%");
                });
            })
            ->orderByDesc('bookings_count')
            ->paginate(10, ['*'], 'registered_page')
            ->withQueryString();

        $guestSearch = $request->string('guest_search')->toString();

        $guestBookings = Booking::query()
            ->whereNull('user_id')
            ->select('email')
            ->selectRaw('MAX(customer_name) as customer_name')
            ->selectRaw('MAX(phone) as phone')
            ->selectRaw('COUNT(*) as booking_count')
            ->selectRaw('MAX(created_at) as latest_booking_at')
            ->selectRaw('SUM(CASE WHEN status != ? THEN total_price ELSE 0 END) as total_amount', [Booking::STATUS_CANCELLED])
            ->when($guestSearch, function ($q) use ($guestSearch) {
                $q->where(function ($sub) use ($guestSearch) {
                    $sub->where('email', 'like', "%{$guestSearch}%")
                        ->orWhere('customer_name', 'like', "%{$guestSearch}%");
                });
            })
            ->groupBy('email')
            ->orderByDesc('latest_booking_at')
            ->paginate(10, ['*'], 'guest_page')
            ->withQueryString();

        $profiles = GuestProfile::forEmails(
            $registeredCustomers->pluck('email')->merge($guestBookings->pluck('email'))
        );

        return view('admin.guests.index', compact('registeredCustomers', 'guestBookings', 'profiles'));
    }

    /**
     * One guest's stay history plus the staff-only VIP flag and notes,
     * identified by email (registered or not).
     */
    public function show(Request $request)
    {
        $email = GuestProfile::normalizeEmail($request->validate(['email' => ['required', 'email', 'max:255']])['email']);

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $bookings = Booking::with(['room', 'additionalRooms.room'])
            ->where(function ($q) use ($email, $user) {
                $q->whereRaw('LOWER(email) = ?', [$email]);
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->latest()
            ->get();

        abort_if(! $user && $bookings->isEmpty(), 404);

        $profile = GuestProfile::forEmail($email) ?? new GuestProfile(['email' => $email]);
        $name = $user?->name ?? $bookings->first()?->customer_name;

        return view('admin.guests.show', compact('email', 'user', 'bookings', 'profile', 'name'));
    }

    public function update(Request $request, AuditLogService $auditLog)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'is_vip' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $email = GuestProfile::normalizeEmail($validated['email']);

        $known = User::whereRaw('LOWER(email) = ?', [$email])->exists()
            || Booking::whereRaw('LOWER(email) = ?', [$email])->exists();
        abort_unless($known, 404);

        $profile = GuestProfile::firstOrNew(['email' => $email]);
        $wasVip = (bool) $profile->is_vip;
        $oldNotes = $profile->notes;

        $profile->fill([
            'is_vip' => $request->boolean('is_vip'),
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user('admin')->id,
        ])->save();

        if ($wasVip !== $profile->is_vip) {
            $auditLog->log('guest.vip_changed', 'guest_profile', $profile->id,
                ($profile->is_vip ? 'Marked' : 'Unmarked')." {$email} as VIP.", ['is_vip' => $profile->is_vip]);
        }

        // Note content is deliberately not copied into the audit trail.
        if ($oldNotes !== $profile->notes) {
            $auditLog->log('guest.notes_updated', 'guest_profile', $profile->id, "Updated staff notes for {$email}.");
        }

        return redirect()->route('admin.guests.show', ['email' => $email])->with('success', 'Guest profile saved.');
    }
}
