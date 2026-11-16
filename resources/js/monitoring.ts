const MAX_REPORTS_PER_PAGE = 5;

const reported = new Set<string>();
let reportCount = 0;

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function send(message: string, source?: string, line?: number): void {
  if (reportCount >= MAX_REPORTS_PER_PAGE) {
    return;
  }

  const key = `${message}|${source ?? ''}|${line ?? ''}`;
  if (reported.has(key)) {
    return;
  }

  reported.add(key);
  reportCount += 1;

  const body = JSON.stringify({
    message: message.slice(0, 500),
    source: source?.slice(0, 200),
    line,
    page: window.location.pathname,
  });

  void fetch('/client-errors', {
    method: 'POST',
    keepalive: true,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body,
  }).catch(() => undefined);
}

export function installClientErrorReporting(): void {
  window.addEventListener('error', (event) => {
    if (!event.message) {
      return;
    }

    const source = event.filename ? event.filename.split('/').pop() : undefined;
    send(event.message, source, event.lineno);
  });

  window.addEventListener('unhandledrejection', (event) => {
    const reason: unknown = event.reason;
    const message = reason instanceof Error
      ? reason.message
      : typeof reason === 'string' && reason !== ''
        ? reason
        : 'Unhandled promise rejection';

    send(message);
  });
}
