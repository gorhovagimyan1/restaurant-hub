<script setup>
import { ref, onMounted } from 'vue'
import { fetchTables, createTable, deleteTable } from '@/services/tables'
import TableQrCard from '@/components/dashboard/TableQrCard.vue'

const tables = ref([])
const loading = ref(true)
const error = ref(null)

const newName = ref('')
const newCapacity = ref(4)
const creating = ref(false)

async function load() {
  loading.value = true
  error.value = null
  try {
    tables.value = await fetchTables()
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load tables.'
  } finally {
    loading.value = false
  }
}

async function add() {
  const name = newName.value.trim()
  if (!name || creating.value) return
  creating.value = true
  try {
    const table = await createTable({ name, capacity: Number(newCapacity.value) || 4 })
    tables.value.push(table)
    newName.value = ''
    newCapacity.value = 4
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not create the table.'
  } finally {
    creating.value = false
  }
}

async function remove(table) {
  if (!window.confirm(`Delete ${table.name}? Its QR code will stop working.`)) return
  try {
    await deleteTable(table.id)
    tables.value = tables.value.filter((t) => t.id !== table.id)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not delete the table.'
  }
}

onMounted(load)
</script>

<template>
  <div>
    <header class="mb-5">
      <h1 class="text-2xl font-bold text-stone-900">Tables & QR codes</h1>
      <p class="text-sm text-stone-500">
        Print a QR code for each table. Scanning it opens the menu and lets guests order.
      </p>
    </header>

    <!-- Add table -->
    <form
      class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5"
      @submit.prevent="add"
    >
      <div>
        <label class="block text-xs font-medium text-stone-500">Table name</label>
        <input
          v-model="newName"
          type="text"
          placeholder="e.g. Table 9 / Patio 2"
          class="mt-1 rounded-xl border border-stone-200 px-3.5 py-2 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
        />
      </div>
      <div>
        <label class="block text-xs font-medium text-stone-500">Seats</label>
        <input
          v-model="newCapacity"
          type="number"
          min="1"
          max="50"
          class="mt-1 w-24 rounded-xl border border-stone-200 px-3.5 py-2 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
        />
      </div>
      <button
        type="submit"
        class="rounded-full bg-amber-500 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 disabled:opacity-60"
        :disabled="creating"
      >
        {{ creating ? 'Adding…' : '+ Add table' }}
      </button>
    </form>

    <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

    <div v-if="loading" class="py-16 text-center text-sm text-stone-400">Loading tables…</div>

    <div
      v-else-if="tables.length === 0"
      class="rounded-2xl border border-dashed border-stone-200 px-6 py-16 text-center text-sm text-stone-500"
    >
      No tables yet — add your first one above.
    </div>

    <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
      <TableQrCard v-for="table in tables" :key="table.id" :table="table" @delete="remove" />
    </div>
  </div>
</template>
