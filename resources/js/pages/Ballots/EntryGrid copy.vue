<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Echo from 'laravel-echo'
import BallotEntryLayout from '@/layouts/BallotEntryLayout.vue'
import Toast from '@/components/Toast.vue'

interface Candidate {
    id: number
    full_name: string
    sect?: string
}

interface List {
    id: number
    name: string
    color: string
    number: number
    candidates: Candidate[]
}

interface Props {
    station: {
        id: number
        station_number: number
        location: string
        town: { name: string }
    }
    lists: List[]
    summary: {
        total_ballots_entered: number
        valid_list_votes: number
        valid_preferential_votes: number
        white_papers: number
        cancelled_papers: number
        // per-field optimistic flags used by UI
        __optimistic_total?: boolean
        __optimistic_list?: boolean
        __optimistic_preferential?: boolean
        __optimistic_white?: boolean
        __optimistic_cancelled?: boolean
    }
    aggregates: Array<{
        list_id: number | null
        candidate_id: number | null
        vote_count: number
        list?: { name: string }
        candidate?: { full_name: string }
    }>
}

const props = defineProps<Props>()

const toast = ref<InstanceType<typeof Toast> | null>(null)

// Real-time data
const normalizeAggregate = (agg: any) => {
    return {
        list_id: agg.list_id != null ? Number(agg.list_id) : null,
        candidate_id: agg.candidate_id != null ? Number(agg.candidate_id) : null,
        vote_count: agg.vote_count != null ? Number(agg.vote_count) : 0,
        list: agg.list ?? null,
        candidate: agg.candidate ?? null,
        // server-provided aggregates are not optimistic
        optimistic: false,
    }
}

const aggregates = ref((props.aggregates || []).map(normalizeAggregate))
const summary = ref(props.summary || {
    total_ballots_entered: 0,
    valid_list_votes: 0,
    valid_preferential_votes: 0,
    white_papers: 0,
    cancelled_papers: 0,
})

// Form state
const selectedListId = ref<number | null>(null)
const selectedCandidateId = ref<number | null>(null)
const ballotMode = ref<'list' | 'preferential' | 'white' | 'cancelled' | null>(null)
const cancellationReason = ref<string>('')
const isSubmitting = ref(false)
const lastSubmitTime = ref(0)
const lastUpdate = ref<string | null>(null)

// Initialize Echo for real-time updates
const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
})

// Listen for real-time aggregate updates
onMounted(() => {
    console.log('Mounting EntryGrid, connecting to channel:', `station.${props.station.id}`)

    const channel = echo.private(`station.${props.station.id}`)

    channel
        .subscribed(() => {
            console.log('✅ Successfully subscribed to channel:', `station.${props.station.id}`)
        })
        .error((error: any) => {
            console.error('❌ Channel subscription error:', error)
        })
        .listen('.results.updated', async (data: any) => {
            console.log('✅ Results updated event received:', data)
            // Fetch fresh aggregates and summary
            try {
                const response = await fetch(`/api/stations/${props.station.id}/results`)
                if (response.ok) {
                    const freshData = await response.json()
                    if (freshData.aggregates) {
                        aggregates.value = freshData.aggregates.map(normalizeAggregate)
                    }
                    if (freshData.summary) {
                        summary.value = freshData.summary
                    }
                    lastUpdate.value = new Date().toLocaleTimeString('ar-LB')
                    console.log('✅ Results updated successfully')
                }
            } catch (error) {
                console.error('Failed to fetch updated results:', error)
            }
        })

    console.log('Echo channel setup complete')
})

onUnmounted(() => {
    echo.leave(`station.${props.station.id}`)
})

// Computed properties
const listAggregates = computed(() => {
    return aggregates.value
        .filter(agg => agg.list_id !== null && agg.candidate_id === null)
        .sort((a, b) => (b.vote_count || 0) - (a.vote_count || 0))
})

const cancellationReasons = ['اكثر من ورقة ضمن المغلف', 'ورقة غير رسمية', 'اكثر من لائحة', 'اكثر من صوت تفضيلي', 'علامات مميزة', 'تفضيلي لمرشح في دائرة اخرى', 'اسباب اخرى']

const candidateAggregates = computed(() => {
    return aggregates.value
        .filter(agg => agg.candidate_id !== null)
        .sort((a, b) => (b.vote_count || 0) - (a.vote_count || 0))
})

const selectedList = computed(() => {
    return props.lists.find(list => list.id === selectedListId.value)
})

// Dynamic grid style: one column per list so they sit side-by-side
const listsGridStyle = computed(() => {
    const n = Math.max(1, (props.lists || []).length)
    return { gridTemplateColumns: `repeat(${n}, minmax(0, 1fr))` }
})

const canSubmit = computed(() => {
    if (ballotMode.value === 'white') {
        return true
    }
    if (ballotMode.value === 'cancelled') {
        return cancellationReason.value.trim() !== ''
    }
    if (ballotMode.value === 'list') {
        return selectedListId.value !== null
    }
    if (ballotMode.value === 'preferential') {
        return selectedListId.value !== null && selectedCandidateId.value !== null
    }
    return false
})

const getButtonText = computed(() => {
    if (isSubmitting.value) {
        return 'جارٍ الإدخال...'
    }

    if (ballotMode.value === 'white') {
        return 'تأكيد ورقة بيضاء'
    }
    if (ballotMode.value === 'cancelled') {
        return 'تأكيد ورقة ملغاة'
    }
    if (ballotMode.value === 'list') {
        return 'تأكيد صوت اللائحة'
    }
    if (ballotMode.value === 'preferential') {
        return 'تأكيد الصوت التفضيلي'
    }

    return 'تأكيد إدخال الورقة'
})

// Methods
const handleCandidateClick = (listId: number, candidateId: number) => {
    if (ballotMode.value === 'white' || ballotMode.value === 'cancelled') {
        return
    }

    selectedListId.value = listId
    selectedCandidateId.value = candidateId
    ballotMode.value = 'preferential'
}

const handleListClick = (listId: number) => {
    if (ballotMode.value === 'white' || ballotMode.value === 'cancelled') {
        return
    }

    selectedListId.value = listId
    selectedCandidateId.value = null
    ballotMode.value = 'list'
}

const handleWhitePaper = () => {
    selectedListId.value = null
    selectedCandidateId.value = null
    ballotMode.value = 'white'
    cancellationReason.value = ''
}

const handleCancelledPaper = () => {
    selectedListId.value = null
    selectedCandidateId.value = null
    ballotMode.value = 'cancelled'
}

const clearSelection = () => {
    selectedListId.value = null
    selectedCandidateId.value = null
    ballotMode.value = null
    cancellationReason.value = ''
}

const submitBallot = async () => {
    if (!canSubmit.value || isSubmitting.value) {
        return
    }

    // Cooldown check (500ms between submissions)
    const now = Date.now()
    if (now - lastSubmitTime.value < 500) {
        return
    }
    lastSubmitTime.value = now

    isSubmitting.value = true

    try {
        const payload: any = {
            station_id: props.station.id,
            ballot_type: ballotMode.value === 'preferential' ? 'valid_preferential' :
                ballotMode.value === 'list' ? 'valid_list' :
                    ballotMode.value === 'white' ? 'white' : 'cancelled',
        }

        if (ballotMode.value === 'list' || ballotMode.value === 'preferential') {
            payload.list_id = selectedListId.value
        }

        if (ballotMode.value === 'preferential') {
            payload.candidate_id = selectedCandidateId.value
        }

        if (ballotMode.value === 'cancelled') {
            payload.cancellation_reason = cancellationReason.value
        }
        console.log('Submitting ballot:', payload)
        const response = await fetch(`/api/stations/${props.station.id}/ballots`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(payload),
        })

        if (!response.ok) {
            const errorData = await response.json()
            throw new Error(errorData.message || 'فشل إدخال الورقة')
        }

        // Show success message
        let message = ''
        const ballotInfo: any = { type: ballotMode.value }

        if (ballotMode.value === 'white') {
            message = '✓ تم إدخال ورقة بيضاء'
        } else if (ballotMode.value === 'cancelled') {
            message = `✓ تم إدخال ورقة ملغاة: ${cancellationReason.value}`
            ballotInfo.reason = cancellationReason.value
        } else if (ballotMode.value === 'list') {
            const listName = selectedList.value?.name || ''
            message = `✓ تم إدخال صوت للائحة: ${listName}`
            ballotInfo.listName = listName
        } else if (ballotMode.value === 'preferential') {
            const listName = selectedList.value?.name || ''
            const candidateName = selectedList.value?.candidates.find(c => c.id === selectedCandidateId.value)?.full_name || ''
            message = `✓ تم إدخال صوت تفضيلي: ${candidateName} (${listName})`
            ballotInfo.listName = listName
            ballotInfo.candidateName = candidateName
        }

        toast.value?.addToast(message, 'success', 4000)

        // Optimistic UI update: apply the new ballot to the local aggregates & summary
        try {
            // Update summary totals
            summary.value.total_ballots_entered = (Number(summary.value.total_ballots_entered) || 0) + 1

            if (ballotMode.value === 'white') {
                summary.value.white_papers = (Number(summary.value.white_papers) || 0) + 1
            } else if (ballotMode.value === 'cancelled') {
                summary.value.cancelled_papers = (Number(summary.value.cancelled_papers) || 0) + 1
            } else if (ballotMode.value === 'list') {
                summary.value.valid_list_votes = (Number(summary.value.valid_list_votes) || 0) + 1
            } else if (ballotMode.value === 'preferential') {
                summary.value.valid_preferential_votes = (Number(summary.value.valid_preferential_votes) || 0) + 1
                // Preferential ballots also count toward the list totals
                summary.value.valid_list_votes = (Number(summary.value.valid_list_votes) || 0) + 1
            }

            // Mark only the relevant summary field as optimistic so the UI highlights it
            if (ballotMode.value === 'white') {
                summary.value.__optimistic_white = true
            } else if (ballotMode.value === 'cancelled') {
                summary.value.__optimistic_cancelled = true
            } else if (ballotMode.value === 'list') {
                summary.value.__optimistic_list = true
            } else if (ballotMode.value === 'preferential') {
                summary.value.__optimistic_preferential = true
            }

            // Update aggregates array
            // Ensure IDs are numbers
            const listId = selectedListId.value != null ? Number(selectedListId.value) : null
            const candidateId = selectedCandidateId.value != null ? Number(selectedCandidateId.value) : null

            // For list votes (including preferential) increment the list aggregate
            if (ballotMode.value === 'list' || ballotMode.value === 'preferential') {
                const existingListAgg = aggregates.value.find(a => a.list_id === listId && (a.candidate_id === null || a.candidate_id === undefined))
                if (existingListAgg) {
                    existingListAgg.vote_count = (Number(existingListAgg.vote_count) || 0) + 1
                    existingListAgg.optimistic = true
                } else {
                    const newAgg = normalizeAggregate({ list_id: listId, candidate_id: null, vote_count: 1, list: { name: selectedList.value?.name } })
                    newAgg.optimistic = true
                    aggregates.value.push(newAgg)
                }
            }

            // For preferential votes increment candidate aggregate
            if (ballotMode.value === 'preferential' && candidateId != null) {
                const existingCandidateAgg = aggregates.value.find(a => a.candidate_id === candidateId)
                const candidateName = selectedList.value?.candidates.find(c => c.id === candidateId)?.full_name || null
                if (existingCandidateAgg) {
                    existingCandidateAgg.vote_count = (Number(existingCandidateAgg.vote_count) || 0) + 1
                    existingCandidateAgg.optimistic = true
                } else {
                    const newCAgg = normalizeAggregate({ list_id: null, candidate_id: candidateId, vote_count: 1, candidate: { full_name: candidateName } })
                    newCAgg.optimistic = true
                    aggregates.value.push(newCAgg)
                }
            }
        } catch (e) {
            console.error('Failed to apply optimistic update:', e)
        }

        // Clear form
        clearSelection()
    } catch (error: any) {
        console.error('Failed to submit ballot:', error)
        toast.value?.addToast(error.message || 'حدث خطأ أثناء إدخال الورقة', 'error', 5000)
    } finally {
        isSubmitting.value = false
    }
}

// Transition handlers for expand animation
const onEnter = (el: Element) => {
    const element = el as HTMLElement
    element.style.height = '0'
}

const onAfterEnter = (el: Element) => {
    const element = el as HTMLElement
    element.style.height = `${element.scrollHeight}px`
    setTimeout(() => {
        element.style.height = 'auto'
    }, 300)
}

const onLeave = (el: Element) => {
    const element = el as HTMLElement
    element.style.height = `${element.scrollHeight}px`
    setTimeout(() => {
        element.style.height = '0'
    }, 0)
}
</script>

<template>

    <Head title="إدخال الأصوات - عرض شبكي" />

    <Toast ref="toast" />

    <BallotEntryLayout>
        <div class="pb-12 pt-2">
            <div class="mx-1 sm:px-6 lg:px-8">
                <!-- Station Header -->
                <div
                    class="mb-2 flex justify-between rounded-lg border border-gray-200 bg-white px-6 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div>

                        <h1 class="text-gray-900 dark:text-white">
                            <span class="text-base">
                                قلم الإقتراع رقم {{ station.station_number }}
                            </span>
                            <span class="text-2xl font-bold px-1">
                                {{ station.town.name }}
                            </span>
                            <span class="font-bold">
                                ({{ station.location }})
                            </span>
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            إدخال أوراق الاقتراع - عرض شبكي سريع
                        </p>
                    </div>
                    <div class="grid grid-cols-5 gap-2 text-center">
                        <div
                            class="rounded-lg border border-gray-200 bg-gray-200 p-3 dark:border-gray-600 dark:bg-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400">إجمالي الأوراق</div>
                            <div class="flex items-center justify-center">
                                <span v-if="summary.__optimistic_total"
                                    class="ml-2 inline-block h-2 w-2 rounded-full bg-yellow-600 animate-pulse"
                                    title="Pending"></span>
                                <div class="text-xl font-bold text-gray-900 dark:text-white">{{
                                    summary.total_ballots_entered }}</div>
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-blue-200 bg-blue-200 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                            <div class="text-xs text-blue-600 dark:text-blue-400">أصوات اللوائح</div>
                            <div class="flex items-center justify-center">
                                <span v-if="summary.__optimistic_list"
                                    class="ml-2 inline-block h-2 w-2 rounded-full bg-yellow-600 animate-pulse"
                                    title="Pending"></span>
                                <div class="text-xl font-bold text-blue-900 dark:text-blue-300">{{
                                    summary.valid_list_votes }}</div>

                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-green-200 bg-green-200 p-3 dark:border-green-800 dark:bg-green-900/20">
                            <div class="text-xs text-green-600 dark:text-green-400">أصوات تفضيلية</div>
                            <div class="flex justify-center items-center">
                                <span v-if="summary.__optimistic_preferential"
                                    class="ml-2 inline-block h-2 w-2 rounded-full bg-yellow-600 animate-pulse"
                                    title="Pending"></span>
                                <div class="text-xl font-bold text-green-900 dark:text-green-300">{{
                                    summary.valid_preferential_votes }}</div>
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-yellow-200 bg-cyan-200 p-3 dark:border-yellow-800 dark:bg-cyan-900/20">
                            <div class="text-xs text-yellow-600 dark:text-yellow-400">أوراق بيضاء</div>
                            <div class="flex items-center justify-center">
                                <span v-if="summary.__optimistic_white"
                                    class="ml-2 inline-block h-2 w-2 rounded-full bg-yellow-600 animate-pulse"
                                    title="Pending"></span>
                                <div class="text-xl font-bold text-yellow-900 dark:text-yellow-300">{{
                                    summary.white_papers }}</div>
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-red-200 bg-red-200 p-3 dark:border-red-800 dark:bg-red-900/20">
                            <div class="text-xs text-red-600 dark:text-red-400">أوراق ملغاة</div>
                            <div class="flex items-center justify-center">
                                <span v-if="summary.__optimistic_cancelled"
                                    class="ml-2 inline-block h-2 w-2 rounded-full bg-yellow-600 animate-pulse"
                                    title="Pending"></span>
                                <div class="text-xl font-bold text-red-900 dark:text-red-300">{{
                                    summary.cancelled_papers }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="w-full">
                    <!-- Ballot Entry Grid Form -->
                    <div>
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="mb-2 flex items-center justify-between">
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">إدخال ورقة اقتراع</h2>
                                <div v-if="lastUpdate" class="flex items-center gap-2">
                                    <div class="size-2 animate-pulse rounded-full bg-green-500"></div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">آخر تحديث: {{ lastUpdate
                                        }}</span>
                                </div>
                            </div>

                            <!-- Summary Cards -->


                            <!-- Action Buttons -->
                            <div class="mb-3 flex gap-2">
                                <button @click="handleWhitePaper" :class="[
                                    'w-50 rounded-lg border-2 px-4 py-2 font-semibold transition-all duration-200',
                                    ballotMode === 'white'
                                        ? 'border-yellow-500 bg-yellow-50 text-yellow-700 shadow-md dark:border-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-300'
                                        : 'border-gray-300 bg-white text-gray-700 hover:border-yellow-400 hover:bg-yellow-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-yellow-500 dark:hover:bg-yellow-900/20'
                                ]">
                                    <TransitionGroup name="fade-slide-x">
                                        <span v-if="ballotMode === 'white'" key="selected"
                                            class="flex items-center justify-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            ورقة بيضاء
                                        </span>
                                        <span v-else key="default">ورقة بيضاء</span>
                                    </TransitionGroup>
                                </button>

                                <button @click="handleCancelledPaper" :class="[
                                    'w-50 rounded-lg border-2 px-4 py-2 font-semibold transition-all duration-200',
                                    ballotMode === 'cancelled'
                                        ? 'border-red-500 bg-red-50 text-red-700 shadow-md dark:border-red-600 dark:bg-red-900/30 dark:text-red-300'
                                        : 'border-gray-300 bg-white text-gray-700 hover:border-red-400 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-red-500 dark:hover:bg-red-900/20'
                                ]">
                                    <TransitionGroup name="fade-slide-x">
                                        <span v-if="ballotMode === 'cancelled'" key="selected"
                                            class="flex items-center justify-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            ورقة ملغاة
                                        </span>
                                        <span v-else key="default">ورقة ملغاة</span>
                                    </TransitionGroup>
                                </button>

                                <button v-if="ballotMode !== null" @click="clearSelection"
                                    class="w-50 rounded-lg border-2 border-gray-300 bg-white px-4 py-2 font-semibold text-gray-700 transition-all duration-200 hover:border-gray-400 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-gray-500 dark:hover:bg-gray-600">
                                    إلغاء
                                </button>
                            </div>

                            <!-- Cancellation Reason -->
                            <Transition name="expand" @enter="onEnter" @after-enter="onAfterEnter" @leave="onLeave">
                                <div v-if="ballotMode === 'cancelled'"
                                    class="mb-3 border border-gray-400 w-96 mr-50 p-2 rounded">
                                    <label for="cancellation-reason"
                                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        سبب الإلغاء
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label v-for="reason in cancellationReasons" :key="reason"
                                            class="flex items-center gap-2">
                                            <input type="radio" class="form-radio accent-red-600" :value="reason"
                                                v-model="cancellationReason" />
                                            <span>{{ reason }}</span>
                                        </label>
                                    </div>
                                </div>
                            </Transition>

                            <!-- Electoral Lists Grid -->
                            <Transition name="expand" @enter="onEnter" @after-enter="onAfterEnter" @leave="onLeave">
                                <div v-if="ballotMode !== 'white' && ballotMode !== 'cancelled'"
                                    class="overflow-x-auto">
                                    <div :style="listsGridStyle" class="grid gap-2">
                                        <div v-for="list in lists" :key="list.id"
                                            class="rounded-lg border-2 transition-all duration-200 overflow-hidden"
                                            :class="[
                                                selectedListId === list.id
                                                    ? 'border-blue-500 bg-blue-50/50 shadow-md dark:border-blue-400 dark:bg-blue-900/20'
                                                    : 'border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800'
                                            ]">
                                            <!-- List Header (Clickable) -->
                                            <button @click="handleListClick(list.id)"
                                                class="flex w-full items-center justify-between px-1 text-right transition-all duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                                :style="{ backgroundColor: list.color + '80' }" :class="[
                                                    selectedListId === list.id && selectedCandidateId === null
                                                        ? 'bg-blue-100 dark:bg-blue-900/30'
                                                        : ''
                                                ]">
                                                <div class="flex flex-col gap-2 p-1">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="flex h-7 w-7 items-center justify-center rounded-md bg-gray-50 dark:bg-gray-700 text-sm font-bold text-gray-700 dark:text-gray-100 shadow-sm">
                                                            {{ list.number }}
                                                        </span>
                                                        <span class="text-base font-bold text-gray-900 dark:text-white">
                                                            {{ list.name }}
                                                        </span>

                                                    </div>

                                                    <!-- List Vote Count Badge -->
                                                    <span v-if="listAggregates.find(agg => agg.list_id === list.id)"
                                                        class="px-2 text-xs font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                                        <span
                                                            v-if="listAggregates.find(agg => agg.list_id === list.id)?.optimistic"
                                                            class="inline-block h-2 w-2 rounded-full bg-yellow-600 animate-pulse"
                                                            title="Pending"></span>
                                                        <span>
                                                            {{listAggregates.find(agg => agg.list_id ===
                                                                list.id)?.vote_count || 0}} صوت
                                                        </span>
                                                    </span>
                                                </div>

                                                <!-- Checkmark if selected as list vote -->
                                                <svg v-if="selectedListId === list.id && selectedCandidateId === null"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-6 w-6 text-blue-600 dark:text-blue-400" viewBox="0 0 20 20"
                                                    fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </button>

                                            <!-- Candidates Grid -->
                                            <div
                                                class="grid grid-cols-1 gap-2 border-t border-gray-200 p-2 dark:border-gray-600">
                                                <button v-for="candidate in list.candidates" :key="candidate.id"
                                                    @click="handleCandidateClick(list.id, candidate.id)"
                                                    class="relative rounded-lg border-2 p-2 text-right text-sm transition-all duration-200"
                                                    :class="[
                                                        selectedCandidateId === candidate.id && selectedListId === list.id
                                                            ? 'text-white shadow-md'
                                                            : 'border border-gray-300 bg-gray-50 text-gray-900 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-400'
                                                    ]"
                                                    :style="selectedCandidateId === candidate.id ? { backgroundColor: list.color } : {}">
                                                    <div class="font-semibold">{{ candidate.full_name }}</div>

                                                    <!-- Candidate Vote Count Badge -->
                                                    <div v-if="candidateAggregates.find(agg => agg.candidate_id === candidate.id)"
                                                        class="mt-1 text-xs" :class="[
                                                            selectedCandidateId === candidate.id && selectedListId === list.id
                                                                ? 'text-gray-700 dark:text-gray-100'
                                                                : 'text-gray-500 dark:text-gray-100'
                                                        ]">
                                                        <span
                                                            v-if="candidateAggregates.find(agg => agg.candidate_id === candidate.id)?.optimistic"
                                                            class="ml-2 inline-block h-2 w-2 rounded-full bg-yellow-400 animate-pulse"
                                                            title="Pending"></span>
                                                        <span>
                                                            {{candidateAggregates.find(agg => agg.candidate_id ===
                                                                candidate.id)?.vote_count || 0}} صوت
                                                        </span>
                                                    </div>

                                                    <!-- Checkmark for selected candidate -->
                                                    <svg v-if="selectedCandidateId === candidate.id && selectedListId === list.id"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        class="absolute left-1 top-1 h-5 w-5 text-gray-600 dark:text-gray-200"
                                                        viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Transition>

                            <!-- Submit Button -->
                            <Transition name="fade" mode="out-in">
                                <button v-if="canSubmit" @click="submitBallot" :disabled="isSubmitting"
                                    class="w-96 sticky bottom-1 mt-4 mx-auto block rounded-lg bg-blue-600 px-6 py-3 text-lg font-bold text-white transition-all duration-200 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-500 dark:hover:bg-blue-600">
                                    <TransitionGroup name="fade">
                                        <span :key="getButtonText">{{ getButtonText }}</span>
                                    </TransitionGroup>
                                </button>
                            </Transition>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BallotEntryLayout>
</template>

<style scoped>
/* Expand transition for showing/hiding sections */
.expand-enter-active,
.expand-leave-active {
    transition: all 0.3s ease;
    overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
    opacity: 0;
}

/* Fade slide x transition for button text */
.fade-slide-x-enter-active,
.fade-slide-x-leave-active {
    transition: all 0.2s ease;
}

.fade-slide-x-enter-from {
    opacity: 0;
    transform: translateX(10px);
}

.fade-slide-x-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}

/* Simple fade transition */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
