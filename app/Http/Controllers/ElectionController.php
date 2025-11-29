<?php

namespace App\Http\Controllers;

use App\Models\Election;
use Illuminate\Http\Request;

class ElectionController extends Controller
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
    public function store(Request $request): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Display the specified resource.
     */
    public function show(Election $election): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Election $election): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Election $election): \Illuminate\Http\Response
    {
        return response()->noContent();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Election $election): \Illuminate\Http\Response
    {
        return response()->noContent();
    }
}
