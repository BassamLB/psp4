<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role): \Illuminate\Http\Response
    {
        return response()->noContent();
    }
}
