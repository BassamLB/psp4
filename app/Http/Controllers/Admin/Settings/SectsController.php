<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Sect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SectsController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index(Request $request): InertiaResponse
    {
        $items = Sect::orderBy('name')->paginate(20);

        return Inertia::render('admin/Settings/Sects', ['items' => $items]);
    }

    public function create(): RedirectResponse
    {
        // Redirect to the index and open the create modal via query param
        return redirect()->route('admin.settings.sects.index', ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:sects,name']);
        Sect::create($data);

        return redirect()->route('admin.settings.sects.index')->with('success', 'تم إنشاء الطائفة بنجاح.');
    }

    public function edit(Sect $sect): RedirectResponse
    {
        // Redirect to index and open the edit modal for the given id
        return redirect()->route('admin.settings.sects.index', ['edit' => $sect->id]);
    }

    public function update(Request $request, Sect $sect): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:sects,name,'.$sect->id]);
        $sect->update($data);

        return redirect()->route('admin.settings.sects.index')->with('success', 'تم تعديل الطائفة بنجاح.');
    }

    public function destroy(Sect $sect): RedirectResponse
    {
        $sect->delete();

        return redirect()->route('admin.settings.sects.index')->with('success', 'تم حذف الطائفة بنجاح.');
    }
}
