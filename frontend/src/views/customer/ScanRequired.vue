<script setup>
// Shown when someone tries to reach the customer portal without having
// scanned a table QR code. The menu is only available at the table.
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-stone-50 px-6 text-center">
    <div class="max-w-sm">
      <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-brand-100 text-3xl">📱</div>
      <h1 class="mt-5 text-xl font-bold text-stone-900">Scan to view the menu</h1>
      <p class="mt-2 text-sm text-stone-500">
        Please scan the QR code on your table to browse the menu and place an order.
      </p>
    </div>
  </div>
</template>
