import { onMounted, onUnmounted, ref } from 'vue'

function useMediaQuery(query: string) {
  const matches = ref(false)

  if (typeof window !== 'undefined') {
    matches.value = window.matchMedia(query).matches
  }

  onMounted(() => {
    const media = window.matchMedia(query)
    const update = () => {
      matches.value = media.matches
    }
    update()
    media.addEventListener('change', update)
    onUnmounted(() => media.removeEventListener('change', update))
  })

  return matches
}

export function useViewport() {
  const mobile = useMediaQuery('(max-width: 767px)')
  const tablet = useMediaQuery('(min-width: 768px) and (max-width: 1023px)')
  return { mobile, tablet }
}
