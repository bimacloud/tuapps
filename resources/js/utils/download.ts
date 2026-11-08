import axios from 'axios'

/**
 * The API needs the bearer token, so a plain link cannot download files.
 * Fetch the file as a blob and hand it to the browser.
 */
export const downloadFile = async (url: string, fileName: string, params: Record<string, unknown> = {}): Promise<void> => {
  const response = await axios.get(url, { params, responseType: 'blob' })
  const objectUrl = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = fileName
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(objectUrl)
}
