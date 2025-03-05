<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request)
    {
        $query = Testimonial::with('user')->latest();

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('min_rating')) {
            $query->minRating($request->min_rating);
        }

        $testimonials = $query->paginate(10);

        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function show(Testimonial $testimonial)
    {
        $testimonial->load('user');
        return view('admin.testimonials.show', compact('testimonial'));
    }

    public function approve(Testimonial $testimonial)
    {
        $testimonial->update([
            'status' => true
        ]);

        return redirect()->route('admin.testimonials.index')
            ->with('success', 'Témoignage approuvé avec succès.');
    }

    public function reject(Testimonial $testimonial)
    {
        $testimonial->delete();

        return redirect()->route('admin.testimonials.index')
            ->with('success', 'Témoignage rejeté et supprimé avec succès.');
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return redirect()->route('admin.testimonials.index')
            ->with('success', 'Témoignage supprimé avec succès.');
    }
} 