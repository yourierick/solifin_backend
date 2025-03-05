<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdvertisementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index()
    {
        $advertisements = Advertisement::latest()->paginate(10);
        return view('admin.advertisements.index', compact('advertisements'));
    }

    public function create()
    {
        return view('admin.advertisements.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'url' => 'nullable|url',
            'image' => 'nullable|image|max:2048',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('advertisements', 'public');
        }

        Advertisement::create($validated);

        return redirect()->route('admin.advertisements.index')
            ->with('success', 'Publicité créée avec succès.');
    }

    public function edit(Advertisement $advertisement)
    {
        return view('admin.advertisements.edit', compact('advertisement'));
    }

    public function update(Request $request, Advertisement $advertisement)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'url' => 'nullable|url',
            'image' => 'nullable|image|max:2048',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($advertisement->image_path) {
                Storage::disk('public')->delete($advertisement->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('advertisements', 'public');
        }

        $advertisement->update($validated);

        return redirect()->route('admin.advertisements.index')
            ->with('success', 'Publicité mise à jour avec succès.');
    }

    public function destroy(Advertisement $advertisement)
    {
        if ($advertisement->image_path) {
            Storage::disk('public')->delete($advertisement->image_path);
        }

        $advertisement->delete();

        return redirect()->route('admin.advertisements.index')
            ->with('success', 'Publicité supprimée avec succès.');
    }

    public function toggle(Advertisement $advertisement)
    {
        $advertisement->update([
            'status' => !$advertisement->status
        ]);

        return redirect()->route('admin.advertisements.index')
            ->with('success', 'Statut de la publicité mis à jour avec succès.');
    }
} 