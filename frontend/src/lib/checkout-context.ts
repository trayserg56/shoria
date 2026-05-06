const STORAGE_KEY = 'shoria_checkout_incentives_v1'

export type CheckoutIncentivesPayload = {
  promoCode: string
  loyaltyPointsToSpend: number
}

export function saveCheckoutIncentives(payload: CheckoutIncentivesPayload): void {
  try {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        promoCode: payload.promoCode.trim(),
        loyaltyPointsToSpend: Math.max(0, Math.floor(Number(payload.loyaltyPointsToSpend) || 0)),
      }),
    )
  } catch {
    /* ignore quota / private mode */
  }
}

export function loadCheckoutIncentives(): CheckoutIncentivesPayload | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) {
      return null
    }
    const parsed = JSON.parse(raw) as Partial<CheckoutIncentivesPayload>
    return {
      promoCode: typeof parsed.promoCode === 'string' ? parsed.promoCode : '',
      loyaltyPointsToSpend:
        typeof parsed.loyaltyPointsToSpend === 'number' ? parsed.loyaltyPointsToSpend : 0,
    }
  } catch {
    return null
  }
}

export function clearCheckoutIncentives(): void {
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  } catch {
    /* ignore */
  }
}
