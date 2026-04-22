import { getApiBaseUrl } from './api'
import { getAppSessionId } from './session'

export async function trackEvent(eventName: string, payload?: Record<string, unknown>) {
  try {
    await fetch(`${getApiBaseUrl()}/api/events`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        event_name: eventName,
        page_url: window.location.href,
        referrer: document.referrer || null,
        session_id: getAppSessionId(),
        occurred_at: new Date().toISOString(),
        payload: payload ?? {},
      }),
    })
  } catch (error) {
    console.error('trackEvent failed', error)
  }
}
