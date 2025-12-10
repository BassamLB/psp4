<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, router, InfiniteScroll } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Edit,
    Save,
    X,
    Users,
    UserCheck,
    Plane,
} from 'lucide-vue-next';

interface Voter {
    id: number;
    sijil_number: string;
    first_name: string;
    family_name: string;
    father_name: string;
    mother_full_name: string;
    belong_id?: number;
    travelled: boolean;
    country_id?: number;
    profession_id?: number;
    deceased: boolean;
    mobile_number?: string;
    admin_notes?: string;
    family_id?: number;
    date_of_birth?: string | null;
    town?: { id: number; name: string };
    gender?: { id: number; name: string };
    sect?: { id: number; name: string };
    profession?: { id: number; name: string };
    country?: { id: number; name: string };
    belong?: { id: number; name: string };
}

interface FilterOptions {
    genders: Array<{ id: number; name: string }>;
    sects: Array<{ id: number; name: string }>;
    professions: Array<{ id: number; name: string }>;
    countries: Array<{ id: number; name: string }>;
    belongs: Array<{ id: number; name: string }>;
    towns: Array<{ id: number; name: string }>;
}

interface Props {
    voters: {
        data: Voter[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url?: string;
            label: string;
            active: boolean;
        }>;
    };
    filters: FilterOptions;
    currentFilters?: {
        search_sijil?: string;
        search_family_name?: string;
        gender_id?: number;
        sect_id?: number;
        profession_id?: number;
        town_id?: number;
        has_belong?: string;
        is_deceased?: string;
        is_travelled?: string;
    };
    message?: string;
}

const props = withDefaults(defineProps<Props>(), {
    currentFilters: () => ({}),
});

// Edit modal state
const editingVoter = ref<Voter | null>(null);
const editForm = ref<Partial<Voter>>({});
const isEditModalOpen = ref(false);
const isSubmitting = ref(false);

// Open edit modal
const openEditModal = (voter: Voter) => {
    editingVoter.value = voter;
    editForm.value = {
        belong_id: voter.belong_id,
        travelled: voter.travelled,
        country_id: voter.country_id,
        profession_id: voter.profession_id,
        deceased: voter.deceased,
        mobile_number: voter.mobile_number || '',
        admin_notes: voter.admin_notes || '',
    };
    isEditModalOpen.value = true;
};

// Close edit modal
const closeEditModal = () => {
    editingVoter.value = null;
    editForm.value = {};
    isEditModalOpen.value = false;
    isSubmitting.value = false;
};

// Save voter changes
const saveVoter = async () => {
    if (!editingVoter.value) return;

    isSubmitting.value = true;

    try {
        await router.put(`/data-editor/voters/${editingVoter.value.id}`, editForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                closeEditModal();
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
            }
        });
    } finally {
        isSubmitting.value = false;
    }
};

// Get status badge for voter
const getStatusBadge = (voter: Voter) => {
    const badges = [];

    if (voter.deceased) {
        badges.push({ text: 'متوفى', variant: 'destructive' as const, icon: X });
    }

    if (voter.travelled) {
        badges.push({ text: 'مسافر', variant: 'secondary' as const, icon: Plane });
    }

    if (voter.belong_id) {
        badges.push({ text: 'منتمي', variant: 'default' as const, icon: UserCheck });
    }

    return badges;
};

// Compute rounded age in years from date_of_birth (returns null if unknown)
const getAge = (voter: Voter): number | null => {
    if (!voter.date_of_birth) return null;
    const dob = new Date(voter.date_of_birth);
    if (isNaN(dob.getTime())) return null;
    const now = new Date();
    const diffMs = now.getTime() - dob.getTime();
    const years = Math.floor(diffMs / (365.25 * 24 * 60 * 60 * 1000));
    return years;
};

// Computed stats
const stats = computed(() => ({
    total: props.voters.total,
    hasBelogn: props.voters.data.filter(v => v.belong_id).length,
    travelled: props.voters.data.filter(v => v.travelled).length,
    deceased: props.voters.data.filter(v => v.deceased).length,
}));

const breadcrumbs = [
    {
        title: 'محرر البيانات',
        href: '/data-editor',
    },
];

// Debug flag to enable manual load-more tools and logging (use ?debug_load_more=1)
const debugLoadMore = Boolean(new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('debug_load_more'));

// InfiniteScroll ref + manual helpers
const infiniteScrollRef = ref<any>(null);
const isLoadingMore = ref(false);
const loadMoreError = ref<string | null>(null);

const loadNextPage = async () => {
    try {
        isLoadingMore.value = true;
        loadMoreError.value = null;

        if (infiniteScrollRef.value?.fetchNext) {
            await infiniteScrollRef.value.fetchNext();
        } else {
            if (!props.voters || props.voters.current_page >= props.voters.last_page) return;
            const next = props.voters.current_page + 1;
            await router.get(window.location.pathname, { voters: next }, {
                preserveState: true,
                preserveScroll: true,
                only: ['voters'],
            });
        }
    } catch (e) {
        loadMoreError.value = 'خطأ عند تحميل الصفحة التالية';
        // eslint-disable-next-line no-console
        console.error(e);
    } finally {
        isLoadingMore.value = false;
    }
};

onMounted(() => {
    if (debugLoadMore) {
        // eslint-disable-next-line no-console
        console.log('Initial props.voters', props.voters);
    }
});

// Watch for page changes so we can see whether the client is auto-fetching
watch(
    () => props.voters?.current_page,
    (current, previous) => {
        if (debugLoadMore) {
            // eslint-disable-next-line no-console
            console.log(`voters current_page changed: ${previous} -> ${current}`);
            // eslint-disable-next-line no-console
            console.log('voters.links', props.voters?.links);
        }
    }
);
</script>

<template>

    <Head title="محرر البيانات - إدارة الناخبين" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Main Content -->
        <div class="flex flex-col h-full overflow-hidden">
            <!-- Header Stats -->
            <div class="p-6 border-b bg-muted/30">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-2xl font-bold">إدارة بيانات الناخبين</h1>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">إجمالي الناخبين</CardTitle>
                            <Users class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.total.toLocaleString() }}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">المنتمون</CardTitle>
                            <UserCheck class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-green-600">{{ stats.hasBelogn }}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">المسافرون</CardTitle>
                            <Plane class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-blue-600">{{ stats.travelled }}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">المتوفون</CardTitle>
                            <X class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-red-600">{{ stats.deceased }}</div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Message -->
                <div v-if="message" class="mt-4">
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                        <p class="text-yellow-800">{{ message }}</p>
                    </div>
                </div>
            </div>

            <!-- Voters Table -->
            <div class="flex-1 overflow-auto p-6 scroll-region">
                <div class="rounded-md border">

                    <!-- Make InfiniteScroll manual to avoid auto-fetching multiple pages at once -->
                    <InfiniteScroll ref="infiniteScrollRef" manual data="voters" items-element="#table-body">

                        <div class="p-3 border-b bg-gray-50 flex gap-2 items-center">
                            <button class="px-3 py-1 rounded bg-blue-600 text-white text-sm" @click="loadNextPage" :disabled="isLoadingMore">
                                تحميل الصفحة التالية
                            </button>
                            <span class="text-sm text-muted-foreground">الصفحة الحالية: {{ props.voters.current_page }} / {{ props.voters.last_page }}</span>
                            <span v-if="loadMoreError" class="text-sm text-red-600">{{ loadMoreError }}</span>
                        </div>

                        <table class="min-w-full divide-y divide-gray-200 rtl:text-right">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-[100px] px-4 py-2 text-sm font-medium text-gray-700">رقم السجل</th>
                                    <th class="px-4 py-2 text-sm font-medium text-gray-700">الاسم الكامل</th>
                                    <th class="px-4 py-2 text-sm font-medium text-gray-700">البلدة</th>
                                    <th class="px-4 py-2 text-sm font-medium text-gray-700">الجنس</th>
                                    <th class="px-4 py-2 text-sm font-medium text-gray-700">الطائفة</th>
                                    <th class="px-4 py-2 text-sm font-medium text-gray-700">المهنة</th>
                                    <th class="px-4 py-2 text-sm font-medium text-gray-700">الحالة</th>
                                    <th class="w-[100px] px-4 py-2 text-sm font-medium text-gray-700">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="table-body" class="bg-white divide-y divide-gray-100">
                                <tr v-for="voter in props.voters.data" :key="voter.id">
                                    <td class="px-4 py-2 align-top font-medium">{{ voter.sijil_number }}</td>
                                    <td class="px-4 py-2 align-top">
                                        <div class="font-medium">
                                            {{ voter.first_name }} {{ voter.father_name }} {{ voter.family_name }}
                                            <span v-if="getAge(voter) !== null" class="text-sm text-muted-foreground mr-2 bg-">({{ getAge(voter) }})</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 align-top">{{ voter.town?.name }}</td>
                                    <td class="px-4 py-2 align-top">{{ voter.gender?.name }}</td>
                                    <td class="px-4 py-2 align-top">{{ voter.sect?.name }}</td>
                                    <td class="px-4 py-2 align-top">{{ voter.profession?.name || 'غير محدد' }}</td>
                                    <td class="px-4 py-2 align-top">
                                        <div class="flex flex-wrap gap-1">
                                            <Badge v-for="badge in getStatusBadge(voter)" :key="badge.text"
                                                :variant="badge.variant" class="text-xs">
                                                <component :is="badge.icon" class="w-3 h-3 mr-1" />
                                                {{ badge.text }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 align-top">
                                        <Button size="sm" variant="outline" @click="openEditModal(voter)">
                                            <Edit class="h-3 w-3" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                    </InfiniteScroll>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>تعديل بيانات الناخب</DialogTitle>
                    <DialogDescription>
                        تعديل البيانات المسموح تغييرها للناخب: {{ editingVoter?.first_name }} {{
                        editingVoter?.family_name }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <!-- Belong Selection -->
                    <div class="space-y-2">
                        <Label>الانتماء</Label>
                        <Select :model-value="editForm.belong_id?.toString() || ''"
                            @update:model-value="(value: string) => editForm.belong_id = value ? Number(value) : undefined">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر الانتماء" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">بدون انتماء</SelectItem>
                                <SelectItem v-for="belong in props.filters.belongs" :key="belong.id"
                                    :value="belong.id.toString()">
                                    {{ belong.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Travel Status -->
                    <div class="space-y-2">
                        <Label>حالة السفر</Label>
                        <Select :model-value="editForm.travelled?.toString() || 'false'"
                            @update:model-value="(value: string) => editForm.travelled = value === 'true'">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر حالة السفر" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="false">مقيم</SelectItem>
                                <SelectItem value="true">مسافر</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Country (if travelled) -->
                    <div v-if="editForm.travelled" class="space-y-2">
                        <Label>بلد السفر</Label>
                        <Select :model-value="editForm.country_id?.toString() || ''"
                            @update:model-value="(value: string) => editForm.country_id = value ? Number(value) : undefined">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر بلد السفر" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="country in props.filters.countries" :key="country.id"
                                    :value="country.id.toString()">
                                    {{ country.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Profession -->
                    <div class="space-y-2">
                        <Label>المهنة</Label>
                        <Select :model-value="editForm.profession_id?.toString() || ''"
                            @update:model-value="(value: string) => editForm.profession_id = value ? Number(value) : undefined">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر المهنة" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">غير محدد</SelectItem>
                                <SelectItem v-for="profession in props.filters.professions" :key="profession.id"
                                    :value="profession.id.toString()">
                                    {{ profession.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Deceased Status -->
                    <div class="space-y-2">
                        <Label>حالة الوفاة</Label>
                        <Select :model-value="editForm.deceased?.toString() || 'false'"
                            @update:model-value="(value: string) => editForm.deceased = value === 'true'">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر حالة الوفاة" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="false">على قيد الحياة</SelectItem>
                                <SelectItem value="true">متوفى</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Mobile Number -->
                    <div class="space-y-2">
                        <Label>رقم الهاتف</Label>
                        <Input v-model="editForm.mobile_number" placeholder="رقم الهاتف المحمول" type="tel" />
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label>ملاحظات إدارية</Label>
                        <textarea v-model="editForm.admin_notes" class="w-full p-2 border rounded-md resize-none"
                            rows="3" placeholder="ملاحظات إدارية..." />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeEditModal">إلغاء</Button>
                    <Button @click="saveVoter" :disabled="isSubmitting">
                        <Save class="h-4 w-4 mr-1" />
                        حفظ التغييرات
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>