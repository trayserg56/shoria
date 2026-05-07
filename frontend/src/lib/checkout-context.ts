const STORAGE_KEY = 'shoria_checkout_incentives_v1'

export type CheckoutIncentivesPayload = {
  promoCode: string
  giftCertificateCode: string
  giftCertificateId: number | null
  loyaltyPointsToSpend: number
}

export function saveCheckoutIncentives(payload: CheckoutIncentivesPayload): void {
  try {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        promoCode: payload.promoCode.trim(),
        giftCertificateCode: payload.giftCertificateCode.trim(),
        giftCertificateId:
          typeof payload.giftCertificateId === 'number' && Number.isFinite(payload.giftCertificateId)
            ? payload.giftCertificateId
            : null,
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
    const idRaw = parsed.giftCertificateId
    return {
      promoCode: typeof parsed.promoCode === 'string' ? parsed.promoCode : '',
      giftCertificateCode: typeof parsed.giftCertificateCode === 'string' ? parsed.giftCertificateCode : '',
      giftCertificateId: typeof idRaw === 'number' && Number.isFinite(idRaw) ? idRaw : null,
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
