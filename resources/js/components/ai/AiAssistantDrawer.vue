<script setup lang="ts">
import OctoMascot from '@/components/OctoMascot.vue';
import OctoSpinner from '@/components/OctoSpinner.vue';
import { useActiveSection } from '@/composables/useActiveSection';
import { type AiScope, type ChatAttachment, useAiAssistant } from '@/composables/useAiAssistant';
import DOMPurify from 'dompurify';
import {
    AlertCircle,
    ArrowUp,
    BookOpen,
    Check,
    ChevronDown,
    ChevronRight,
    Clock,
    Copy,
    Cpu,
    Database,
    ExternalLink,
    FileText,
    HelpCircle,
    History,
    LayoutGrid,
    ListChecks,
    Loader2,
    Maximize2,
    MessageSquare,
    Minimize2,
    Paperclip,
    Pencil,
    Play,
    Plus,
    RotateCcw,
    Search,
    ShieldCheck,
    Sparkles,
    Square,
    Trash2,
    Users,
    X,
    Zap,
} from 'lucide-vue-next';
import { marked } from 'marked';
import { computed, nextTick, ref, watch } from 'vue';

const {
    isAiAssistantOpen,
    isAiEnabled,
    messages,
    conversations,
    currentConversationId,
    isSending,
    streamingStatusText,
    isOllamaOnline,
    isLocalEndpoint,
    currentScope,
    setScope,
    toggleAssistant,
    closeAssistant,
    startNewConversation,
    switchConversation,
    deleteConversation,
    clearAllConversations,
    sendMessage,
    retryMessage,
    executeActionProposal,
    dismissProposal,
    stopStreaming,
} = useAiAssistant();

const { activeSection } = useActiveSection();

const inputPrompt = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const copiedId = ref<string | null>(null);
const suggestions = ref<string[]>([]);
const openSources = ref<Record<string, boolean>>({});
const editingProposal = ref<Record<string, boolean>>({});
const isExpanded = ref(false);
const showConversationsPanel = ref(false);
const conversationSearchQuery = ref('');
const confirmingDeleteId = ref<string | null>(null);

const activeSectionId = computed(() => activeSection.value?.id || null);

const filteredConversations = computed(() => {
    const q = conversationSearchQuery.value.trim().toLowerCase();
    if (!q) return conversations.value;
    return conversations.value.filter((c) => {
        const titleMatch = c.title?.toLowerCase().includes(q);
        const scopeMatch = c.scope?.toLowerCase().includes(q);
        return titleMatch || scopeMatch;
    });
});

const formatScopeName = (scope: string) => {
    switch (scope) {
        case 'current_section':
            return 'Section';
        case 'all_classes':
            return 'All Classes';
        case 'app_help':
            return 'System Help';
        default:
            return 'Chat';
    }
};

const formatRelativeTime = (timestamp?: number) => {
    if (!timestamp) return 'Just now';
    const now = Date.now();
    const diff = Math.floor((now - timestamp) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 86400 * 2) return 'Yesterday';
    const date = new Date(timestamp);
    return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const handleStartNewConversation = () => {
    startNewConversation(activeSectionId.value, currentScope.value);
    showConversationsPanel.value = false;
    nextTick(() => {
        textareaRef.value?.focus();
    });
};

const handleSwitchConversation = (id: string) => {
    switchConversation(id);
    showConversationsPanel.value = false;
    nextTick(() => {
        scrollToBottom();
    });
};

const handleDeleteConversation = (id: string) => {
    deleteConversation(id, activeSectionId.value);
    confirmingDeleteId.value = null;
};

const handleClearAll = () => {
    if (window.confirm('Are you sure you want to delete all previous conversations?')) {
        clearAllConversations(activeSectionId.value);
        showConversationsPanel.value = false;
    }
};

const handleExecuteProposal = async (proposal: any) => {
    await executeActionProposal(proposal, activeSectionId.value);
};

// Fetch quick suggestions based on active scope & section
const fetchSuggestions = async () => {
    try {
        const queryParams = new URLSearchParams();
        if (activeSectionId.value) queryParams.set('section_id', String(activeSectionId.value));
        queryParams.set('scope', currentScope.value);

        const res = await fetch(`/ai-assistant/suggestions?${queryParams.toString()}`);
        if (res.ok) {
            const data = await res.json();
            suggestions.value = data.suggestions || [];
        }
    } catch {
        suggestions.value = [];
    }
};

watch(
    [activeSectionId, currentScope],
    () => {
        fetchSuggestions();
    },
    { immediate: true },
);

watch(
    () => isAiAssistantOpen.value,
    (open) => {
        if (open) {
            nextTick(() => {
                scrollToBottom();
                textareaRef.value?.focus();
            });
        }
    },
);

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

watch(
    () => [messages.value.length, messages.value[messages.value.length - 1]?.content],
    () => {
        nextTick(scrollToBottom);
    },
    { deep: true },
);

const pendingAttachments = ref<ChatAttachment[]>([]);
const fileInputRef = ref<HTMLInputElement | null>(null);
const isReadingFile = ref(false);
const fileReadError = ref<string | null>(null);

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const handleFileSelected = async (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    if (file.size > 4 * 1024 * 1024) {
        fileReadError.value = 'File exceeds 4MB limit.';
        setTimeout(() => {
            fileReadError.value = null;
        }, 4000);
        target.value = '';
        return;
    }

    isReadingFile.value = true;
    fileReadError.value = null;

    try {
        const text = await new Promise<string>((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve((reader.result as string) || '');
            reader.onerror = reject;
            reader.readAsText(file);
        });

        pendingAttachments.value.push({
            name: file.name,
            size: file.size,
            content: text,
        });
    } catch {
        fileReadError.value = 'Could not read file content.';
        setTimeout(() => {
            fileReadError.value = null;
        }, 4000);
    } finally {
        isReadingFile.value = false;
        target.value = '';
    }
};

const removeAttachment = (index: number) => {
    pendingAttachments.value.splice(index, 1);
};

const handleSelectChoice = async (optionText: string) => {
    await sendMessage(optionText, activeSectionId.value);
    nextTick(scrollToBottom);
};

const handlePrepopulateChoice = (promptPrefix: string) => {
    inputPrompt.value = promptPrefix;
    textareaRef.value?.focus();
};

const handleSend = () => {
    const prompt = inputPrompt.value.trim();
    if ((!prompt && pendingAttachments.value.length === 0) || isSending.value) return;

    const attachmentsToSend = [...pendingAttachments.value];
    inputPrompt.value = '';
    pendingAttachments.value = [];
    sendMessage(prompt, activeSectionId.value, attachmentsToSend);
};

const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
    }
};

const useSuggestion = (s: string) => {
    inputPrompt.value = s;
    handleSend();
};

const copyContent = async (id: string, text: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedId.value = id;
        setTimeout(() => {
            if (copiedId.value === id) copiedId.value = null;
        }, 2000);
    } catch {
        // Fallback
    }
};

const toggleSourceAccordion = (msgId: string) => {
    openSources.value[msgId] = !openSources.value[msgId];
};

const renderMarkdown = (content: string) => {
    if (!content) return '';
    try {
        const rawHtml = marked.parse(content, { gfm: true, breaks: true }) as string;
        return DOMPurify.sanitize(rawHtml);
    } catch {
        return DOMPurify.sanitize(content);
    }
};

const formatModelName = (model?: string) => {
    if (!model) return 'Ollama Model';
    const base = model.split(':')[0];
    if (base.toLowerCase().includes('qwen2.5-coder')) return 'Qwen 2.5 Coder';
    if (base.toLowerCase().includes('qwen2.5')) return 'Qwen 2.5 Instruct';
    if (base.toLowerCase().includes('llama3')) return 'Llama 3';
    if (base.toLowerCase().includes('mistral')) return 'Mistral';
    if (base.toLowerCase().includes('deepseek')) return 'DeepSeek';
    return model;
};

const switchScope = (scope: AiScope) => {
    setScope(scope, activeSectionId.value);
    fetchSuggestions();
};
</script>

<template>
    <!-- Floating Circular Action Button (Lower Right) -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="transform scale-75 opacity-0 translate-y-3"
        enter-to-class="transform scale-100 opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="transform scale-100 opacity-100 translate-y-0"
        leave-to-class="transform scale-75 opacity-0 translate-y-3"
    >
        <button
            v-if="isAiEnabled && !isAiAssistantOpen"
            type="button"
            @click="toggleAssistant(activeSectionId)"
            class="group fixed bottom-6 right-6 z-40 flex cursor-pointer items-center justify-center rounded-full border-0 bg-transparent p-0 shadow-none outline-none transition-transform duration-200 hover:scale-110 active:scale-95"
            title="Ask Octo AI (Ctrl+J)"
            aria-label="Open Octo AI Teaching Copilot"
        >
            <OctoMascot
                size="lg"
                :interactive="true"
                :show-bubble="true"
                :bubble-text="isSending ? 'Octo is generating... 💭' : 'Ask me anything! (Ctrl+J)'"
                :show-status="true"
                :is-online="isOllamaOnline"
                :is-thinking="isSending"
            />
        </button>
    </Transition>

    <!-- Modal Dialog Backdrop & Teaching Ledger Container -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="isAiAssistantOpen"
            class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 sm:p-4 print:hidden"
            @click.self="closeAssistant"
            role="dialog"
            aria-modal="true"
            aria-label="Octo AI Teaching Copilot"
        >
            <div
                class="paper-card relative flex flex-col overflow-hidden border border-border/90 bg-card shadow-2xl transition-all duration-300"
                :class="isExpanded ? 'h-[96vh] w-[96vw] max-w-6xl rounded-2xl' : 'h-[92vh] max-h-[850px] w-full max-w-3xl rounded-2xl sm:h-[85vh]'"
            >
                <!-- Teaching-Ledger Header -->
                <div class="flex items-center justify-between border-b border-border/80 bg-secondary/30 px-4 py-3 sm:px-5 sm:py-3.5">
                    <div class="flex items-center gap-2.5 sm:gap-3">
                        <div
                            class="shadow-xs relative flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/60 bg-card sm:size-10"
                        >
                            <OctoMascot size="sm" :interactive="true" :is-thinking="isSending" />
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <h3 class="text-sm font-bold tracking-tight text-foreground sm:text-base">Octo Copilot</h3>
                                <span
                                    v-if="isLocalEndpoint"
                                    class="xs:inline-flex hidden items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-300"
                                >
                                    <ShieldCheck class="size-3" /> Local & Private
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Controls (Previous Conversations, New Chat, Expand, Close) -->
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <!-- Previous Conversations Toggle Button -->
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition-colors"
                            :class="
                                showConversationsPanel
                                    ? 'shadow-2xs border-primary bg-primary/10 font-bold text-primary'
                                    : 'border-border/80 bg-secondary/50 text-muted-foreground hover:bg-secondary hover:text-foreground'
                            "
                            title="View and manage previous conversations"
                            @click="showConversationsPanel = !showConversationsPanel"
                        >
                            <History class="size-3.5" />
                            <span class="hidden sm:inline">Previous</span>
                            <span
                                v-if="conversations.length"
                                class="py-0.2 rounded-full bg-secondary/80 px-1.5 text-[10px] font-bold text-foreground/80"
                            >
                                {{ conversations.length }}
                            </span>
                        </button>

                        <!-- New Conversation Button -->
                        <button
                            type="button"
                            class="shadow-2xs inline-flex items-center gap-1 rounded-lg border border-primary/40 bg-primary/10 px-2.5 py-1.5 text-xs font-bold text-primary transition-colors hover:bg-primary/20"
                            title="Start a new conversation"
                            @click="handleStartNewConversation"
                        >
                            <Plus class="size-3.5" />
                            <span class="xs:inline hidden">New Chat</span>
                        </button>

                        <!-- Expand / Minimize Button -->
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            :title="isExpanded ? 'Collapse view' : 'Expand full width'"
                            @click="isExpanded = !isExpanded"
                        >
                            <Minimize2 v-if="isExpanded" class="size-4" />
                            <Maximize2 v-else class="size-4" />
                        </button>

                        <!-- Close Button -->
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            title="Close Octo (Esc)"
                            @click="closeAssistant"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- Slide-over / Popout Previous Conversations Panel -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-x-4"
                    enter-to-class="opacity-100 translate-x-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-x-0"
                    leave-to-class="opacity-0 -translate-x-4"
                >
                    <div
                        v-if="showConversationsPanel"
                        class="absolute inset-y-0 left-0 z-40 flex w-full max-w-sm flex-col border-r border-border/90 bg-card shadow-2xl backdrop-blur-md"
                    >
                        <!-- Panel Header -->
                        <div class="flex items-center justify-between border-b border-border/80 bg-secondary/30 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <History class="size-4 text-primary" />
                                <h4 class="text-xs font-bold uppercase tracking-wide text-foreground">Previous Conversations</h4>
                                <span class="py-0.2 rounded-full bg-secondary px-1.5 text-[10px] font-bold text-muted-foreground">
                                    {{ conversations.length }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-primary/40 bg-primary/10 px-2 py-1 text-[11px] font-bold text-primary transition-colors hover:bg-primary/20"
                                    @click="handleStartNewConversation"
                                >
                                    <Plus class="size-3" /> New
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                    title="Close panel"
                                    @click="showConversationsPanel = false"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Filter Search Input -->
                        <div v-if="conversations.length > 2" class="border-b border-border/60 bg-secondary/10 p-2.5">
                            <div class="relative">
                                <Search class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    v-model="conversationSearchQuery"
                                    type="text"
                                    placeholder="Search past conversations..."
                                    class="shadow-2xs w-full rounded-lg border border-border/80 bg-card py-1.5 pl-8 pr-3 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                />
                            </div>
                        </div>

                        <!-- Conversations List -->
                        <div class="flex-1 space-y-1.5 overflow-y-auto p-2">
                            <div
                                v-for="conv in filteredConversations"
                                :key="conv.id"
                                class="group relative flex cursor-pointer flex-col gap-1 rounded-xl border p-2.5 transition-all"
                                :class="
                                    conv.id === currentConversationId
                                        ? 'shadow-xs border-primary/60 bg-primary/10'
                                        : 'border-border/60 bg-card hover:border-border hover:bg-secondary/40'
                                "
                                @click="handleSwitchConversation(conv.id)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <MessageSquare
                                            class="size-3.5 shrink-0"
                                            :class="conv.id === currentConversationId ? 'text-primary' : 'text-muted-foreground'"
                                        />
                                        <span class="truncate text-xs font-semibold leading-snug text-foreground" :title="conv.title">
                                            {{ conv.title || 'New Conversation' }}
                                        </span>
                                    </div>

                                    <!-- Delete Button / Inline Confirmation -->
                                    <div class="flex shrink-0 items-center gap-1" @click.stop>
                                        <template v-if="confirmingDeleteId === conv.id">
                                            <span class="text-[10px] font-semibold text-rose-600">Delete?</span>
                                            <button
                                                type="button"
                                                class="rounded p-1 text-rose-600 transition-colors hover:bg-rose-500/10"
                                                title="Confirm Delete"
                                                @click="handleDeleteConversation(conv.id)"
                                            >
                                                <Check class="size-3" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded p-1 text-muted-foreground transition-colors hover:bg-secondary"
                                                title="Cancel"
                                                @click="confirmingDeleteId = null"
                                            >
                                                <X class="size-3" />
                                            </button>
                                        </template>
                                        <template v-else>
                                            <button
                                                type="button"
                                                class="rounded p-1 text-muted-foreground/60 opacity-0 transition-all hover:bg-rose-500/10 hover:text-rose-600 group-hover:opacity-100"
                                                title="Delete this conversation"
                                                @click="confirmingDeleteId = conv.id"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <!-- Metadata Row: Scope, Date, Count -->
                                <div class="flex items-center justify-between pt-0.5 text-[10px] text-muted-foreground">
                                    <span class="py-0.2 rounded bg-secondary/80 px-1.5 font-medium text-foreground/80">
                                        {{ formatScopeName(conv.scope) }}
                                    </span>
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ formatRelativeTime(conv.updatedAt || conv.createdAt) }}</span>
                                        <span>•</span>
                                        <span>{{ conv.messages?.length || 0 }} msgs</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty Search / List State -->
                            <div v-if="filteredConversations.length === 0" class="flex flex-col items-center justify-center p-8 text-center">
                                <MessageSquare class="mb-2 size-8 text-muted-foreground/40" />
                                <p class="text-xs font-semibold text-foreground">No conversations found</p>
                                <p class="mb-3 mt-0.5 text-[11px] text-muted-foreground">
                                    {{ conversationSearchQuery ? 'Try a different search term.' : 'Start a fresh conversation with Octo.' }}
                                </p>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-primary/40 bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary transition-colors hover:bg-primary/20"
                                    @click="handleStartNewConversation"
                                >
                                    <Plus class="size-3.5" /> Start New Chat
                                </button>
                            </div>
                        </div>

                        <!-- Footer: Clear All History -->
                        <div
                            v-if="conversations.length > 1"
                            class="flex items-center justify-between border-t border-border/80 bg-secondary/20 p-2.5 text-xs"
                        >
                            <span class="text-[11px] text-muted-foreground">{{ conversations.length }} total sessions</span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-[11px] font-medium text-rose-600 hover:underline"
                                @click="handleClearAll"
                            >
                                <Trash2 class="size-3" /> Clear All History
                            </button>
                        </div>
                    </div>
                </Transition>

                <!-- Scope Selection Bar -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/70 bg-card/90 px-5 py-2 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="mr-1 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Scope:</span>
                        <button
                            v-if="activeSectionId"
                            type="button"
                            class="rounded-lg border px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                currentScope === 'current_section'
                                    ? 'shadow-2xs border-primary bg-primary/10 font-bold text-primary'
                                    : 'border-border/70 bg-secondary/40 text-muted-foreground hover:text-foreground'
                            "
                            @click="switchScope('current_section')"
                        >
                            <LayoutGrid class="mr-1 inline size-3" />
                            <span>{{ activeSection?.name || 'Current Section' }}</span>
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                currentScope === 'all_classes'
                                    ? 'shadow-2xs border-primary bg-primary/10 font-bold text-primary'
                                    : 'border-border/70 bg-secondary/40 text-muted-foreground hover:text-foreground'
                            "
                            @click="switchScope('all_classes')"
                        >
                            <BookOpen class="mr-1 inline size-3" />
                            <span>All My Classes</span>
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                currentScope === 'app_help'
                                    ? 'shadow-2xs border-primary bg-primary/10 font-bold text-primary'
                                    : 'border-border/70 bg-secondary/40 text-muted-foreground hover:text-foreground'
                            "
                            @click="switchScope('app_help')"
                        >
                            <HelpCircle class="mr-1 inline size-3" />
                            <span>ClassCheck Help</span>
                        </button>
                    </div>

                    <!-- Connection Status Badge -->
                    <div class="flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                        <span class="size-2 rounded-full" :class="isOllamaOnline ? 'bg-emerald-500' : 'bg-amber-500'" />
                        <span>{{ isOllamaOnline ? 'Ollama Active' : 'Ollama Offline' }}</span>
                    </div>
                </div>

                <!-- Messages Stream Scroll Container -->
                <div ref="messagesContainer" class="flex-1 space-y-5 overflow-y-auto p-4 sm:p-6">
                    <!-- Welcome Banner if no messages -->
                    <div v-if="messages.length === 0" class="flex flex-col items-center justify-center py-8 text-center">
                        <div class="flex size-16 items-center justify-center rounded-full border border-border bg-card p-1 shadow-md">
                            <OctoMascot size="xl" :interactive="true" />
                        </div>
                        <h4 class="mt-3 text-base font-bold text-foreground">Grounded Teaching Copilot</h4>
                        <p class="mt-1 max-w-md text-xs leading-relaxed text-muted-foreground">
                            Octo directly references your live ClassCheck gradebooks, attendance rosters, and curriculum materials to answer questions
                            with verified domain accuracy.
                        </p>

                        <!-- Suggested Prompts Grid -->
                        <div class="mt-6 w-full max-w-xl space-y-2 text-left">
                            <div class="px-1 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Suggested Inquiries</div>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <button
                                    v-for="(s, idx) in suggestions"
                                    :key="idx"
                                    type="button"
                                    class="shadow-2xs group flex items-start gap-2.5 rounded-xl border border-border/80 bg-card p-3 text-left text-xs leading-relaxed text-foreground transition-all hover:border-primary/50 hover:bg-secondary/40"
                                    @click="useSuggestion(s)"
                                >
                                    <Sparkles class="mt-0.5 size-3.5 shrink-0 text-primary/70 transition-colors group-hover:text-primary" />
                                    <span class="flex-1">{{ s }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Message Ledger Rows -->
                    <div v-for="msg in messages" :key="msg.id" class="space-y-1.5">
                        <!-- User Query Bubble -->
                        <div v-if="msg.role === 'user'" class="flex flex-col items-end space-y-1">
                            <!-- Attached Files Pills -->
                            <div v-if="msg.attachments && msg.attachments.length > 0" class="flex max-w-[85%] flex-wrap justify-end gap-1.5">
                                <div
                                    v-for="(att, aIdx) in msg.attachments"
                                    :key="aIdx"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-primary/40 bg-primary/15 px-2.5 py-1 text-[11px] font-semibold text-primary"
                                >
                                    <Paperclip class="size-3 text-primary" />
                                    <span class="max-w-[180px] truncate">{{ att.name }}</span>
                                    <span v-if="att.size" class="text-[9px] text-muted-foreground">({{ (att.size / 1024).toFixed(1) }} KB)</span>
                                </div>
                            </div>
                            <div
                                class="rounded-tr-xs shadow-xs max-w-[85%] break-words rounded-2xl bg-primary px-4 py-2.5 text-xs font-medium leading-relaxed text-primary-foreground"
                            >
                                {{ msg.content }}
                            </div>
                        </div>

                        <!-- Assistant Teaching Ledger Card (Near Full-Width) -->
                        <div
                            v-else
                            class="shadow-xs space-y-3.5 rounded-2xl border border-border/90 bg-gradient-to-br from-card via-card to-secondary/15 p-4 sm:p-5"
                            :class="msg.error ? 'border-rose-500/40 bg-rose-500/5' : ''"
                        >
                            <!-- Card Header: Identity + Streaming / Done status -->
                            <div class="flex items-center justify-between border-b border-border/60 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="grid size-6 place-items-center overflow-hidden rounded-full border border-border/70 bg-card p-0.5">
                                        <OctoMascot
                                            size="xs"
                                            :interactive="true"
                                            :is-streaming="msg.isStreaming"
                                            :is-thinking="msg.isStreaming && !msg.content"
                                        />
                                    </div>
                                    <span class="text-xs font-bold text-foreground">Octo Ledger Response</span>
                                    <span class="text-[10px] text-muted-foreground">&bull; {{ msg.timestamp }}</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- Streaming Spinner -->
                                    <span
                                        v-if="msg.isStreaming"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-0.5 text-[10px] font-bold text-primary"
                                    >
                                        <span class="inline-flex gap-0.5">
                                            <span class="size-1 animate-bounce rounded-full bg-primary [animation-delay:-0.3s]" />
                                            <span class="size-1 animate-bounce rounded-full bg-primary [animation-delay:-0.15s]" />
                                            <span class="size-1 animate-bounce rounded-full bg-primary" />
                                        </span>
                                        <span>{{ !msg.content ? 'Octo is thinking...' : 'Generating...' }}</span>
                                    </span>

                                    <!-- Copy Button -->
                                    <button
                                        v-if="msg.content && !msg.isStreaming"
                                        type="button"
                                        class="rounded-md border border-border/70 bg-card p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                        :title="copiedId === msg.id ? 'Copied!' : 'Copy response'"
                                        @click="copyContent(msg.id, msg.content)"
                                    >
                                        <Check v-if="copiedId === msg.id" class="size-3.5 text-emerald-600" />
                                        <Copy v-else class="size-3.5" />
                                    </button>

                                    <!-- Retry Button -->
                                    <button
                                        v-if="!msg.isStreaming"
                                        type="button"
                                        class="rounded-md border border-border/70 bg-card p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                        title="Retry query"
                                        @click="retryMessage(msg.id, activeSectionId)"
                                    >
                                        <RotateCcw class="size-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Live Markdown Output (Rendered in real-time for organized tables, lists & headings) -->
                            <div class="text-xs leading-relaxed text-foreground">
                                <!-- In-card streaming status indicator before first token -->
                                <div
                                    v-if="msg.isStreaming && !msg.content"
                                    class="flex items-center gap-3.5 rounded-xl border border-primary/20 bg-gradient-to-r from-primary/5 via-primary/10 to-transparent px-3.5 py-3"
                                >
                                    <div class="relative shrink-0">
                                        <OctoMascot size="sm" :interactive="false" :forced-state="'thinking'" :is-thinking="true" />
                                    </div>
                                    <div class="min-w-0 flex-1 space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-foreground">{{ streamingStatusText || 'Octo is thinking...' }}</span>
                                            <span class="inline-flex items-center gap-1">
                                                <span class="size-1.5 animate-bounce rounded-full bg-primary [animation-delay:-0.3s]" />
                                                <span class="size-1.5 animate-bounce rounded-full bg-primary [animation-delay:-0.15s]" />
                                                <span class="size-1.5 animate-bounce rounded-full bg-primary" />
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground">
                                            Consulting live classroom records and verified curriculum data
                                        </p>
                                    </div>
                                </div>

                                <!-- Fallback for interrupted or empty past responses -->
                                <div v-else-if="!msg.content && !msg.isStreaming" class="py-1 text-xs italic text-muted-foreground">
                                    No response was generated. Click the retry button to run this query again.
                                </div>

                                <!-- eslint-disable-next-line vue/no-v-html -->
                                <div v-if="msg.content" class="octo-markdown max-w-none break-words" v-html="renderMarkdown(msg.content)" />
                                <span v-if="msg.isStreaming && msg.content" class="ml-0.5 inline-block animate-pulse font-bold text-primary">▍</span>

                                <!-- Truncation Continuation Action -->
                                <div v-if="msg.metrics?.is_truncated && !msg.isStreaming" class="pt-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-primary/40 bg-primary/10 px-3 py-1 text-xs font-bold text-primary transition-colors hover:bg-primary/20"
                                        @click="sendMessage('Please continue generating the rest of the activity...', activeSectionId)"
                                    >
                                        <Play class="size-3" />
                                        <span>Continue Generation</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Interactive AI Action Proposal Cards -->
                            <div v-if="msg.proposals && msg.proposals.length > 0" class="space-y-2 pt-2">
                                <div
                                    v-for="(proposal, pIdx) in msg.proposals"
                                    :key="pIdx"
                                    class="shadow-xs overflow-hidden rounded-xl border transition-all"
                                    :class="[
                                        proposal.status === 'executed'
                                            ? 'border-emerald-500/40 bg-emerald-500/5 dark:bg-emerald-950/20'
                                            : proposal.status === 'dismissed'
                                              ? 'border-border/40 bg-muted/20 opacity-60'
                                              : proposal.action === 'delete_assessment'
                                                ? 'border-destructive/40 bg-destructive/5'
                                                : 'border-primary/30 bg-primary/5 dark:bg-primary/10',
                                    ]"
                                >
                                    <div class="p-3">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                                                    :class="[
                                                        proposal.action === 'delete_assessment'
                                                            ? 'bg-destructive/15 text-destructive'
                                                            : proposal.status === 'executed'
                                                              ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                              : 'bg-primary/15 text-primary',
                                                    ]"
                                                >
                                                    <Trash2 v-if="proposal.action === 'delete_assessment'" class="size-3.5" />
                                                    <Check v-else-if="proposal.status === 'executed'" class="size-3.5" />
                                                    <Sparkles v-else class="size-3.5" />
                                                </div>
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        <span class="text-xs font-bold text-foreground">{{ proposal.title }}</span>
                                                        <span
                                                            v-if="proposal.type"
                                                            class="rounded-full border border-border/50 bg-background/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
                                                        >
                                                            {{ proposal.type.replace('_', ' ') }}
                                                        </span>
                                                        <span
                                                            v-if="proposal.group_count"
                                                            class="rounded-full border border-border/50 bg-secondary px-2 py-0.5 text-[10px] font-bold text-foreground"
                                                        >
                                                            {{ proposal.group_count }} groups
                                                        </span>
                                                        <span
                                                            v-if="proposal.source_project_title"
                                                            class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                                                        >
                                                            Cloned from: {{ proposal.source_project_title }}
                                                        </span>
                                                        <span
                                                            v-if="proposal.max_points"
                                                            class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary"
                                                        >
                                                            {{ proposal.max_points }} pts
                                                        </span>
                                                    </div>
                                                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                                                        {{
                                                            proposal.confirmation_prompt ||
                                                            (proposal.action === 'create_assessment'
                                                                ? `Would you like to add "${proposal.title}" as an activity to ${proposal.section_name || 'your section'}?`
                                                                : proposal.action === 'create_project_groups'
                                                                  ? `Would you like to create this group activity in ${proposal.section_name || 'your section'}?`
                                                                  : 'Confirmation required')
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <span
                                                v-if="proposal.status === 'executed'"
                                                class="inline-flex shrink-0 items-center gap-1 rounded-md bg-emerald-500/15 px-2 py-0.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400"
                                            >
                                                <Check class="size-3" /> Added
                                            </span>
                                            <span
                                                v-else-if="proposal.status === 'dismissed'"
                                                class="shrink-0 text-[10px] font-medium italic text-muted-foreground"
                                            >
                                                Dismissed
                                            </span>
                                        </div>

                                        <!-- Inline Quick Edit Fields -->
                                        <div
                                            v-if="editingProposal[`${msg.id}_${pIdx}`] && proposal.status === 'pending'"
                                            class="mt-2.5 space-y-2 rounded-lg border border-border/70 bg-card p-2.5"
                                        >
                                            <div class="text-[11px] font-bold text-foreground">Edit Activity Details</div>
                                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                                <div class="sm:col-span-2">
                                                    <label class="text-[10px] font-semibold text-muted-foreground">Title</label>
                                                    <input
                                                        v-model="proposal.title"
                                                        type="text"
                                                        class="focus:outline-hidden w-full rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground focus:ring-1 focus:ring-primary"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-semibold text-muted-foreground">Type</label>
                                                    <select
                                                        v-model="proposal.type"
                                                        class="focus:outline-hidden w-full rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground focus:ring-1 focus:ring-primary"
                                                    >
                                                        <option value="activity">Activity</option>
                                                        <option value="laboratory">Laboratory</option>
                                                        <option value="quiz">Quiz</option>
                                                        <option value="exam">Exam</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-semibold text-muted-foreground">Max Points</label>
                                                    <input
                                                        v-model.number="proposal.max_points"
                                                        type="number"
                                                        min="1"
                                                        max="1000"
                                                        class="focus:outline-hidden w-full rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground focus:ring-1 focus:ring-primary"
                                                    />
                                                </div>
                                            </div>
                                            <div class="flex justify-end pt-1">
                                                <button
                                                    type="button"
                                                    class="cursor-pointer rounded-md bg-secondary px-2.5 py-1 text-[11px] font-semibold text-foreground hover:bg-secondary/80"
                                                    @click="editingProposal[`${msg.id}_${pIdx}`] = false"
                                                >
                                                    Done Editing
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Error Message -->
                                        <div
                                            v-if="proposal.error_message"
                                            class="mt-2 flex items-center gap-1.5 rounded-lg bg-destructive/10 p-2 text-[11px] text-destructive"
                                        >
                                            <AlertCircle class="size-3.5 shrink-0" />
                                            <span>{{ proposal.error_message }}</span>
                                        </div>

                                        <!-- Executed Success Confirmation & Link -->
                                        <div
                                            v-if="proposal.status === 'executed'"
                                            class="mt-2.5 flex items-center justify-between border-t border-emerald-500/20 pt-2 text-xs"
                                        >
                                            <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">{{
                                                proposal.result_message || 'Saved to section gradebook!'
                                            }}</span>
                                            <a
                                                v-if="proposal.redirect_url"
                                                :href="proposal.redirect_url"
                                                class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline"
                                                target="_blank"
                                            >
                                                <span>{{ proposal.action === 'create_project_groups' ? 'View Activity' : 'View Assessment' }}</span>
                                                <ExternalLink class="size-3" />
                                            </a>
                                        </div>

                                        <!-- Interactive Action Buttons -->
                                        <div
                                            v-else-if="proposal.status !== 'dismissed'"
                                            class="mt-3 flex flex-wrap items-center gap-2 border-t border-border/50 pt-2"
                                        >
                                            <!-- Create Assessment Buttons -->
                                            <template v-if="proposal.action === 'create_assessment'">
                                                <button
                                                    type="button"
                                                    :disabled="proposal.status === 'executing'"
                                                    class="shadow-xs inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-primary px-3.5 py-1.5 text-xs font-bold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-50"
                                                    @click="handleExecuteProposal(proposal)"
                                                >
                                                    <OctoSpinner v-if="proposal.status === 'executing'" size="xs" />
                                                    <Check v-else class="size-3.5" />
                                                    <span
                                                        >Yes, Add as
                                                        {{
                                                            proposal.type === 'laboratory'
                                                                ? 'Lab Activity'
                                                                : proposal.type === 'quiz'
                                                                  ? 'Quiz'
                                                                  : proposal.type === 'exam'
                                                                    ? 'Exam'
                                                                    : 'Activity'
                                                        }}</span
                                                    >
                                                </button>

                                                <button
                                                    type="button"
                                                    :disabled="proposal.status === 'executing'"
                                                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                                                    @click="editingProposal[`${msg.id}_${pIdx}`] = !editingProposal[`${msg.id}_${pIdx}`]"
                                                >
                                                    <Pencil class="size-3" />
                                                    <span>{{ editingProposal[`${msg.id}_${pIdx}`] ? 'Close Editor' : 'Edit Details' }}</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    :disabled="proposal.status === 'executing'"
                                                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/70 px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary/50 hover:text-foreground"
                                                    @click="dismissProposal(proposal, activeSectionId)"
                                                >
                                                    <X class="size-3" />
                                                    <span>No, Keep as Draft</span>
                                                </button>
                                            </template>

                                            <!-- Delete Assessment Buttons -->
                                            <template v-else-if="proposal.action === 'delete_assessment'">
                                                <button
                                                    type="button"
                                                    :disabled="proposal.status === 'executing'"
                                                    class="shadow-xs inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-destructive px-3 py-1.5 text-xs font-bold text-destructive-foreground transition-colors hover:bg-destructive/90 disabled:opacity-50"
                                                    @click="handleExecuteProposal(proposal)"
                                                >
                                                    <OctoSpinner v-if="proposal.status === 'executing'" size="xs" />
                                                    <Trash2 v-else class="size-3.5" />
                                                    <span>Yes, Delete</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    :disabled="proposal.status === 'executing'"
                                                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary"
                                                    @click="dismissProposal(proposal, activeSectionId)"
                                                >
                                                    <span>No, Keep Record</span>
                                                </button>
                                            </template>

                                            <!-- Create Project / Group Activity Buttons -->
                                            <template v-else-if="proposal.action === 'create_project_groups'">
                                                <button
                                                    type="button"
                                                    :disabled="proposal.status === 'executing'"
                                                    class="shadow-xs inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-primary px-3.5 py-1.5 text-xs font-bold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-50"
                                                    @click="handleExecuteProposal(proposal)"
                                                >
                                                    <OctoSpinner v-if="proposal.status === 'executing'" size="xs" />
                                                    <Users v-else class="size-3.5" />
                                                    <span>Yes, Create Activity & Groups</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    :disabled="proposal.status === 'executing'"
                                                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/70 px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary/50 hover:text-foreground"
                                                    @click="dismissProposal(proposal, activeSectionId)"
                                                >
                                                    <X class="size-3" />
                                                    <span>No, Keep as Draft</span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Interactive Clarifying Questions & Choice Options Card (Gemini / Antigravity Style) -->
                            <div
                                v-if="msg.choices"
                                class="shadow-2xs space-y-3 rounded-xl border border-primary/35 bg-gradient-to-br from-primary/10 via-card to-secondary/30 p-4"
                            >
                                <div class="flex items-start gap-2.5 text-xs font-bold text-foreground">
                                    <div class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-md bg-primary/20 text-primary">
                                        <ListChecks class="size-3.5" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <div class="text-xs font-bold leading-snug text-foreground">{{ msg.choices.question }}</div>
                                        <div class="text-[11px] font-normal text-muted-foreground">
                                            Select a choice to proceed with this configuration, or write a custom response:
                                        </div>
                                    </div>
                                </div>

                                <!-- Choice Buttons -->
                                <div class="flex flex-wrap gap-2 pt-1">
                                    <button
                                        v-for="(option, optIdx) in msg.choices.options"
                                        :key="optIdx"
                                        type="button"
                                        :disabled="isSending"
                                        class="hover:shadow-xs active:scale-98 group inline-flex cursor-pointer items-center gap-2 rounded-xl border border-border/80 bg-card px-3.5 py-2 text-xs font-semibold text-foreground transition-all duration-150 hover:border-primary hover:bg-primary hover:text-primary-foreground disabled:opacity-50"
                                        @click="handleSelectChoice(option)"
                                    >
                                        <span
                                            class="size-4.5 grid place-items-center rounded-full bg-primary/10 text-[10px] font-bold text-primary group-hover:bg-primary-foreground/20 group-hover:text-primary-foreground"
                                        >
                                            {{ optIdx + 1 }}
                                        </span>
                                        <span>{{ option }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        :disabled="isSending"
                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-dashed border-border bg-card/60 px-3 py-2 text-xs font-medium text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                                        @click="handlePrepopulateChoice('Regarding ' + msg.choices.question + ': ')"
                                    >
                                        <Pencil class="size-3 text-muted-foreground" />
                                        <span>Write Custom / Other...</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Collapsible Provenance / Data Used Section -->
                            <div v-if="msg.sources && msg.sources.length > 0" class="border-t border-border/60 pt-2.5">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-lg bg-secondary/40 px-3 py-1.5 text-[11px] font-semibold text-muted-foreground transition-colors hover:text-foreground"
                                    @click="toggleSourceAccordion(msg.id)"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <Database class="size-3 text-primary" />
                                        <span>Data Used ({{ msg.sources.length }} sources verified)</span>
                                    </div>
                                    <ChevronDown v-if="openSources[msg.id]" class="size-3.5" />
                                    <ChevronRight v-else class="size-3.5" />
                                </button>

                                <div v-if="openSources[msg.id]" class="mt-2 space-y-1.5 pl-2">
                                    <div
                                        v-for="(src, sIdx) in msg.sources"
                                        :key="sIdx"
                                        class="space-y-0.5 rounded-lg border border-border/70 bg-card p-2 text-[11px]"
                                    >
                                        <div class="flex items-center gap-1.5 font-bold text-foreground">
                                            <span class="py-0.2 rounded bg-primary/10 px-1.5 font-mono text-[9px] uppercase text-primary">{{
                                                src.type
                                            }}</span>
                                            <span>{{ src.title }}</span>
                                        </div>
                                        <p class="text-[10px] text-muted-foreground">{{ src.summary }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Model Performance Evaluation Telemetry Bar -->
                            <div
                                v-if="msg.metrics && !msg.isStreaming"
                                class="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-2 text-[10px] text-muted-foreground"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 font-semibold text-foreground/90">
                                        <Cpu class="size-3 text-primary" />
                                        <span>{{ formatModelName(msg.metrics.model) }}</span>
                                    </span>

                                    <span
                                        v-if="msg.metrics.eval_tokens_per_sec && msg.metrics.eval_tokens_per_sec > 0"
                                        class="inline-flex items-center gap-1 rounded-md bg-secondary/70 px-1.5 py-0.5 font-medium text-foreground"
                                        title="Generation Speed"
                                    >
                                        <Zap class="size-2.5 text-amber-500" />
                                        <span>{{ msg.metrics.eval_tokens_per_sec }} tok/s</span>
                                    </span>

                                    <span v-if="msg.metrics.eval_tokens" class="inline-flex items-center gap-1">
                                        <span>&bull;</span>
                                        <span>{{ msg.metrics.eval_tokens }} eval tokens</span>
                                        <span v-if="msg.metrics.prompt_tokens" class="text-muted-foreground/70"
                                            >({{ msg.metrics.prompt_tokens }} prompt)</span
                                        >
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span v-if="msg.metrics.duration_ms" class="inline-flex items-center gap-1 font-mono">
                                        <Clock class="size-2.5 text-muted-foreground" />
                                        <span>{{ (msg.metrics.duration_ms / 1000).toFixed(2) }}s</span>
                                    </span>
                                    <span
                                        v-if="msg.metrics.retrieval_time_ms && msg.metrics.retrieval_time_ms > 0"
                                        class="py-0.2 rounded bg-primary/10 px-1 font-mono text-[9px] text-primary"
                                        title="Tool Retrieval Execution Latency"
                                    >
                                        {{ msg.metrics.retrieval_time_ms }}ms retrieval
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Input Toolbar -->
                <div class="space-y-2 border-t border-border/80 bg-secondary/20 p-3 sm:p-4">
                    <!-- Streaming Status Banner -->
                    <div v-if="isSending" class="flex items-center justify-between px-1 text-[11px] text-muted-foreground">
                        <div class="flex items-center gap-1.5 font-medium text-foreground">
                            <Loader2 class="size-3.5 animate-spin text-primary" />
                            <span>{{ streamingStatusText || 'Octo is thinking...' }}</span>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-border/80 bg-card px-2 py-0.5 text-xs font-semibold text-rose-600 hover:bg-rose-500/10"
                            @click="stopStreaming"
                        >
                            <Square class="size-3" /> Stop
                        </button>
                    </div>

                    <!-- Hidden File Input for Proposals/Documents -->
                    <input
                        ref="fileInputRef"
                        type="file"
                        class="hidden"
                        accept=".txt,.pdf,.csv,.doc,.docx,.json,.md,.py,.cpp,.java,.c,.php,.js,.ts,.html,.css,.sql"
                        @change="handleFileSelected"
                    />

                    <!-- Pending Attached Files Pill Box -->
                    <div v-if="pendingAttachments.length > 0" class="flex flex-wrap gap-1.5 px-1 pb-1">
                        <div
                            v-for="(att, attIdx) in pendingAttachments"
                            :key="attIdx"
                            class="shadow-2xs inline-flex items-center gap-1.5 rounded-lg border border-primary/40 bg-primary/10 px-2.5 py-1 text-xs font-medium text-foreground"
                        >
                            <FileText class="size-3.5 shrink-0 text-primary" />
                            <span class="max-w-[180px] truncate font-semibold text-primary">{{ att.name }}</span>
                            <span v-if="att.size" class="text-[10px] text-muted-foreground">({{ (att.size / 1024).toFixed(1) }} KB)</span>
                            <button
                                type="button"
                                class="ml-1 rounded-md p-0.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                @click="removeAttachment(attIdx)"
                            >
                                <X class="size-3" />
                            </button>
                        </div>
                    </div>

                    <div v-if="fileReadError" class="px-1 text-[11px] font-semibold text-rose-500">
                        {{ fileReadError }}
                    </div>

                    <!-- Input Box & Buttons -->
                    <div class="relative flex items-center">
                        <textarea
                            ref="textareaRef"
                            v-model="inputPrompt"
                            rows="2"
                            placeholder="Ask Octo, attach a proposal/file, or request choices... (Enter to send)"
                            class="shadow-xs w-full resize-none rounded-xl border border-border/80 bg-card px-3.5 py-2.5 pr-20 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            :disabled="isSending"
                            @keydown="handleKeyDown"
                        />

                        <div class="absolute right-2.5 flex items-center gap-1.5">
                            <!-- Paperclip Attachment Button -->
                            <button
                                type="button"
                                class="shadow-2xs inline-flex size-8 cursor-pointer items-center justify-center rounded-lg border border-border/80 bg-card text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground active:scale-95 disabled:opacity-50"
                                :disabled="isSending || isReadingFile"
                                title="Attach proposal or file document (.txt, .pdf, .csv, code, doc)"
                                @click="triggerFileInput"
                            >
                                <Loader2 v-if="isReadingFile" class="size-4 animate-spin text-primary" />
                                <Paperclip v-else class="size-4" />
                            </button>

                            <!-- Send Button -->
                            <button
                                type="button"
                                class="shadow-xs inline-flex size-8 cursor-pointer items-center justify-center rounded-lg bg-primary text-primary-foreground transition-transform duration-150 hover:scale-105 active:scale-95 disabled:opacity-50"
                                :disabled="(!inputPrompt.trim() && pendingAttachments.length === 0) || isSending"
                                @click="handleSend"
                                title="Send message"
                            >
                                <ArrowUp class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.octo-markdown :deep(h1),
.octo-markdown :deep(h2),
.octo-markdown :deep(h3),
.octo-markdown :deep(h4) {
    font-weight: 700;
    color: hsl(var(--foreground));
    margin-top: 0.85rem;
    margin-bottom: 0.35rem;
    line-height: 1.35;
}
.octo-markdown :deep(h1) {
    font-size: 1.1rem;
}
.octo-markdown :deep(h2) {
    font-size: 1rem;
}
.octo-markdown :deep(h3) {
    font-size: 0.9rem;
    color: hsl(var(--primary));
}
.octo-markdown :deep(h4) {
    font-size: 0.82rem;
}

.octo-markdown :deep(p) {
    margin-top: 0.35rem;
    margin-bottom: 0.35rem;
    line-height: 1.55;
}

/* Polished Table Styling */
.octo-markdown :deep(table) {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 0.65rem;
    margin-bottom: 0.65rem;
    border-radius: 0.65rem;
    border: 1px solid hsl(var(--border) / 0.8);
    background: hsl(var(--card));
    overflow: hidden;
    font-size: 0.75rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.octo-markdown :deep(thead) {
    background: hsl(var(--secondary) / 0.7);
}

.octo-markdown :deep(th) {
    padding: 0.45rem 0.65rem;
    text-align: left;
    font-weight: 700;
    color: hsl(var(--foreground));
    border-bottom: 1px solid hsl(var(--border));
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.octo-markdown :deep(td) {
    padding: 0.45rem 0.65rem;
    border-bottom: 1px solid hsl(var(--border) / 0.4);
    color: hsl(var(--foreground));
    vertical-align: middle;
}

.octo-markdown :deep(tbody tr:last-child td) {
    border-bottom: none;
}

.octo-markdown :deep(tbody tr:nth-child(even)) {
    background: hsl(var(--secondary) / 0.25);
}

.octo-markdown :deep(tbody tr:hover) {
    background: hsl(var(--primary) / 0.05);
}

/* Lists styling */
.octo-markdown :deep(ul) {
    list-style-type: disc;
    padding-left: 1.15rem;
    margin-top: 0.35rem;
    margin-bottom: 0.35rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.octo-markdown :deep(ol) {
    list-style-type: decimal;
    padding-left: 1.15rem;
    margin-top: 0.35rem;
    margin-bottom: 0.35rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.octo-markdown :deep(li) {
    line-height: 1.5;
}

/* Inline code & code blocks */
.octo-markdown :deep(code) {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.72rem;
    background: hsl(var(--secondary) / 0.6);
    border: 1px solid hsl(var(--border) / 0.6);
    padding: 0.1rem 0.3rem;
    border-radius: 0.35rem;
    color: hsl(var(--foreground));
}

.octo-markdown :deep(pre) {
    background: hsl(var(--secondary) / 0.4);
    border: 1px solid hsl(var(--border) / 0.7);
    border-radius: 0.65rem;
    padding: 0.65rem 0.85rem;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    overflow-x: auto;
}

.octo-markdown :deep(pre code) {
    background: transparent;
    border: none;
    padding: 0;
    font-size: 0.72rem;
}

/* Blockquotes */
.octo-markdown :deep(blockquote) {
    border-left: 3px solid hsl(var(--primary));
    padding-left: 0.65rem;
    margin-top: 0.4rem;
    margin-bottom: 0.4rem;
    color: hsl(var(--muted-foreground));
    font-style: italic;
    background: hsl(var(--primary) / 0.04);
    border-radius: 0 0.35rem 0.35rem 0;
    padding-top: 0.2rem;
    padding-bottom: 0.2rem;
}

/* Strong / bold */
.octo-markdown :deep(strong) {
    font-weight: 700;
    color: hsl(var(--foreground));
}

/* Horizontal Rule */
.octo-markdown :deep(hr) {
    border: none;
    border-top: 1px solid hsl(var(--border) / 0.7);
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}
</style>
