<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminPostController extends Controller
{
    /**
     * Afficher la liste des publications en attente de validation
     */
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $type = $request->input('type', null);
        
        $query = Post::with('user')->orderBy('created_at', 'desc');
        
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($type) {
            $query->where('type', $type);
        }
        
        $posts = $query->paginate(15);
        
        return response()->json(['posts' => $posts]);
    }
    
    /**
     * Approuver une publication
     */
    public function approve($id)
    {
        $post = Post::findOrFail($id);
        
        // Vérifier si la publication est en attente
        if ($post->status !== 'pending') {
            return response()->json(['message' => 'Cette publication n\'est pas en attente de validation'], 400);
        }
        
        $post->status = 'approved';
        $post->approved_at = now();
        $post->save();
        
        return response()->json([
            'message' => 'Publication approuvée avec succès',
            'post' => $post
        ]);
    }
    
    /**
     * Rejeter une publication
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $post = Post::findOrFail($id);
        
        // Vérifier si la publication est en attente
        if ($post->status !== 'pending') {
            return response()->json(['message' => 'Cette publication n\'est pas en attente de validation'], 400);
        }
        
        $post->status = 'rejected';
        $post->rejected_at = now();
        $post->rejection_reason = $request->rejection_reason;
        $post->save();
        
        return response()->json([
            'message' => 'Publication rejetée avec succès',
            'post' => $post
        ]);
    }
    
    /**
     * Afficher les détails d'une publication
     */
    public function show($id)
    {
        $post = Post::with(['user', 'likes', 'comments' => function($query) {
            $query->with('user')->latest();
        }])->findOrFail($id);
        
        return response()->json(['post' => $post]);
    }
    
    /**
     * Supprimer une publication (admin)
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();
        
        return response()->json(['message' => 'Publication supprimée avec succès']);
    }
}
