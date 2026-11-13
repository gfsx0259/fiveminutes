FROM node:24 AS base

FROM base AS deps
WORKDIR /app
COPY package*.json ./
RUN npm i

FROM base AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
COPY .env.production .env
ARG NEXT_PUBLIC_ORY_SDK_URL
ENV NEXT_PUBLIC_ORY_SDK_URL=${NEXT_PUBLIC_ORY_SDK_URL}

RUN npm run build

FROM base AS runner
WORKDIR /app

COPY --from=builder /app/.env.production ./.env
COPY --from=builder /app/.next ./.next
COPY --from=builder /app/public ./public
COPY --from=builder /app/package.json ./package.json
COPY --from=builder /app/package-lock.json ./package-lock.json

RUN npm ci --omit=dev

EXPOSE 3000

CMD ["npm", "start"]