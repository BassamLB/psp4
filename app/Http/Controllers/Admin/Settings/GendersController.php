<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Gender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class GendersController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index(Request $request): InertiaResponse
    {
        $items = Gender::orderBy('name')->paginate(20);

        return Inertia::render('admin/Settings/Genders', ['items' => $items]);
    }

    public function create(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.settings.genders.index', ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:genders,name']);
        Gender::create($data);

        return redirect()->route('admin.settings.genders.index')->with('success', 'تم إنشاء الجنس بنجاح.');
    }

    public function edit(Gender $gender): RedirectResponse
    {
        return redirect()->route('admin.settings.genders.index', ['edit' => $gender->id]);
    }

    public function update(Request $request, Gender $gender): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:genders,name,'.$gender->id]);
        $gender->update($data);

        return redirect()->route('admin.settings.genders.index')->with('success', 'تم تعديل الجنس بنجاح.');
    }

    public function destroy(Gender $gender): RedirectResponse
    {
        $gender->delete();

        return redirect()->route('admin.settings.genders.index')->with('success', 'تم حذف الجنس بنجاح.');
    }
}
