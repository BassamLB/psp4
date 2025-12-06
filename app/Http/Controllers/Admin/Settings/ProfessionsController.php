<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Profession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProfessionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index(Request $request): InertiaResponse
    {
        $items = Profession::orderBy('name')->paginate(20);

        return Inertia::render('admin/Settings/Professions', ['items' => $items]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.settings.professions.index', ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:professions,name']);
        Profession::create($data);

        return redirect()->route('admin.settings.professions.index')->with('success', 'تم إنشاء المهنة بنجاح.');
    }

    public function edit(Profession $profession): RedirectResponse
    {
        return redirect()->route('admin.settings.professions.index', ['edit' => $profession->id]);
    }

    public function update(Request $request, Profession $profession): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:professions,name,'.$profession->id]);
        $profession->update($data);

        return redirect()->route('admin.settings.professions.index')->with('success', 'تم تعديل المهنة بنجاح.');
    }

    public function destroy(Profession $profession): RedirectResponse
    {
        $profession->delete();

        return redirect()->route('admin.settings.professions.index')->with('success', 'تم حذف المهنة بنجاح.');
    }
}
