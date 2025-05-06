<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionFee;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Models\ExchangeRates;

class TransactionFeeApiController extends Controller
{
    /**
     * Récupère les frais de transaction pour un moyen de paiement spécifique.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    // public function getFeesForPaymentMethod(Request $request)
    // {
    //     $paymentMethod = $request->input('payment_method');
        
    //     if (!$paymentMethod) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Le paramètre payment_method est requis'
    //         ], 400);
    //     }
        
    //     $transactionFee = TransactionFee::getFeesForPaymentMethod($paymentMethod);
        
    //     if (!$transactionFee) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Aucun frais de transaction trouvé pour ce moyen de paiement'
    //         ], 404);
    //     }
        
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $transactionFee
    //     ]);
    // }
    
    /**
     * Calcule les frais de transfert pour un montant donné.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function calculateTransferFee(Request $request)
    {
        $amount = $request->input('amount');
        $currency = $request->input('currency', 'USD');

        // Récupérer le paramètre global transfer_fee_percentage depuis les paramètres du système
        $globalFeePercentage = (float) Setting::getValue('transfer_fee_percentage', 0);

        $fee = ((float)$amount) * ($globalFeePercentage / 100);
        $total = ((float)$amount) + $fee;

        return response()->json([
            'success' => true,
            'fee' => round($fee, 2),
            'percentage' => $globalFeePercentage,
            'total' => round($total, 2),
            'payment_method' => 'global',
            'payment_type' => null
        ]);
    }
    
    /**
     * Calcule les frais de retrait pour un montant donné.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function calculateWithdrawalFee(Request $request)
    {
        $paymentMethod = $request->input('payment_method');
        $paymentType = $request->input('payment_type', null);
        $amount = $request->input('amount');
        
        if (!$paymentMethod || !$amount) {
            return response()->json([
                'status' => 'error',
                'message' => 'Les paramètres payment_method et amount sont requis'
            ], 400);
        }
        
        // Récupérer le paramètre global withdrawal_fee_percentage depuis les paramètres du système
        $globalFeePercentage = 0; // Valeur par défaut si le paramètre n'est pas défini
        $setting = Setting::where('key', 'withdrawal_fee_percentage')->first();
        if ($setting) {
            $globalFeePercentage = (float) $setting->value;
        }
        
        // Calculer les frais en utilisant le pourcentage global mais en conservant les autres paramètres
        // (fee_fixed, fee_cap) spécifiques à la méthode de paiement
        $fee = $amount * ($globalFeePercentage / 100);
        
        // // Appliquer le montant minimum des frais
        // if ($fee < $transactionFee->fee_fixed) {
        //     $fee = $transactionFee->fee_fixed;
        // }
        
        // // Appliquer le montant maximum des frais si défini
        // if ($transactionFee->fee_cap && $fee > $transactionFee->fee_cap) {
        //     $fee = $transactionFee->fee_cap;
        // }
        
        $fee = round($fee, 2);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'amount' => (float) $amount,
                'percentage' => $globalFeePercentage,
                'fee' => $fee,
            ]
        ]);
    }
}
