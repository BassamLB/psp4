<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExternalPollingStationRequest;
use App\Models\City;
use App\Models\ElectoralDistrict;
use App\Models\ExternalPollingStation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PollingStationController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $stations = ExternalPollingStation::with(['city', 'electoralDistricts'])->orderBy('id', 'desc')->paginate(20);
        $cities = City::orderBy('name')->get();
        $districts = ElectoralDistrict::orderBy('name')->get();

        return Inertia::render('Admin/PollingStations/Index', [
            'stations' => $stations,
            'cities' => $cities,
            'districts' => $districts,
        ]);
    }

    public function create(): \Inertia\Response
    {
        $cities = City::orderBy('name')->get();
        $districts = ElectoralDistrict::orderBy('name')->get();

        return Inertia::render('Admin/PollingStations/Create', [
            'cities' => $cities,
            'districts' => $districts,
        ]);
    }

    public function store(StoreExternalPollingStationRequest $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();

        $station = ExternalPollingStation::create($data);

        if (isset($data['electoral_districts'])) {
            $station->electoralDistricts()->sync($data['electoral_districts']);
        }

        return redirect()->route('admin.polling-stations.index')->with('success', 'Polling station created.');
    }

    public function edit(ExternalPollingStation $polling_station): \Inertia\Response
    {
        $station = $polling_station->load(['city', 'electoralDistricts']);
        $cities = City::orderBy('name')->get();
        $districts = ElectoralDistrict::orderBy('name')->get();

        return Inertia::render('Admin/PollingStations/Edit', [
            'station' => $station,
            'cities' => $cities,
            'districts' => $districts,
        ]);
    }

    public function update(StoreExternalPollingStationRequest $request, ExternalPollingStation $polling_station): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();

        $polling_station->update($data);

        $polling_station->electoralDistricts()->sync($data['electoral_districts'] ?? []);

        return redirect()->route('admin.polling-stations.index')->with('success', 'Polling station updated.');
    }

    public function destroy(ExternalPollingStation $polling_station): \Illuminate\Http\RedirectResponse
    {
        $polling_station->electoralDistricts()->detach();
        $polling_station->delete();

        return redirect()->route('admin.polling-stations.index')->with('success', 'Polling station deleted.');
    }
}
