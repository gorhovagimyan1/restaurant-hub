<script setup>
// Shown when someone tries to reach the customer portal without having
// scanned a table QR code. The menu is only available at the table.
import { QrCode } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import LanguageSwitcher from '@/components/ui/LanguageSwitcher.vue'
import RateDishesCard from '@/components/order/RateDishesCard.vue'

const { t } = useI18n()
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center bg-canvas px-6 py-16 text-center">
    <LanguageSwitcher class="absolute right-4 top-4" />
    <div class="max-w-sm">
      <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-accent-500 text-white shadow-sm shadow-brand-500/30">
        <QrCode :size="30" />
      </div>
      <h1 class="mt-5 text-xl font-bold text-ink-900">{{ t('customer.scanTitle') }}</h1>
      <p class="mt-2 text-sm text-ink-500">
        {{ t('customer.scanText') }}
      </p>

      <!-- After a visit: only appears if there are dishes left to rate. -->
      <RateDishesCard />
    </div>
  </div>
</template>
