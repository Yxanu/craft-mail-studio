export class ApiClient {
  constructor(
    private readonly baseUrl: string,
    private readonly csrfToken: string,
  ) {}

  async get<T>(path = ''): Promise<T> {
    return this.request<T>(path, { method: 'GET' })
  }

  async post<T>(path: string, body: unknown): Promise<T> {
    return this.request<T>(path, { method: 'POST', body: JSON.stringify(body) })
  }

  async put<T>(path: string, body: unknown): Promise<T> {
    return this.request<T>(path, { method: 'PUT', body: JSON.stringify(body) })
  }

  async delete(path: string): Promise<void> {
    await this.request(path, { method: 'DELETE' })
  }

  private async request<T>(path: string, init: RequestInit): Promise<T> {
    const response = await fetch(`${this.baseUrl}${path}`, {
      ...init,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        ...(init.headers ?? {}),
      },
    })

    if (response.status === 204) {
      return undefined as T
    }

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) {
      const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : ''
      throw new Error(validation || payload.message || `Request failed with status ${response.status}.`)
    }

    return payload as T
  }
}
