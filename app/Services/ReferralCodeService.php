<?php

namespace App\Services;

use App\Models\User;

class ReferralCodeService
{
    private const CODE_PREFIX = 'CPR-';
    private const MAX_NUMBER = 9999;

    public static function generateNextCode(): string
    {
        $lastCode = User::orderBy('referral_code', 'desc')->first()?->referral_code;

        if (!$lastCode) {
            return self::CODE_PREFIX . 'A1';
        }

        // Extraire la lettre et le numéro du dernier code
        preg_match('/CPR-([A-Z])(\d+)/', $lastCode, $matches);
        $letter = $matches[1];
        $number = (int)$matches[2];

        // Incrémenter le numéro ou passer à la lettre suivante
        if ($number < self::MAX_NUMBER) {
            $number++;
        } else {
            $letter = chr(ord($letter) + 1);
            $number = 1;
        }

        return self::CODE_PREFIX . $letter . $number;
    }

    public static function isValidCode(?string $code): bool
    {
        if (!$code) {
            return false;
        }

        // Vérifier le format du code
        if (!preg_match('/^CPR-[A-Z]\d+$/', $code)) {
            return false;
        }

        // Vérifier si le code existe et si le compte est actif
        return User::where('referral_code', $code)
            ->where('email_verified_at', '!=', null)
            ->exists();
    }

    public static function getAllActiveCodes(): array
    {
        return User::where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->orderBy('referral_code')
            ->pluck('referral_code')
            ->toArray();
    }
} 