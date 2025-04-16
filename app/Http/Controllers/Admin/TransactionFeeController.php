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
            'provider' => 'required|string|max:255',
            'transfer_fee_percentage' => 'required|numeric|min:0|max:100',
            'withdrawal_fee_percentage' => 'required|numeric|min:0|max:100',
            'purchase_fee_percentage' => 'required|numeric|min:0|max:100',
            'min_fee_amount' => 'required|numeric|min:0',
            'max_fee_amount' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:3',
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
            $transactionFee = TransactionFee::create($request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Frais de transaction créés avec succès',
                'data' => $transactionFee
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création des frais de transaction', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
        $validator = Validator::make($request->all(), [
            'payment_method' => 'string|max:255|unique:transaction_fees,payment_method,' . $id,
            'provider' => 'string|max:255',
            'transfer_fee_percentage' => 'numeric|min:0|max:100',
            'withdrawal_fee_percentage' => 'numeric|min:0|max:100',
            'purchase_fee_percentage' => 'numeric|min:0|max:100',
            'min_fee_amount' => 'numeric|min:0',
            'max_fee_amount' => 'nullable|numeric|min:0',
            'currency' => 'string|max:3',
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
            $transactionFee = TransactionFee::findOrFail($id);
            $transactionFee->update($request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Frais de transaction mis à jour avec succès',
                'data' => $transactionFee
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des frais de transaction', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
            Log::error('Erreur lors de la suppression des frais de transaction', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
                'message' => $transactionFee->is_active ? 'Frais de transaction activés avec succès' : 'Frais de transaction désactivés avec succès',
                'data' => $transactionFee
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors du changement d\'état des frais de transaction'
            ], 500);
        }
    }

    /**
     * Mettre à jour les frais de transaction depuis l'API externe.
     *
     * @return \Illuminate\Http\Response
     */
    public function updateFromApi()
    {
        try {
            $result = TransactionFee::updateFeesFromApi();
            
            if ($result) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Frais de transaction mis à jour depuis l\'API avec succès'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Une erreur est survenue lors de la mise à jour des frais de transaction depuis l\'API'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des frais de transaction depuis l\'API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la mise à jour des frais de transaction depuis l\'API'
            ], 500);
        }
    }
}
