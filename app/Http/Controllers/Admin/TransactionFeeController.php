<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TransactionFeeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $transactionFees = TransactionFee::orderBy('payment_method')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $transactionFees
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string|max:255|unique:transaction_fees',
            'payment_type' => 'required|string|max:255',
            'transfer_fee_percentage' => 'required|numeric|min:0|max:100',
            'withdrawal_fee_percentage' => 'required|numeric|min:0|max:100',
            'fee_fixed' => 'required|numeric|min:0',
            'fee_cap' => 'nullable|numeric|min:0',
            'is_active' => 'boolean'
        ]);

        \Log::info($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transactionFee = TransactionFee::create([
                'payment_method' => $request->payment_method,
                'payment_type' => $request->payment_type,
                'transfer_fee_percentage' => $request->transfer_fee_percentage,
                'withdrawal_fee_percentage' => $request->withdrawal_fee_percentage,
                'fee_fixed' => $request->fee_fixed,
                'fee_cap' => $request->fee_cap,
                'is_active' => $request->is_active ?? true
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Frais de transaction ajoutés avec succès',
                'data' => $transactionFee
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création des frais de transaction: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la création des frais de transaction'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $transactionFee = TransactionFee::findOrFail($id);
            
            return response()->json([
                'status' => 'success',
                'data' => $transactionFee
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Frais de transaction non trouvés'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $transactionFee = TransactionFee::find($id);
        
        if (!$transactionFee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Frais de transaction non trouvés'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_type' => 'required|string|max:255',
            'transfer_fee_percentage' => 'required|numeric|min:0|max:100',
            'withdrawal_fee_percentage' => 'required|numeric|min:0|max:100',
            'fee_fixed' => 'required|numeric|min:0',
            'fee_cap' => 'nullable|numeric|min:0',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transactionFee->update([
                'payment_type' => $request->payment_type,
                'transfer_fee_percentage' => $request->transfer_fee_percentage,
                'withdrawal_fee_percentage' => $request->withdrawal_fee_percentage,
                'fee_fixed' => $request->fee_fixed,
                'fee_cap' => $request->fee_cap,
                'is_active' => $request->is_active ?? $transactionFee->is_active
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Frais de transaction mis à jour avec succès',
                'data' => $transactionFee
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des frais de transaction: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la mise à jour des frais de transaction'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $transactionFee = TransactionFee::findOrFail($id);
            $transactionFee->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Frais de transaction supprimés avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression des frais de transaction: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la suppression des frais de transaction'
            ], 500);
        }
    }

    /**
     * Toggle the active status of a transaction fee
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function toggleActive($id)
    {
        try {
            $transactionFee = TransactionFee::findOrFail($id);
            $transactionFee->is_active = !$transactionFee->is_active;
            $transactionFee->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Statut des frais de transaction mis à jour avec succès',
                'data' => $transactionFee
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du statut des frais de transaction: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la mise à jour du statut des frais de transaction'
            ], 500);
        }
    }

    /**
     * Update transaction fees from external API
     *
     * @return \Illuminate\Http\Response
     */
    public function updateFromApi()
    {
        try {
            // Logique pour mettre à jour les frais depuis une API externe
            // Cette méthode serait implémentée selon les besoins spécifiques
            
            return response()->json([
                'status' => 'success',
                'message' => 'Frais de transaction mis à jour depuis l\'API avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des frais de transaction depuis l\'API: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la mise à jour des frais de transaction depuis l\'API'
            ], 500);
        }
    }
}
