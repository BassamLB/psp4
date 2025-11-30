<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'

interface Assignment {
    id: number
    user: {
        name: string
        email: string
    }
    polling_station: {
        station_number: number
        town: {
            name: string
        }
    }
    role: string
    is_active: boolean
    assigned_at: string
    assigned_by: {
        name: string
    }
}

interface Props {
    assignments: {
        data: Assignment[]
        links: any[]
        current_page: number
        last_page: number
    }
    filters: {
        search?: string
        role?: string
        is_active?: string
    }
}

const props = defineProps<Props>()

const search = ref(props.filters.search || '')
const roleFilter = ref(props.filters.role || '')
const statusFilter = ref(props.filters.is_active || '')

const applyFilters = () => {
    router.get('/admin/station-assignments', {
        search: search.value,
        role: roleFilter.value,
        is_active: statusFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const toggleStatus = (assignmentId: number) => {
    router.post(`/admin/station-assignments/${assignmentId}/toggle`, {}, {
        preserveScroll: true,
    })
}

const deleteAssignment = (assignmentId: number) => {
    if (confirm('هل أنت متأكد من حذف هذا التعيين؟')) {
        router.delete(`/admin/station-assignments/${assignmentId}`, {
            preserveScroll: true,
        })
    }
}

const getRoleLabel = (role: string) => {
    const roles: Record<string, string> = {
        counter: 'عداد',
        verifier: 'مدقق',
        supervisor: 'مشرف',
    }
    return roles[role] || role
}
</script>

<template>
    <Head title="تعيينات الأقلام" />

    <AdminLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">تعيينات الأقلام</h1>
                    <Link
                        href="/admin/station-assignments/create"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700"
                    >
                        + إضافة تعيين جديد
                    </Link>
                </div>

                <!-- Filters -->
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">بحث</label>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="اسم المستخدم أو رقم القلم..."
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">الدور</label>
                            <select
                                v-model="roleFilter"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                @change="applyFilters"
                            >
                                <option value="">الكل</option>
                                <option value="counter">عداد</option>
                                <option value="verifier">مدقق</option>
                                <option value="supervisor">مشرف</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">الحالة</label>
                            <select
                                v-model="statusFilter"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                @change="applyFilters"
                            >
                                <option value="">الكل</option>
                                <option value="1">نشط</option>
                                <option value="0">غير نشط</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button
                                type="button"
                                class="w-full rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:border-gray-600 dark:hover:bg-gray-700"
                                @click="search = ''; roleFilter = ''; statusFilter = ''; applyFilters()"
                            >
                                إعادة تعيين
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">المستخدم</th>
                                    <th scope="col" class="px-6 py-3">رقم القلم</th>
                                    <th scope="col" class="px-6 py-3">البلدة</th>
                                    <th scope="col" class="px-6 py-3">الدور</th>
                                    <th scope="col" class="px-6 py-3">الحالة</th>
                                    <th scope="col" class="px-6 py-3">تاريخ التعيين</th>
                                    <th scope="col" class="px-6 py-3">عُيّن بواسطة</th>
                                    <th scope="col" class="px-6 py-3">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="assignment in assignments.data"
                                    :key="assignment.id"
                                    class="border-b bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-600"
                                >
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ assignment.user.name }}</div>
                                        <div class="text-xs text-gray-500">{{ assignment.user.email }}</div>
                                    </td>
                                    <td class="px-6 py-4 font-medium">{{ assignment.polling_station.station_number }}</td>
                                    <td class="px-6 py-4">{{ assignment.polling_station.town.name }}</td>
                                    <td class="px-6 py-4">
                                        <span class="rounded bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            {{ getRoleLabel(assignment.role) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            v-if="assignment.is_active"
                                            class="rounded bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300"
                                        >
                                            نشط
                                        </span>
                                        <span
                                            v-else
                                            class="rounded bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            غير نشط
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs">{{ new Date(assignment.assigned_at).toLocaleDateString('ar-LB') }}</td>
                                    <td class="px-6 py-4 text-xs">{{ assignment.assigned_by.name }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-500"
                                                @click="toggleStatus(assignment.id)"
                                            >
                                                {{ assignment.is_active ? 'تعطيل' : 'تفعيل' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="text-sm font-medium text-red-600 hover:underline dark:text-red-500"
                                                @click="deleteAssignment(assignment.id)"
                                            >
                                                حذف
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="assignments.data.length === 0">
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                        لا توجد تعيينات
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="assignments.links.length > 3" class="border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700 dark:text-gray-400">
                                صفحة {{ assignments.current_page }} من {{ assignments.last_page }}
                            </div>
                            <div class="flex gap-2">
                                <Link
                                    v-for="link in assignments.links"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    :class="[
                                        'rounded px-3 py-1 text-sm',
                                        link.active
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300',
                                        !link.url && 'cursor-not-allowed opacity-50',
                                    ]"
                                >
                                    {{ link.label }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
