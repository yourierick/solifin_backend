<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessOpportunity;
use Illuminate\Http\Request;

class BusinessOpportunityController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request)
    {
        $query = BusinessOpportunity::latest();

        // Filtres
        if ($request->filled('sector')) {
            $query->bySector($request->sector);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $opportunities = $query->paginate(10);
        $sectors = BusinessOpportunity::SECTORS;

        return view('admin.business-opportunities.index', compact('opportunities', 'sectors'));
    }

    public function create()
    {
        $sectors = BusinessOpportunity::SECTORS;
        return view('admin.business-opportunities.create', compact('sectors'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'sector' => 'required|string|in:' . implode(',', array_keys(BusinessOpportunity::SECTORS)),
            'investment_min' => 'nullable|numeric|min:0',
            'investment_max' => 'nullable|numeric|min:0|gte:investment_min',
            'requirements' => 'required|array',
            'requirements.*' => 'required|string',
            'benefits' => 'required|array',
            'benefits.*' => 'required|string',
            'location' => 'required|string|max:255',
            'deadline' => 'nullable|date|after:today',
            'contact_email' => 'required|email',
            'contact_phone' => 'nullable|string|max:20',
            'status' => 'boolean',
        ]);

        BusinessOpportunity::create($validated);

        return redirect()->route('admin.business-opportunities.index')
            ->with('success', 'Opportunité d\'affaires créée avec succès.');
    }

    public function edit(BusinessOpportunity $businessOpportunity)
    {
        $sectors = BusinessOpportunity::SECTORS;
        return view('admin.business-opportunities.edit', compact('businessOpportunity', 'sectors'));
    }

    public function update(Request $request, BusinessOpportunity $businessOpportunity)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'sector' => 'required|string|in:' . implode(',', array_keys(BusinessOpportunity::SECTORS)),
            'investment_min' => 'nullable|numeric|min:0',
            'investment_max' => 'nullable|numeric|min:0|gte:investment_min',
            'requirements' => 'required|array',
            'requirements.*' => 'required|string',
            'benefits' => 'required|array',
            'benefits.*' => 'required|string',
            'location' => 'required|string|max:255',
            'deadline' => 'nullable|date|after:today',
            'contact_email' => 'required|email',
            'contact_phone' => 'nullable|string|max:20',
            'status' => 'boolean',
        ]);

        $businessOpportunity->update($validated);

        return redirect()->route('admin.business-opportunities.index')
            ->with('success', 'Opportunité d\'affaires mise à jour avec succès.');
    }

    public function destroy(BusinessOpportunity $businessOpportunity)
    {
        $businessOpportunity->delete();

        return redirect()->route('admin.business-opportunities.index')
            ->with('success', 'Opportunité d\'affaires supprimée avec succès.');
    }

    public function toggle(BusinessOpportunity $businessOpportunity)
    {
        $businessOpportunity->update([
            'status' => !$businessOpportunity->status
        ]);

        return redirect()->route('admin.business-opportunities.index')
            ->with('success', 'Statut de l\'opportunité d\'affaires mis à jour avec succès.');
    }
} 