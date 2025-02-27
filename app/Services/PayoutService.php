<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\GcashAccount;
use App\Models\PayoutMethod;
use App\Models\PaypalAccount;
use Exception;

class PayoutService
{
    public function getPayoutMethods()
    {
        $user = auth()->user();

        return $user->payoutMethods()
            ->with('payoutable')
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    public function addPayoutMethod(array $data)
    {
        // Create the specific payout method
        $account = $this->createPayoutMethod($data['type'], $data['account']);

        // Get the authenticated user
        $user = auth()->user();

        // If this is the first payout method, make it default
        $isFirst = !$user->payoutMethods()->exists();

        // Create the payout method
        $payoutMethod = $account->payoutMethod()->create([
            'user_id' => $user->id,
            'is_default' => $isFirst || $data['is_default'],
        ]);

        // If this is set as default, remove default from others
        if ($payoutMethod->is_default) {
            $this->resetDefaultPayoutMethod($payoutMethod);
        }

        return $payoutMethod;
    }

    /**
     * @throws Exception
     */
    public function updatePayoutMethod(string $id, array $data)
    {
        // Get the authenticated user
        $user = auth()->user();

        // Get the specific payout method
        $payoutMethod = $user->payoutMethods()->findOrFail($id);

        // If this is the only payout method, it must be default
        if ($user->payoutMethods()->count() === 1 && !$data['is_default']) {
            $payoutMethod->update([
                'is_default' => true,
            ]);

            throw new Exception('You must have at least one payout method.', 400);
        }

        // Update the specific payout method
        $payoutMethod->update($data);

        // Update the specific payout method account
        $payoutMethod->payoutable->update($data['account']);

        // If this is set as default, remove default from others
        if ($payoutMethod->is_default) {
            $this->resetDefaultPayoutMethod($payoutMethod);
        }

        return $payoutMethod;
    }

    public function deletePayoutMethod(string $id)
    {
        // Get the authenticated user
        $user = auth()->user();

        // Get the specific payout method
        $payoutMethod = $user->payoutMethods()->findOrFail($id);

        // Delete the specific payout method account
        $payoutMethod->payoutable->delete();

        // Delete the specific payout method
        $payoutMethod->delete();

        // If this is the default payout method, get a replacement
        if ($payoutMethod->is_default) {
            $this->resetDefaultPayoutMethod();
        }

        return $payoutMethod;
    }

    private function createPayoutMethod(string $type, array $accountData)
    {
        return match ($type) {
            'gcash' => GcashAccount::create($accountData),
            'paypal' => PaypalAccount::create($accountData),
            'bank' => BankAccount::create($accountData),
        };
    }

    private function resetDefaultPayoutMethod(?PayoutMethod $currentPayoutMethod = null)
    {
        // Get the authenticated user
        $user = auth()->user();

        // If there is no current default payout method, make a new one
        if (!$currentPayoutMethod) {
            $currentPayoutMethod = $user->payoutMethods()->where('is_default', false)->first();

            if ($currentPayoutMethod) {
                $currentPayoutMethod->update([
                    'is_default' => true,
                ]);
            }
        }

        // If there are no other payout methods, return
        if (!$user->payoutMethods()->exists() || $user->payoutMethods()->count() <= 1) {
            return;
        }

        // Set the current payout method as default
        $user->payoutMethods()->where('id', '!=', $currentPayoutMethod->id)->update([
            'is_default' => false,
        ]);
    }
}
