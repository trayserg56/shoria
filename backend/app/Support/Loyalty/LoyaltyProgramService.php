<?php

namespace App\Support\Loyalty;

use App\Models\LoyaltyProgramSetting;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

class LoyaltyProgramService
{
    public function getSetting(): LoyaltyProgramSetting
    {
        return LoyaltyProgramSetting::current();
    }

    public function isEnabled(?LoyaltyProgramSetting $setting = null): bool
    {
        $config = $setting ?? $this->getSetting();

        return (bool) $config->is_enabled;
    }

    public function normalizedTiers(?LoyaltyProgramSetting $setting = null): array
    {
        $config = $setting ?? $this->getSetting();
        $tiers = is_array($config->tiers) ? $config->tiers : [];

        $normalized = collect($tiers)
            ->map(function ($tier): ?array {
                if (! is_array($tier)) {
                    return null;
                }

                $name = trim((string) ($tier['name'] ?? ''));
                $minSpent = (float) ($tier['min_spent'] ?? 0);
                $accrualPercent = (float) ($tier['accrual_percent'] ?? 0);

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'min_spent' => max(0, round($minSpent, 2)),
                    'accrual_percent' => max(0, round($accrualPercent, 2)),
                ];
            })
            ->filter()
            ->sortBy('min_spent')
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : [
            [
                'name' => 'Base',
                'min_spent' => 0.0,
                'accrual_percent' => (float) $config->base_accrual_percent,
            ],
            [
                'name' => 'Silver',
                'min_spent' => 30000.0,
                'accrual_percent' => (float) $config->base_accrual_percent + 1.0,
            ],
            [
                'name' => 'Gold',
                'min_spent' => 80000.0,
                'accrual_percent' => (float) $config->base_accrual_percent + 2.0,
            ],
            [
                'name' => 'Platinum',
                'min_spent' => 150000.0,
                'accrual_percent' => (float) $config->base_accrual_percent + 3.0,
            ],
        ];
    }

    public function resolveCurrentTier(float $totalSpent, ?LoyaltyProgramSetting $setting = null): ?array
    {
        $tier = collect($this->normalizedTiers($setting))
            ->filter(fn (array $item): bool => $totalSpent >= (float) $item['min_spent'])
            ->sortByDesc('min_spent')
            ->first();

        return $tier ? (array) $tier : null;
    }

    public function resolveNextTier(float $totalSpent, ?LoyaltyProgramSetting $setting = null): ?array
    {
        $next = collect($this->normalizedTiers($setting))
            ->filter(fn (array $item): bool => (float) $item['min_spent'] > $totalSpent)
            ->sortBy('min_spent')
            ->first();

        return $next ? (array) $next : null;
    }

    public function resolveEffectiveAccrualPercent(User $user, ?LoyaltyProgramSetting $setting = null): float
    {
        $config = $setting ?? $this->getSetting();
        $tier = $this->resolveCurrentTier((float) $user->loyalty_total_spent, $config);

        if ($tier) {
            return max(0, (float) $tier['accrual_percent']);
        }

        return max(0, (float) $config->base_accrual_percent);
    }

    public function resolveRedeemDiscountByPoints(
        int $pointsToSpend,
        ?LoyaltyProgramSetting $setting = null,
    ): float {
        $config = $setting ?? $this->getSetting();
        $pointValue = max(0.01, (float) $config->point_value);

        return round(max(0, $pointsToSpend) * $pointValue, 2);
    }

    public function resolveMaxRedeemPoints(
        User $user,
        float $subtotalAfterPromo,
        ?LoyaltyProgramSetting $setting = null,
    ): int {
        $config = $setting ?? $this->getSetting();

        if ((float) $config->min_order_total_for_redeem > $subtotalAfterPromo) {
            return 0;
        }

        $maxByPercent = max(0, round($subtotalAfterPromo * ((float) $config->max_redeem_percent / 100), 2));
        $pointValue = max(0.01, (float) $config->point_value);
        $maxPointsByOrder = (int) floor($maxByPercent / $pointValue);

        return min((int) $user->loyalty_points_balance, max(0, $maxPointsByOrder));
    }

    public function resolveAccrualPoints(
        User $user,
        float $accrualBaseAmount,
        ?LoyaltyProgramSetting $setting = null,
    ): int {
        $percent = $this->resolveEffectiveAccrualPercent($user, $setting);
        if ($percent <= 0 || $accrualBaseAmount <= 0) {
            return 0;
        }

        return (int) floor($accrualBaseAmount * ($percent / 100));
    }

    public function applyRedeem(
        User $user,
        Order $order,
        int $pointsToSpend,
        float $discountTotal,
    ): void {
        if ($pointsToSpend <= 0) {
            return;
        }

        $nextBalance = max(0, (int) $user->loyalty_points_balance - $pointsToSpend);
        $user->loyalty_points_balance = $nextBalance;
        $user->save();

        LoyaltyTransaction::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'redeem',
            'points_delta' => -$pointsToSpend,
            'balance_after' => $nextBalance,
            'description' => sprintf('Списание за заказ %s (−%s ₽)', $order->order_number, number_format($discountTotal, 0, '.', ' ')),
        ]);
    }

    public function applyAccrual(
        User $user,
        Order $order,
        int $pointsEarned,
        float $accrualBaseAmount,
        float $accrualPercent,
    ): void {
        if ($pointsEarned <= 0) {
            return;
        }

        $nextBalance = (int) $user->loyalty_points_balance + $pointsEarned;
        $nextTotalSpent = round((float) $user->loyalty_total_spent + max(0, $accrualBaseAmount), 2);

        $user->loyalty_points_balance = $nextBalance;
        $user->loyalty_total_spent = $nextTotalSpent;
        $user->save();

        LoyaltyTransaction::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'accrual',
            'points_delta' => $pointsEarned,
            'balance_after' => $nextBalance,
            'description' => sprintf(
                'Начисление за заказ %s (+%d баллов, %.2f%%)',
                $order->order_number,
                $pointsEarned,
                $accrualPercent,
            ),
            'meta' => [
                'accrual_base' => round($accrualBaseAmount, 2),
                'accrual_percent' => round($accrualPercent, 2),
            ],
        ]);
    }

    public function userSnapshot(?User $user, ?LoyaltyProgramSetting $setting = null): ?array
    {
        if (! $user) {
            return null;
        }

        $config = $setting ?? $this->getSetting();
        $totalSpent = (float) $user->loyalty_total_spent;
        $currentTier = $this->resolveCurrentTier($totalSpent, $config);
        $nextTier = $this->resolveNextTier($totalSpent, $config);

        return [
            'points_balance' => (int) $user->loyalty_points_balance,
            'total_spent' => round($totalSpent, 2),
            'accrual_percent' => $this->resolveEffectiveAccrualPercent($user, $config),
            'current_tier' => $currentTier,
            'next_tier' => $nextTier,
            'amount_to_next_tier' => $nextTier
                ? round(max(0, (float) $nextTier['min_spent'] - $totalSpent), 2)
                : 0.0,
        ];
    }

    public function infoPayload(?LoyaltyProgramSetting $setting = null): array
    {
        $config = $setting ?? $this->getSetting();
        $tiers = $this->normalizedTiers($config);

        return [
            'is_enabled' => (bool) $config->is_enabled,
            'base_accrual_percent' => (float) $config->base_accrual_percent,
            'max_redeem_percent' => (float) $config->max_redeem_percent,
            'point_value' => (float) $config->point_value,
            'min_order_total_for_redeem' => (float) $config->min_order_total_for_redeem,
            'tiers' => $tiers,
            'terms_content' => $config->terms_content,
        ];
    }

    public function userHistory(User $user, int $limit = 20): Collection
    {
        return LoyaltyTransaction::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (LoyaltyTransaction $tx): array => [
                'id' => $tx->id,
                'type' => $tx->type,
                'points_delta' => (int) $tx->points_delta,
                'balance_after' => (int) $tx->balance_after,
                'description' => $tx->description,
                'order_id' => $tx->order_id,
                'created_at' => $tx->created_at?->toIso8601String(),
            ]);
    }
}
