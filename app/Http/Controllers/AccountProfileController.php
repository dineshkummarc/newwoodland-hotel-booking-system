<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\GuestProfile;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AccountProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    /**
     * Name and email only. Past bookings keep the contact details they were
     * made with (they are historical records sent to that address).
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $oldEmail = $user->email;

        $user->fill($request->safe()->only(['name', 'email']));
        $emailChanged = $user->isDirty('email');
        $user->save();

        if ($emailChanged) {
            // Any pending reset link was issued to the old address.
            DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();

            // Staff notes / VIP flag follow the customer to their new address,
            // unless staff already keep a separate profile for it.
            $old = GuestProfile::forEmail($oldEmail);
            if ($old && ! GuestProfile::forEmail($user->email)) {
                $old->update(['email' => GuestProfile::normalizeEmail($user->email)]);
            }
        }

        return redirect()->route('account.profile')->with('success', 'Your profile has been updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            // Invalidates "remember me" cookies on every other device.
            'remember_token' => Str::random(60),
        ])->save();

        // Any outstanding email reset link is no longer needed.
        Password::broker()->getRepository()->delete($user);

        // Sign out the account's other sessions (database session driver).
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $request->session()->regenerate();

        event(new PasswordReset($user));

        return redirect()->route('account.profile')->with('success', 'Your password has been changed.');
    }
}
