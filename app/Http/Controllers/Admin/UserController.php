<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\Town;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::with('role')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->filter_status, function ($query, $status) {
                if ($status === 'pending') {
                    $query->where('is_allowed', false)->where('is_blocked', false);
                } elseif ($status === 'approved') {
                    $query->where('is_allowed', true);
                } elseif ($status === 'blocked') {
                    $query->where('is_blocked', true);
                }
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'filter_status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/Create', [
            'roles' => Role::all(),
            'towns' => Town::with('district')->orderBy('name')->get()->map(function ($town) {
                return [
                    'id' => $town->id,
                    'name' => $town->name,
                    'district' => $town->district?->name ?? 'غير محدد',
                ];
            }),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create($data);

        return redirect()->route('admin.users.index')
            ->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/Edit', [
            'user' => $user->load('role'),
            'roles' => Role::all(),
            'towns' => Town::with('district')->orderBy('name')->get()->map(function ($town) {
                return [
                    'id' => $town->id,
                    'name' => $town->name,
                    'district' => $town->district?->name ?? 'غير محدد',
                ];
            }),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with('success', 'تم تحديث المستخدم بنجاح.');
    }

    public function destroy(User $user): RedirectResponse
    {
        // Prevent deleting own account
        if ($user->id === Auth::id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك الخاص.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'تم حذف المستخدم بنجاح.');
    }

    public function approve(User $user): RedirectResponse
    {
        $user->update([
            'is_allowed' => true,
            'is_blocked' => false,
        ]);

        return back()->with('success', 'تم الموافقة على المستخدم.');
    }

    public function block(User $user): RedirectResponse
    {
        $user->update([
            'is_blocked' => true,
            'is_allowed' => false,
        ]);

        return back()->with('success', 'تم حظر المستخدم.');
    }

    public function unblock(User $user): RedirectResponse
    {
        $user->update([
            'is_blocked' => false,
        ]);

        return back()->with('success', 'تم إلغاء حظر المستخدم.');
    }
}
