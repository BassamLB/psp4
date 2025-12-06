<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RolesController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index(Request $request): InertiaResponse
    {
        $roles = Role::orderBy('name')->paginate(20);

        return Inertia::render('admin/Settings/Roles', ['roles' => $roles]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.settings.roles.index', ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:roles,name']);
        Role::create($data);

        return redirect()->route('admin.settings.roles.index')->with('success', 'تم إنشاء الدور بنجاح.');
    }

    public function edit(Role $role): RedirectResponse
    {
        return redirect()->route('admin.settings.roles.index', ['edit' => $role->id]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:roles,name,'.$role->id]);
        $role->update($data);

        return redirect()->route('admin.settings.roles.index')->with('success', 'تم تعديل الدور بنجاح.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        return redirect()->route('admin.settings.roles.index')->with('success', 'تم حذف الدور بنجاح.');
    }
}
