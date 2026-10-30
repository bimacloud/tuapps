import { reactive } from 'vue'

export type NoticeTone = 'success' | 'error'

interface Notice {
  id: number
  tone: NoticeTone
  message: string
}

interface ConfirmRequest {
  open: boolean
  title: string
  message: string
  confirmLabel: string
  danger: boolean
  resolve: ((value: boolean) => void) | null
}

const notices = reactive<Notice[]>([])
const confirmRequest = reactive<ConfirmRequest>({
  open: false,
  title: '',
  message: '',
  confirmLabel: 'Ya, lanjutkan',
  danger: false,
  resolve: null,
})

let nextId = 1

/**
 * App-wide feedback: short notices (success disappears, errors stay until closed)
 * and a confirmation dialog that replaces window.confirm().
 */
export function useNotice() {
  const dismiss = (id: number) => {
    const index = notices.findIndex((notice) => notice.id === id)
    if (index !== -1) notices.splice(index, 1)
  }

  const notify = (tone: NoticeTone, message: string) => {
    const id = nextId++
    notices.push({ id, tone, message })
    if (tone === 'success') {
      setTimeout(() => dismiss(id), 5000)
    }
  }

  const confirm = (options: { title: string; message: string; confirmLabel?: string; danger?: boolean }) =>
    new Promise<boolean>((resolve) => {
      confirmRequest.title = options.title
      confirmRequest.message = options.message
      confirmRequest.confirmLabel = options.confirmLabel ?? 'Ya, lanjutkan'
      confirmRequest.danger = options.danger ?? false
      confirmRequest.resolve = resolve
      confirmRequest.open = true
    })

  const answerConfirm = (value: boolean) => {
    confirmRequest.resolve?.(value)
    confirmRequest.resolve = null
    confirmRequest.open = false
  }

  return {
    notices,
    confirmRequest,
    success: (message: string) => notify('success', message),
    error: (message: string) => notify('error', message),
    dismiss,
    confirm,
    answerConfirm,
  }
}
