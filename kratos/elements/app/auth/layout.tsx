// app/layout.tsx
import "@ory/elements-react/theme/styles.css"
import { PropsWithChildren } from "react"

export default function AuthLayout({ children }: PropsWithChildren) {
  return (
      <div className="min-h-screen flex bg-white">
          {/* Левая колонка - форма */}
          <div className="w-full lg:w-1/2 flex flex-col">
              <header className="p-6 flex items-center">
                  <button className="flex items-center gap-2 text-gray-700 hover:text-gray-900 transition-colors">
                      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M15.8333 10H4.16667M4.16667 10L9.58333 4.58333M4.16667 10L9.58333 15.4167" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                      </svg>
                      <span className="font-bold text-xl tracking-tight">Rider</span>
                  </button>
              </header>

              <div className="flex-1 flex items-center justify-center p-8">
                  <div className="w-full max-w-md">
                      {children}
                  </div>
              </div>
          </div>

          {/* Правая колонка - светлый абстрактный градиент */}
          <div className="hidden lg:block w-1/2 relative overflow-hidden bg-white">
              <div className="absolute inset-0">
                  {/* Base - очень светлый фон */}
                  <div className="absolute inset-0 bg-gradient-to-br from-blue-50 via-purple-50 to-cyan-50" />

                  {/* Animated blobs - светлые пастельные цвета с низкой opacity */}
                  <div className="absolute -top-1/2 -left-1/2 w-full h-full rounded-full animate-float1"
                       style={{
                           background: 'radial-gradient(circle, rgba(147, 197, 253, 0.4) 0%, transparent 70%)', // light blue-300
                           filter: 'blur(80px)',
                       }}
                  />

                  <div className="absolute -top-1/2 -right-1/2 w-full h-full rounded-full animate-float2"
                       style={{
                           background: 'radial-gradient(circle, rgba(216, 180, 254, 0.4) 0%, transparent 70%)', // light purple-300
                           filter: 'blur(80px)',
                       }}
                  />

                  <div className="absolute -bottom-1/2 -left-1/2 w-full h-full rounded-full animate-float3"
                       style={{
                           background: 'radial-gradient(circle, rgba(165, 243, 252, 0.4) 0%, transparent 70%)', // light cyan-300
                           filter: 'blur(80px)',
                       }}
                  />

                  <div className="absolute -bottom-1/2 -right-1/2 w-full h-full rounded-full animate-float4"
                       style={{
                           background: 'radial-gradient(circle, rgba(196, 181, 253, 0.4) 0%, transparent 70%)', // light violet-300
                           filter: 'blur(80px)',
                       }}
                  />

                  <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3/4 h-3/4 rounded-full animate-pulse-slow"
                       style={{
                           background: 'radial-gradient(circle, rgba(167, 139, 250, 0.3) 0%, transparent 70%)', // light indigo-400
                           filter: 'blur(100px)',
                       }}
                  />

                  {/* Mesh gradient overlay - очень subtle */}
                  <div
                      className="absolute inset-0 animate-mesh"
                      style={{
                          background: `
                radial-gradient(at 40% 20%, rgba(147, 197, 253, 0.3) 0px, transparent 50%),
                radial-gradient(at 80% 0%, rgba(216, 180, 254, 0.3) 0px, transparent 50%),
                radial-gradient(at 0% 50%, rgba(165, 243, 252, 0.3) 0px, transparent 50%),
                radial-gradient(at 80% 100%, rgba(196, 181, 253, 0.3) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(167, 139, 250, 0.3) 0px, transparent 50%)
              `,
                      }}
                  />

                  {/* Очень subtle noise texture */}
                  <div
                      className="absolute inset-0 opacity-[0.01]"
                      style={{
                          backgroundImage: `url("image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E")`,
                      }}
                  />
              </div>

              {/* Очень subtle glow - почти незаметный */}
              <div className="absolute inset-0 bg-gradient-to-t from-gray-100/20 via-transparent to-white/30 pointer-events-none" />
          </div>
      </div>
  )
}