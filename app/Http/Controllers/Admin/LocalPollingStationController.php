<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePollingStationRequest;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Town;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocalPollingStationController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $stations = PollingStation::with('town')->orderBy('id', 'desc')->paginate(20);

        $towns = Town::orderBy('name')->get();
        $elections = Election::orderBy('id', 'desc')->get();

        return Inertia::render('admin/LocalPollingStations/Index', [
            'stations' => $stations,
            'towns' => $towns,
            'elections' => $elections,
        ]);
    }

    public function create(): \Inertia\Response
    {
        $towns = Town::orderBy('name')->get();
        $elections = Election::orderBy('id', 'desc')->get();

        return Inertia::render('admin/LocalPollingStations/Create', [
            'towns' => $towns,
            'elections' => $elections,
        ]);
    }

    public function store(StorePollingStationRequest $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();

        PollingStation::create($data);

        return redirect()->route('admin.local-polling-stations.index')->with('success', 'Polling station created.');
    }

    public function edit(PollingStation $local_polling_station): \Inertia\Response
    {
        $station = $local_polling_station->load('town');
        $towns = Town::orderBy('name')->get();
        $elections = Election::orderBy('id', 'desc')->get();

        return Inertia::render('admin/LocalPollingStations/Edit', [
            'station' => $station,
            'towns' => $towns,
            'elections' => $elections,
        ]);
    }

    public function update(StorePollingStationRequest $request, PollingStation $local_polling_station): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();

        $local_polling_station->update($data);

        return redirect()->route('admin.local-polling-stations.index')->with('success', 'Polling station updated.');
    }

    public function destroy(PollingStation $local_polling_station): \Illuminate\Http\RedirectResponse
    {
        $local_polling_station->delete();

        return redirect()->route('admin.local-polling-stations.index')->with('success', 'Polling station deleted.');
    }
}
