<script setup>
// Shown when someone tries to reach the customer portal without having
// scanned a table QR code. The menu is only available at the table.
import { QrCode } from 'lucide-vue-next'
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-canvas px-6 text-center">
    <div class="max-w-sm">
      <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-accent-500 text-white shadow-sm shadow-brand-500/30">
        <QrCode :size="30" />
      </div>
      <h1 class="mt-5 text-xl font-bold text-ink-900">Scan to view the menu</h1>
      <p class="mt-2 text-sm text-ink-500">
        Please scan the QR code on your table to browse the menu and place an order.
      </p>
    </div>
  </div>
</template>
