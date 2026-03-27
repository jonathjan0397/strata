<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
    methods: Array,
});

// ---- Stripe setup intent flow ----
const addingCard = ref(false);
const cardError = ref(null);
const cardProcessing = ref(false);
let stripe = null;
let elements = null;
let cardElement = null;

async function openAddCard() {
    addingCard.value = true;
    cardError.value = null;

    await nextTick();

    const res = await fetch(route('client.payment-methods.setup-intent'));
    const { clientSecret } = await res.json();

    stripe = Stripe(window.stripeKey);
    elements = stripe.elements();
    cardElement = elements.create('card', { style: { base: { fontSize: '14px' } } });
    cardElement.mount('#card-element');
    cardElement.on('change', (e) => { cardError.value = e.error?.message ?? null; });

    window.__clientSecret = clientSecret;
}

async function confirmCard() {
    cardProcessing.value = true;
    cardError.value = null;

    const { error, setupIntent } = await stripe.confirmCardSetup(window.__clientSecret, {
        payment_method: { card: cardElement },
    });

    if (error) {
        cardError.value = error.message;
        cardProcessing.value = false;
        return;
    }

    router.post(route('client.payment-methods.store'), {
        payment_method_id: setupIntent.payment_method,
    }, {
        onFinish: () => { cardProcessing.value = false; addingCard.value = false; },
    });
}

function setDefault(id) {
    router.post(route('client.payment-methods.default', id));
}

function remove(id) {
    if (confirm('Remove this card?')) {
        router.delete(route('client.payment-methods.destroy', id));
    }
}

const brandIcon = (brand) => {
    const icons = { visa: '💳', mastercard: '💳', amex: '💳', discover: '💳' };
    return icons[brand] ?? '💳';
};
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Payment Methods</h1>
        </template>

        <div class="max-w-2xl mx-auto space-y-4">

            <!-- Saved cards -->
            <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
                <div v-for="method in methods" :key="method.id" class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">{{ brandIcon(method.brand) }}</span>
                        <div>
                            <p class="text-sm font-medium text-gray-900 capitalize">{{ method.brand }} ···· {{ method.last4 }}</p>
                            <p class="text-xs text-gray-500">Expires {{ method.expiry }}</p>
                        </div>
                        <span v-if="method.is_default" class="ml-2 inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Default</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button v-if="!method.is_default" @click="setDefault(method.id)" class="text-xs text-indigo-600 hover:underline">Set default</button>
                        <button @click="remove(method.id)" class="text-xs text-red-500 hover:underline">Remove</button>
                    </div>
                </div>
                <div v-if="!methods.length" class="px-6 py-10 text-center text-sm text-gray-500">
                    No cards saved yet.
                </div>
            </div>

            <!-- Add card -->
            <div v-if="!addingCard">
                <button @click="openAddCard" class="rounded-md border border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                    + Add Card
                </button>
            </div>

            <div v-else class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900">Add a new card</h2>
                <div id="card-element" class="rounded-md border border-gray-300 p-3" />
                <p v-if="cardError" class="text-xs text-red-600">{{ cardError }}</p>
                <div class="flex gap-3">
                    <button @click="confirmCard" :disabled="cardProcessing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ cardProcessing ? 'Saving…' : 'Save Card' }}
                    </button>
                    <button @click="addingCard = false" class="text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
