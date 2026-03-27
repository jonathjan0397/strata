<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
  loginId:  { type: String, required: true },
  clientKey:{ type: String, required: true },
  sandbox:  { type: Boolean, default: true },
  amount:   { type: [Number, String], required: true },
})

const emit = defineEmits(['token', 'error'])

const cardNumber  = ref('')
const expMonth    = ref('')
const expYear     = ref('')
const cvv         = ref('')
const loading     = ref(false)
const localError  = ref(null)

onMounted(() => {
  const src = props.sandbox
    ? 'https://jstest.authorize.net/v1/Accept.js'
    : 'https://js.authorize.net/v1/Accept.js'

  if (!document.querySelector(`script[src="${src}"]`)) {
    const s = document.createElement('script')
    s.src = src
    s.charset = 'utf-8'
    document.head.appendChild(s)
  }
})

function tokenize() {
  localError.value = null
  loading.value = true

  const secureData = {
    authData: {
      clientKey: props.clientKey,
      apiLoginID: props.loginId,
    },
    cardData: {
      cardNumber:  cardNumber.value.replace(/\s/g, ''),
      month:       expMonth.value,
      year:        expYear.value,
      cardCode:    cvv.value,
    },
  }

  window.Accept.dispatchData(secureData, (response) => {
    loading.value = false
    if (response.messages.resultCode === 'Error') {
      const msg = response.messages.message[0]?.text ?? 'Card error'
      localError.value = msg
      emit('error', msg)
    } else {
      emit('token', {
        descriptor: response.opaqueData.dataDescriptor,
        value:      response.opaqueData.dataValue,
      })
    }
  })
}

defineExpose({ tokenize })
</script>

<template>
  <div class="space-y-3">
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">Card Number</label>
      <input
        v-model="cardNumber"
        type="text"
        inputmode="numeric"
        placeholder="1234 5678 9012 3456"
        maxlength="19"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
      />
    </div>
    <div class="grid grid-cols-3 gap-3">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Month</label>
        <input v-model="expMonth" type="text" placeholder="MM" maxlength="2"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-center" />
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Year</label>
        <input v-model="expYear" type="text" placeholder="YYYY" maxlength="4"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-center" />
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">CVV</label>
        <input v-model="cvv" type="text" placeholder="123" maxlength="4"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-center" />
      </div>
    </div>
    <p v-if="localError" class="text-red-500 text-xs">{{ localError }}</p>
  </div>
</template>
