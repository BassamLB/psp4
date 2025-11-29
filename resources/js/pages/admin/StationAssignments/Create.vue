<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'

interface User {
    id: number
    name: string
    email: string
    role: string
}

interface PollingStation {
    id: number
    station_number: number
    station_location: string
    town_name: string
    district_name: string
    electoral_district: string
}

interface Props {
    users: User[]
    pollingStations: PollingStation[]
}

const props = defineProps<Props>()

const form = useForm({
    user_id: null as number | null,
    polling_station_id: null as number | null,
    role: 'counter' as 'counter' | 'verifier' | 'supervisor',
})

const stationSearch = ref('')
const userSearch = ref('')

const filteredStations = computed(() => {
    if (!stationSearch.value) return props.pollingStations
    const search = stationSearch.value.toLowerCase()
    return props.pollingStations.filter(
        (station) =>
            station.station_number.toString().includes(search) ||
            station.town_name.toLowerCase().includes(search) ||
            station.district_name.toLowerCase().includes(search) ||
            station.electoral_district.toLowerCase().includes(search)
    )
})

const filteredUsers = computed(() => {
    if (!userSearch.value) return props.users
    const search = userSearch.value.toLowerCase()
    return props.users.filter(
        (user) =>
            user.name.toLowerCase().includes(search) ||
            user.email.toLowerCase().includes(search) ||
            user.role?.toLowerCase().includes(search)
    )
})

const submit = () => {
    form.post('/admin/station-assignments', {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="إضافة تعيين جديد" />

    <AdminLayout>
        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">إضافة تعيين جديد</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        قم بتعيين مستخدم إلى قلم اقتراع محدد
                    </p>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="space-y-6">
                        <!-- User Selection -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                                المستخدم *
                            </label>
                            <input
                                v-model="userSearch"
                                type="text"
                                placeholder="ابحث عن مستخدم..."
                                class="mb-2 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            <select
                                v-model="form.user_id"
                                required
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option :value="null">-- اختر مستخدم --</option>
                                <option v-for="user in filteredUsers" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }}) - {{ user.role }}
                                </option>
                            </select>
                            <div v-if="form.errors.user_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.user_id }}
                            </div>
                        </div>

                        <!-- Polling Station Selection -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                                قلم الاقتراع *
                            </label>
                            <input
                                v-model="stationSearch"
                                type="text"
                                placeholder="ابحث عن قلم اقتراع..."
                                class="mb-2 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            <select
                                v-model="form.polling_station_id"
                                required
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option :value="null">-- اختر قلم اقتراع --</option>
                                <option v-for="station in filteredStations" :key="station.id" :value="station.id">
                                    قلم {{ station.station_number }} - {{ station.town_name }} ({{ station.district_name }}, {{ station.electoral_district }}) - {{ station.station_location }}
                                </option>
                            </select>
                            <div v-if="form.errors.polling_station_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.polling_station_id }}
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                                الدور *
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 rounded-lg border border-gray-200 p-4 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                    <input v-model="form.role" type="radio" value="counter" class="size-4 text-blue-600" />
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">عداد</div>
                                        <div class="text-xs text-gray-500">مسؤول عن إدخال أوراق الاقتراع</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 rounded-lg border border-gray-200 p-4 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                    <input v-model="form.role" type="radio" value="verifier" class="size-4 text-blue-600" />
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">مدقق</div>
                                        <div class="text-xs text-gray-500">مسؤول عن التحقق من دقة البيانات</div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 rounded-lg border border-gray-200 p-4 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                    <input v-model="form.role" type="radio" value="supervisor" class="size-4 text-blue-600" />
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">مشرف</div>
                                        <div class="text-xs text-gray-500">مسؤول عن الإشراف على العملية</div>
                                    </div>
                                </label>
                            </div>
                            <div v-if="form.errors.role" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.role }}
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-700"
                            >
                                {{ form.processing ? 'جاري الحفظ...' : 'حفظ التعيين' }}
                            </button>
                            <button
                                type="button"
                                @click="router.visit('/admin/station-assignments')"
                                class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
