<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Belong;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class BelongsController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index(Request $request): InertiaResponse
    {
        $items = Belong::orderBy('name')->paginate(20);

        return Inertia::render('admin/Settings/Belongs', ['items' => $items]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.settings.belongs.index', ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:belongs,name']);
        Belong::create($data);

        return redirect()->route('admin.settings.belongs.index')->with('success', 'تم إنشاء الانتماء بنجاح.');
    }

    public function edit(Belong $belong): RedirectResponse
    {
        return redirect()->route('admin.settings.belongs.index', ['edit' => $belong->id]);
    }

    public function update(Request $request, Belong $belong): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:belongs,name,'.$belong->id]);
        $belong->update($data);

        return redirect()->route('admin.settings.belongs.index')->with('success', 'تم تعديل الانتماء بنجاح.');
    }

    public function destroy(Belong $belong): RedirectResponse
    {
        $belong->delete();

        return redirect()->route('admin.settings.belongs.index')->with('success', 'تم حذف الانتماء بنجاح.');
    }
}
