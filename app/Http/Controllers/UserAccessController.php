<?php

namespace App\Http\Controllers;

use App\Enums\AccessRole;
use App\Enums\TimekeeperRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserAccessController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Users', [
            'users' => User::orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'access_role' => $user->access_role->value,
                'two_factor' => $user->two_factor_confirmed_at !== null,
            ]),
            'accessRoles' => AccessRole::options(),
            'grades' => TimekeeperRole::options(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'access_role' => ['required', Rule::enum(AccessRole::class)],
            'role' => ['nullable', Rule::enum(TimekeeperRole::class)],
        ]);

        // Don't saw off the branch: the last administrator stays one.
        if (
            $user->isAdmin()
            && $data['access_role'] !== AccessRole::Admin->value
            && User::where('access_role', AccessRole::Admin)->count() === 1
        ) {
            return back()->with('error', 'There must be at least one administrator.');
        }

        $user->update($data);

        return back()->with('success', "Access updated for {$user->name}.");
    }

    /** Replace the client's ethical wall with the given user list. */
    public function syncWall(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $ids = collect($data['user_ids'] ?? [])->unique();

        $client->walls()->whereNotIn('user_id', $ids)->get()->each->delete();
        $ids->each(fn ($id) => $client->walls()->firstOrCreate(['user_id' => $id]));

        return back()->with('success', $ids->isEmpty()
            ? "Wall removed — {$client->name} is visible to everyone."
            : "Access to {$client->name} is now restricted to {$ids->count()} user(s) plus administrators.");
    }
}
