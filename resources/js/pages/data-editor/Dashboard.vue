<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
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
    Search, 
    Edit, 
    Save, 
    X, 
    Filter,
    Users,
    Building2,
    UserCheck,
    UserX,
    Plane,
    Briefcase,
    Heart
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

const page = usePage();

// Reactive filter state
const filters = ref({
    search_sijil: props.currentFilters?.search_sijil || '',
    search_family_name: props.currentFilters?.search_family_name || '',
    gender_id: props.currentFilters?.gender_id?.toString() || '',
    sect_id: props.currentFilters?.sect_id?.toString() || '',
    profession_id: props.currentFilters?.profession_id?.toString() || '',
    town_id: props.currentFilters?.town_id?.toString() || '',
    has_belong: props.currentFilters?.has_belong || '',
    is_deceased: props.currentFilters?.is_deceased || '',
    is_travelled: props.currentFilters?.is_travelled || '',
});

// Edit modal state
const editingVoter = ref<Voter | null>(null);
const editForm = ref<Partial<Voter>>({});
const isEditModalOpen = ref(false);
const isSubmitting = ref(false);

// Sidebar state
const sidebarCollapsed = ref(false);

// Apply filters to search
const applyFilters = () => {
    const filterParams = Object.fromEntries(
        Object.entries(filters.value).filter(([_, value]) => value !== '')
    );

    router.get('/data-editor', filterParams, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Clear all filters
const clearFilters = () => {
    Object.keys(filters.value).forEach(key => {
        filters.value[key as keyof typeof filters.value] = '';
    });
    router.get('/data-editor', {}, { preserveState: true });
};

// Watch for filter changes and apply them with debouncing
let filterTimeout: number;
watch(
    () => [filters.value.search_sijil, filters.value.search_family_name],
    () => {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(applyFilters, 500);
    }
);

// Watch for select filter changes and apply immediately
watch(
    () => [
        filters.value.gender_id,
        filters.value.sect_id,
        filters.value.profession_id,
        filters.value.town_id,
        filters.value.has_belong,
        filters.value.is_deceased,
        filters.value.is_travelled,
    ],
    applyFilters
);

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
</script>

<template>
    <Head title="محرر البيانات - إدارة الناخبين" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full">
            <!-- Sidebar Filters -->
            <div 
                :class="[
                    'bg-background border-r transition-all duration-300 flex flex-col',
                    sidebarCollapsed ? 'w-12' : 'w-80'
                ]"
            >
                <!-- Sidebar Header -->
                <div class="p-4 border-b flex items-center justify-between">
                    <div v-if="!sidebarCollapsed">
                        <h3 class="font-semibold flex items-center gap-2">
                            <Filter class="h-4 w-4" />
                            تصفية البيانات
                        </h3>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                    >
                        <Filter class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Filters Content -->
                <div 
                    v-if="!sidebarCollapsed" 
                    class="flex-1 p-4 space-y-4 overflow-y-auto"
                >
                    <!-- Search Filters -->
                    <div class="space-y-3">
                        <div class="space-y-2">
                            <Label class="text-sm font-medium">البحث برقم السجل</Label>
                            <Input
                                v-model="filters.search_sijil"
                                placeholder="ادخل رقم السجل..."
                                class="h-9"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium">البحث باسم العائلة</Label>
                            <Input
                                v-model="filters.search_family_name"
                                placeholder="ادخل اسم العائلة..."
                                class="h-9"
                            />
                        </div>
                    </div>

                    <!-- Select Filters -->
                    <div class="space-y-3">
                        <div class="space-y-2">
                            <Label class="text-sm font-medium">البلدة</Label>
                            <Select v-model="filters.town_id">
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="اختر البلدة" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">جميع البلدات</SelectItem>
                                    <SelectItem 
                                        v-for="town in props.filters.towns" 
                                        :key="town.id" 
                                        :value="town.id.toString()"
                                    >
                                        {{ town.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium">الجنس</Label>
                            <Select v-model="filters.gender_id">
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="اختر الجنس" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">جميع الأجناس</SelectItem>
                                    <SelectItem 
                                        v-for="gender in props.filters.genders" 
                                        :key="gender.id" 
                                        :value="gender.id.toString()"
                                    >
                                        {{ gender.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium">الطائفة</Label>
                            <Select v-model="filters.sect_id">
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="اختر الطائفة" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">جميع الطوائف</SelectItem>
                                    <SelectItem 
                                        v-for="sect in props.filters.sects" 
                                        :key="sect.id" 
                                        :value="sect.id.toString()"
                                    >
                                        {{ sect.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium">المهنة</Label>
                            <Select v-model="filters.profession_id">
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="اختر المهنة" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">جميع المهن</SelectItem>
                                    <SelectItem 
                                        v-for="profession in props.filters.professions" 
                                        :key="profession.id" 
                                        :value="profession.id.toString()"
                                    >
                                        {{ profession.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium">الانتماء</Label>
                            <Select v-model="filters.has_belong">
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="حالة الانتماء" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">الجميع</SelectItem>
                                    <SelectItem value="yes">منتمي</SelectItem>
                                    <SelectItem value="no">غير منتمي</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium">المتوفون</Label>
                            <Select v-model="filters.is_deceased">
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="حالة الوفاة" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">الجميع</SelectItem>
                                    <SelectItem value="yes">متوفى</SelectItem>
                                    <SelectItem value="no">على قيد الحياة</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium">المسافرون</Label>
                            <Select v-model="filters.is_travelled">
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="حالة السفر" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">الجميع</SelectItem>
                                    <SelectItem value="yes">مسافر</SelectItem>
                                    <SelectItem value="no">مقيم</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Clear Filters -->
                    <div class="pt-4 border-t">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            @click="clearFilters"
                            class="w-full"
                        >
                            مسح جميع المرشحات
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col overflow-hidden">
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
                <div class="flex-1 overflow-auto p-6">
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[100px]">رقم السجل</TableHead>
                                    <TableHead>الاسم الكامل</TableHead>
                                    <TableHead>البلدة</TableHead>
                                    <TableHead>الجنس</TableHead>
                                    <TableHead>الطائفة</TableHead>
                                    <TableHead>المهنة</TableHead>
                                    <TableHead>الحالة</TableHead>
                                    <TableHead class="w-[100px]">الإجراءات</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="voter in props.voters.data" :key="voter.id">
                                    <TableCell class="font-medium">{{ voter.sijil_number }}</TableCell>
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">{{ voter.first_name }} {{ voter.family_name }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                والد: {{ voter.father_name }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>{{ voter.town?.name }}</TableCell>
                                    <TableCell>{{ voter.gender?.name }}</TableCell>
                                    <TableCell>{{ voter.sect?.name }}</TableCell>
                                    <TableCell>{{ voter.profession?.name || 'غير محدد' }}</TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge 
                                                v-for="badge in getStatusBadge(voter)" 
                                                :key="badge.text"
                                                :variant="badge.variant"
                                                class="text-xs"
                                            >
                                                <component :is="badge.icon" class="w-3 h-3 mr-1" />
                                                {{ badge.text }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Button 
                                            size="sm" 
                                            variant="outline" 
                                            @click="openEditModal(voter)"
                                        >
                                            <Edit class="h-3 w-3" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="props.voters.links" class="mt-6 flex items-center justify-center space-x-1 space-x-reverse">
                        <Button
                            v-for="link in props.voters.links"
                            :key="link.label"
                            :variant="link.active ? 'default' : 'outline'"
                            :disabled="!link.url"
                            size="sm"
                            @click="link.url && router.get(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>تعديل بيانات الناخب</DialogTitle>
                    <DialogDescription>
                        تعديل البيانات المسموح تغييرها للناخب: {{ editingVoter?.first_name }} {{ editingVoter?.family_name }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <!-- Belong Selection -->
                    <div class="space-y-2">
                        <Label>الانتماء</Label>
                        <Select :model-value="editForm.belong_id?.toString() || ''" @update:model-value="(value: string) => editForm.belong_id = value ? Number(value) : undefined">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر الانتماء" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">بدون انتماء</SelectItem>
                                <SelectItem 
                                    v-for="belong in props.filters.belongs" 
                                    :key="belong.id" 
                                    :value="belong.id.toString()"
                                >
                                    {{ belong.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Travel Status -->
                    <div class="space-y-2">
                        <Label>حالة السفر</Label>
                        <Select :model-value="editForm.travelled?.toString() || 'false'" @update:model-value="(value: string) => editForm.travelled = value === 'true'">
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
                        <Select :model-value="editForm.country_id?.toString() || ''" @update:model-value="(value: string) => editForm.country_id = value ? Number(value) : undefined">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر بلد السفر" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem 
                                    v-for="country in props.filters.countries" 
                                    :key="country.id" 
                                    :value="country.id.toString()"
                                >
                                    {{ country.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Profession -->
                    <div class="space-y-2">
                        <Label>المهنة</Label>
                        <Select :model-value="editForm.profession_id?.toString() || ''" @update:model-value="(value: string) => editForm.profession_id = value ? Number(value) : undefined">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر المهنة" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">غير محدد</SelectItem>
                                <SelectItem 
                                    v-for="profession in props.filters.professions" 
                                    :key="profession.id" 
                                    :value="profession.id.toString()"
                                >
                                    {{ profession.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Deceased Status -->
                    <div class="space-y-2">
                        <Label>حالة الوفاة</Label>
                        <Select :model-value="editForm.deceased?.toString() || 'false'" @update:model-value="(value: string) => editForm.deceased = value === 'true'">
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
                        <Input
                            v-model="editForm.mobile_number"
                            placeholder="رقم الهاتف المحمول"
                            type="tel"
                        />
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label>ملاحظات إدارية</Label>
                        <textarea
                            v-model="editForm.admin_notes"
                            class="w-full p-2 border rounded-md resize-none"
                            rows="3"
                            placeholder="ملاحظات إدارية..."
                        />
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