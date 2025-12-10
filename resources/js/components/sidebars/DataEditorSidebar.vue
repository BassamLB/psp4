<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarGroupContent,
} from '@/components/ui/sidebar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { LayoutGrid, Filter } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';

const side = computed(() => (typeof document !== 'undefined' && document.documentElement?.dir === 'rtl' ? 'right' : 'left'));

const page = usePage();

// Access filters from the page props
const filterOptions = computed(() => (page.props as any).filters || {
    genders: [],
    sects: [],
    professions: [],
    countries: [],
    belongs: [],
    towns: [],
});

const currentFilters = computed(() => (page.props as any).currentFilters || {});

// Reactive filter state
const filters = ref({
    search_sijil: currentFilters.value?.search_sijil || '',
    search_family_name: currentFilters.value?.search_family_name || '',
    gender_id: currentFilters.value?.gender_id?.toString() || '',
    sect_id: currentFilters.value?.sect_id?.toString() || '',
    profession_id: currentFilters.value?.profession_id?.toString() || '',
    town_id: currentFilters.value?.town_id?.toString() || '',
    has_belong: currentFilters.value?.has_belong || '',
    is_deceased: currentFilters.value?.is_deceased || '',
    is_travelled: currentFilters.value?.is_travelled || '',
});

// Apply filters to search
const applyFilters = () => {
    const filterParams = Object.fromEntries(
        Object.entries(filters.value).filter(([, value]) => value !== '')
    );

    // Don't preserve component state when applying filters so InfiniteScroll resets
    router.get('/data-editor', filterParams, {
        preserveScroll: true,
    });
};

// Clear all filters
const clearFilters = () => {
    Object.keys(filters.value).forEach(key => {
        filters.value[key as keyof typeof filters.value] = '';
    });
    // Clear filters and reload without preserving state so pagination resets
    router.get('/data-editor', {});
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
</script>

<template>
    <Sidebar :side="side" collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child tooltip="الرئيسية">
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="overflow-y-auto">
            <!-- Dashboard Link -->
            <SidebarGroup>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton as-child tooltip="لوحة التحكم">
                                <Link :href="dashboard()">
                                    <LayoutGrid class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                                    <span class="group-data-[collapsible=icon]:hidden">لوحة التحكم</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Filters Section -->
            <SidebarGroup class="group-data-[collapsible=icon]:hidden">
                <SidebarGroupLabel class="flex items-center gap-2">
                    <Filter class="h-4 w-4" />
                    تصفية البيانات
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <div class="space-y-4 px-2">
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
                                            v-for="town in filterOptions.towns" 
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
                                            v-for="gender in filterOptions.genders" 
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
                                            v-for="sect in filterOptions.sects" 
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
                                            v-for="profession in filterOptions.professions" 
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
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>

