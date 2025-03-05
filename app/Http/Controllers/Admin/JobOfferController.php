<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Http\Request;

class JobOfferController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request)
    {
        $query = JobOffer::latest();

        // Filtres
        if ($request->filled('contract_type')) {
            $query->byContractType($request->contract_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $jobOffers = $query->paginate(10);
        $contractTypes = JobOffer::CONTRACT_TYPES;

        return view('admin.job-offers.index', compact('jobOffers', 'contractTypes'));
    }

    public function create()
    {
        $contractTypes = JobOffer::CONTRACT_TYPES;
        return view('admin.job-offers.create', compact('contractTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'company' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'contract_type' => 'required|string|in:' . implode(',', array_keys(JobOffer::CONTRACT_TYPES)),
            'salary_min' => 'nullable|numeric|min:0',
            'salary_max' => 'nullable|numeric|min:0|gte:salary_min',
            'requirements' => 'required|array',
            'requirements.*' => 'required|string',
            'deadline' => 'nullable|date|after:today',
            'contact_email' => 'required|email',
            'status' => 'boolean',
        ]);

        JobOffer::create($validated);

        return redirect()->route('admin.job-offers.index')
            ->with('success', 'Offre d\'emploi créée avec succès.');
    }

    public function edit(JobOffer $jobOffer)
    {
        $contractTypes = JobOffer::CONTRACT_TYPES;
        return view('admin.job-offers.edit', compact('jobOffer', 'contractTypes'));
    }

    public function update(Request $request, JobOffer $jobOffer)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'company' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'contract_type' => 'required|string|in:' . implode(',', array_keys(JobOffer::CONTRACT_TYPES)),
            'salary_min' => 'nullable|numeric|min:0',
            'salary_max' => 'nullable|numeric|min:0|gte:salary_min',
            'requirements' => 'required|array',
            'requirements.*' => 'required|string',
            'deadline' => 'nullable|date|after:today',
            'contact_email' => 'required|email',
            'status' => 'boolean',
        ]);

        $jobOffer->update($validated);

        return redirect()->route('admin.job-offers.index')
            ->with('success', 'Offre d\'emploi mise à jour avec succès.');
    }

    public function destroy(JobOffer $jobOffer)
    {
        $jobOffer->delete();

        return redirect()->route('admin.job-offers.index')
            ->with('success', 'Offre d\'emploi supprimée avec succès.');
    }

    public function toggle(JobOffer $jobOffer)
    {
        $jobOffer->update([
            'status' => !$jobOffer->status
        ]);

        return redirect()->route('admin.job-offers.index')
            ->with('success', 'Statut de l\'offre d\'emploi mis à jour avec succès.');
    }
} 