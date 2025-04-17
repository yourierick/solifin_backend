<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionFee;
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
        \Log::info($request->all());
        $paymentMethod = $request->input('payment_method');
        if ($paymentMethod === "wallet") {
            $paymentMethod = "solifin-wallet";
        }
        $paymentType = $request->input('payment_type', null);
        $amount = $request->input('amount');

        $currency = $request->input('currency');
        
        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Les paramètres payment_method et amount sont requis'
            ], 400);
        }
        
        $query = TransactionFee::where('payment_method', $paymentMethod)
                              ->where('is_active', true);
        
        if ($paymentType) {
            $query->where('payment_type', $paymentType);
        }
        
        $transactionFee = $query->first();
        
        if (!$transactionFee) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun frais de transaction trouvé pour ce moyen de paiement'
            ], 404);
        }
        
        $fee = $transactionFee->calculateTransferFee((float) $amount);
        
    
        return response()->json([
            'success' => true,
            'fee' => $fee,
            'percentage' => $transactionFee->transfer_fee_percentage,
            'total' => (float) $amount + $fee,
            'payment_method' => $transactionFee->payment_method,
            'payment_type' => $transactionFee->payment_type
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
        
        $query = TransactionFee::where('payment_method', $paymentMethod)
                              ->where('is_active', true);
        
        if ($paymentType) {
            $query->where('payment_type', $paymentType);
        }
        
        $transactionFee = $query->first();
        
        if (!$transactionFee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucun frais de transaction trouvé pour ce moyen de paiement'
            ], 404);
        }
        
        $fee = $transactionFee->calculateWithdrawalFee((float) $amount);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'amount' => (float) $amount,
                'fee' => $fee,
                'total' => (float) $amount - $fee, // Pour un retrait, les frais sont déduits du montant
                'payment_method' => $transactionFee->payment_method,
                'payment_type' => $transactionFee->payment_type
            ]
        ]);
    }
    
    
    /**
     * Récupère les frais de retrait pour un moyen de paiement spécifique.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    // public function getWithdrawalFee(Request $request)
    // {
    //     $paymentMethod = $request->input('payment_method');
    //     $amount = $request->input('amount');
    //     $currency = $request->input('currency', 'USD');
        
    //     if (!$paymentMethod || !$amount) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Les paramètres payment_method et amount sont requis'
    //         ], 400);
    //     }
        
    //     // Pour l'instant, nous simulons des données de frais
    //     // En production, vous récupéreriez ces données depuis la base de données
    //     $feePercentage = 0.025; // 2.5% par défaut
        
    //     // Différents taux selon la méthode de paiement
    //     switch ($paymentMethod) {
    //         case 'card':
    //             $feePercentage = 0.035; // 3.5%
    //             $baseFee = 1.00;
    //             $processingFee = round($amount * 0.02, 2);
    //             $networkFee = round($amount * 0.015, 2);
    //             $note = "Des frais supplémentaires peuvent être appliqués par votre banque.";
    //             break;
    //         case 'mobile-money':
    //             $feePercentage = 0.03; // 3%
    //             $baseFee = 0.50;
    //             $processingFee = round($amount * 0.015, 2);
    //             $networkFee = round($amount * 0.015, 2);
    //             $note = "Des frais supplémentaires peuvent être appliqués par votre opérateur mobile.";
    //             break;
    //         case 'bank-transfer':
    //             $feePercentage = 0.025; // 2.5%
    //             $baseFee = 2.00;
    //             $processingFee = round($amount * 0.01, 2);
    //             $networkFee = round($amount * 0.015, 2);
    //             $note = "Le délai de traitement peut varier selon votre banque.";
    //             break;
    //         case 'money-transfer':
    //             $feePercentage = 0.04; // 4%
    //             $baseFee = 3.00;
    //             $processingFee = round($amount * 0.025, 2);
    //             $networkFee = round($amount * 0.015, 2);
    //             $note = "Des frais supplémentaires peuvent être appliqués par le service de transfert.";
    //             break;
    //         default:
    //             $feePercentage = 0.025; // 2.5% par défaut
    //             $baseFee = 1.00;
    //             $processingFee = round($amount * 0.01, 2);
    //             $networkFee = round($amount * 0.015, 2);
    //             $note = "";
    //     }
        
    //     // Calcul du montant total des frais
    //     $totalFee = $baseFee + $processingFee + $networkFee;
        
    //     // Arrondir à 2 décimales
    //     $totalFee = round($totalFee, 2);
        
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => [
    //             'amount' => (float) $amount,
    //             'fee' => $totalFee,
    //             'fee_percentage' => $feePercentage * 100,
    //             'fee_breakdown' => [
    //                 'baseFee' => $baseFee,
    //                 'processingFee' => $processingFee,
    //                 'networkFee' => $networkFee
    //             ],
    //             'fee_details' => [
    //                 'note' => $note,
    //                 'description' => "Frais de retrait pour $currency"
    //             ],
    //             'net_amount' => (float) $amount - $totalFee,
    //             'currency' => $currency
    //         ]
    //     ]);
    // }
}
