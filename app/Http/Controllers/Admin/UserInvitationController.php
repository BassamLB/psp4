<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserInvitationRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserInvitationController extends Controller
{
    public function index(): \Inertia\Response
    {
        $pendingInvitations = User::whereNotNull('registration_code')
            ->whereNull('password')
            ->with('role')
            ->latest()
            ->get();

        return Inertia::render('admin/invitations/Index', [
            'invitations' => $pendingInvitations,
        ]);
    }

    public function create(): \Inertia\Response
    {
        return Inertia::render('admin/invitations/Create', [
            'roles' => Role::all(),
        ]);
    }

    public function store(StoreUserInvitationRequest $request): \Illuminate\Http\RedirectResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'registration_code' => Str::random(32),
            'is_active' => false,
            'is_allowed' => false,
        ]);

        // TODO: Send invitation email to user with registration code

        return redirect()->route('admin.invitations.index')->with('success', 'تم إرسال الدعوة بنجاح.');
    }
}
