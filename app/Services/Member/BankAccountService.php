<?php

namespace App\Services\Member;

use App\Models\Member\BankAccount;
use App\Models\Member\Member;

class BankAccountService
{
    /**
     * Create a bank account for a member.
     */
    public function createForMember(Member $member, array $data): BankAccount
    {
        if (!empty($data['is_default'])) {
            $member->bankAccounts()->update(['is_default' => false]);
        }

        return $member->bankAccounts()->create([
            'account_holder' => $this->normalizeText($data['account_holder'] ?? ''),
            'iban' => $this->normalizeIban($data['iban'] ?? ''),
            'bic' => $this->normalizeBic($data['bic'] ?? null),
            'mandate_reference' => $this->normalizeText($data['mandate_reference'] ?? null),
            'mandate_signed_at' => $data['mandate_signed_at'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'status' => $data['status'] ?? 'active',
        ]);
    }

    /**
     * Update a bank account and optionally set it as default.
     */
    public function updateBankAccount(BankAccount $account, array $data): void
    {
        if (!empty($data['is_default'])) {
            $account->member->bankAccounts()->update(['is_default' => false]);
        }

        $account->update([
            'account_holder' => $this->normalizeText($data['account_holder'] ?? ''),
            'iban' => $this->normalizeIban($data['iban'] ?? ''),
            'bic' => $this->normalizeBic($data['bic'] ?? null),
            'mandate_reference' => $this->normalizeText($data['mandate_reference'] ?? null),
            'mandate_signed_at' => $data['mandate_signed_at'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);
    }

    /**
     * Revoke a bank account.
     */
    public function revokeBankAccount(BankAccount $account): void
    {
        $account->update([
            'status' => 'revoked',
            'is_default' => false,
        ]);
    }

    /**
     * Delete a bank account if no payments are associated with it.
     */
    public function deleteBankAccount(BankAccount $account): bool
    {
        // Check if there are any payments associated with this bank account
        if ($account->payments()->exists()) {
            return false; // Cannot delete if payments exist
        }

        // Reset default account if needed
        if ($account->is_default) {
            $account->member->bankAccounts()
                ->where('id', '!=', $account->id)
                ->orderBy('created_at')
                ->first()?->update(['is_default' => true]);
        }

        $account->delete();
        return true; // Successfully deleted
    }

    private function normalizeText(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;
        return $value === '' ? null : $value;
    }

    private function normalizeIban(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', $value));
    }

    private function normalizeBic(?string $value): ?string
    {
        $value = $this->normalizeText($value);
        return $value !== null ? strtoupper($value) : null;
    }
}
